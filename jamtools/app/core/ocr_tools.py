"""OCR via Tesseract (pytesseract wrapper or raw CLI fallback)."""

from __future__ import annotations

import os
import shutil
import subprocess
import tempfile

from .common import MissingDependencyError

LANG_PACKS = {
    "fas+eng": (("fas", "eng"), "Persian + English"),
    "eng": (("eng",), "English"),
    "fas": (("fas",), "Persian"),
}

COMMON_WINDOWS_PATHS = [
    r"C:\Program Files\Tesseract-OCR\tesseract.exe",
    r"C:\Program Files (x86)\Tesseract-OCR\tesseract.exe",
]


def find_tesseract(custom: str = "") -> str:
    """Return tesseract executable path or '' if not found."""
    if custom and os.path.isfile(custom):
        return custom
    found = shutil.which("tesseract")
    if found:
        return found
    for p in COMMON_WINDOWS_PATHS:
        if os.path.isfile(p):
            return p
    return ""


def tesseract_available(custom: str = "") -> bool:
    return bool(find_tesseract(custom))


def _ocr_with_cli(tess: str, image: str, lang: str) -> str:
    with tempfile.TemporaryDirectory(prefix="jamtools-ocr") as tmp:
        out_base = os.path.join(tmp, "out")
        cmd = [tess, image, out_base, "-l", lang, "--psm", "6", "-c", "preserve_interword_spaces=1"]
        subprocess.run(cmd, capture_output=True, check=False, timeout=300)
        txt = out_base + ".txt"
        if os.path.isfile(txt):
            with open(txt, encoding="utf-8", errors="replace") as fh:
                return fh.read()
        return ""


def ocr_image(image: str, lang: str = "fas+eng", tesseract_path: str = "") -> str:
    """Run OCR on a single image file. Raises MissingDependencyError if Tesseract is absent."""
    tess = find_tesseract(tesseract_path)
    if not tess:
        raise MissingDependencyError(
            "Tesseract-OCR",
            "Install Tesseract (https://github.com/tesseract-ocr/tesseract) "
            "and set its path in Settings, or: pip install pytesseract",
        )
    # Prefer pytesseract when installed (better error handling), else raw CLI.
    try:
        import pytesseract  # type: ignore

        if tesseract_path or tess != shutil.which("tesseract"):
            pytesseract.pytesseract.tesseract_cmd = tess
        from PIL import Image

        with Image.open(image) as im:
            return pytesseract.image_to_string(im, lang=lang, config="--psm 6")
    except ImportError:
        return _ocr_with_cli(tess, image, lang)


def ocr_pdf(pdf: str, lang: str = "fas+eng", dpi: int = 200, tesseract_path: str = "",
            on_progress=None, cancel=None) -> str:
    """Render PDF pages to images, then OCR each page."""
    from . import pdf_tools

    if not pdf_tools.available():
        raise MissingDependencyError("PyMuPDF", "pip install PyMuPDF")
    with tempfile.TemporaryDirectory(prefix="jamtools-ocrpdf") as tmp:
        pages = pdf_tools.pdf_to_images(pdf, tmp, dpi=dpi, fmt="png",
                                        overwrite=True, cancel=cancel)
        texts: list[str] = []
        for i, page_img in enumerate(pages):
            if cancel and cancel():
                break
            texts.append(f"===== page {i + 1} =====\n" + ocr_image(page_img, lang, tesseract_path))
            if on_progress:
                on_progress(i + 1, len(pages), page_img)
        return "\n\n".join(texts)
