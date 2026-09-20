"""Image tools built on Pillow: convert / resize / compress / watermark."""

from __future__ import annotations

import os

from .common import ensure_dir, require, unique_path

SUPPORTED_INPUT = {".jpg", ".jpeg", ".png", ".webp", ".bmp", ".tiff", ".tif", ".gif", ".ico", ".ppm", ".pgm"}
FORMAT_EXT = {"JPEG": ".jpg", "PNG": ".png", "WEBP": ".webp", "BMP": ".bmp", "TIFF": ".tiff", "GIF": ".gif", "ICO": ".ico"}


def _pil():
    return require("PIL.Image", "Pillow", "pip install Pillow")


def image_info(path: str) -> dict:
    Image = _pil()
    with Image.open(path) as im:
        return {
            "path": path,
            "format": im.format or "?",
            "size": f"{im.width}×{im.height}",
            "width": im.width,
            "height": im.height,
            "mode": im.mode,
            "bytes": os.path.getsize(path),
        }


def _prep_save(im, fmt: str):
    """Convert image mode so it can be saved in *fmt*."""
    fmt = fmt.upper()
    if fmt == "JPEG" and im.mode in ("RGBA", "LA", "PA", "P"):
        bg = _pil().new("RGB", im.size, (255, 255, 255))
        if im.mode == "P":
            im = im.convert("RGBA")
        if "A" in im.getbands():
            bg.paste(im, mask=im.split()[-1])
        else:
            bg.paste(im)
        return bg
    if fmt in ("BMP", "ICO") and im.mode == "RGBA":
        bg = _pil().new("RGB", im.size, (255, 255, 255))
        bg.paste(im, mask=im.split()[-1])
        return bg
    if fmt == "GIF" and im.mode == "RGBA":
        return im.convert("P", dither=_pil().Dither.FLOYDSTEINBERG)
    return im


def convert_image(src: str, out_dir: str, fmt: str, quality: int = 90,
                  keep_metadata: bool = False, overwrite: bool = False) -> str:
    Image = _pil()
    Exif = None
    fmt = fmt.upper()
    if fmt == "JPG":
        fmt = "JPEG"
    ensure_dir(out_dir)
    stem = os.path.splitext(os.path.basename(src))[0]
    dst = os.path.join(out_dir, stem + FORMAT_EXT.get(fmt, ".img"))
    if not overwrite:
        dst = unique_path(dst)
    with Image.open(src) as im:
        exif = im.info.get("exif")
        out = _prep_save(im, fmt)
        kw: dict = {}
        if fmt in ("JPEG", "WEBP"):
            kw["quality"] = int(quality)
        if fmt == "PNG":
            kw["optimize"] = True
        if keep_metadata and exif and fmt == "JPEG":
            kw["exif"] = exif
        out.save(dst, fmt, **kw)
    return dst


def resize_image(src: str, out_dir: str, width: int = 0, height: int = 0,
                 mode: str = "fit", keep_aspect: bool = True, max_side: int = 0,
                 overwrite: bool = False, suffix: str = "") -> str:
    """Resize one image. mode: fit | fill | exact. If max_side>0 it wins over w/h."""
    Image = _pil()
    ensure_dir(out_dir)
    stem, ext = os.path.splitext(os.path.basename(src))
    dst = os.path.join(out_dir, f"{stem}{suffix}{ext or '.jpg'}")
    if not overwrite:
        dst = unique_path(dst)
    with Image.open(src) as im:
        src_w, src_h = im.size
        if max_side and max_side > 0:
            scale = min(1.0, max_side / max(src_w, src_h))
            target = (max(1, round(src_w * scale)), max(1, round(src_h * scale)))
            out = im.resize(target, Image.LANCZOS)
        else:
            w = width or src_w
            h = height or src_h
            if keep_aspect and mode != "exact":
                scale = min(w / src_w, h / src_h)
                target = (max(1, round(src_w * scale)), max(1, round(src_h * scale)))
                out = im.resize(target, Image.LANCZOS)
            elif mode == "fill":
                scale = max(w / src_w, h / src_h)
                tmp = im.resize((round(src_w * scale), round(src_h * scale)), Image.LANCZOS)
                left = (tmp.width - w) // 2
                top = (tmp.height - h) // 2
                out = tmp.crop((left, top, left + w, top + h))
            else:  # exact
                out = im.resize((w, h), Image.LANCZOS)
        save_kw: dict = {}
        fmt = (im.format or "PNG").upper()
        if fmt in ("JPEG", "JPG"):
            fmt = "JPEG"
            save_kw = {"quality": 92}
        out = _prep_save(out, fmt)
        out.save(dst, fmt, **save_kw)
    return dst


def compress_image(src: str, out_dir: str, quality: int = 75, max_side: int = 1920,
                   overwrite: bool = False) -> tuple[str, int, int]:
    """Shrink + re-encode. Returns (dst, before, after)."""
    Image = _pil()
    ensure_dir(out_dir)
    before = os.path.getsize(src)
    stem = os.path.splitext(os.path.basename(src))[0]
    with Image.open(src) as im:
        fmt = (im.format or "JPEG").upper()
        if fmt == "JPG":
            fmt = "JPEG"
        if fmt not in ("JPEG", "PNG", "WEBP"):
            fmt = "JPEG"  # photos compress best as JPEG
        scale = min(1.0, max_side / max(im.size)) if max_side else 1.0
        out = im if scale >= 1.0 else im.resize(
            (max(1, round(im.width * scale)), max(1, round(im.height * scale))), Image.LANCZOS)
        out = _prep_save(out, fmt)
        dst = os.path.join(out_dir, stem + FORMAT_EXT[fmt])
        if not overwrite:
            dst = unique_path(dst)
        kw: dict = {"optimize": True}
        if fmt in ("JPEG", "WEBP"):
            kw["quality"] = int(quality)
        out.save(dst, fmt, **kw)
    return dst, before, os.path.getsize(dst)


POSITIONS = ("top-left", "top-right", "bottom-left", "bottom-right", "center")


def _anchor(pos: str, base: tuple[int, int], box: tuple[int, int], margin: int) -> tuple[int, int]:
    bw, bh = base
    w, h = box
    return {
        "top-left": (margin, margin),
        "top-right": (bw - w - margin, margin),
        "bottom-left": (margin, bh - h - margin),
        "bottom-right": (bw - w - margin, bh - h - margin),
        "center": ((bw - w) // 2, (bh - h) // 2),
    }[pos]


def watermark_image(src: str, out_dir: str, text: str = "", logo: str = "",
                    position: str = "bottom-right", opacity: int = 40,
                    scale: float = 0.18, tiled: bool = False,
                    overwrite: bool = False) -> str:
    """Add a text and/or logo watermark. Returns output path."""
    Image = _pil()
    ImageDraw = require("PIL.ImageDraw", "Pillow")
    ImageFont = require("PIL.ImageFont", "Pillow")
    ensure_dir(out_dir)
    stem, ext = os.path.splitext(os.path.basename(src))
    dst = os.path.join(out_dir, f"{stem}_wm{ext or '.jpg'}")
    if not overwrite:
        dst = unique_path(dst)

    with Image.open(src) as im:
        base = im.convert("RGBA")
        layer = Image.new("RGBA", base.size, (0, 0, 0, 0))
        draw = ImageDraw.Draw(layer)
        alpha = round(255 * max(5, min(opacity, 100)) / 100)

        overlays: list = []
        if logo and os.path.isfile(logo):
            with Image.open(logo) as lg:
                lg = lg.convert("RGBA")
                target_w = max(16, round(base.width * scale))
                r = target_w / lg.width
                lg = lg.resize((target_w, max(16, round(lg.height * r))), Image.LANCZOS)
                if alpha < 255:  # apply global opacity to logo
                    px = lg.load()
                    for y in range(lg.height):
                        for x in range(lg.width):
                            rr, gg, bb, aa = px[x, y]
                            px[x, y] = (rr, gg, bb, round(aa * alpha / 255))
                overlays.append(("img", lg))
        if text:
            try:
                font_size = max(12, round(base.height * scale / 3))
                font = ImageFont.truetype("arial.ttf", font_size)
            except Exception:
                font = ImageFont.load_default()
            bbox = draw.textbbox((0, 0), text, font=font)
            tw, th = bbox[2] - bbox[0] + 20, bbox[3] - bbox[1] + 16
            tile = Image.new("RGBA", (tw, th), (0, 0, 0, 0))
            ImageDraw.Draw(tile).text((10, 8), text, font=font, fill=(255, 255, 255, alpha))
            # dark outline for readability
            ol = Image.new("RGBA", (tw, th), (0, 0, 0, 0))
            ImageDraw.Draw(ol).text((10, 8), text, font=font, fill=(0, 0, 0, alpha))
            overlays.append(("txt", tile, ol))

        margin = max(12, base.width // 80)
        if tiled and overlays:
            kind = overlays[0][0]
            mark = overlays[0][1]
            step_x, step_y = mark.width + margin * 3, mark.height + margin * 3
            for y in range(margin, base.height, step_y):
                for x in range(margin, base.width, step_x):
                    if kind == "txt":
                        layer.alpha_composite(overlays[0][2], (x + 1, y + 1))
                    layer.alpha_composite(mark, (x, y))
        else:
            for ov in overlays:
                if ov[0] == "txt":
                    _, tile, ol = ov
                    x, y = _anchor(position, base.size, tile.size, margin)
                    layer.alpha_composite(ol, (x + 1, y + 1))
                    layer.alpha_composite(tile, (x, y))
                else:
                    mark = ov[1]
                    x, y = _anchor(position, base.size, mark.size, margin)
                    layer.alpha_composite(mark, (x, y))

        out = Image.alpha_composite(base, layer).convert("RGB")
        fmt = (im.format or "JPEG").upper()
        if fmt == "JPG":
            fmt = "JPEG"
        if fmt not in FORMAT_EXT:
            fmt = "JPEG"
        out.save(dst, fmt, quality=93)
    return dst
