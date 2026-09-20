"""PDF tools built on PyMuPDF (optional) and pdf2docx (optional)."""

from __future__ import annotations

import os

from .common import MissingDependencyError, ensure_dir, require, unique_path

PDF_HINT = "pip install PyMuPDF"
DOCX_HINT = "pip install pdf2docx"


def _fitz():
    try:
        return require("pymupdf", "PyMuPDF", PDF_HINT)
    except MissingDependencyError:
        return require("fitz", "PyMuPDF", PDF_HINT)


def available() -> bool:
    try:
        _fitz()
        return True
    except MissingDependencyError:
        return False


def docx_available() -> bool:
    try:
        require("pdf2docx", "pdf2docx", DOCX_HINT)
        return True
    except MissingDependencyError:
        return False


def pdf_info(path: str) -> dict:
    fitz = _fitz()
    with fitz.open(path) as doc:
        return {
            "path": path,
            "pages": doc.page_count,
            "title": doc.metadata.get("title", "") if doc.metadata else "",
            "author": doc.metadata.get("author", "") if doc.metadata else "",
            "bytes": os.path.getsize(path),
        }


def pdf_to_images(pdf: str, out_dir: str, dpi: int = 150, fmt: str = "png",
                  overwrite: bool = False, on_progress=None, cancel=None) -> list[str]:
    fitz = _fitz()
    ensure_dir(out_dir)
    stem = os.path.splitext(os.path.basename(pdf))[0]
    zoom = dpi / 72.0
    out: list[str] = []
    with fitz.open(pdf) as doc:
        for i, page in enumerate(doc):
            if cancel and cancel():
                break
            pix = page.get_pixmap(matrix=fitz.Matrix(zoom, zoom))
            dst = os.path.join(out_dir, f"{stem}_p{i + 1:03d}.{fmt}")
            if not overwrite:
                dst = unique_path(dst)
            pix.save(dst)
            out.append(dst)
            if on_progress:
                on_progress(i + 1, doc.page_count, dst)
    return out


def pdf_extract_text(pdf: str, on_progress=None, cancel=None) -> str:
    fitz = _fitz()
    parts: list[str] = []
    with fitz.open(pdf) as doc:
        for i, page in enumerate(doc):
            if cancel and cancel():
                break
            parts.append(page.get_text("text"))
            if on_progress:
                on_progress(i + 1, doc.page_count, f"page {i + 1}")
    return "\n".join(parts)


def pdf_merge(files: list[str], out_file: str, overwrite: bool = False) -> str:
    fitz = _fitz()
    if not out_file.lower().endswith(".pdf"):
        out_file += ".pdf"
    ensure_dir(os.path.dirname(out_file) or ".")
    if not overwrite:
        out_file = unique_path(out_file)
    merged = fitz.open()
    for f in files:
        with fitz.open(f) as doc:
            merged.insert_pdf(doc)
    merged.save(out_file, garbage=4, deflate=True)
    merged.close()
    return out_file


def pdf_split(pdf: str, out_dir: str, overwrite: bool = False,
              on_progress=None, cancel=None) -> list[str]:
    fitz = _fitz()
    ensure_dir(out_dir)
    stem = os.path.splitext(os.path.basename(pdf))[0]
    out: list[str] = []
    with fitz.open(pdf) as doc:
        for i in range(doc.page_count):
            if cancel and cancel():
                break
            single = fitz.open()
            with fitz.open(pdf) as src:
                single.insert_pdf(src, from_page=i, to_page=i)
            dst = os.path.join(out_dir, f"{stem}_p{i + 1:03d}.pdf")
            if not overwrite:
                dst = unique_path(dst)
            single.save(dst)
            single.close()
            out.append(dst)
            if on_progress:
                on_progress(i + 1, doc.page_count, dst)
    return out


def pdf_compress(pdf: str, out_dir: str, dpi: int = 150, quality: int = 70,
                 overwrite: bool = False) -> tuple[str, int, int]:
    """Re-render each page as a JPEG at *dpi* and rebuild the PDF. Returns (dst, before, after)."""
    fitz = _fitz()
    ensure_dir(out_dir)
    before = os.path.getsize(pdf)
    stem = os.path.splitext(os.path.basename(pdf))[0]
    dst = os.path.join(out_dir, f"{stem}_small.pdf")
    if not overwrite:
        dst = unique_path(dst)
    zoom = dpi / 72.0
    with fitz.open(pdf) as src:
        out = fitz.open()
        for page in src:
            pix = page.get_pixmap(matrix=fitz.Matrix(zoom, zoom))
            img = pix.tobytes("jpg", jpg_quality=quality)
            rect = fitz.Rect(0, 0, pix.width * 72 / dpi, pix.height * 72 / dpi)
            p = out.new_page(width=rect.width, height=rect.height)
            p.insert_image(rect, stream=img)
        out.set_metadata({})
        out.save(dst, garbage=4, deflate=True)
        out.close()
    return dst, before, os.path.getsize(dst)


def pdf_to_docx(pdf: str, out_dir: str, overwrite: bool = False) -> str:
    converter_mod = require("pdf2docx", "pdf2docx", DOCX_HINT)
    ensure_dir(out_dir)
    stem = os.path.splitext(os.path.basename(pdf))[0]
    dst = os.path.join(out_dir, stem + ".docx")
    if not overwrite:
        dst = unique_path(dst)
    cv = converter_mod.Converter(pdf)
    try:
        cv.convert(dst)
    finally:
        cv.close()
    return dst
