import os

import pytest

pymupdf = pytest.importorskip("pymupdf")

from app.core import pdf_tools


def _pdf(path, pages=2, text="Hello PDF"):
    doc = pymupdf.open()
    for i in range(pages):
        doc.new_page().insert_text((72, 72), f"{text} {i}")
    doc.save(path)
    doc.close()
    return path


def test_info_extract_merge_split(tmp_path):
    a = _pdf(str(tmp_path / "a.pdf"), pages=2)
    assert pdf_tools.pdf_info(a)["pages"] == 2
    text = pdf_tools.pdf_extract_text(a)
    assert "Hello PDF 0" in text and "Hello PDF 1" in text
    merged = pdf_tools.pdf_merge([a, a], str(tmp_path / "m.pdf"))
    assert pdf_tools.pdf_info(merged)["pages"] == 4
    parts = pdf_tools.pdf_split(a, str(tmp_path / "parts"))
    assert len(parts) == 2 and all(os.path.isfile(p) for p in parts)


def test_to_images_and_compress(tmp_path):
    a = _pdf(str(tmp_path / "a.pdf"), pages=1)
    imgs = pdf_tools.pdf_to_images(a, str(tmp_path / "imgs"), dpi=72)
    assert len(imgs) == 1 and os.path.isfile(imgs[0])
    dst, before, after = pdf_tools.pdf_compress(a, str(tmp_path / "small"), dpi=72)
    assert os.path.isfile(dst) and after > 0
