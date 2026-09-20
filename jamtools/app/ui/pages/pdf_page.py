"""PDF tools page: images / docx / text / merge / split."""

from __future__ import annotations

import os

from PySide6.QtWidgets import (
    QCheckBox, QComboBox, QFileDialog, QHBoxLayout, QLineEdit, QMessageBox,
    QPushButton, QSpinBox, QTextEdit,
)

from ...core.common import CancelledError, MissingDependencyError, iter_files
from ...i18n import tr
from ..widgets import make_card
from .base import ToolPage


class PdfPage(ToolPage):
    tool_key = "tool_pdf"
    desc_key = "tool_pdf_desc"
    file_filter = "PDF (*.pdf)"
    allow_folders = True

    def build_options(self) -> None:
        self.cb_op = QComboBox()
        self.cb_op.addItems([tr("pdf_to_images"), tr("pdf_to_docx"), tr("pdf_to_text"),
                             tr("pdf_merge"), tr("pdf_split")])
        self.cb_op.currentIndexChanged.connect(self._toggle)
        self.form.addRow(tr("pdf_op"), self.cb_op)

        self.sp_dpi = QSpinBox()
        self.sp_dpi.setRange(72, 400)
        self.sp_dpi.setValue(150)
        self.form.addRow(tr("pdf_dpi"), self.sp_dpi)
        self.row_dpi = self.form.rowCount() - 1

        self.cb_fmt = QComboBox()
        self.cb_fmt.addItems(["png", "jpg"])
        self.form.addRow(tr("pdf_img_format"), self.cb_fmt)
        self.row_fmt = self.form.rowCount() - 1

        self.ed_name = QLineEdit("merged.pdf")
        self.form.addRow(tr("pdf_merged_name"), self.ed_name)
        self.row_name = self.form.rowCount() - 1

        self.ck_over = QCheckBox(tr("overwrite"))
        self.form.addRow("", self.ck_over)

        # result text viewer
        card, lay = make_card("pdf_result_text")
        self.result = QTextEdit()
        self.result.setReadOnly(True)
        self.result.setMinimumHeight(140)
        lay.addWidget(self.result)
        row = QHBoxLayout()
        self.btn_save = QPushButton(tr("pdf_save_text"))
        self.btn_save.setObjectName("ghost")
        self.btn_save.clicked.connect(self._save_text)
        row.addWidget(self.btn_save)
        row.addStretch(1)
        lay.addLayout(row)
        self._extra_cards.append(card)
        self._toggle()

    def _show_row(self, row, show):
        from PySide6.QtWidgets import QFormLayout as _FL

        for role in (_FL.LabelRole, _FL.FieldRole):
            item = self.form.itemAt(row, role)
            if item and item.widget():
                item.widget().setVisible(show)

    def _toggle(self):
        op = self.cb_op.currentIndex()
        self._show_row(self.row_dpi, op == 0)
        self._show_row(self.row_fmt, op == 0)
        self._show_row(self.row_name, op == 3)

    def _save_text(self):
        text = self.result.toPlainText()
        if not text.strip():
            return
        path, _ = QFileDialog.getSaveFileName(self, tr("pdf_save_text"), "extracted.txt", "Text (*.txt)")
        if path:
            with open(path, "w", encoding="utf-8") as fh:
                fh.write(text)
            self.log(tr("msg_saved", path=path))

    def make_task(self, files, output):
        from ...core import pdf_tools

        if not pdf_tools.available():
            raise MissingDependencyError("PyMuPDF", "pip install PyMuPDF")
        op = self.cb_op.currentIndex()
        over = self.ck_over.isChecked()
        real = iter_files(files, {".pdf"}, recursive=True)
        if not real:
            raise ValueError("No PDFs found")

        if op == 0:
            dpi, fmt = self.sp_dpi.value(), self.cb_fmt.currentText()

            def task(progress, log, cancelled):
                ok, fail, total_pages = 0, 0, 0
                for i, f in enumerate(real):
                    if cancelled():
                        raise CancelledError()
                    out_dir = self.resolve_output(output, f, os.path.splitext(os.path.basename(f))[0])
                    try:
                        pages = pdf_tools.pdf_to_images(
                            f, out_dir, dpi, fmt, over,
                            on_progress=lambda d, t, dst: progress(
                                int((i + d / max(t, 1)) / len(real) * 100), os.path.basename(str(dst))),
                            cancel=cancelled)
                        ok += 1
                        total_pages += len(pages)
                        self.log(f"✓ {os.path.basename(f)} → {len(pages)} images")
                    except Exception as exc:
                        fail += 1
                        self.log(f"✕ {os.path.basename(f)}: {exc}")
                return f"{self.fmt_result(ok, fail)} — {total_pages} pages"

            return self.guard(task)

        if op == 1:
            if not pdf_tools.docx_available():
                raise MissingDependencyError("pdf2docx", "pip install pdf2docx")

            def task(progress, log, cancelled):
                out_dir = self.resolve_output(output, real[0], "docx")
                ok, fail = 0, 0
                for i, f in enumerate(real):
                    if cancelled():
                        raise CancelledError()
                    try:
                        dst = pdf_tools.pdf_to_docx(f, out_dir, over)
                        ok += 1
                        self.log(f"✓ {os.path.basename(dst)}")
                    except Exception as exc:
                        fail += 1
                        self.log(f"✕ {os.path.basename(f)}: {exc}")
                    progress(int((i + 1) / len(real) * 100), os.path.basename(f))
                return self.fmt_result(ok, fail)

            return self.guard(task)

        if op == 2:
            def task(progress, log, cancelled):
                chunks = []
                for i, f in enumerate(real):
                    if cancelled():
                        raise CancelledError()
                    text = pdf_tools.pdf_extract_text(
                        f,
                        on_progress=lambda d, t, _m: progress(
                            int((i + d / max(t, 1)) / len(real) * 100), os.path.basename(f)),
                        cancel=cancelled)
                    chunks.append(f"===== {os.path.basename(f)} =====\n{text}")
                    self.log(f"✓ {os.path.basename(f)} ({len(text)} chars)")
                full = "\n\n".join(chunks)
                self.ui_call(lambda: self.result.setPlainText(full))
                return f"{len(real)} files, {len(full)} chars"

            return self.guard(task)

        if op == 3:
            name = self.ed_name.text().strip() or "merged.pdf"

            def task(progress, log, cancelled):
                base = output or os.path.dirname(real[0])
                os.makedirs(base, exist_ok=True)
                self._output_used = base
                progress(20, name)
                dst = pdf_tools.pdf_merge(real, os.path.join(base, name), over)
                progress(100, dst)
                self.log(f"✓ {os.path.basename(dst)}")
                return os.path.basename(dst)

            return self.guard(task)

        # split
        def task(progress, log, cancelled):
            ok, fail, total_pages = 0, 0, 0
            for i, f in enumerate(real):
                if cancelled():
                    raise CancelledError()
                out_dir = self.resolve_output(output, f, os.path.splitext(os.path.basename(f))[0])
                try:
                    parts = pdf_tools.pdf_split(
                        f, out_dir, over,
                        on_progress=lambda d, t, dst: progress(
                            int((i + d / max(t, 1)) / len(real) * 100), os.path.basename(str(dst))),
                        cancel=cancelled)
                    ok += 1
                    total_pages += len(parts)
                    self.log(f"✓ {os.path.basename(f)} → {len(parts)} files")
                except Exception as exc:
                    fail += 1
                    self.log(f"✕ {os.path.basename(f)}: {exc}")
            return f"{self.fmt_result(ok, fail)} — {total_pages} pages"

        return self.guard(task)
