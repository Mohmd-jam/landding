"""Archive tools: create / extract ZIP (plus TAR/GZ extract) with stdlib only."""

from __future__ import annotations

import os
import shutil
import tarfile
import zipfile

from .common import ensure_dir, unique_path

EXTRACTABLE = {".zip", ".tar", ".gz", ".tgz", ".tar.gz", ".bz2", ".xz"}


def create_zip(paths: list[str], out_file: str, level: int = 6, overwrite: bool = False) -> tuple[str, int, int]:
    """Zip *paths* (files or folders). Returns (zip_path, file_count, total_bytes)."""
    if not out_file.lower().endswith(".zip"):
        out_file += ".zip"
    ensure_dir(os.path.dirname(out_file) or ".")
    if not overwrite:
        out_file = unique_path(out_file)
    count, total = 0, 0
    level = max(0, min(9, level))
    with zipfile.ZipFile(out_file, "w", zipfile.ZIP_DEFLATED, compresslevel=level) as zf:
        for p in paths:
            if os.path.isfile(p):
                zf.write(p, os.path.basename(p))
                count += 1
                total += os.path.getsize(p)
            elif os.path.isdir(p):
                base = os.path.basename(os.path.normpath(p))
                for root, _dirs, files in os.walk(p):
                    for f in files:
                        fp = os.path.join(root, f)
                        arc = os.path.join(base, os.path.relpath(fp, p))
                        zf.write(fp, arc)
                        count += 1
                        total += os.path.getsize(fp)
    return out_file, count, total


def extract_archive(archive: str, out_dir: str, overwrite: bool = False) -> tuple[str, int]:
    """Extract one archive. Returns (out_dir, extracted_count)."""
    ensure_dir(out_dir)
    lower = archive.lower()
    count = 0
    if lower.endswith(".zip"):
        with zipfile.ZipFile(archive) as zf:
            for info in zf.infolist():
                if info.is_dir():
                    continue
                target = os.path.join(out_dir, info.filename)
                if os.path.exists(target) and not overwrite:
                    target = unique_path(target)
                ensure_dir(os.path.dirname(target))
                with zf.open(info) as src, open(target, "wb") as dst:
                    shutil.copyfileobj(src, dst)
                count += 1
    elif tarfile.is_tarfile(archive):
        with tarfile.open(archive) as tf:
            for member in tf.getmembers():
                if not member.isfile():
                    continue
                target = os.path.join(out_dir, member.name)
                if os.path.exists(target) and not overwrite:
                    target = unique_path(target)
                ensure_dir(os.path.dirname(target))
                with tf.extractfile(member) as src, open(target, "wb") as dst:  # type: ignore[union-attr]
                    shutil.copyfileobj(src, dst)
                count += 1
    else:
        raise ValueError(f"Unsupported archive: {archive}")
    return out_dir, count
