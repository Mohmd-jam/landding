import os

from app.core.rename import RenameOptions, build_plan, execute_plan


def test_pattern_plan(tmp_path):
    files = []
    for i in range(3):
        p = tmp_path / f"img{i}.jpg"
        p.write_bytes(b"x")
        files.append(str(p))
    opt = RenameOptions(mode="pattern", pattern="photo_{n}", start=5, pad=3)
    plan = build_plan(files, opt)
    assert [os.path.basename(i.dst) for i in plan] == [
        "photo_005.jpg", "photo_006.jpg", "photo_007.jpg"]
    assert all(not i.skipped for i in plan)


def test_pattern_tokens_and_case(tmp_path):
    p = tmp_path / "Hello World.TXT"
    p.write_bytes(b"x")
    opt = RenameOptions(mode="pattern", pattern="{name}_v{n}", start=1, pad=0, case="lower")
    (item,) = build_plan([str(p)], opt)
    assert os.path.basename(item.dst) == "hello world_v1.TXT"  # stem cased, ext untouched


def test_find_replace(tmp_path):
    p = tmp_path / "IMG_2024_01.jpg"
    p.write_bytes(b"x")
    opt = RenameOptions(mode="find", find="IMG_", replace="pic-")
    (item,) = build_plan([str(p)], opt)
    assert os.path.basename(item.dst) == "pic-2024_01.jpg"


def test_find_regex(tmp_path):
    p = tmp_path / "file123.txt"
    p.write_bytes(b"x")
    opt = RenameOptions(mode="find", find=r"\d+", replace="N", use_regex=True)
    (item,) = build_plan([str(p)], opt)
    assert os.path.basename(item.dst) == "fileN.txt"


def test_execute_and_collision(tmp_path):
    a = tmp_path / "a.txt"
    b = tmp_path / "b.txt"
    a.write_text("A")
    b.write_text("B")
    # both want the same name -> second skipped
    opt = RenameOptions(mode="pattern", pattern="same", start=1, pad=0)
    plan = build_plan([str(a), str(b)], opt)
    assert plan[1].skipped
    ok, fail, errors = execute_plan(plan)
    assert ok == 1 and fail == 0
    assert (tmp_path / "same.txt").read_text() == "A"
    assert (tmp_path / "b.txt").exists()


def test_prefix_suffix(tmp_path):
    p = tmp_path / "doc.pdf"
    p.write_bytes(b"x")
    opt = RenameOptions(mode="pattern", pattern="{name}", prefix="PRE_", suffix="_SUF")
    (item,) = build_plan([str(p)], opt)
    assert os.path.basename(item.dst) == "PRE_doc_SUF.pdf"
