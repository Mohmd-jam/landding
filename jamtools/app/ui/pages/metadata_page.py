"""Metadata viewer / stripper page."""

from __future__ import annotations

import os

from PySide6.QtWidgets import QCheckBox, QComboBox, QLabel, QTableWidget, QTableWidgetItem

from ...core.common import CancelledError
from ...core.metadata import kind_of, read_metadata, strip_metadata
from ...i18n import tr
from ..widgets import make_card
from .base import ToolPage


class MetadataPage(ToolPage):
    tool_key = "tool_metadata"
    desc_key = "tool_metadata_desc"
    file_filter = "Supported (*.jpg *.jpeg *.png *.webp *.tiff *.tif *.bmp *.gif *.pdf *.mp3 *.flac *.m4a *.ogg *.opus)"
    allow_folders = True

    def build_options(self) -> None:
        self.cb_action = QComboBox()
        self.cb_action.addItems([tr("md_strip"), tr("md_view")])
        self.form.addRow(tr("md_action"), self.cb_action)
        self.ck_over = QCheckBox(tr("overwrite"))
        self.form.addRow("", self.ck_over)
        note = QLabel(tr("md_supported"))
        note.setObjectName("muted")
        note.setWordWrap(True)
        self.form.addRow("", note)

        card, lay = make_card("md_view")
        self.table = QTableWidget(0, 3)
        self.table.setHorizontalHeaderLabels(["File", "Key", "Value"])
        self.table.horizontalHeader().setStretchLastSection(True)
        self.table.verticalHeader().setVisible(False)
        self.table.setMinimumHeight(150)
        lay.addWidget(self.table)
        self._extra_cards.append(card)
        self.drop.changed.connect(self._auto_view)

    def _auto_view(self) -> None:
        if self.cb_action.currentIndex() == 1:
            self._view_now()

    def _view_now(self) -> None:
        rows: list[tuple[str, str, str]] = []
        for f in self.drop.files():
            if os.path.isdir(f):
                continue
            try:
                meta = read_metadata(f)
            except Exception as exc:
                meta = {"(error)": str(exc)}
            for k, v in meta.items():
                rows.append((os.path.basename(f), k, v))
        self.table.setRowCount(len(rows))
        for i, (f, k, v) in enumerate(rows):
            self.table.setItem(i, 0, QTableWidgetItem(f))
            self.table.setItem(i, 1, QTableWidgetItem(k))
            self.table.setItem(i, 2, QTableWidgetItem(v))

    def on_run(self) -> None:
        if self.cb_action.currentIndex() == 1:
            self._view_now()  # view mode is instant, no job needed
            self.log(f"{len(self.drop.files())} files inspected")
            return
        super().on_run()

    def make_task(self, files, output):
        over = self.ck_over.isChecked()
        real = [f for f in files if os.path.isfile(f) and kind_of(f) != "other"]
        if not real:
            raise ValueError("No supported files")

        def task(progress, log, cancelled):
            out_dir = self.resolve_output(output, real[0], "clean")
            ok, fail = 0, 0
            for i, f in enumerate(real):
                if cancelled():
                    raise CancelledError()
                try:
                    dst = strip_metadata(f, out_dir, over)
                    ok += 1
                    self.log(f"✓ {os.path.basename(dst)}")
                except Exception as exc:
                    fail += 1
                    self.log(f"✕ {os.path.basename(f)}: {exc}")
                progress(int((i + 1) / len(real) * 100), os.path.basename(f))
            return self.fmt_result(ok, fail)

        return self.guard(task)
