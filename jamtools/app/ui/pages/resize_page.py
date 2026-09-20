"""Batch image-resize page."""

from __future__ import annotations

import os

from PySide6.QtWidgets import QCheckBox, QComboBox, QHBoxLayout, QRadioButton, QSpinBox

from ...core.common import CancelledError, iter_files
from ...core.images import SUPPORTED_INPUT, resize_image
from ...i18n import tr
from .base import ToolPage


class ResizePage(ToolPage):
    tool_key = "tool_resize"
    desc_key = "tool_resize_desc"
    file_filter = "Images (*.jpg *.jpeg *.png *.webp *.bmp *.tiff *.tif *.gif)"
    allow_folders = True

    def build_options(self) -> None:
        self.rb_max = QRadioButton(tr("rs_by_max"))
        self.rb_max.setChecked(True)
        self.rb_wh = QRadioButton(tr("rs_by_wh"))
        row0 = QHBoxLayout()
        row0.addWidget(self.rb_max)
        row0.addWidget(self.rb_wh)
        row0.addStretch(1)
        self.form.addRow(row0)

        self.sp_max = QSpinBox()
        self.sp_max.setRange(16, 12000)
        self.sp_max.setValue(1920)
        self.form.addRow(tr("rs_max_dim"), self.sp_max)

        wh = QHBoxLayout()
        self.sp_w = QSpinBox()
        self.sp_w.setRange(1, 12000)
        self.sp_w.setValue(1280)
        self.sp_h = QSpinBox()
        self.sp_h.setRange(1, 12000)
        self.sp_h.setValue(720)
        wh.addWidget(self.sp_w)
        wh.addWidget(self.sp_h)
        self.form.addRow(f"{tr('rs_width')} × {tr('rs_height')}", wh)

        self.cb_mode = QComboBox()
        self.cb_mode.addItems([tr("rs_mode_fit"), tr("rs_mode_fill"), tr("rs_mode_exact")])
        self.form.addRow(tr("rs_mode"), self.cb_mode)

        self.ck_aspect = QCheckBox(tr("rs_keep_aspect"))
        self.ck_aspect.setChecked(True)
        self.form.addRow("", self.ck_aspect)
        self.ck_over = QCheckBox(tr("overwrite"))
        self.form.addRow("", self.ck_over)

    def make_task(self, files, output):
        by_max = self.rb_max.isChecked()
        max_side = self.sp_max.value()
        w, h = self.sp_w.value(), self.sp_h.value()
        mode = ["fit", "fill", "exact"][self.cb_mode.currentIndex()]
        aspect = self.ck_aspect.isChecked()
        over = self.ck_over.isChecked()
        real = iter_files(files, SUPPORTED_INPUT, recursive=True)

        def task(progress, log, cancelled):
            out_dir = self.resolve_output(output, real[0], "resized")
            ok, fail = 0, 0
            for i, f in enumerate(real):
                if cancelled():
                    raise CancelledError()
                try:
                    dst = resize_image(f, out_dir, width=w, height=h, mode=mode,
                                       keep_aspect=aspect, max_side=max_side if by_max else 0,
                                       overwrite=over)
                    ok += 1
                    self.log(f"✓ {os.path.basename(dst)}")
                except Exception as exc:
                    fail += 1
                    self.log(f"✕ {os.path.basename(f)}: {exc}")
                progress(int((i + 1) / len(real) * 100), os.path.basename(f))
            return self.fmt_result(ok, fail)

        return self.guard(task)
