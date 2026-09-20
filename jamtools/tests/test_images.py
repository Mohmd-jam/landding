import os

import pytest

from app.core import images

PIL = pytest.importorskip("PIL.Image")


def _make(path, size=(400, 300), color=(200, 30, 30)):
    from PIL import Image

    im = Image.new("RGB", size, color)
    im.save(path)
    return path


def test_convert(tmp_path):
    src = _make(str(tmp_path / "a.jpg"))
    out = images.convert_image(src, str(tmp_path / "out"), "PNG")
    assert out.endswith(".png") and os.path.isfile(out)
    info = images.image_info(out)
    assert info["format"] == "PNG" and info["width"] == 400


def test_resize_max_side(tmp_path):
    src = _make(str(tmp_path / "a.jpg"), size=(800, 400))
    out = images.resize_image(src, str(tmp_path / "out"), max_side=200)
    info = images.image_info(out)
    assert max(info["width"], info["height"]) == 200


def test_resize_fill(tmp_path):
    src = _make(str(tmp_path / "a.png"), size=(800, 400))
    out = images.resize_image(src, str(tmp_path / "out"), width=100, height=100,
                              mode="fill", keep_aspect=False)
    info = images.image_info(out)
    assert (info["width"], info["height"]) == (100, 100)


def test_compress_shrinks(tmp_path):
    from PIL import Image
    import random

    random.seed(1)
    # noisy image compresses poorly at q95, well at q40
    im = Image.new("RGB", (600, 600))
    px = im.load()
    for y in range(600):
        for x in range(600):
            px[x, y] = (random.randrange(256), random.randrange(256), random.randrange(256))
    src = str(tmp_path / "noise.png")
    im.save(src)
    dst, before, after = images.compress_image(src, str(tmp_path / "out"), quality=40)
    assert os.path.isfile(dst) and after < before


def test_watermark_text(tmp_path):
    src = _make(str(tmp_path / "a.jpg"))
    out = images.watermark_image(src, str(tmp_path / "out"), text="JamTools ©",
                                 position="bottom-right", opacity=50)
    assert os.path.isfile(out)


def test_watermark_logo(tmp_path):
    src = _make(str(tmp_path / "a.jpg"), size=(500, 500))
    logo = _make(str(tmp_path / "logo.png"), size=(80, 40), color=(30, 30, 200))
    out = images.watermark_image(src, str(tmp_path / "out"), logo=logo, tiled=True)
    assert os.path.isfile(out)
