"""Image format converter page."""

from __future__ import annotations

import os

from PySide6.QtCore import Qt
from PySide6.QtWidgets import QCheckBox, QComboBox, QHBoxLayout, QLabel, QSlider

from ...core.common import iter_files
from ...core.images import SUPPORTED_INPUT, convert_image
from ...i18n import tr
from .base import ToolPage


class ConvertPage(ToolPage):
    tool_key = "tool_convert"
    desc_key = "tool_convert_desc"
    file_filter = "Images (*.jpg *.jpeg *.png *.webp *.bmp *.tiff *.tif *.gif *.ico)"
    allow_folders = True

    def build_options(self) -> None:
        self.cb_fmt = QComboBox()
        self.cb_fmt.addItems(["JPEG", "PNG", "WEBP", "BMP", "TIFF", "GIF", "ICO"])
        self.form.addRow(tr("cv_format"), self.cb_fmt)

        row = QHBoxLayout()
        self.sl_q = QSlider(Qt.Horizontal)
        self.sl_q.setRange(10, 100)
        self.sl_q.setValue(90)
        self.lb_q = QLabel("90")
        self.sl_q.valueChanged.connect(lambda v: self.lb_q.setText(str(v)))
        row.addWidget(self.sl_q, 1)
        row.addWidget(self.lb_q)
        self.form.addRow(tr("cv_quality"), row)

        self.ck_meta = QCheckBox(tr("cv_keep_meta"))
        self.form.addRow("", self.ck_meta)
        self.ck_over = QCheckBox(tr("overwrite"))
        self.form.addRow("", self.ck_over)

    def make_task(self, files, output):
        fmt = self.cb_fmt.currentText()
        quality = self.sl_q.value()
        keep = self.ck_meta.isChecked()
        over = self.ck_over.isChecked()
        real = iter_files(files, SUPPORTED_INPUT, recursive=True)

        def task(progress, log, cancelled):
            out_dir = self.resolve_output(output, real[0], "converted")
            ok, fail = 0, 0
            for i, f in enumerate(real):
                if cancelled():
                    from ...core.common import CancelledError

                    raise CancelledError()
                try:
                    dst = convert_image(f, out_dir, fmt, quality, keep, over)
                    ok += 1
                    self.log(f"✓ {os.path.basename(dst)}")
                except Exception as exc:
                    fail += 1
                    self.log(f"✕ {os.path.basename(f)}: {exc}")
                progress(int((i + 1) / len(real) * 100), os.path.basename(f))
            return self.fmt_result(ok, fail)

        return self.guard(task)
