"""Clipboard manager page: live monitor + searchable pinned history."""

from __future__ import annotations

import os

from PySide6.QtCore import Qt, QTimer
from PySide6.QtGui import QImage
from PySide6.QtWidgets import (
    QApplication, QCheckBox, QFileDialog, QHBoxLayout, QLabel, QLineEdit,
    QListWidget, QListWidgetItem, QMessageBox, QPushButton, QSpinBox, QSplitter,
    QTextEdit, QVBoxLayout, QWidget,
)

from ...core.clipboard_store import ClipboardStore
from ...i18n import tr
from ...settings import app_data_dir, get_settings
from ..widgets import make_card


class ClipboardPage(QWidget):
    tool_key = "tool_clipboard"

    def __init__(self, parent=None) -> None:
        super().__init__(parent)
        s = get_settings()
        self.store = ClipboardStore(os.path.join(app_data_dir(), "clipboard.sqlite"),
                                    max_items=s.clipboard_max_items)
        self._last_seen = ""
        self._ignore_next = False

        root = QVBoxLayout(self)
        root.setContentsMargins(18, 14, 18, 14)
        root.setSpacing(10)
        title = QLabel(tr("tool_clipboard"))
        title.setObjectName("title")
        root.addWidget(title)
        desc = QLabel(tr("tool_clipboard_desc"))
        desc.setObjectName("muted")
        root.addWidget(desc)

        bar_card, bar_lay = make_card()
        row = QHBoxLayout()
        self.ck_mon = QCheckBox(tr("cb_monitor"))
        self.ck_mon.setChecked(s.clipboard_monitor)
        row.addWidget(self.ck_mon)
        row.addWidget(QLabel(tr("cb_max_items")))
        self.sp_max = QSpinBox()
        self.sp_max.setRange(10, 2000)
        self.sp_max.setValue(s.clipboard_max_items)
        self.sp_max.valueChanged.connect(self._save_prefs)
        self.ck_mon.toggled.connect(self._save_prefs)
        row.addWidget(self.sp_max)
        row.addStretch(1)
        self.ed_search = QLineEdit()
        self.ed_search.setPlaceholderText(tr("search"))
        self.ed_search.textChanged.connect(lambda *_: self.refresh())
        row.addWidget(self.ed_search, 1)
        bar_lay.addLayout(row)

        row2 = QHBoxLayout()
        self.btn_copy = QPushButton(tr("cb_copy_back"))
        self.btn_copy.setObjectName("primary")
        self.btn_copy.clicked.connect(self.copy_back)
        self.btn_pin = QPushButton(tr("cb_pin"))
        self.btn_pin.setObjectName("ghost")
        self.btn_pin.clicked.connect(self.toggle_pin)
        self.btn_del = QPushButton(tr("delete"))
        self.btn_del.setObjectName("ghost")
        self.btn_del.clicked.connect(self.delete_selected)
        self.btn_clear = QPushButton(tr("cb_clear"))
        self.btn_clear.setObjectName("ghost")
        self.btn_clear.clicked.connect(self.clear_history)
        self.btn_export = QPushButton(tr("cb_export"))
        self.btn_export.setObjectName("ghost")
        self.btn_export.clicked.connect(self.export)
        for b in (self.btn_copy, self.btn_pin, self.btn_del, self.btn_clear, self.btn_export):
            row2.addWidget(b)
        row2.addStretch(1)
        bar_lay.addLayout(row2)
        root.addWidget(bar_card)

        split = QSplitter()
        self.list = QListWidget()
        self.list.itemSelectionChanged.connect(self.show_selected)
        self.list.itemDoubleClicked.connect(lambda *_: self.copy_back())
        self.preview = QTextEdit()
        self.preview.setReadOnly(True)
        self.preview.setPlaceholderText(tr("cb_empty"))
        split.addWidget(self.list)
        split.addWidget(self.preview)
        split.setStretchFactor(0, 1)
        split.setStretchFactor(1, 1)
        root.addWidget(split, 1)

        # live monitoring
        cb = QApplication.clipboard()
        cb.dataChanged.connect(self._on_clipboard)
        self._timer = QTimer(self)
        self._timer.setInterval(1500)
        self._timer.timeout.connect(self._poll)
        self._timer.start()
        self.refresh()

    # ── prefs ──
    def _save_prefs(self) -> None:
        s = get_settings()
        s.clipboard_monitor = self.ck_mon.isChecked()
        s.clipboard_max_items = self.sp_max.value()
        self.store.max_items = s.clipboard_max_items
        from ...settings import save_settings

        save_settings(s)

    # ── capture ──
    def _on_clipboard(self) -> None:
        self._poll()

    def _poll(self) -> None:
        if not self.ck_mon.isChecked() or self._ignore_next:
            return
        cb = QApplication.clipboard()
        mime = cb.mimeData()
        if mime is None:
            return
        added = False
        if mime.hasImage():
            img = cb.image()
            if not img.isNull():
                # cheap dedupe via size+first bytes signature
                sig = f"img:{img.width()}x{img.height()}:{img.cacheKey()}"
                if sig != self._last_seen:
                    self._last_seen = sig
                    from PySide6.QtCore import QBuffer, QIODevice

                    buf = QBuffer()
                    buf.open(QIODevice.WriteOnly)
                    img.save(buf, "PNG")
                    self.store.add_image(bytes(buf.data()))
                    added = True
        elif mime.hasText():
            text = cb.text()
            if text and text != self._last_seen:
                self._last_seen = text
                self.store.add_text(text)
                added = True
        if added:
            self.refresh(keep_selection=True)

    # ── list ──
    def refresh(self, keep_selection: bool = False) -> None:
        sel = self.current_id() if keep_selection else None
        self.list.clear()
        for item in self.store.list(self.ed_search.text().strip()):
            pin = "📌 " if item.pinned else ""
            if item.kind == "image":
                text = f"{pin}🖼 [image]"
            else:
                text = f"{pin}{item.preview[:140]}"
            li = QListWidgetItem(text)
            li.setData(Qt.UserRole, item.id)
            self.list.addItem(li)
            if sel is not None and item.id == sel:
                li.setSelected(True)

    def current_id(self) -> int | None:
        items = self.list.selectedItems()
        return items[0].data(Qt.UserRole) if items else None

    def show_selected(self) -> None:
        cid = self.current_id()
        if cid is None:
            self.preview.clear()
            return
        text = self.store.get_text(cid)
        if text:
            self.preview.setPlainText(text)
        else:
            self.preview.setPlainText("[image] — " + tr("cb_copy_back"))

    def copy_back(self) -> None:
        cid = self.current_id()
        if cid is None:
            return
        text = self.store.get_text(cid)
        img_bytes = b"" if text else self.store.get_image(cid)
        self._ignore_next = True
        try:
            cb = QApplication.clipboard()
            if text:
                cb.setText(text)
                self._last_seen = text
            elif img_bytes:
                img = QImage()
                img.loadFromData(img_bytes, "PNG")
                if not img.isNull():
                    cb.setImage(img)
        finally:
            QTimer.singleShot(800, lambda: setattr(self, "_ignore_next", False))

    def toggle_pin(self) -> None:
        cid = self.current_id()
        if cid is None:
            return
        items = self.store.list()
        cur = next((i for i in items if i.id == cid), None)
        if cur:
            self.store.set_pinned(cid, not cur.pinned)
            self.refresh(keep_selection=True)

    def delete_selected(self) -> None:
        cid = self.current_id()
        if cid is None:
            return
        self.store.delete(cid)
        self.refresh()

    def clear_history(self) -> None:
        ans = QMessageBox.question(self, tr("msg_confirm_title"), tr("cb_clear"))
        if ans == QMessageBox.Yes:
            self.store.clear(keep_pinned=True)
            self.refresh()

    def export(self) -> None:
        path, _ = QFileDialog.getSaveFileName(self, tr("cb_export"), "clipboard.txt", "Text (*.txt)")
        if path:
            self.store.export_text(path)
