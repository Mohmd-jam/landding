"""Compressor page: ZIP create/extract + image/PDF shrink."""

from __future__ import annotations

import os

from PySide6.QtWidgets import QCheckBox, QComboBox, QLineEdit, QSpinBox

from ...core.common import CancelledError, MissingDependencyError, format_size, iter_files
from ...core.images import SUPPORTED_INPUT, compress_image
from ...i18n import tr
from .base import ToolPage


class CompressPage(ToolPage):
    tool_key = "tool_compress"
    desc_key = "tool_compress_desc"
    allow_folders = True

    def build_options(self) -> None:
        self.cb_mode = QComboBox()
        self.cb_mode.addItems([tr("cp_zip"), tr("cp_unzip"), tr("cp_shrink_img"), tr("cp_shrink_pdf")])
        self.cb_mode.currentIndexChanged.connect(self._toggle_opts)
        self.form.addRow(tr("cp_mode"), self.cb_mode)

        self.ed_name = QLineEdit("archive.zip")
        self.form.addRow(tr("cp_archive_name"), self.ed_name)
        self.row_name = self.form.rowCount() - 1

        self.sp_level = QSpinBox()
        self.sp_level.setRange(0, 9)
        self.sp_level.setValue(6)
        self.form.addRow(tr("cp_level"), self.sp_level)
        self.row_level = self.form.rowCount() - 1

        self.sp_q = QSpinBox()
        self.sp_q.setRange(10, 95)
        self.sp_q.setValue(70)
        self.form.addRow(tr("cv_quality"), self.sp_q)
        self.row_q = self.form.rowCount() - 1

        self.sp_dim = QSpinBox()
        self.sp_dim.setRange(320, 8000)
        self.sp_dim.setValue(1920)
        self.form.addRow(tr("cp_max_dim"), self.sp_dim)
        self.row_dim = self.form.rowCount() - 1

        self.sp_dpi = QSpinBox()
        self.sp_dpi.setRange(72, 300)
        self.sp_dpi.setValue(150)
        self.form.addRow(tr("cp_pdf_dpi"), self.sp_dpi)
        self.row_dpi = self.form.rowCount() - 1

        self.ck_over = QCheckBox(tr("overwrite"))
        self.form.addRow("", self.ck_over)
        self._toggle_opts()

    def _show_row(self, row: int, show: bool) -> None:
        from PySide6.QtWidgets import QFormLayout as _FL

        for role in (_FL.LabelRole, _FL.FieldRole):
            item = self.form.itemAt(row, role)
            if item and item.widget():
                item.widget().setVisible(show)

    def _toggle_opts(self) -> None:
        mode = self.cb_mode.currentIndex()
        self._show_row(self.row_name, mode == 0)
        self._show_row(self.row_level, mode == 0)
        self._show_row(self.row_q, mode in (2, 3))
        self._show_row(self.row_dim, mode == 2)
        self._show_row(self.row_dpi, mode == 3)

    def make_task(self, files, output):
        mode = self.cb_mode.currentIndex()
        over = self.ck_over.isChecked()
        if mode == 0:
            return self._task_zip(files, output, over)
        if mode == 1:
            return self._task_unzip(files, output, over)
        if mode == 2:
            return self._task_shrink_img(files, output, over)
        return self._task_shrink_pdf(files, output, over)

    # ── tasks ──
    def _task_zip(self, files, output, over):
        from ...core.archives import create_zip

        name = self.ed_name.text().strip() or "archive.zip"
        level = self.sp_level.value()

        def task(progress, log, cancelled):
            base = output or (os.path.dirname(files[0]) if os.path.isfile(files[0]) else files[0])
            os.makedirs(base, exist_ok=True)
            self._output_used = base
            progress(10, name)
            zpath, count, total = create_zip(files, os.path.join(base, name), level, over)
            progress(100, zpath)
            self.log(f"✓ {os.path.basename(zpath)} — {count} files, {format_size(total)}")
            return os.path.basename(zpath)

        return self.guard(task)

    def _task_unzip(self, files, output, over):
        from ...core.archives import EXTRACTABLE, extract_archive

        real = [f for f in files if os.path.isfile(f)]

        def task(progress, log, cancelled):
            ok, fail, total_n = 0, 0, 0
            for i, f in enumerate(real):
                if cancelled():
                    raise CancelledError()
                stem = os.path.splitext(os.path.basename(f))[0]
                base = output or os.path.dirname(f)
                target = os.path.join(base, stem)
                try:
                    _, n = extract_archive(f, target, over)
                    ok += 1
                    total_n += n
                    self._output_used = base
                    self.log(f"✓ {os.path.basename(f)} → {n} files")
                except Exception as exc:
                    fail += 1
                    self.log(f"✕ {os.path.basename(f)}: {exc}")
                progress(int((i + 1) / max(len(real), 1) * 100), os.path.basename(f))
            self.log(f"{total_n} files extracted")
            return self.fmt_result(ok, fail)

        return self.guard(task)

    def _task_shrink_img(self, files, output, over):
        q = self.sp_q.value()
        dim = self.sp_dim.value()
        real = iter_files(files, SUPPORTED_INPUT, recursive=True)
        if not real:
            raise ValueError("No images found")

        def task(progress, log, cancelled):
            out_dir = self.resolve_output(output, real[0], "compressed")
            ok, fail, saved = 0, 0, 0
            for i, f in enumerate(real):
                if cancelled():
                    raise CancelledError()
                try:
                    dst, before, after = compress_image(f, out_dir, q, dim, over)
                    ok += 1
                    saved += max(0, before - after)
                    self.log(f"✓ {os.path.basename(dst)} ({format_size(before)} → {format_size(after)})")
                except Exception as exc:
                    fail += 1
                    self.log(f"✕ {os.path.basename(f)}: {exc}")
                progress(int((i + 1) / len(real) * 100), os.path.basename(f))
            self.log(f"💾 {tr('du_wasted')}: {format_size(saved)}")
            return self.fmt_result(ok, fail)

        return self.guard(task)

    def _task_shrink_pdf(self, files, output, over):
        from ...core import pdf_tools

        if not pdf_tools.available():
            raise MissingDependencyError("PyMuPDF", "pip install PyMuPDF")
        dpi = self.sp_dpi.value()
        q = self.sp_q.value()
        real = iter_files(files, {".pdf"}, recursive=True)
        if not real:
            raise ValueError("No PDFs found")

        def task(progress, log, cancelled):
            out_dir = self.resolve_output(output, real[0], "compressed")
            ok, fail, saved = 0, 0, 0
            for i, f in enumerate(real):
                if cancelled():
                    raise CancelledError()
                try:
                    dst, before, after = pdf_tools.pdf_compress(f, out_dir, dpi, q, over)
                    ok += 1
                    saved += max(0, before - after)
                    self.log(f"✓ {os.path.basename(dst)} ({format_size(before)} → {format_size(after)})")
                except Exception as exc:
                    fail += 1
                    self.log(f"✕ {os.path.basename(f)}: {exc}")
                progress(int((i + 1) / len(real) * 100), os.path.basename(f))
            self.log(f"💾 {tr('du_wasted')}: {format_size(saved)}")
            return self.fmt_result(ok, fail)

        return self.guard(task)
