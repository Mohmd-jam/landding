import os
import zipfile

from app.core import archives


def test_zip_roundtrip(tmp_path):
    (tmp_path / "a.txt").write_text("hello")
    sub = tmp_path / "sub"
    sub.mkdir()
    (sub / "b.txt").write_text("world")
    zpath, count, total = archives.create_zip(
        [str(tmp_path / "a.txt"), str(sub)], str(tmp_path / "out.zip"))
    assert count == 2 and total > 0
    assert zipfile.is_zipfile(zpath)
    outdir, n = archives.extract_archive(zpath, str(tmp_path / "ex"))
    assert n == 2
    assert (tmp_path / "ex" / "a.txt").read_text() == "hello"


def test_extract_no_clobber(tmp_path):
    (tmp_path / "a.txt").write_text("orig")
    zpath, _, _ = archives.create_zip([str(tmp_path / "a.txt")], str(tmp_path / "o.zip"))
    ex = tmp_path / "ex"
    ex.mkdir()
    (ex / "a.txt").write_text("existing")
    _, n = archives.extract_archive(zpath, str(ex))
    assert n == 1
    assert (ex / "a.txt").read_text() == "existing"  # untouched
    assert (ex / "a (2).txt").read_text() == "orig"
