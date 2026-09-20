"""File-organizer page: pick folder -> preview plan -> apply (+undo)."""

from __future__ import annotations

import os

from PySide6.QtWidgets import (
    QComboBox, QFileDialog, QHBoxLayout, QLabel, QLineEdit, QMessageBox,
    QProgressBar, QPushButton, QTableWidget, QTableWidgetItem, QVBoxLayout, QWidget,
)

from ...core.jobs import JobStatus, get_manager
from ...core.organizer import apply_plan, plan_organize, undo_from_log
from ...i18n import tr
from ..widgets import get_bridge, make_card


class OrganizerPage(QWidget):
    tool_key = "tool_organizer"

    def __init__(self, parent=None) -> None:
        super().__init__(parent)
        self.moves = []
        self.job_ids: list[int] = []

        root = QVBoxLayout(self)
        root.setContentsMargins(18, 14, 18, 14)
        root.setSpacing(10)
        title = QLabel(tr("tool_organizer"))
        title.setObjectName("title")
        root.addWidget(title)
        desc = QLabel(tr("tool_organizer_desc"))
        desc.setObjectName("muted")
        root.addWidget(desc)

        src_card, src_lay = make_card("or_source")
        row = QHBoxLayout()
        self.ed_src = QLineEdit()
        self.ed_src.setPlaceholderText(os.path.join(os.path.expanduser("~"), "Downloads"))
        btn = QPushButton(tr("browse"))
        btn.clicked.connect(self._pick)
        row.addWidget(self.ed_src, 1)
        row.addWidget(btn)
        src_lay.addLayout(row)

        row2 = QHBoxLayout()
        self.cb_mode = QComboBox()
        self.cb_mode.addItems([tr("or_by_type"), tr("or_by_ext"), tr("or_by_date")])
        row2.addWidget(QLabel(tr("or_mode")))
        row2.addWidget(self.cb_mode)
        row2.addStretch(1)
        self.btn_plan = QPushButton(tr("or_plan"))
        self.btn_plan.setObjectName("ghost")
        self.btn_plan.clicked.connect(self.show_plan)
        self.btn_apply = QPushButton(tr("or_apply"))
        self.btn_apply.setObjectName("primary")
        self.btn_apply.clicked.connect(self.on_apply)
        self.btn_undo = QPushButton("↩ Undo")
        self.btn_undo.setObjectName("ghost")
        self.btn_undo.clicked.connect(self.on_undo)
        self.btn_cancel = QPushButton(tr("cancel"))
        self.btn_cancel.setEnabled(False)
        self.btn_cancel.clicked.connect(self.on_cancel)
        for b in (self.btn_plan, self.btn_apply, self.btn_undo, self.btn_cancel):
            row2.addWidget(b)
        src_lay.addLayout(row2)
        self.progress = QProgressBar()
        self.progress.setRange(0, 100)
        src_lay.addWidget(self.progress)
        hint = QLabel(tr("or_undo_hint"))
        hint.setObjectName("muted")
        src_lay.addWidget(hint)
        root.addWidget(src_card)

        tbl_card, tbl_lay = make_card("or_moves")
        self.table = QTableWidget(0, 2)
        self.table.setHorizontalHeaderLabels([tr("or_from"), tr("or_to")])
        self.table.horizontalHeader().setStretchLastSection(True)
        self.table.verticalHeader().setVisible(False)
        self.table.setMinimumHeight(240)
        tbl_lay.addWidget(self.table)
        root.addWidget(tbl_card, 1)

        get_bridge().job_changed.connect(self._on_job_changed)

    def _pick(self) -> None:
        folder = QFileDialog.getExistingDirectory(self, tr("or_source"), self.ed_src.text())
        if folder:
            self.ed_src.setText(folder)

    def _mode(self) -> str:
        return ["type", "ext", "date"][self.cb_mode.currentIndex()]

    def show_plan(self) -> None:
        folder = self.ed_src.text().strip()
        if not folder or not os.path.isdir(folder):
            QMessageBox.information(self, tr("tool_organizer"), tr("or_source"))
            return
        self.moves = plan_organize(folder, self._mode())
        folder_abs = os.path.abspath(folder)
        self.table.setRowCount(len(self.moves))
        for i, m in enumerate(self.moves):
            self.table.setItem(i, 0, QTableWidgetItem(os.path.relpath(m.src, folder_abs)))
            self.table.setItem(i, 1, QTableWidgetItem(os.path.relpath(m.dst, folder_abs)))

    def on_apply(self) -> None:
        if not self.moves:
            self.show_plan()
        if not self.moves:
            return
        moves = list(self.moves)

        def task(progress, log, cancelled):
            moved, errors, undo = apply_plan(
                moves,
                on_progress=lambda d, t, f: progress(int(d / max(t, 1) * 100), os.path.basename(str(f))),
                cancel=cancelled)
            return f"{moved} moved, {len(errors)} errors"

        self.btn_cancel.setEnabled(True)
        job = get_manager().submit(tr("tool_organizer"), os.path.basename(self.ed_src.text()), task)
        self.job_ids.append(job.id)

    def on_undo(self) -> None:
        folder = self.ed_src.text().strip()
        undo = os.path.join(folder, ".jamtools-undo.json") if folder else ""
        if not undo or not os.path.isfile(undo):
            QMessageBox.information(self, tr("tool_organizer"), "No undo log found.")
            return
        restored, errors = undo_from_log(undo)
        QMessageBox.information(self, tr("tool_organizer"), f"{restored} restored, {len(errors)} errors")
        self.show_plan()

    def on_cancel(self) -> None:
        mgr = get_manager()
        for jid in reversed(self.job_ids):
            job = mgr.get(jid)
            if job and job.status in (JobStatus.QUEUED, JobStatus.RUNNING):
                mgr.cancel(jid)
                break

    def _on_job_changed(self, job_id: int) -> None:
        if job_id not in self.job_ids:
            return
        job = get_manager().get(job_id)
        if not job:
            return
        self.progress.setValue(job.progress)
        running = any((get_manager().get(j) is not None and
                       get_manager().get(j).status in (JobStatus.QUEUED, JobStatus.RUNNING))
                      for j in self.job_ids)
        self.btn_cancel.setEnabled(running)
        if job.status in (JobStatus.DONE, JobStatus.FAILED, JobStatus.CANCELLED):
            self.moves = []
            self.table.setRowCount(0)
