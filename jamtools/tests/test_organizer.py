import os

from app.core import organizer


def _messy(folder):
    for name in ["song.mp3", "photo.jpg", "doc.pdf", "app.exe", "data.zip", "note.txt", "weird.xyz"]:
        (folder / name).write_text("x")


def test_plan_by_type(tmp_path):
    _messy(tmp_path)
    moves = organizer.plan_organize(str(tmp_path), mode="type")
    targets = {os.path.basename(m.src): m.dst for m in moves}
    assert targets["song.mp3"].endswith(os.path.join("Audio", "song.mp3"))
    assert targets["photo.jpg"].endswith(os.path.join("Images", "photo.jpg"))
    assert targets["weird.xyz"].endswith(os.path.join("Others", "weird.xyz"))


def test_apply_and_undo(tmp_path):
    _messy(tmp_path)
    moves = organizer.plan_organize(str(tmp_path), mode="type")
    moved, errors, undo = organizer.apply_plan(moves)
    assert moved == 7 and not errors and os.path.isfile(undo)
    assert (tmp_path / "Audio" / "song.mp3").exists()
    restored, errors = organizer.undo_from_log(undo)
    assert restored == 7 and not errors
    assert (tmp_path / "song.mp3").exists()


def test_plan_by_date(tmp_path):
    (tmp_path / "a.mp3").write_text("x")
    (moves,) = organizer.plan_organize(str(tmp_path), mode="date")
    assert os.path.join("Audio", "") in moves.dst
