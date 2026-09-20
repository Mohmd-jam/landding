"""Shared helpers for all tools."""

from __future__ import annotations

import importlib
import os
import re


class MissingDependencyError(Exception):
    """Raised when an optional third-party package is not installed."""

    def __init__(self, pip_name: str, hint: str = "") -> None:
        self.pip_name = pip_name
        self.hint = hint or f"pip install {pip_name}"
        super().__init__(f"Missing dependency: {pip_name} ({self.hint})")


def require(module: str, pip_name: str = "", hint: str = ""):
    """Import *module* or raise MissingDependencyError with install hint."""
    try:
        return importlib.import_module(module)
    except ImportError as exc:
        raise MissingDependencyError(pip_name or module, hint) from exc


def format_size(num: int) -> str:
    num = float(max(0, num))
    for unit in ("B", "KB", "MB", "GB", "TB"):
        if num < 1024 or unit == "TB":
            return f"{num:.1f} {unit}" if unit != "B" else f"{int(num)} B"
        num /= 1024
    return f"{num:.1f} TB"


_INVALID_CHARS = re.compile(r'[<>:"/\\|?*\x00-\x1f]')


def safe_filename(name: str, fallback: str = "file") -> str:
    """Make a string safe to use as a file name on Windows."""
    name = _INVALID_CHARS.sub("_", name).strip().rstrip(".")
    return name or fallback


def unique_path(path: str) -> str:
    """Return *path* or a free variant like ``name (2).ext``."""
    if not os.path.exists(path):
        return path
    root, ext = os.path.splitext(path)
    i = 2
    while True:
        candidate = f"{root} ({i}){ext}"
        if not os.path.exists(candidate):
            return candidate
        i += 1


def ensure_dir(path: str) -> str:
    os.makedirs(path, exist_ok=True)
    return path


def iter_files(paths: list[str], exts: set[str] | None = None, recursive: bool = False) -> list[str]:
    """Expand files/folders into a sorted unique file list, optionally filtered by extension."""
    out: list[str] = []
    for p in paths:
        if os.path.isfile(p):
            out.append(p)
        elif os.path.isdir(p):
            if recursive:
                for root, _dirs, files in os.walk(p):
                    for f in files:
                        out.append(os.path.join(root, f))
            else:
                for f in os.listdir(p):
                    fp = os.path.join(p, f)
                    if os.path.isfile(fp):
                        out.append(fp)
    if exts:
        exts = {e.lower() if e.startswith(".") else f".{e.lower()}" for e in exts}
        out = [f for f in out if os.path.splitext(f)[1].lower() in exts]
    return sorted(set(out))


class CancelledError(Exception):
    """Raised by long tasks when the user presses Cancel."""
