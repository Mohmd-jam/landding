"""Dark / light QSS themes + font setup."""

from __future__ import annotations

ACCENT = "#6c8cff"
ACCENT_DARK = "#3b5bdb"

DARK_QSS = """
* { outline: none; }
QMainWindow, QWidget#central { background: #141822; color: #e9ecf3; }
QWidget { font-size: 13px; }
QLabel { color: #e9ecf3; background: transparent; }
QLabel#muted { color: #9aa3b5; }
QLabel#title { font-size: 20px; font-weight: 700; }
QLabel#cardTitle { font-size: 14px; font-weight: 700; }
QFrame#sidebar { background: #1b2130; border-right: 1px solid #2a3348; }
QFrame#card { background: #1e2433; border: 1px solid #2b3350; border-radius: 12px; }
QFrame#topbar { background: #1b2130; border-bottom: 1px solid #2a3348; }
QListWidget#nav { background: transparent; border: none; }
QListWidget#nav::item { color: #c6ccda; padding: 10px 12px; border-radius: 8px; margin: 1px 8px; }
QListWidget#nav::item:selected { background: #2b3document5c8; color: #ffffff; }
QListWidget#nav::item:hover:!selected { background: #232b40; }
QListWidget, QTreeWidget, QTableWidget { background: #171c29; border: 1px solid #2a3348; border-radius: 8px; }
QListWidget::item, QTableWidget::item { padding: 4px; }
QPushButton { background: #2a3350; color: #e9ecf3; border: 1px solid #38436a; border-radius: 8px; padding: 7px 14px; }
QPushButton:hover { background: #354063; border-color: #6c8cff; }
QPushButton:disabled { color: #6b718f; background: #20263a; border-color: #2a3348; }
QPushButton#primary { background: #3b5bdb; border-color: #3b5bdb; color: white; font-weight: 700; }
QPushButton#primary:hover { background: #4c6ef5; }
QPushButton#danger { background: #5c1f28; border-color: #a61e4d; color: #ffd6dd; }
QPushButton#ghost { background: transparent; border: 1px solid #2a3348; }
QLineEdit, QTextEdit, QPlainTextEdit, QSpinBox, QComboBox { background: #171c29; color: #e9ecf3;
    border: 1px solid #2a3348; border-radius: 8px; padding: 6px 8px; selection-background-color: #3b5bdb; }
QComboBox QAbstractItemView { background: #1e2433; selection-background-color: #3b5bdb; }
QProgressBar { background: #171c29; border: 1px solid #2a3348; border-radius: 8px; text-align: center; color: #e9ecf3; height: 18px; }
QProgressBar::chunk { background: qlineargradient(x1:0, y1:0, x2:1, y2:0, stop:0 #3b5bdb, stop:1 #6c8cff); border-radius: 7px; }
QTabWidget::pane { border: 1px solid #2a3348; border-radius: 8px; background: #1e2433; }
QTabBar::tab { background: transparent; color: #9aa3b5; padding: 8px 16px; margin-right: 4px; border-top-left-radius: 8px; border-top-right-radius: 8px; }
QTabBar::tab:selected { background: #2a3350; color: white; }
QCheckBox, QRadioButton { color: #e9ecf3; spacing: 8px; }
QSlider::groove:horizontal { height: 6px; background: #2a3348; border-radius: 3px; }
QSlider::handle:horizontal { background: #6c8cff; width: 16px; margin: -6px 0; border-radius: 8px; }
QHeaderView::section { background: #232b40; color: #c6ccda; border: none; padding: 6px; }
QTableCornerButton::section { background: #232b40; border: none; }
QSplitter::handle { background: #2a3348; }
QScrollBar:vertical { background: transparent; width: 10px; }
QScrollBar::handle:vertical { background: #38436a; border-radius: 5px; min-height: 30px; }
QScrollBar::add-line:vertical, QScrollBar::sub-line:vertical { height: 0; }
QStatusBar { background: #1b2130; color: #9aa3b5; }
QToolTip { background: #232b40; color: #e9ecf3; border: 1px solid #38436a; }
QMessageBox { background: #1e2433; }
"""

LIGHT_QSS = """
* { outline: none; }
QMainWindow, QWidget#central { background: #eef1f7; color: #1d2433; }
QWidget { font-size: 13px; }
QLabel { color: #1d2433; background: transparent; }
QLabel#muted { color: #6b7686; }
QLabel#title { font-size: 20px; font-weight: 700; }
QLabel#cardTitle { font-size: 14px; font-weight: 700; }
QFrame#sidebar { background: #ffffff; border-right: 1px solid #dde3ee; }
QFrame#card { background: #ffffff; border: 1px solid #dde3ee; border-radius: 12px; }
QFrame#topbar { background: #ffffff; border-bottom: 1px solid #dde3ee; }
QListWidget#nav { background: transparent; border: none; }
QListWidget#nav::item { color: #3c4657; padding: 10px 12px; border-radius: 8px; margin: 1px 8px; }
QListWidget#nav::item:selected { background: #e3e9ff; color: #1d2433; }
QListWidget#nav::item:hover:!selected { background: #f0f3fa; }
QListWidget, QTreeWidget, QTableWidget { background: #ffffff; border: 1px solid #dde3ee; border-radius: 8px; }
QListWidget::item, QTableWidget::item { padding: 4px; }
QPushButton { background: #ffffff; color: #1d2433; border: 1px solid #ccd4e3; border-radius: 8px; padding: 7px 14px; }
QPushButton:hover { border-color: #3b5bdb; color: #3b5bdb; }
QPushButton:disabled { color: #a5adbd; background: #f0f3f8; }
QPushButton#primary { background: #3b5bdb; border-color: #3b5bdb; color: white; font-weight: 700; }
QPushButton#primary:hover { background: #4c6ef5; }
QPushButton#danger { background: #fff0f2; border-color: #e08596; color: #a61e4d; }
QPushButton#ghost { background: transparent; border: 1px solid #ccd4e3; }
QLineEdit, QTextEdit, QPlainTextEdit, QSpinBox, QComboBox { background: #ffffff; color: #1d2433;
    border: 1px solid #ccd4e3; border-radius: 8px; padding: 6px 8px; selection-background-color: #b9c6ff; }
QProgressBar { background: #ffffff; border: 1px solid #ccd4e3; border-radius: 8px; text-align: center; color: #1d2433; height: 18px; }
QProgressBar::chunk { background: qlineargradient(x1:0, y1:0, x2:1, y2:0, stop:0 #3b5bdb, stop:1 #6c8cff); border-radius: 7px; }
QTabWidget::pane { border: 1px solid #dde3ee; border-radius: 8px; background: #ffffff; }
QTabBar::tab { background: transparent; color: #6b7686; padding: 8px 16px; margin-right: 4px; border-top-left-radius: 8px; border-top-right-radius: 8px; }
QTabBar::tab:selected { background: #e3e9ff; color: #1d2433; }
QCheckBox, QRadioButton { color: #1d2433; spacing: 8px; }
QSlider::groove:horizontal { height: 6px; background: #dde3ee; border-radius: 3px; }
QSlider::handle:horizontal { background: #3b5bdb; width: 16px; margin: -6px 0; border-radius: 8px; }
QHeaderView::section { background: #eef1f7; color: #3c4657; border: none; padding: 6px; }
QTableCornerButton::section { background: #eef1f7; border: none; }
QSplitter::handle { background: #dde3ee; }
QScrollBar:vertical { background: transparent; width: 10px; }
QScrollBar::handle:vertical { background: #b9c2d4; border-radius: 5px; min-height: 30px; }
QScrollBar::add-line:vertical, QScrollBar::sub-line:vertical { height: 0; }
QStatusBar { background: #ffffff; color: #6b7686; }
QMessageBox { background: #ffffff; }
"""


def get_qss(theme: str) -> str:
    qss = DARK_QSS if theme == "dark" else LIGHT_QSS
    # NOTE: keep the file free of stray non-ascii typos in color codes
    return qss.replace("#2b3document5c8", "#2b3560").replace("#6b7避18f", "#6b718f")


def setup_font(app) -> None:
    from PySide6.QtGui import QFont

    for family in ("Vazirmatn", "IRANSans", "Segoe UI", "Tahoma"):
        font = QFont(family, 10)
        if font.exactMatch() or family in ("Segoe UI", "Tahoma"):
            app.setFont(font)
            return
