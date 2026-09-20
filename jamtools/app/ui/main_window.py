"""Main window: sidebar navigation + stacked pages + top bar."""

from __future__ import annotations

import os

from PySide6.QtCore import Qt
from PySide6.QtWidgets import (
    QComboBox, QDialog, QDialogButtonBox, QFileDialog, QFormLayout, QFrame,
    QHBoxLayout, QLabel, QLineEdit, QListWidget, QListWidgetItem, QMainWindow,
    QMessageBox, QPushButton, QSpinBox, QSplitter, QStackedWidget, QVBoxLayout, QWidget,
)

from .. import __version__
from ..core.jobs import JobStatus, get_manager
from ..core.ocr_tools import find_tesseract
from ..i18n import get_lang, is_rtl, set_lang, tr
from ..settings import get_settings, save_settings
from .pages.batch_page import BatchPage
from .pages.clipboard_page import ClipboardPage
from .pages.compress_page import CompressPage
from .pages.convert_page import ConvertPage
from .pages.dashboard import DashboardPage, TOOLS
from .pages.duplicates_page import DuplicatesPage
from .pages.metadata_page import MetadataPage
from .pages.ocr_page import OcrPage
from .pages.organizer_page import OrganizerPage
from .pages.pdf_page import PdfPage
from .pages.rename_page import RenamePage
from .pages.resize_page import ResizePage
from .pages.watermark_page import WatermarkPage
from .theme import get_qss
from .widgets import get_bridge

PAGE_CLASSES = {
    "dashboard": DashboardPage,
    "rename": RenamePage, "convert": ConvertPage, "resize": ResizePage,
    "watermark": WatermarkPage, "compress": CompressPage, "pdf": PdfPage,
    "ocr": OcrPage, "metadata": MetadataPage, "duplicates": DuplicatesPage,
    "organizer": OrganizerPage, "clipboard": ClipboardPage, "batch": BatchPage,
}

NAV = [("dashboard", "🏠", "dashboard")] + TOOLS


class SettingsDialog(QDialog):
    def __init__(self, parent=None) -> None:
        super().__init__(parent)
        s = get_settings()
        self.setWindowTitle(tr("settings"))
        self.setMinimumWidth(420)
        lay = QVBoxLayout(self)
        form = QFormLayout()

        self.cb_lang = QComboBox()
        self.cb_lang.addItems(["فارسی", "English"])
        self.cb_lang.setCurrentIndex(0 if s.lang == "fa" else 1)
        form.addRow(tr("language"), self.cb_lang)

        self.cb_theme = QComboBox()
        self.cb_theme.addItems([tr("theme_dark"), tr("theme_light")])
        self.cb_theme.setCurrentIndex(0 if s.theme == "dark" else 1)
        form.addRow(tr("theme"), self.cb_theme)

        out_row = QHBoxLayout()
        self.ed_out = QLineEdit(s.default_output)
        self.ed_out.setPlaceholderText(tr("same_as_source"))
        b1 = QPushButton(tr("browse"))
        b1.clicked.connect(self._pick_out)
        out_row.addWidget(self.ed_out, 1)
        out_row.addWidget(b1)
        form.addRow(tr("st_output_default"), out_row)

        tess_row = QHBoxLayout()
        self.ed_tess = QLineEdit(s.tesseract_path)
        self.ed_tess.setPlaceholderText(tr("st_auto_detect"))
        b2 = QPushButton(tr("browse"))
        b2.clicked.connect(self._pick_tess)
        tess_row.addWidget(self.ed_tess, 1)
        tess_row.addWidget(b2)
        form.addRow(tr("st_tesseract"), tess_row)
        self.lb_tess = QLabel("")
        self.lb_tess.setObjectName("muted")
        form.addRow("", self.lb_tess)
        self._check_tess()

        self.cb_ocr = QComboBox()
        self.cb_ocr.addItems(["fas+eng", "eng", "fas"])
        try:
            self.cb_ocr.setCurrentText(s.ocr_lang)
        except Exception:
            pass
        form.addRow(tr("ocr_lang"), self.cb_ocr)

        self.sp_cb = QSpinBox()
        self.sp_cb.setRange(10, 2000)
        self.sp_cb.setValue(s.clipboard_max_items)
        form.addRow(tr("cb_max_items"), self.sp_cb)

        lay.addLayout(form)
        about = QLabel(tr("st_about_text", v=__version__))
        about.setObjectName("muted")
        lay.addWidget(about)

        btns = QDialogButtonBox(QDialogButtonBox.Save | QDialogButtonBox.Cancel)
        btns.button(QDialogButtonBox.Save).setText(tr("save"))
        btns.button(QDialogButtonBox.Cancel).setText(tr("close"))
        btns.accepted.connect(self.accept)
        btns.rejected.connect(self.reject)
        lay.addWidget(btns)

    def _pick_out(self) -> None:
        d = QFileDialog.getExistingDirectory(self, tr("st_output_default"), self.ed_out.text())
        if d:
            self.ed_out.setText(d)

    def _pick_tess(self) -> None:
        f, _ = QFileDialog.getOpenFileName(self, tr("st_tesseract"), "", "tesseract (tesseract.exe)")
        if f:
            self.ed_tess.setText(f)
            self._check_tess()

    def _check_tess(self) -> None:
        found = find_tesseract(self.ed_tess.text().strip())
        self.lb_tess.setText(("✓ " + found) if found else ("✕ " + tr("ocr_hint")))

    def apply(self) -> tuple[bool, bool]:
        """Save to settings. Returns (lang_changed, theme_changed)."""
        s = get_settings()
        new_lang = "fa" if self.cb_lang.currentIndex() == 0 else "en"
        new_theme = "dark" if self.cb_theme.currentIndex() == 0 else "light"
        lang_changed, theme_changed = (new_lang != s.lang), (new_theme != s.theme)
        s.lang, s.theme = new_lang, new_theme
        s.default_output = self.ed_out.text().strip()
        s.tesseract_path = self.ed_tess.text().strip()
        s.ocr_lang = self.cb_ocr.currentText()
        s.clipboard_max_items = self.sp_cb.value()
        save_settings(s)
        set_lang(new_lang)
        return lang_changed, theme_changed


class MainWindow(QMainWindow):
    def __init__(self) -> None:
        super().__init__()
        self.pages: dict[str, QWidget] = {}
        self.setWindowTitle(f"JamTools — {tr('app_tagline')}")
        self.resize(1180, 780)

        central = QWidget()
        central.setObjectName("central")
        self.setCentralWidget(central)
        root = QVBoxLayout(central)
        root.setContentsMargins(0, 0, 0, 0)
        root.setSpacing(0)

        # ── top bar ──
        top = QFrame()
        top.setObjectName("topbar")
        top_lay = QHBoxLayout(top)
        top_lay.setContentsMargins(14, 8, 14, 8)
        logo = QLabel("🧰 JamTools")
        logo.setStyleSheet("font-size: 16px; font-weight: 800;")
        top_lay.addWidget(logo)
        self.search = QLineEdit()
        self.search.setPlaceholderText(tr("search_tools"))
        self.search.setMaximumWidth(260)
        self.search.textChanged.connect(self._filter_nav)
        top_lay.addWidget(self.search)
        top_lay.addStretch(1)
        self.btn_theme = QPushButton("🌙")
        self.btn_theme.setObjectName("ghost")
        self.btn_theme.setToolTip(tr("theme"))
        self.btn_theme.clicked.connect(self.toggle_theme)
        self.btn_lang = QPushButton("EN" if get_lang() == "fa" else "فا")
        self.btn_lang.setObjectName("ghost")
        self.btn_lang.clicked.connect(self.toggle_lang)
        self.btn_settings = QPushButton(f"⚙ {tr('settings')}")
        self.btn_settings.setObjectName("ghost")
        self.btn_settings.clicked.connect(self.open_settings)
        for b in (self.btn_theme, self.btn_lang, self.btn_settings):
            top_lay.addWidget(b)
        root.addWidget(top)

        # ── sidebar + stack ──
        split = QSplitter()
        side = QFrame()
        side.setObjectName("sidebar")
        side.setMinimumWidth(220)
        side.setMaximumWidth(260)
        side_lay = QVBoxLayout(side)
        side_lay.setContentsMargins(6, 10, 6, 10)
        self.nav = QListWidget()
        self.nav.setObjectName("nav")
        self.nav.currentRowChanged.connect(self._nav_changed)
        side_lay.addWidget(self.nav)
        split.addWidget(side)

        self.stack = QStackedWidget()
        split.addWidget(self.stack)
        split.setStretchFactor(1, 1)
        root.addWidget(split, 1)

        self.statusBar().showMessage(f"JamTools v{__version__}")
        get_bridge().job_changed.connect(lambda _jid: self._update_status())

        self._build_nav()
        self.apply_theme()
        self.apply_direction()
        self.nav.setCurrentRow(0)

    # ── build / rebuild ──
    def _build_nav(self) -> None:
        self.nav.clear()
        while self.stack.count():
            w = self.stack.widget(0)
            self.stack.removeWidget(w)
            w.deleteLater()
        self.pages.clear()
        for pid, icon, tkey in NAV:
            item = QListWidgetItem(f"{icon}  {tr(tkey)}")
            item.setData(0x0100, pid)
            self.nav.addItem(item)
            cls = PAGE_CLASSES[pid]
            page = cls(goto=self.goto) if pid == "dashboard" else cls()
            self.pages[pid] = page
            self.stack.addWidget(page)

    def rebuild_ui(self) -> None:
        current = self.current_pid()
        self.setWindowTitle(f"JamTools — {tr('app_tagline')}")
        self.search.setPlaceholderText(tr("search_tools"))
        self.btn_lang.setText("EN" if get_lang() == "fa" else "فا")
        self.btn_settings.setText(f"⚙ {tr('settings')}")
        self._build_nav()
        self.apply_direction()
        self.goto(current or "dashboard")

    def current_pid(self) -> str | None:
        item = self.nav.currentItem()
        return item.data(0x0100) if item else None

    # ── nav ──
    def _nav_changed(self, row: int) -> None:
        item = self.nav.item(row)
        if item:
            self.stack.setCurrentWidget(self.pages[item.data(0x0100)])

    def _filter_nav(self, text: str) -> None:
        text = text.strip()
        for i in range(self.nav.count()):
            item = self.nav.item(i)
            item.setHidden(bool(text) and text not in item.text())

    def goto(self, pid: str) -> None:
        for i in range(self.nav.count()):
            if self.nav.item(i).data(0x0100) == pid:
                self.nav.setCurrentRow(i)
                return

    # ── theme / lang / settings ──
    def apply_theme(self) -> None:
        from PySide6.QtWidgets import QApplication

        s = get_settings()
        QApplication.instance().setStyleSheet(get_qss(s.theme))
        self.btn_theme.setText("🌙" if s.theme == "dark" else "☀️")

    def apply_direction(self) -> None:
        from PySide6.QtWidgets import QApplication

        app = QApplication.instance()
        assert app is not None
        app.setLayoutDirection(Qt.RightToLeft if is_rtl() else Qt.LeftToRight)

    def toggle_theme(self) -> None:
        s = get_settings()
        s.theme = "light" if s.theme == "dark" else "dark"
        save_settings(s)
        self.apply_theme()

    def toggle_lang(self) -> None:
        s = get_settings()
        s.lang = "en" if s.lang == "fa" else "fa"
        save_settings(s)
        set_lang(s.lang)
        self.rebuild_ui()

    def open_settings(self) -> None:
        dlg = SettingsDialog(self)
        if dlg.exec():
            lang_changed, theme_changed = dlg.apply()
            if theme_changed:
                self.apply_theme()
            if lang_changed:
                self.rebuild_ui()
            else:
                # default-output may have changed -> refresh pages' output bars
                self.rebuild_ui()

    def _update_status(self) -> None:
        jobs = get_manager().jobs
        n_run = sum(1 for j in jobs if j.status in (JobStatus.QUEUED, JobStatus.RUNNING))
        if n_run:
            self.statusBar().showMessage(f"⚙ {n_run} active • {len(jobs)} total")
        else:
            self.statusBar().showMessage(f"JamTools v{__version__} • {len(jobs)} jobs")

    def closeEvent(self, event) -> None:
        running = [j for j in get_manager().jobs if j.status in (JobStatus.QUEUED, JobStatus.RUNNING)]
        if running:
            ans = QMessageBox.question(self, "JamTools", f"{len(running)} jobs running. Quit anyway?")
            if ans != QMessageBox.Yes:
                event.ignore()
                return
            for j in running:
                get_manager().cancel(j.id)
        super().closeEvent(event)
