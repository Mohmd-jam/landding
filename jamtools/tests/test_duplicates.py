from app.core import duplicates


def test_find_groups(tmp_path):
    (tmp_path / "a.bin").write_bytes(b"X" * 5000)
    (tmp_path / "b.bin").write_bytes(b"X" * 5000)
    (tmp_path / "c.bin").write_bytes(b"Y" * 5000)
    (tmp_path / "tiny.txt").write_text("hi")
    groups = duplicates.find_duplicates([str(tmp_path)], recursive=False)
    assert len(groups) == 1
    g = groups[0]
    assert g.size == 5000 and len(g.files) == 2 and g.wasted == 5000


def test_same_size_different_content_not_grouped(tmp_path):
    (tmp_path / "a.bin").write_bytes(b"X" * 5000)
    (tmp_path / "b.bin").write_bytes(b"Z" * 5000)
    groups = duplicates.find_duplicates([str(tmp_path)])
    assert groups == []


def test_victims_and_move(tmp_path):
    (tmp_path / "a.bin").write_bytes(b"X" * 100)
    (tmp_path / "b.bin").write_bytes(b"X" * 100)
    groups = duplicates.find_duplicates([str(tmp_path)])
    victims = duplicates.pick_victims(groups, keep="first")
    assert len(victims) == 1
    ok, errors = duplicates.move_files(victims, str(tmp_path / "dupes"))
    assert ok == 1 and not errors
    assert len(list(tmp_path.glob("*.bin"))) == 1
