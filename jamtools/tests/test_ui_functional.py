"""Functional UI tests: actually run tools through their pages (offscreen)."""

import os
import time

import pytest

pytest.importorskip("PySide6.QtWidgets")
pytest.importorskip("PIL.Image")

os.environ.setdefault("QT_QPA_PLATFORM", "offscreen")


@pytest.fixture(autouse=True)
def _no_modal_dialogs(monkeypatch):
    """Fail fast instead of hanging on an unexpected modal dialog (offscreen)."""
    from PySide6.QtWidgets import QMessageBox

    def _boom(*args, **kwargs):
        raise AssertionError(f"unexpected dialog: {args[1] if len(args) > 1 else ''} {args[2] if len(args) > 2 else ''}")

    monkeypatch.setattr(QMessageBox, "information", staticmethod(_boom))
    monkeypatch.setattr(QMessageBox, "warning", staticmethod(_boom))
    monkeypatch.setattr(QMessageBox, "critical", staticmethod(_boom))
    monkeypatch.setattr(QMessageBox, "question", staticmethod(lambda *a, **k: QMessageBox.No))


def _pump(app, manager, timeout=30):
    end = time.time() + timeout
    while time.time() < end:
        app.processEvents()
        time.sleep(0.02)
        pending = [j for j in manager.jobs
                   if j.status.value in ("queued", "running")]
        if not pending:
            app.processEvents()
            return True
    return False


def _make_images(tmp_path, n=3):
    from PIL import Image

    files = []
    for i in range(n):
        p = tmp_path / f"img{i}.jpg"
        Image.new("RGB", (120, 80), (i * 40, 100, 150)).save(p)
        files.append(str(p))
    return files


def test_rename_convert_resize_watermark_flow(tmp_path):
    from PySide6.QtWidgets import QApplication

    from app.core.jobs import JobStatus, get_manager
    from app.i18n import set_lang
    from app.ui.main_window import MainWindow

    app = QApplication.instance() or QApplication([])
    set_lang("en")
    mgr = get_manager()
    mgr.jobs.clear()
    win = MainWindow()

    files = _make_images(tmp_path)

    # — convert —
    conv = win.pages["convert"]
    conv.drop.add_paths(files)
    conv.output.set_path(str(tmp_path / "conv_out"))
    conv.cb_fmt.setCurrentText("PNG")
    conv.on_run()
    assert _pump(app, mgr)
    assert mgr.jobs[-1].status == JobStatus.DONE, mgr.jobs[-1].summary
    assert len(os.listdir(tmp_path / "conv_out" / "converted")) == 3

    # — resize —
    rs = win.pages["resize"]
    rs.drop.add_paths(files)
    rs.output.set_path(str(tmp_path / "rs_out"))
    rs.rb_max.setChecked(True)
    rs.sp_max.setValue(64)
    rs.on_run()
    assert _pump(app, mgr)
    assert mgr.jobs[-1].status == JobStatus.DONE, mgr.jobs[-1].summary
    from PIL import Image

    first_out = os.path.join(tmp_path / "rs_out" / "resized", os.listdir(tmp_path / "rs_out" / "resized")[0])
    with Image.open(first_out) as im:
        assert max(im.size) == 64

    # — watermark —
    wm = win.pages["watermark"]
    wm.drop.add_paths(files[:1])
    wm.output.set_path(str(tmp_path / "wm_out"))
    wm.ed_text.setText("TEST")
    wm.on_run()
    assert _pump(app, mgr)
    assert mgr.jobs[-1].status == JobStatus.DONE, mgr.jobs[-1].summary
    assert len(os.listdir(tmp_path / "wm_out" / "watermarked")) == 1

    # — rename (last, renames originals) —
    rn = win.pages["rename"]
    rn.drop.add_paths(files)
    rn.ed_pattern.setText("photo_{n}")
    rn.sp_start.setValue(10)
    rn.sp_pad.setValue(2)
    app.processEvents()
    assert rn.table.rowCount() == 3
    assert "photo_10" in rn.table.item(0, 1).text()
    rn.on_run()
    assert _pump(app, mgr)
    assert mgr.jobs[-1].status == JobStatus.DONE, mgr.jobs[-1].summary
    names = sorted(os.listdir(tmp_path))
    assert "photo_10.jpg" in names and "photo_12.jpg" in names

    # batch center reflects all jobs
    batch = win.pages["batch"]
    win.goto("batch")
    batch.refresh()
    assert batch.table.rowCount() == 4
    win.close()


def test_duplicates_organizer_clipboard_flow(tmp_path):
    from PySide6.QtWidgets import QApplication

    from app.core.jobs import JobStatus, get_manager
    from app.i18n import set_lang
    from app.ui.main_window import MainWindow

    app = QApplication.instance() or QApplication([])
    set_lang("fa")
    mgr = get_manager()
    mgr.jobs.clear()
    win = MainWindow()

    # — duplicates —
    dup_dir = tmp_path / "dups"
    dup_dir.mkdir()
    (dup_dir / "a.bin").write_bytes(b"Z" * 3000)
    (dup_dir / "b.bin").write_bytes(b"Z" * 3000)
    (dup_dir / "c.bin").write_bytes(b"Q" * 3000)
    du = win.pages["duplicates"]
    du.drop.add_paths([str(dup_dir)])
    du.on_scan()
    assert _pump(app, mgr)
    assert mgr.jobs[-1].status == JobStatus.DONE
    assert len(du.groups) == 1
    assert du.tree.topLevelItemCount() == 1
    du.select_victims()
    checked = du._checked()
    assert len(checked) == 1

    # — organizer —
    org_dir = tmp_path / "mess"
    org_dir.mkdir()
    for name in ("s.mp3", "p.jpg", "d.pdf"):
        (org_dir / name).write_text("x")
    org = win.pages["organizer"]
    org.ed_src.setText(str(org_dir))
    org.show_plan()
    assert org.table.rowCount() == 3
    org.on_apply()
    assert _pump(app, mgr)
    assert mgr.jobs[-1].status == JobStatus.DONE, mgr.jobs[-1].summary
    assert (org_dir / "Audio" / "s.mp3").exists()
    # undo via log file
    org.on_undo = org.on_undo  # keep
    from app.core.organizer import undo_from_log

    restored, errors = undo_from_log(str(org_dir / ".jamtools-undo.json"))
    assert restored == 3 and not errors

    # — clipboard store —
    cb = win.pages["clipboard"]
    cb.store.add_text("سلام دنیا")
    cb.ed_search.setText("سلام")
    cb.refresh()
    assert cb.list.count() == 1
    cb.list.setCurrentRow(0)
    cb.show_selected()
    assert "سلام" in cb.preview.toPlainText()

    # — compress (zip) —
    cp = win.pages["compress"]
    cp.drop.add_paths([str(org_dir / "s.mp3")])
    cp.output.set_path(str(tmp_path / "zip_out"))
    cp.cb_mode.setCurrentIndex(0)
    cp.ed_name.setText("t.zip")
    cp.on_run()
    assert _pump(app, mgr)
    assert mgr.jobs[-1].status == JobStatus.DONE, mgr.jobs[-1].summary
    assert (tmp_path / "zip_out" / "t.zip").exists()

    # — metadata strip —
    meta_src = tmp_path / "meta_src"
    meta_src.mkdir(exist_ok=True)
    _make_images(meta_src)
    md = win.pages["metadata"]
    md.drop.add_paths([str(p) for p in meta_src.glob("*.jpg")])
    md.output.set_path(str(tmp_path / "meta_out"))
    md.cb_action.setCurrentIndex(0)
    md.on_run()
    assert _pump(app, mgr)
    assert mgr.jobs[-1].status == JobStatus.DONE, mgr.jobs[-1].summary
    assert len(os.listdir(tmp_path / "meta_out" / "clean")) == 3
    win.close()


def test_pdf_text_extract_flow(tmp_path):
    pymupdf = pytest.importorskip("pymupdf")
    from PySide6.QtWidgets import QApplication

    from app.core.jobs import JobStatus, get_manager
    from app.i18n import set_lang
    from app.ui.main_window import MainWindow

    app = QApplication.instance() or QApplication([])
    set_lang("en")
    mgr = get_manager()
    mgr.jobs.clear()
    win = MainWindow()

    pdf = str(tmp_path / "doc.pdf")
    doc = pymupdf.open()
    doc.new_page().insert_text((72, 72), "Hello JamTools")
    doc.save(pdf)
    doc.close()

    pg = win.pages["pdf"]
    pg.drop.add_paths([pdf])
    pg.output.set_path(str(tmp_path / "pdf_out"))
    pg.cb_op.setCurrentIndex(2)  # extract text
    pg.on_run()
    assert _pump(app, mgr)
    assert mgr.jobs[-1].status == JobStatus.DONE, mgr.jobs[-1].summary
    assert "Hello JamTools" in pg.result.toPlainText()

    pg.cb_op.setCurrentIndex(0)  # to images
    pg.on_run()
    assert _pump(app, mgr)
    assert mgr.jobs[-1].status == JobStatus.DONE, mgr.jobs[-1].summary
    win.close()
