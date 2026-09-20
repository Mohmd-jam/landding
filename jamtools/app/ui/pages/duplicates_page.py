"""Duplicate-finder page: scan -> review groups -> move/delete."""

from __future__ import annotations

import os

from PySide6.QtCore import Qt
from PySide6.QtWidgets import (
    QCheckBox, QComboBox, QFileDialog, QHBoxLayout, QLabel, QMessageBox,
    QProgressBar, QPushButton, QTreeWidget, QTreeWidgetItem, QVBoxLayout, QWidget,
)

from ...core.common import format_size
from ...core.duplicates import DuplicateGroup, delete_files, find_duplicates, move_files, pick_victims
from ...core.jobs import JobStatus, get_manager
from ...i18n import tr
from ..widgets import DropListWidget, get_bridge, make_card


class DuplicatesPage(QWidget):
    tool_key = "tool_duplicates"

    def __init__(self, parent=None) -> None:
        super().__init__(parent)
        self.groups: list[DuplicateGroup] = []
        self.job_ids: list[int] = []
        self._scan_result: list[DuplicateGroup] = []

        root = QVBoxLayout(self)
        root.setContentsMargins(18, 14, 18, 14)
        root.setSpacing(10)
        title = QLabel(tr("tool_duplicates"))
        title.setObjectName("title")
        root.addWidget(title)
        desc = QLabel(tr("tool_duplicates_desc"))
        desc.setObjectName("muted")
        root.addWidget(desc)

        in_card, in_lay = make_card("input_files")
        self.drop = DropListWidget(folders=True)
        in_lay.addWidget(self.drop)
        root.addWidget(in_card)

        opt_card, opt_lay = make_card("options")
        row = QHBoxLayout()
        self.ck_rec = QCheckBox(tr("recursive"))
        self.ck_rec.setChecked(True)
        self.cb_min = QComboBox()
        self.cb_min.addItems(["0 B", "1 KB", "100 KB", "1 MB", "10 MB"])
        self.cb_min.setCurrentIndex(1)
        self.min_bytes = [0, 1024, 102400, 1048576, 10485760]
        self.cb_keep = QComboBox()
        self.cb_keep.addItems([tr("du_keep_first"), tr("du_keep_newest"), tr("du_keep_oldest")])
        row.addWidget(self.ck_rec)
        row.addWidget(QLabel(tr("du_min_size")))
        row.addWidget(self.cb_min)
        row.addWidget(QLabel(tr("du_keep")))
        row.addWidget(self.cb_keep)
        row.addStretch(1)
        opt_lay.addLayout(row)

        row2 = QHBoxLayout()
        self.btn_scan = QPushButton(tr("du_scan"))
        self.btn_scan.setObjectName("primary")
        self.btn_scan.clicked.connect(self.on_scan)
        self.btn_cancel = QPushButton(tr("cancel"))
        self.btn_cancel.setEnabled(False)
        self.btn_cancel.clicked.connect(self.on_cancel)
        self.lb_wasted = QLabel("")
        self.lb_wasted.setObjectName("muted")
        row2.addWidget(self.btn_scan)
        row2.addWidget(self.btn_cancel)
        row2.addWidget(self.lb_wasted)
        row2.addStretch(1)
        opt_lay.addLayout(row2)
        self.progress = QProgressBar()
        self.progress.setRange(0, 100)
        opt_lay.addWidget(self.progress)
        self.lb_detail = QLabel("")
        self.lb_detail.setObjectName("muted")
        opt_lay.addWidget(self.lb_detail)
        root.addWidget(opt_card)

        res_card, res_lay = make_card("du_groups")
        self.tree = QTreeWidget()
        self.tree.setHeaderLabels(["✓", "File", "Size", "Modified"])
        self.tree.setColumnWidth(0, 40)
        self.tree.header().setStretchLastSection(False)
        self.tree.setColumnWidth(1, 420)
        self.tree.setMinimumHeight(200)
        res_lay.addWidget(self.tree)
        row3 = QHBoxLayout()
        self.btn_sel = QPushButton(tr("du_select_all"))
        self.btn_sel.setObjectName("ghost")
        self.btn_sel.clicked.connect(self.select_victims)
        self.btn_move = QPushButton(tr("du_move"))
        self.btn_move.clicked.connect(self.on_move)
        self.btn_del = QPushButton(tr("du_delete"))
        self.btn_del.setObjectName("danger")
        self.btn_del.clicked.connect(self.on_delete)
        for b in (self.btn_sel, self.btn_move, self.btn_del):
            row3.addWidget(b)
        row3.addStretch(1)
        res_lay.addLayout(row3)
        root.addWidget(res_card, 1)

        get_bridge().job_changed.connect(self._on_job_changed)

    # ── scan ──
    def on_scan(self) -> None:
        roots = self.drop.files()
        if not roots:
            QMessageBox.information(self, tr("tool_duplicates"), tr("msg_no_files"))
            return
        rec = self.ck_rec.isChecked()
        min_size = self.min_bytes[self.cb_min.currentIndex()]

        def task(progress, log, cancelled):
            groups = find_duplicates(
                roots, recursive=rec, min_size=min_size,
                on_progress=lambda d, t, f: progress(int(d / max(t, 1) * 100), os.path.basename(str(f))),
                cancel=cancelled)
            self._scan_result = groups
            return f"{len(groups)} groups"

        self.btn_cancel.setEnabled(True)
        job = get_manager().submit(tr("tool_duplicates"), tr("du_scan"), task)
        self.job_ids.append(job.id)

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
        self.lb_detail.setText(job.detail)
        running = any((get_manager().get(j) is not None and
                       get_manager().get(j).status in (JobStatus.QUEUED, JobStatus.RUNNING))
                      for j in self.job_ids)
        self.btn_cancel.setEnabled(running)
        if job.status == JobStatus.DONE and job.name == tr("du_scan"):
            self.groups = self._scan_result
            self._render_groups()
        elif job.status == JobStatus.DONE:
            self.groups = []  # after move/delete, force rescan
            self._render_groups()
            self.lb_wasted.setText("")

    # ── results ──
    def _render_groups(self) -> None:
        self.tree.clear()
        import datetime

        wasted = sum(g.wasted for g in self.groups)
        if self.groups:
            self.lb_wasted.setText(f"📦 {len(self.groups)} groups • {tr('du_wasted')}: {format_size(wasted)}")
        for gi, g in enumerate(self.groups):
            header = QTreeWidgetItem([ "", f"Group {gi + 1} — {format_size(g.size)} × {len(g.files)}",
                                       format_size(g.wasted), ""])
            header.setFirstColumnSpanned(True)
            self.tree.addTopLevelItem(header)
            for f in g.files:
                try:
                    mt = datetime.datetime.fromtimestamp(os.path.getmtime(f)).strftime("%Y-%m-%d %H:%M")
                except OSError:
                    mt = "-"
                item = QTreeWidgetItem(["", f, format_size(g.size), mt])
                item.setCheckState(0, Qt.Unchecked)
                item.setData(1, Qt.UserRole, f)
                header.addChild(item)
            header.setExpanded(True)
        self.tree.expandAll()

    def select_victims(self) -> None:
        keep = ["first", "newest", "oldest"][self.cb_keep.currentIndex()]
        victims = set(pick_victims(self.groups, keep=keep))
        for i in range(self.tree.topLevelItemCount()):
            header = self.tree.topLevelItem(i)
            for j in range(header.childCount()):
                child = header.child(j)
                path = child.data(1, Qt.UserRole)
                child.setCheckState(0, Qt.Checked if path in victims else Qt.Unchecked)

    def _checked(self) -> list[str]:
        out = []
        for i in range(self.tree.topLevelItemCount()):
            header = self.tree.topLevelItem(i)
            for j in range(header.childCount()):
                child = header.child(j)
                if child.checkState(0) == Qt.Checked:
                    out.append(child.data(1, Qt.UserRole))
        return out

    def on_move(self) -> None:
        victims = self._checked()
        if not victims:
            self.select_victims()
            victims = self._checked()
        if not victims:
            return
        dest = QFileDialog.getExistingDirectory(self, tr("du_move"), "")
        if not dest:
            return

        def task(progress, log, cancelled):
            ok, errors = move_files(
                victims, dest,
                on_progress=lambda d, t, f: progress(int(d / max(t, 1) * 100), os.path.basename(str(f))),
                cancel=cancelled)
            return f"{ok} moved, {len(errors)} errors"

        job = get_manager().submit(tr("tool_duplicates"), tr("du_move"), task)
        self.job_ids.append(job.id)

    def on_delete(self) -> None:
        victims = self._checked()
        if not victims:
            self.select_victims()
            victims = self._checked()
        if not victims:
            return
        ans = QMessageBox.question(self, tr("msg_confirm_title"),
                                   tr("du_confirm_delete", n=len(victims)))
        if ans != QMessageBox.Yes:
            return

        def task(progress, log, cancelled):
            ok, errors = delete_files(
                victims,
                on_progress=lambda d, t, f: progress(int(d / max(t, 1) * 100), os.path.basename(str(f))),
                cancel=cancelled)
            return f"{ok} deleted, {len(errors)} errors"

        job = get_manager().submit(tr("tool_duplicates"), tr("du_delete"), task)
        self.job_ids.append(job.id)
