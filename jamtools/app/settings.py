"""Persistent settings (QSettings wrapper with plain-dataclass access)."""

from __future__ import annotations

from dataclasses import dataclass


@dataclass
class AppSettings:
    lang: str = "fa"
    theme: str = "dark"          # dark | light
    default_output: str = ""     # empty => same as source
    tesseract_path: str = ""     # empty => auto-detect
    ocr_lang: str = "fas+eng"
    clipboard_max_items: int = 300
    clipboard_monitor: bool = True


_SETTINGS: AppSettings | None = None


def get_settings() -> AppSettings:
    global _SETTINGS
    if _SETTINGS is None:
        _SETTINGS = AppSettings()
        try:
            from PySide6.QtCore import QSettings

            q = QSettings("JamSoft", "JamTools")
            _SETTINGS.lang = q.value("lang", "fa")
            _SETTINGS.theme = q.value("theme", "dark")
            _SETTINGS.default_output = q.value("default_output", "")
            _SETTINGS.tesseract_path = q.value("tesseract_path", "")
            _SETTINGS.ocr_lang = q.value("ocr_lang", "fas+eng")
            _SETTINGS.clipboard_max_items = int(q.value("clipboard_max_items", 300))
            _SETTINGS.clipboard_monitor = q.value("clipboard_monitor", True) in (True, "true", 1, "1")
        except Exception:
            pass
    return _SETTINGS


def save_settings(s: AppSettings | None = None) -> None:
    s = s or get_settings()
    try:
        from PySide6.QtCore import QSettings

        q = QSettings("JamSoft", "JamTools")
        q.setValue("lang", s.lang)
        q.setValue("theme", s.theme)
        q.setValue("default_output", s.default_output)
        q.setValue("tesseract_path", s.tesseract_path)
        q.setValue("ocr_lang", s.ocr_lang)
        q.setValue("clipboard_max_items", s.clipboard_max_items)
        q.setValue("clipboard_monitor", s.clipboard_monitor)
    except Exception:
        pass


def app_data_dir() -> str:
    """Folder for sqlite db / logs (per-user, cross-platform)."""
    import os

    base = os.environ.get("APPDATA") or os.path.expanduser("~/.local/share")
    path = os.path.join(base, "JamTools")
    os.makedirs(path, exist_ok=True)
    return path
