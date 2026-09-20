"""Application entry point."""

from __future__ import annotations

import os
import sys


def main() -> int:
    from PySide6.QtCore import Qt
    from PySide6.QtWidgets import QApplication

    from . import __app_name__, __version__
    from .i18n import set_lang
    from .settings import get_settings
    from .ui.main_window import MainWindow
    from .ui.theme import setup_font

    # crisp rendering on high-DPI Windows displays
    try:
        QApplication.setHighDpiScaleFactorRoundingPolicy(
            Qt.HighDpiScaleFactorRoundingPolicy.PassThrough)
    except Exception:
        pass

    app = QApplication(sys.argv)
    app.setApplicationName(__app_name__)
    app.setApplicationVersion(__version__)
    app.setOrganizationName("JamSoft")

    icon_path = os.path.join(os.path.dirname(__file__), "..", "assets", "icon.png")
    if os.path.isfile(icon_path):
        from PySide6.QtGui import QIcon

        app.setWindowIcon(QIcon(icon_path))

    s = get_settings()
    set_lang(s.lang)
    setup_font(app)

    win = MainWindow()
    win.show()
    return app.exec()


if __name__ == "__main__":
    sys.exit(main())
