"""Watermark page (text and/or logo)."""

from __future__ import annotations

import os

from PySide6.QtCore import Qt
from PySide6.QtWidgets import (
    QCheckBox, QComboBox, QFileDialog, QHBoxLayout, QLabel, QLineEdit,
    QPushButton, QSlider,
)

from ...core.common import CancelledError, iter_files
from ...core.images import POSITIONS, SUPPORTED_INPUT, watermark_image
from ...i18n import tr
from .base import ToolPage


class WatermarkPage(ToolPage):
    tool_key = "tool_watermark"
    desc_key = "tool_watermark_desc"
    file_filter = "Images (*.jpg *.jpeg *.png *.webp *.bmp *.tiff *.tif)"
    allow_folders = True

    def build_options(self) -> None:
        self.ed_text = QLineEdit("© JamTools")
        self.form.addRow(tr("wm_text"), self.ed_text)

        row = QHBoxLayout()
        self.ed_logo = QLineEdit()
        self.ed_logo.setPlaceholderText(tr("wm_logo"))
        btn = QPushButton(tr("browse"))
        btn.clicked.connect(self._pick_logo)
        row.addWidget(self.ed_logo, 1)
        row.addWidget(btn)
        self.form.addRow(tr("wm_logo"), row)

        self.cb_pos = QComboBox()
        self.cb_pos.addItems(list(POSITIONS))
        self.cb_pos.setCurrentText("bottom-right")
        self.form.addRow(tr("wm_position"), self.cb_pos)

        orow = QHBoxLayout()
        self.sl_op = QSlider()
        self.sl_op.setOrientation(Qt.Horizontal)
        self.sl_op.setRange(5, 100)
        self.sl_op.setValue(45)
        self.lb_op = QLabel("45%")
        self.sl_op.valueChanged.connect(lambda v: self.lb_op.setText(f"{v}%"))
        orow.addWidget(self.sl_op, 1)
        orow.addWidget(self.lb_op)
        self.form.addRow(tr("wm_opacity"), orow)

        srow = QHBoxLayout()
        self.sl_size = QSlider(Qt.Horizontal)
        self.sl_size.setRange(5, 60)
        self.sl_size.setValue(18)
        self.lb_size = QLabel("18%")
        self.sl_size.valueChanged.connect(lambda v: self.lb_size.setText(f"{v}%"))
        srow.addWidget(self.sl_size, 1)
        srow.addWidget(self.lb_size)
        self.form.addRow(tr("wm_size"), srow)

        self.ck_tile = QCheckBox(tr("wm_tile"))
        self.form.addRow("", self.ck_tile)
        self.ck_over = QCheckBox(tr("overwrite"))
        self.form.addRow("", self.ck_over)

    def _pick_logo(self) -> None:
        path, _ = QFileDialog.getOpenFileName(self, tr("wm_logo"), "",
                                              "Images (*.png *.jpg *.jpeg *.webp *.bmp)")
        if path:
            self.ed_logo.setText(path)

    def validate(self, files, output) -> str:
        err = super().validate(files, output)
        if err:
            return err
        if not self.ed_text.text().strip() and not self.ed_logo.text().strip():
            return tr("wm_text")  # need at least one of text/logo
        return ""

    def make_task(self, files, output):
        text = self.ed_text.text().strip()
        logo = self.ed_logo.text().strip()
        pos = self.cb_pos.currentText()
        op = self.sl_op.value()
        scale = self.sl_size.value() / 100.0
        tiled = self.ck_tile.isChecked()
        over = self.ck_over.isChecked()
        real = iter_files(files, SUPPORTED_INPUT, recursive=True)

        def task(progress, log, cancelled):
            out_dir = self.resolve_output(output, real[0], "watermarked")
            ok, fail = 0, 0
            for i, f in enumerate(real):
                if cancelled():
                    raise CancelledError()
                try:
                    dst = watermark_image(f, out_dir, text=text, logo=logo, position=pos,
                                          opacity=op, scale=scale, tiled=tiled, overwrite=over)
                    ok += 1
                    self.log(f"✓ {os.path.basename(dst)}")
                except Exception as exc:
                    fail += 1
                    self.log(f"✕ {os.path.basename(f)}: {exc}")
                progress(int((i + 1) / len(real) * 100), os.path.basename(f))
            return self.fmt_result(ok, fail)

        return self.guard(task)
