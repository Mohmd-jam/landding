"""Read & strip metadata from images, PDFs and audio files."""

from __future__ import annotations

import os

from .common import MissingDependencyError, ensure_dir, unique_path

IMAGE_EXTS = {".jpg", ".jpeg", ".png", ".webp", ".tiff", ".tif", ".bmp", ".gif"}
PDF_EXTS = {".pdf"}
AUDIO_EXTS = {".mp3", ".flac", ".m4a", ".mp4", ".ogg", ".opus", ".wav", ".wma"}


def read_image_metadata(path: str) -> dict:
    from PIL import Image
    from PIL.ExifTags import TAGS

    info: dict = {}
    with Image.open(path) as im:
        info["Format"] = str(im.format)
        info["Size"] = f"{im.width}×{im.height}"
        info["Mode"] = im.mode
        exif = im.getexif()
        if exif:
            for tag_id, value in exif.items():
                tag = TAGS.get(tag_id, f"Tag {tag_id}")
                info[f"EXIF:{tag}"] = str(value)[:200]
        for k, v in im.info.items():
            if k == "exif":
                continue
            info[f"Info:{k}"] = str(v)[:200]
    if len(info) <= 3:
        info["(status)"] = "no embedded metadata found"
    return info


def strip_image_metadata(src: str, out_dir: str, overwrite: bool = False) -> str:
    from PIL import Image

    ensure_dir(out_dir)
    stem, ext = os.path.splitext(os.path.basename(src))
    dst = os.path.join(out_dir, f"{stem}_clean{ext or '.jpg'}")
    if not overwrite:
        dst = unique_path(dst)
    with Image.open(src) as im:
        clean = im.copy()
        clean.info = {}  # drop PNG text chunks / dpi / etc. (JPEG EXIF is dropped by not passing exif=)
        fmt = (im.format or "PNG").upper()
        if fmt == "JPG":
            fmt = "JPEG"
        kw: dict = {}
        if fmt == "JPEG":
            kw = {"quality": 95}
        clean.save(dst, fmt, **kw)
    return dst


def read_pdf_metadata(path: str) -> dict:
    from . import pdf_tools

    fitz = pdf_tools._fitz()
    with fitz.open(path) as doc:
        meta = dict(doc.metadata or {})
        meta["Pages"] = doc.page_count
        return meta or {"(status)": "no metadata"}


def strip_pdf_metadata(src: str, out_dir: str, overwrite: bool = False) -> str:
    from . import pdf_tools

    fitz = pdf_tools._fitz()
    ensure_dir(out_dir)
    stem = os.path.splitext(os.path.basename(src))[0]
    dst = os.path.join(out_dir, f"{stem}_clean.pdf")
    if not overwrite:
        dst = unique_path(dst)
    with fitz.open(src) as doc:
        doc.set_metadata({})
        # also drop embedded XML metadata
        try:
            doc.del_xml_metadata()
        except Exception:
            pass
        doc.save(dst, garbage=4, deflate=True)
    return dst


def read_audio_metadata(path: str) -> dict:
    try:
        import mutagen  # type: ignore
    except ImportError as exc:
        raise MissingDependencyError("mutagen", "pip install mutagen") from exc
    f = mutagen.File(path, easy=True)
    if not f:
        return {"(status)": "unsupported or tag-less file"}
    out = {}
    for k, v in f.items():
        out[str(k)] = str(v)[:200]
    return out or {"(status)": "no tags"}


def strip_audio_metadata(src: str, out_dir: str, overwrite: bool = False) -> str:
    import shutil

    try:
        import mutagen  # type: ignore
    except ImportError as exc:
        raise MissingDependencyError("mutagen", "pip install mutagen") from exc
    ensure_dir(out_dir)
    stem, ext = os.path.splitext(os.path.basename(src))
    dst = os.path.join(out_dir, f"{stem}_clean{ext}")
    if not overwrite:
        dst = unique_path(dst)
    shutil.copy2(src, dst)
    f = mutagen.File(dst, easy=False)
    if f is not None:
        try:
            f.delete()
            f.save()
        except Exception:
            pass
    return dst


def kind_of(path: str) -> str:
    ext = os.path.splitext(path)[1].lower()
    if ext in IMAGE_EXTS:
        return "image"
    if ext in PDF_EXTS:
        return "pdf"
    if ext in AUDIO_EXTS:
        return "audio"
    return "other"


def read_metadata(path: str) -> dict:
    kind = kind_of(path)
    if kind == "image":
        return read_image_metadata(path)
    if kind == "pdf":
        return read_pdf_metadata(path)
    if kind == "audio":
        return read_audio_metadata(path)
    return {"(status)": f"unsupported type ({os.path.splitext(path)[1] or '?'})"}


def strip_metadata(src: str, out_dir: str, overwrite: bool = False) -> str:
    kind = kind_of(src)
    if kind == "image":
        return strip_image_metadata(src, out_dir, overwrite)
    if kind == "pdf":
        return strip_pdf_metadata(src, out_dir, overwrite)
    if kind == "audio":
        return strip_audio_metadata(src, out_dir, overwrite)
    raise ValueError(f"Unsupported type: {src}")
