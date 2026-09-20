"""Batch Center: live view of the global job queue + history."""

from __future__ import annotations

import datetime

from PySide6.QtWidgets import (
    QHBoxLayout, QLabel, QProgressBar, QPushButton, QSplitter, QTableWidget,
    QTableWidgetItem, QTextEdit, QVBoxLayout, QWidget,
)

from ...core.jobs import JobStatus, get_manager
from ...i18n import tr
from ..widgets import get_bridge, make_card


def _fmt_ts(ts: float) -> str:
    if not ts:
        return "-"
    return datetime.datetime.fromtimestamp(ts).strftime("%H:%M:%S")


STATUS_ICON = {
    JobStatus.QUEUED: "⏳",
    JobStatus.RUNNING: "▶",
    JobStatus.DONE: "✓",
    JobStatus.FAILED: "✕",
    JobStatus.CANCELLED: "⏹",
}


class BatchPage(QWidget):
    tool_key = "batch_center"

    def __init__(self, parent=None) -> None:
        super().__init__(parent)
        root = QVBoxLayout(self)
        root.setContentsMargins(18, 14, 18, 14)
        root.setSpacing(10)
        title = QLabel(tr("batch_center"))
        title.setObjectName("title")
        root.addWidget(title)
        desc = QLabel(tr("bt_hint"))
        desc.setObjectName("muted")
        desc.setWordWrap(True)
        root.addWidget(desc)

        bar_card, bar_lay = make_card()
        row = QHBoxLayout()
        self.btn_cancel = QPushButton(tr("bt_cancel_job"))
        self.btn_cancel.clicked.connect(self.cancel_selected)
        self.btn_clear = QPushButton(tr("bt_clear_done"))
        self.btn_clear.setObjectName("ghost")
        self.btn_clear.clicked.connect(self.clear_finished)
        row.addWidget(self.btn_cancel)
        row.addWidget(self.btn_clear)
        row.addStretch(1)
        self.lb_count = QLabel("")
        self.lb_count.setObjectName("muted")
        row.addWidget(self.lb_count)
        bar_lay.addLayout(row)
        root.addWidget(bar_card)

        from PySide6.QtCore import Qt

        split = QSplitter()
        split.setOrientation(Qt.Vertical)
        self.table = QTableWidget(0, 7)
        self.table.setHorizontalHeaderLabels(
            ["#", tr("bt_tool"), tr("bt_job"), tr("bt_status"),
             tr("bt_progress"), tr("bt_started"), tr("bt_finished")])
        self.table.horizontalHeader().setStretchLastSection(True)
        self.table.verticalHeader().setVisible(False)
        self.table.setSelectionBehavior(QTableWidget.SelectRows)
        self.table.itemSelectionChanged.connect(self.show_detail)
        split.addWidget(self.table)
        self.detail = QTextEdit()
        self.detail.setReadOnly(True)
        self.detail.setPlaceholderText(tr("bt_detail"))
        split.addWidget(self.detail)
        split.setStretchFactor(0, 3)
        split.setStretchFactor(1, 1)
        root.addWidget(split, 1)

        get_bridge().job_changed.connect(lambda _jid: self.refresh())
        self.refresh()

    def showEvent(self, event) -> None:
        super().showEvent(event)
        self.refresh()

    def refresh(self) -> None:
        jobs = list(reversed(get_manager().jobs))
        self.table.setRowCount(len(jobs))
        for i, job in enumerate(jobs):
            vals = [
                str(job.id), job.tool, job.name,
                f"{STATUS_ICON.get(job.status, '')} {job.status.value}",
                f"{job.progress}%", _fmt_ts(job.started_at), _fmt_ts(job.finished_at),
            ]
            for c, v in enumerate(vals):
                item = QTableWidgetItem(v)
                item.setData(0x0100, job.id)  # Qt.UserRole
                self.table.setItem(i, c, item)
        n_run = sum(1 for j in get_manager().jobs if j.status in (JobStatus.QUEUED, JobStatus.RUNNING))
        self.lb_count.setText(f"{len(jobs)} jobs • {n_run} active")

    def selected_id(self) -> int | None:
        rows = self.table.selectionModel().selectedRows()
        if not rows:
            return None
        item = self.table.item(rows[0].row(), 0)
        return item.data(0x0100) if item else None

    def show_detail(self) -> None:
        jid = self.selected_id()
        job = get_manager().get(jid) if jid else None
        if not job:
            self.detail.clear()
            return
        self.detail.setPlainText(
            f"#{job.id} [{job.tool}] {job.name}\n"
            f"status: {job.status.value}  progress: {job.progress}%\n"
            f"detail: {job.detail}\n\n{job.summary}")

    def cancel_selected(self) -> None:
        jid = self.selected_id()
        if jid:
            get_manager().cancel(jid)

    def clear_finished(self) -> None:
        get_manager().clear_finished()
        self.refresh()
