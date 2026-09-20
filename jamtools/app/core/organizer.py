"""File organizer: sort a messy folder by type / extension / month."""

from __future__ import annotations

import datetime
import json
import os
from dataclasses import dataclass

from .common import ensure_dir, unique_path

# folder -> extensions
CATEGORIES: dict[str, set[str]] = {
    "Images": {".jpg", ".jpeg", ".png", ".gif", ".webp", ".bmp", ".tiff", ".tif", ".svg", ".ico", ".heic", ".raw"},
    "Videos": {".mp4", ".mkv", ".avi", ".mov", ".wmv", ".flv", ".webm", ".m4v", ".mpg", ".mpeg", ".3gp"},
    "Audio": {".mp3", ".wav", ".flac", ".m4a", ".ogg", ".opus", ".wma", ".aac", ".mid"},
    "Documents": {".pdf", ".doc", ".docx", ".xls", ".xlsx", ".ppt", ".pptx", ".txt", ".rtf", ".odt", ".ods", ".csv", ".md"},
    "Archives": {".zip", ".rar", ".7z", ".tar", ".gz", ".bz2", ".xz", ".cab"},
    "Programs": {".exe", ".msi", ".apk", ".dmg", ".pkg", ".appimage", ".bat", ".ps1"},
    "Code": {".py", ".js", ".ts", ".html", ".css", ".json", ".xml", ".java", ".c", ".cpp", ".h", ".cs", ".php", ".go", ".rs"},
    "Fonts": {".ttf", ".otf", ".woff", ".woff2"},
    "Torrents": {".torrent"},
}

OTHER = "Others"


def category_of(filename: str) -> str:
    ext = os.path.splitext(filename)[1].lower()
    for cat, exts in CATEGORIES.items():
        if ext in exts:
            return cat
    return OTHER


@dataclass
class Move:
    src: str
    dst: str


def plan_organize(folder: str, mode: str = "type") -> list[Move]:
    """Build the move list (top-level files only; never touches subfolders).

    mode: type | ext | date(=type + YYYY-MM)
    """
    moves: list[Move] = []
    for name in sorted(os.listdir(folder)):
        src = os.path.join(folder, name)
        if not os.path.isfile(src):
            continue
        if name.startswith(".jamtools-undo"):
            continue
        ext = os.path.splitext(name)[1].lower().lstrip(".") or "noext"
        if mode == "ext":
            sub = ext.upper() if len(ext) <= 4 else ext
        elif mode == "date":
            mtime = datetime.datetime.fromtimestamp(os.path.getmtime(src))
            sub = os.path.join(category_of(name), mtime.strftime("%Y-%m"))
        else:
            sub = category_of(name)
        dst = os.path.join(folder, sub, name)
        if os.path.abspath(src) != os.path.abspath(dst):
            moves.append(Move(src, dst))
    return moves


def apply_plan(moves: list[Move], on_progress=None, cancel=None) -> tuple[int, list[str], str]:
    """Execute moves; writes an undo log. Returns (moved, errors, undo_log_path)."""
    moved, errors = 0, []
    done: list[dict[str, str]] = []
    base = os.path.dirname(moves[0].src) if moves else ""
    for i, m in enumerate(moves):
        if cancel and cancel():
            break
        try:
            ensure_dir(os.path.dirname(m.dst))
            dst = unique_path(m.dst)
            os.rename(m.src, dst)
            done.append({"src": m.src, "dst": dst})
            moved += 1
        except Exception as exc:
            errors.append(f"{m.src}: {exc}")
        if on_progress:
            on_progress(i + 1, len(moves), m.src)
    undo_path = ""
    if done and base:
        undo_path = os.path.join(base, ".jamtools-undo.json")
        try:
            with open(undo_path, "w", encoding="utf-8") as fh:
                json.dump(done, fh, ensure_ascii=False, indent=1)
        except OSError:
            undo_path = ""
    return moved, errors, undo_path


def undo_from_log(undo_path: str) -> tuple[int, list[str]]:
    """Restore files using a previously written undo log."""
    with open(undo_path, encoding="utf-8") as fh:
        entries = json.load(fh)
    restored, errors = 0, []
    for e in reversed(entries):
        try:
            if os.path.isfile(e["dst"]):
                ensure_dir(os.path.dirname(e["src"]))
                os.rename(e["dst"], unique_path(e["src"]) if os.path.exists(e["src"]) else e["src"])
                restored += 1
        except Exception as exc:
            errors.append(f"{e.get('dst')}: {exc}")
    return restored, errors
