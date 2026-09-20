"""Batch-processing center: a Qt-free job queue with progress + cancel.

The GUI wraps :class:`JobManager` in a QThread; the engine itself only uses
plain callbacks so it stays unit-testable.
"""

from __future__ import annotations

import threading
import time
import traceback
from dataclasses import dataclass, field
from enum import Enum

from .common import CancelledError


class JobStatus(str, Enum):
    QUEUED = "queued"
    RUNNING = "running"
    DONE = "done"
    FAILED = "failed"
    CANCELLED = "cancelled"


@dataclass
class Job:
    id: int
    tool: str
    name: str
    func: object = field(repr=False)          # (progress_cb, log_cb, cancel_cb) -> str summary
    status: JobStatus = JobStatus.QUEUED
    progress: int = 0                          # 0..100
    detail: str = ""
    summary: str = ""
    created_at: float = field(default_factory=time.time)
    started_at: float = 0.0
    finished_at: float = 0.0
    cancel_event: threading.Event = field(default_factory=threading.Event, repr=False)


class JobManager:
    """Sequential FIFO queue. ``on_change(job)`` is called on every state update."""

    def __init__(self) -> None:
        self.jobs: list[Job] = []
        self._next_id = 1
        self._lock = threading.Lock()
        self._worker: threading.Thread | None = None
        self.on_change = None  # callback(job)

    # ── API ────────────────────────────────────────────
    def submit(self, tool: str, name: str, func) -> Job:
        with self._lock:
            job = Job(id=self._next_id, tool=tool, name=name, func=func)
            self._next_id += 1
            self.jobs.append(job)
        self._emit(job)
        self._ensure_worker()
        return job

    def cancel(self, job_id: int) -> None:
        job = self.get(job_id)
        if job and job.status in (JobStatus.QUEUED, JobStatus.RUNNING):
            job.cancel_event.set()
            if job.status == JobStatus.QUEUED:
                job.status = JobStatus.CANCELLED
                job.finished_at = time.time()
                self._emit(job)

    def clear_finished(self) -> None:
        with self._lock:
            self.jobs = [j for j in self.jobs
                         if j.status in (JobStatus.QUEUED, JobStatus.RUNNING)]

    def get(self, job_id: int) -> Job | None:
        with self._lock:
            for j in self.jobs:
                if j.id == job_id:
                    return j
        return None

    def wait_all(self, timeout: float | None = None) -> bool:
        """Block until the queue drains (used by tests / CLI)."""
        end = None if timeout is None else time.time() + timeout
        while True:
            with self._lock:
                pending = [j for j in self.jobs
                           if j.status in (JobStatus.QUEUED, JobStatus.RUNNING)]
            if not pending:
                return True
            if end is not None and time.time() > end:
                return False
            time.sleep(0.05)

    # ── internals ──────────────────────────────────────
    def _emit(self, job: Job) -> None:
        if self.on_change:
            try:
                self.on_change(job)
            except Exception:
                pass

    def _ensure_worker(self) -> None:
        with self._lock:
            if self._worker and self._worker.is_alive():
                return
            self._worker = threading.Thread(target=self._loop, daemon=True)
            self._worker.start()

    def _loop(self) -> None:
        while True:
            with self._lock:
                nxt = next((j for j in self.jobs if j.status == JobStatus.QUEUED), None)
                if nxt is None:
                    self._worker = None
                    return
                nxt.status = JobStatus.RUNNING
                nxt.started_at = time.time()
            self._emit(nxt)
            self._run(nxt)

    def _run(self, job: Job) -> None:
        def progress(pct: int, detail: str = "") -> None:
            job.progress = max(0, min(100, int(pct)))
            job.detail = detail
            self._emit(job)

        def log(_msg: str) -> None:  # GUI wires its own log; kept for signature parity
            pass

        try:
            summary = job.func(progress, log, job.cancel_event.is_set)
            if job.cancel_event.is_set():
                job.status = JobStatus.CANCELLED
            else:
                job.status = JobStatus.DONE
                job.progress = 100
            job.summary = str(summary or "")
        except CancelledError:
            job.status = JobStatus.CANCELLED
        except Exception as exc:  # never let one job kill the queue
            job.status = JobStatus.FAILED
            job.summary = f"{exc}\n{traceback.format_exc(limit=3)}"
        finally:
            job.finished_at = time.time()
            self._emit(job)


_MANAGER: JobManager | None = None


def get_manager() -> JobManager:
    global _MANAGER
    if _MANAGER is None:
        _MANAGER = JobManager()
    return _MANAGER
