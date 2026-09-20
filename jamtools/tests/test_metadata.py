import os

import pytest

pytest.importorskip("PIL.Image")


def test_strip_image(tmp_path):
    from PIL import Image

    from app.core import metadata

    src = str(tmp_path / "a.jpg")
    im = Image.new("RGB", (64, 64), (10, 20, 30))
    exif = Image.Exif()
    exif[271] = "TestMake"  # Make
    im.save(src, exif=exif)
    before = metadata.read_metadata(src)
    assert any("EXIF" in k for k in before)
    dst = metadata.strip_metadata(src, str(tmp_path / "out"))
    assert os.path.isfile(dst)
    after = metadata.read_metadata(dst)
    assert not any("EXIF" in k for k in after)


def test_pdf_roundtrip(tmp_path):
    pymupdf = pytest.importorskip("pymupdf")
    from app.core import metadata

    src = str(tmp_path / "a.pdf")
    doc = pymupdf.open()
    doc.new_page().insert_text((72, 72), "hello")
    doc.set_metadata({"title": "Secret", "author": "Someone"})
    doc.save(src)
    doc.close()
    assert metadata.read_metadata(src).get("title") == "Secret"
    dst = metadata.strip_metadata(src, str(tmp_path / "out"))
    assert metadata.read_metadata(dst).get("title") in ("", None)
