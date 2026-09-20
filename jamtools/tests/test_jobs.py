import time

from app.core.jobs import JobManager, JobStatus


def _task(steps=5, fail=False):
    def fn(progress, log, cancelled):
        for i in range(steps):
            if cancelled():
                from app.core.common import CancelledError

                raise CancelledError()
            progress(int((i + 1) / steps * 100), f"step {i + 1}")
            time.sleep(0.01)
        if fail:
            raise RuntimeError("boom")
        return "all good"

    return fn


def test_sequential_success():
    m = JobManager()
    seen = []
    m.on_change = seen.append
    m.submit("demo", "job-1", _task(3))
    m.submit("demo", "job-2", _task(2))
    assert m.wait_all(timeout=10)
    assert [j.status for j in m.jobs] == [JobStatus.DONE, JobStatus.DONE]
    assert m.jobs[0].summary == "all good"
    assert m.jobs[0].finished_at <= m.jobs[1].started_at  # sequential
    assert seen  # change events fired


def test_failure_does_not_kill_queue():
    m = JobManager()
    m.submit("demo", "bad", _task(2, fail=True))
    m.submit("demo", "good", _task(2))
    assert m.wait_all(timeout=10)
    assert m.jobs[0].status == JobStatus.FAILED
    assert "boom" in m.jobs[0].summary
    assert m.jobs[1].status == JobStatus.DONE


def test_cancel():
    m = JobManager()
    import threading

    started = threading.Event()

    def fn(progress, log, cancelled):
        started.set()
        for i in range(100):
            if cancelled():
                from app.core.common import CancelledError

                raise CancelledError()
            progress(i, "")
            time.sleep(0.01)
        return "x"

    job = m.submit("demo", "long", fn)
    assert started.wait(5)
    m.cancel(job.id)
    assert m.wait_all(timeout=10)
    assert job.status == JobStatus.CANCELLED
