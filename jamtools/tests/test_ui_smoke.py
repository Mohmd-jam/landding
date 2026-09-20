"""Offscreen smoke test: build the main window and visit every page.

Skipped automatically when PySide6 is unavailable.
"""

import os

import pytest

pyside = pytest.importorskip("PySide6.QtWidgets")

os.environ.setdefault("QT_QPA_PLATFORM", "offscreen")


def test_main_window_all_pages(qapp=None):
    from PySide6.QtWidgets import QApplication

    from app.i18n import set_lang
    from app.ui.main_window import NAV, MainWindow

    app = QApplication.instance() or QApplication([])
    set_lang("fa")
    win = MainWindow()
    win.show()
    assert win.stack.count() == len(NAV) == len(win.pages) == 13  # dashboard + 11 tools + batch
    # visit every page (catches build-time errors in each page)
    for pid in list(win.pages):
        win.goto(pid)
        app.processEvents()
        assert win.stack.currentWidget() is win.pages[pid]
    # exercise theme + language rebuild paths
    win.toggle_theme()
    win.toggle_lang()   # -> en
    win.toggle_lang()   # -> fa
    app.processEvents()
    win.close()
