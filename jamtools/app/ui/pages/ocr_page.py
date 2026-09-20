"""OCR page: images + scanned PDFs -> text (FA/EN)."""

from __future__ import annotations

import os

from PySide6.QtWidgets import QComboBox, QFileDialog, QHBoxLayout, QLabel, QPushButton, QSpinBox, QTextEdit

from ...core.common import CancelledError, MissingDependencyError, iter_files
from ...core.images import SUPPORTED_INPUT
from ...core.ocr_tools import LANG_PACKS, ocr_image, ocr_pdf, tesseract_available
from ...i18n import tr
from ...settings import get_settings
from ..widgets import make_card
from .base import ToolPage


class OcrPage(ToolPage):
    tool_key = "tool_ocr"
    desc_key = "tool_ocr_desc"
    file_filter = "Images & PDF (*.jpg *.jpeg *.png *.webp *.bmp *.tiff *.tif *.pdf)"
    allow_folders = True

    def build_options(self) -> None:
        self.cb_lang = QComboBox()
        self.lang_keys = list(LANG_PACKS.keys())
        for key in self.lang_keys:
            label = {"fas+eng": tr("ocr_fa_en"), "eng": tr("ocr_en"), "fas": tr("ocr_fa")}.get(key, key)
            self.cb_lang.addItem(label)
        try:
            self.cb_lang.setCurrentIndex(self.lang_keys.index(get_settings().ocr_lang))
        except ValueError:
            pass
        self.form.addRow(tr("ocr_lang"), self.cb_lang)

        self.sp_dpi = QSpinBox()
        self.sp_dpi.setRange(100, 400)
        self.sp_dpi.setValue(200)
        self.form.addRow(tr("ocr_dpi"), self.sp_dpi)

        self.lb_engine = QLabel("")
        self.lb_engine.setObjectName("muted")
        self.form.addRow("", self.lb_engine)
        self._refresh_engine_status()

        card, lay = make_card("ocr_result")
        self.result = QTextEdit()
        self.result.setReadOnly(True)
        self.result.setMinimumHeight(160)
        lay.addWidget(self.result)
        row = QHBoxLayout()
        btn = QPushButton(tr("ocr_save"))
        btn.setObjectName("ghost")
        btn.clicked.connect(self._save)
        row.addWidget(btn)
        row.addStretch(1)
        lay.addLayout(row)
        self._extra_cards.append(card)

    def showEvent(self, event) -> None:  # refresh in case user installed tesseract meanwhile
        super().showEvent(event)
        self._refresh_engine_status()

    def _refresh_engine_status(self) -> None:
        ok = tesseract_available(get_settings().tesseract_path)
        self.lb_engine.setText(("✓ Tesseract OK" if ok else "✕ " + tr("ocr_hint")))

    def _save(self) -> None:
        text = self.result.toPlainText()
        if not text.strip():
            return
        path, _ = QFileDialog.getSaveFileName(self, tr("ocr_save"), "ocr.txt", "Text (*.txt)")
        if path:
            with open(path, "w", encoding="utf-8") as fh:
                fh.write(text)
            self.log(tr("msg_saved", path=path))

    def make_task(self, files, output):
        lang = self.lang_keys[self.cb_lang.currentIndex()]
        dpi = self.sp_dpi.value()
        tess = get_settings().tesseract_path
        real = iter_files(files, SUPPORTED_INPUT | {".pdf"}, recursive=True)
        if not real:
            raise ValueError("No images/PDFs found")
        if not tesseract_available(tess):
            raise MissingDependencyError("Tesseract-OCR", tr("msg_need_tesseract"))

        def task(progress, log, cancelled):
            chunks = []
            for i, f in enumerate(real):
                if cancelled():
                    raise CancelledError()
                ext = os.path.splitext(f)[1].lower()
                try:
                    if ext == ".pdf":
                        text = ocr_pdf(f, lang, dpi, tess,
                                       on_progress=lambda d, t, _m: progress(
                                           int((i + d / max(t, 1)) / len(real) * 100),
                                           os.path.basename(f)),
                                       cancel=cancelled)
                    else:
                        progress(int(i / len(real) * 100), os.path.basename(f))
                        text = ocr_image(f, lang, tess)
                    chunks.append(f"===== {os.path.basename(f)} =====\n{text}")
                    self.log(f"✓ {os.path.basename(f)} ({len(text)} chars)")
                except Exception as exc:
                    self.log(f"✕ {os.path.basename(f)}: {exc}")
                    chunks.append(f"===== {os.path.basename(f)} =====\n[ERROR] {exc}")
                progress(int((i + 1) / len(real) * 100), os.path.basename(f))
            full = "\n\n".join(chunks)
            self.ui_call(lambda: self.result.setPlainText(full))
            return f"{len(real)} files"

        return self.guard(task)
