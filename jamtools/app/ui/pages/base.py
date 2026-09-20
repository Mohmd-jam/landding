"""Base class for all tool pages: file list + options + output + run + log.

Each page submits its work to the global JobManager (the Batch Center),
so jobs queue up sequentially and stay visible in one place.
"""

from __future__ import annotations

import os
import traceback

from PySide6.QtCore import Qt, Signal
from PySide6.QtWidgets import (
    QFormLayout, QHBoxLayout, QLabel, QMessageBox, QProgressBar, QPushButton,
    QScrollArea, QTextEdit, QVBoxLayout, QWidget,
)

from ...core.common import MissingDependencyError
from ...core.jobs import JobStatus, get_manager
from ...i18n import tr
from ...settings import get_settings
from ..widgets import DropListWidget, OutputBar, get_bridge, make_card, open_in_explorer


class ToolPage(QWidget):
    log_signal = Signal(str)
    call_signal = Signal(object)  # marshal a zero-arg callable onto the GUI thread

    # ── subclasses override ──
    tool_key = ""        # e.g. "tool_rename" (for nav/job labels)
    desc_key = ""
    file_filter = ""
    allow_folders = False

    def __init__(self, parent=None) -> None:
        super().__init__(parent)
        self.job_ids: list[int] = []       # jobs submitted from this page
        self._output_used = ""
        self._extra_cards: list = []   # pages append cards here during build_options()
        self.log_signal.connect(self._append_log)
        self.call_signal.connect(self._run_callable)

        root = QVBoxLayout(self)
        root.setContentsMargins(18, 14, 18, 14)
        root.setSpacing(10)

        header = QHBoxLayout()
        self.title = QLabel(tr(self.tool_key))
        self.title.setObjectName("title")
        header.addWidget(self.title)
        header.addStretch(1)
        self.status = QLabel(tr("ready"))
        self.status.setObjectName("muted")
        header.addWidget(self.status)
        root.addLayout(header)

        if self.desc_key:
            desc = QLabel(tr(self.desc_key))
            desc.setObjectName("muted")
            desc.setWordWrap(True)
            root.addWidget(desc)

        scroll = QScrollArea()
        scroll.setWidgetResizable(True)
        scroll.setFrameShape(QScrollArea.NoFrame)
        body = QWidget()
        self.body_layout = QVBoxLayout(body)
        self.body_layout.setSpacing(10)
        self.body_layout.setContentsMargins(2, 2, 2, 2)
        scroll.setWidget(body)
        root.addWidget(scroll, 1)

        # input card
        in_card, in_lay = make_card("input_files")
        self.drop = DropListWidget(folders=self.allow_folders, file_filter=self.file_filter)
        self.drop.setMinimumWidth(200)
        in_lay.addWidget(self.drop)
        self.body_layout.addWidget(in_card)

        # options card
        opt_card, opt_lay = make_card("options")
        self.form = QFormLayout()
        self.form.setSpacing(8)
        opt_lay.addLayout(self.form)
        self.build_options()
        self.body_layout.addWidget(opt_card)

        # output card
        out_card, out_lay = make_card()
        self.output = OutputBar()
        out_lay.addWidget(self.output)
        s = get_settings()
        if s.default_output:
            self.output.set_path(s.default_output)
        self.body_layout.addWidget(out_card)

        # run card
        run_card, run_lay = make_card()
        row = QHBoxLayout()
        self.btn_run = QPushButton(tr("run"))
        self.btn_run.setObjectName("primary")
        self.btn_run.clicked.connect(self.on_run)
        self.btn_cancel = QPushButton(tr("cancel"))
        self.btn_cancel.clicked.connect(self.on_cancel)
        self.btn_cancel.setEnabled(False)
        self.btn_open_out = QPushButton(tr("open_output"))
        self.btn_open_out.setObjectName("ghost")
        self.btn_open_out.clicked.connect(lambda: open_in_explorer(self._output_used or self.output.path()))
        row.addWidget(self.btn_run)
        row.addWidget(self.btn_cancel)
        row.addWidget(self.btn_open_out)
        row.addStretch(1)
        self.detail = QLabel("")
        self.detail.setObjectName("muted")
        self.detail.setWordWrap(True)
        row.addWidget(self.detail, 1)
        run_lay.addLayout(row)
        self.progress = QProgressBar()
        self.progress.setRange(0, 100)
        self.progress.setValue(0)
        run_lay.addWidget(self.progress)
        self.log_view = QTextEdit()
        self.log_view.setReadOnly(True)
        self.log_view.setMaximumHeight(130)
        self.log_view.setPlaceholderText(tr("log"))
        run_lay.addWidget(self.log_view)
        self.body_layout.addWidget(run_card)

        # extra cards collected during build_options() go right above the run card
        for card in self._extra_cards:
            self.body_layout.insertWidget(self.body_layout.count() - 1, card)

        get_bridge().job_changed.connect(self._on_job_changed)

    # ── subclass hooks ──
    def build_options(self) -> None:  # noqa: B027 - must override
        pass

    def make_task(self, files: list[str], output: str):
        """Return ``func(progress, log, cancelled) -> summary``. Must override."""
        raise NotImplementedError

    def validate(self, files: list[str], output: str) -> str:
        """Return an error message (translated) or '' if valid."""
        if not files:
            return tr("msg_no_files")
        return ""

    # ── run flow ──
    def on_run(self) -> None:
        files = self.drop.files()
        output = self.output.path()
        err = self.validate(files, output)
        if err:
            QMessageBox.information(self, tr(self.tool_key), err)
            return
        try:
            task = self.make_task(files, output)
        except MissingDependencyError as exc:
            QMessageBox.warning(self, tr(self.tool_key), tr("msg_missing_dep", name=exc.pip_name, hint=exc.hint))
            return
        except Exception as exc:
            QMessageBox.warning(self, tr(self.tool_key), str(exc))
            return
        job = get_manager().submit(tr(self.tool_key), self._job_name(files), task)
        self.job_ids.append(job.id)
        self.btn_cancel.setEnabled(True)
        self.log(tr("running"))
        self._refresh_job_ui()

    def _job_name(self, files: list[str]) -> str:
        if len(files) == 1:
            return os.path.basename(files[0])
        return tr("files_count", n=len(files))

    def on_cancel(self) -> None:
        job = self._active_job()
        if job:
            get_manager().cancel(job.id)
            self.log(tr("cancelled"))

    # ── job tracking ──
    def _active_job(self):
        mgr = get_manager()
        for jid in reversed(self.job_ids):
            job = mgr.get(jid)
            if job and job.status in (JobStatus.QUEUED, JobStatus.RUNNING):
                return job
        return None

    def _latest_job(self):
        mgr = get_manager()
        for jid in reversed(self.job_ids):
            job = mgr.get(jid)
            if job:
                return job
        return None

    def _on_job_changed(self, job_id: int) -> None:
        if job_id not in self.job_ids:
            return
        self._refresh_job_ui()
        job = get_manager().get(job_id)
        if job and job.status in (JobStatus.DONE, JobStatus.FAILED, JobStatus.CANCELLED):
            self.on_job_finished(job)

    def on_job_finished(self, job) -> None:  # hook for subclasses
        if job.status == JobStatus.DONE:
            self.log(f"{tr('done')} — {job.summary}")
        elif job.status == JobStatus.FAILED:
            self.log(f"{tr('failed')}: {job.summary.splitlines()[0] if job.summary else ''}")

    def _refresh_job_ui(self) -> None:
        active = self._active_job()
        latest = self._latest_job()
        show = active or latest
        if show is None:
            self.progress.setValue(0)
            self.status.setText(tr("ready"))
            self.detail.setText("")
            self.btn_cancel.setEnabled(False)
            return
        self.progress.setValue(show.progress)
        labels = {
            JobStatus.QUEUED: tr("bt_queue"),
            JobStatus.RUNNING: tr("running"),
            JobStatus.DONE: tr("done"),
            JobStatus.FAILED: tr("failed"),
            JobStatus.CANCELLED: tr("cancelled"),
        }
        self.status.setText(labels.get(show.status, show.status.value))
        self.detail.setText(show.detail)
        self.btn_cancel.setEnabled(active is not None)

    # ── helpers for tasks (thread-safe) ──
    def log(self, msg: str) -> None:
        self.log_signal.emit(msg)

    def _append_log(self, msg: str) -> None:
        self.log_view.append(msg)

    def ui_call(self, func) -> None:
        """Run *func* on the GUI thread (safe to call from job threads)."""
        self.call_signal.emit(func)

    def _run_callable(self, func) -> None:
        try:
            func()
        except Exception:
            self._append_log(traceback.format_exc(limit=2))

    def resolve_output(self, output: str, first_file: str, subfolder: str = "") -> str:
        """Decide the real output dir: explicit choice, else <source>/<subfolder|.>."""
        if output:
            base = output
        else:
            base = os.path.dirname(first_file) if os.path.isfile(first_file) else first_file
        full = os.path.join(base, subfolder) if subfolder else base
        os.makedirs(full, exist_ok=True)
        self._output_used = full
        return full

    def guard(self, func):
        """Wrap a task so MissingDependency shows a message box instead of failing silently."""
        def inner(progress, log, cancelled):
            try:
                return func(progress, log, cancelled)
            except MissingDependencyError as exc:
                raise RuntimeError(tr("msg_missing_dep", name=exc.pip_name, hint=exc.hint))
        return inner

    @staticmethod
    def fmt_result(ok: int, fail: int) -> str:
        return tr("msg_done_files", ok=ok, fail=fail)
