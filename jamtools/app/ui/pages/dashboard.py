"""Dashboard: welcome + quick-access grid + recent jobs."""

from __future__ import annotations

from PySide6.QtCore import Qt
from PySide6.QtWidgets import QGridLayout, QHBoxLayout, QLabel, QPushButton, QVBoxLayout, QWidget

from ...core.jobs import JobStatus, get_manager
from ...i18n import tr
from ..widgets import get_bridge, make_card


TOOLS: list[tuple[str, str, str]] = [
    # (page_id, icon, tool_key)
    ("rename", "✏️", "tool_rename"),
    ("convert", "🖼️", "tool_convert"),
    ("resize", "📐", "tool_resize"),
    ("watermark", "©️", "tool_watermark"),
    ("compress", "🗜️", "tool_compress"),
    ("pdf", "📕", "tool_pdf"),
    ("ocr", "🔤", "tool_ocr"),
    ("metadata", "🧹", "tool_metadata"),
    ("duplicates", "👯", "tool_duplicates"),
    ("organizer", "🗂️", "tool_organizer"),
    ("clipboard", "📋", "tool_clipboard"),
    ("batch", "⚙️", "batch_center"),
]

DESC_KEYS = {
    "rename": "tool_rename_desc", "convert": "tool_convert_desc", "resize": "tool_resize_desc",
    "watermark": "tool_watermark_desc", "compress": "tool_compress_desc", "pdf": "tool_pdf_desc",
    "ocr": "tool_ocr_desc", "metadata": "tool_metadata_desc", "duplicates": "tool_duplicates_desc",
    "organizer": "tool_organizer_desc", "clipboard": "tool_clipboard_desc", "batch": "bt_hint",
}


class DashboardPage(QWidget):
    tool_key = "dashboard"

    def __init__(self, parent=None, goto=None) -> None:
        super().__init__(parent)
        self._goto = goto or (lambda _pid: None)

        root = QVBoxLayout(self)
        root.setContentsMargins(18, 14, 18, 14)
        root.setSpacing(12)

        hero = QLabel(tr("db_welcome"))
        hero.setObjectName("title")
        root.addWidget(hero)
        sub = QLabel(f"{tr('app_tagline')} • {tr('db_sub')}")
        sub.setObjectName("muted")
        root.addWidget(sub)

        quick_card, quick_lay = make_card("db_quick")
        grid = QGridLayout()
        grid.setSpacing(8)
        for i, (pid, icon, tkey) in enumerate(TOOLS):
            btn = QPushButton(f"{icon}  {tr(tkey)}\n{tr(DESC_KEYS[pid])}")
            btn.setMinimumHeight(64)
            btn.setCursor(Qt.PointingHandCursor)
            btn.clicked.connect(lambda _=False, p=pid: self._goto(p))
            grid.addWidget(btn, i // 3, i % 3)
        quick_lay.addLayout(grid)
        root.addWidget(quick_card)

        row = QHBoxLayout()
        rec_card, rec_lay = make_card("db_recent")
        self.recent = QLabel("")
        self.recent.setObjectName("muted")
        self.recent.setWordWrap(True)
        rec_lay.addWidget(self.recent)
        btn = QPushButton(tr("db_open_batch"))
        btn.setObjectName("ghost")
        btn.clicked.connect(lambda: self._goto("batch"))
        rec_lay.addWidget(btn)
        row.addWidget(rec_card, 1)
        root.addLayout(row, 1)

        get_bridge().job_changed.connect(lambda _jid: self.refresh_recent())
        self.refresh_recent()

    def showEvent(self, event) -> None:
        super().showEvent(event)
        self.refresh_recent()

    def refresh_recent(self) -> None:
        jobs = list(reversed(get_manager().jobs))[:5]
        if not jobs:
            self.recent.setText(tr("db_no_jobs"))
            return
        icons = {JobStatus.DONE: "✓", JobStatus.FAILED: "✕", JobStatus.RUNNING: "▶",
                 JobStatus.QUEUED: "⏳", JobStatus.CANCELLED: "⏹"}
        lines = [f"{icons.get(j.status, '')} [{j.tool}] {j.name} — {j.status.value}" for j in jobs]
        self.recent.setText("\n".join(lines))
