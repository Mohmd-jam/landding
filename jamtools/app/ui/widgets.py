"""Reusable widgets: nav bridge, file drop-list, output bar, cards."""

from __future__ import annotations

import os

from PySide6.QtCore import Qt, QUrl, Signal, QObject
from PySide6.QtWidgets import (
    QFileDialog, QFrame, QHBoxLayout, QLabel, QLineEdit, QListWidget, QListWidgetItem,
    QPushButton, QVBoxLayout, QWidget, QAbstractItemView,
)

from ..core.jobs import Job, get_manager
from ..i18n import tr


class JobBridge(QObject):
    """Forwards JobManager callbacks (worker thread) to Qt signals (GUI thread)."""

    job_changed = Signal(int)  # job id

    def __init__(self) -> None:
        super().__init__()
        get_manager().on_change = self._forward

    def _forward(self, job: Job) -> None:
        try:
            self.job_changed.emit(job.id)
        except Exception:
            pass


_BRIDGE: JobBridge | None = None


def get_bridge() -> JobBridge:
    global _BRIDGE
    if _BRIDGE is None:
        _BRIDGE = JobBridge()
    return _BRIDGE


class DropListWidget(QWidget):
    """File/folder list with drag & drop and add/remove buttons."""

    changed = Signal()

    def __init__(self, parent=None, folders: bool = False, dialog_title: str = "",
                 file_filter: str = "") -> None:
        super().__init__(parent)
        self._folders = folders
        self._dialog_title = dialog_title
        self._file_filter = file_filter

        layout = QVBoxLayout(self)
        layout.setContentsMargins(0, 0, 0, 0)
        layout.setSpacing(6)

        self.list = QListWidget()
        self.list.setAcceptDrops(True)
        self.list.setDragDropMode(QAbstractItemView.DropOnly)
        self.list.setSelectionMode(QAbstractItemView.ExtendedSelection)
        self.list.setMinimumHeight(120)
        self.list.dragEnterEvent = self._drag_enter  # type: ignore[method-assign]
        self.list.dragMoveEvent = self._drag_enter  # type: ignore[method-assign]
        self.list.dropEvent = self._drop  # type: ignore[method-assign]
        layout.addWidget(self.list)

        self.hint = QLabel(tr("drop_hint"))
        self.hint.setObjectName("muted")
        self.hint.setAlignment(Qt.AlignCenter)
        layout.addWidget(self.hint)

        bar = QHBoxLayout()
        bar.setSpacing(6)
        self.btn_add = QPushButton(tr("add_files"))
        self.btn_add.setObjectName("ghost")
        self.btn_add.clicked.connect(self.pick_files)
        self.btn_folder = QPushButton(tr("add_folder"))
        self.btn_folder.setObjectName("ghost")
        self.btn_folder.clicked.connect(self.pick_folder)
        self.btn_remove = QPushButton(tr("remove_selected"))
        self.btn_remove.setObjectName("ghost")
        self.btn_remove.clicked.connect(self.remove_selected)
        self.btn_clear = QPushButton(tr("clear"))
        self.btn_clear.setObjectName("ghost")
        self.btn_clear.clicked.connect(self.clear)
        for b in (self.btn_add, self.btn_folder, self.btn_remove, self.btn_clear):
            bar.addWidget(b)
        bar.addStretch(1)
        self.count = QLabel("")
        self.count.setObjectName("muted")
        bar.addWidget(self.count)
        layout.addLayout(bar)
        self._update_count()

    # ── drag & drop ──
    def _drag_enter(self, event) -> None:
        if event.mimeData().hasUrls():
            event.acceptProposedAction()
        else:
            event.ignore()

    def _drop(self, event) -> None:
        paths = []
        for url in event.mimeData().urls():
            if isinstance(url, QUrl):
                p = url.toLocalFile()
                if p and os.path.exists(p):
                    paths.append(p)
        if paths:
            self.add_paths(paths)
            event.acceptProposedAction()

    # ── API ──
    def pick_files(self) -> None:
        paths, _ = QFileDialog.getOpenFileNames(self, self._dialog_title or tr("add_files"), "", self._file_filter)
        if paths:
            self.add_paths(paths)

    def pick_folder(self) -> None:
        folder = QFileDialog.getExistingDirectory(self, tr("add_folder"), "")
        if folder:
            self.add_paths([folder])

    def add_paths(self, paths: list[str]) -> None:
        existing = set(self.files())
        for p in paths:
            if p not in existing and os.path.exists(p):
                if not self._folders and os.path.isdir(p):
                    for name in sorted(os.listdir(p)):
                        fp = os.path.join(p, name)
                        if os.path.isfile(fp) and fp not in existing:
                            self.list.addItem(QListWidgetItem(fp))
                            existing.add(fp)
                else:
                    self.list.addItem(QListWidgetItem(p))
                    existing.add(p)
        self._update_count()
        self.changed.emit()

    def remove_selected(self) -> None:
        for item in self.list.selectedItems():
            self.list.takeItem(self.list.row(item))
        self._update_count()
        self.changed.emit()

    def clear(self) -> None:
        self.list.clear()
        self._update_count()
        self.changed.emit()

    def files(self) -> list[str]:
        return [self.list.item(i).text() for i in range(self.list.count())]

    def _update_count(self) -> None:
        n = self.list.count()
        self.count.setText(tr("files_count", n=n))
        self.hint.setVisible(n == 0)


class OutputBar(QWidget):
    """Output folder picker with 'same as source' option."""

    def __init__(self, parent=None, for_file: bool = False) -> None:
        super().__init__(parent)
        self._for_file = for_file
        layout = QHBoxLayout(self)
        layout.setContentsMargins(0, 0, 0, 0)
        layout.setSpacing(6)
        label = QLabel(tr("output_folder"))
        layout.addWidget(label)
        self.edit = QLineEdit()
        self.edit.setPlaceholderText(tr("same_as_source"))
        layout.addWidget(self.edit, 1)
        self.btn = QPushButton(tr("browse"))
        self.btn.clicked.connect(self.browse)
        layout.addWidget(self.btn)

    def browse(self) -> None:
        if self._for_file:
            path, _ = QFileDialog.getSaveFileName(self, tr("output_folder"), self.edit.text())
        else:
            path = QFileDialog.getExistingDirectory(self, tr("output_folder"), self.edit.text())
        if path:
            self.edit.setText(path)

    def path(self) -> str:
        return self.edit.text().strip()

    def set_path(self, path: str) -> None:
        self.edit.setText(path)


def make_card(title_key: str = "", desc_key: str = "") -> tuple[QFrame, QVBoxLayout]:
    card = QFrame()
    card.setObjectName("card")
    layout = QVBoxLayout(card)
    layout.setSpacing(8)
    layout.setContentsMargins(14, 12, 14, 12)
    if title_key:
        t = QLabel(tr(title_key))
        t.setObjectName("cardTitle")
        layout.addWidget(t)
    if desc_key:
        d = QLabel(tr(desc_key))
        d.setObjectName("muted")
        d.setWordWrap(True)
        layout.addWidget(d)
    return card, layout


def open_in_explorer(path: str) -> None:
    import subprocess
    import sys

    if not path:
        return
    folder = path if os.path.isdir(path) else os.path.dirname(path)
    try:
        if sys.platform.startswith("win"):
            os.startfile(folder)  # type: ignore[attr-defined]
        elif sys.platform == "darwin":
            subprocess.Popen(["open", folder])
        else:
            subprocess.Popen(["xdg-open", folder])
    except Exception:
        pass
