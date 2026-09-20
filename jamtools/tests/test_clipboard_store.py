from app.core.clipboard_store import ClipboardStore


def test_add_list_search_pin(tmp_path):
    db = str(tmp_path / "cb.sqlite")
    store = ClipboardStore(db, max_items=50)
    store.add_text("hello world")
    store.add_text("another note")
    store.add_text("hello world")  # dup -> refresh, not duplicate
    items = store.list()
    assert len(items) == 2
    assert store.list("hello")[0].preview.startswith("hello")
    first = items[0]
    store.set_pinned(first.id, True)
    assert store.list()[0].pinned
    assert "hello" in store.get_text(first.id) or "another" in store.get_text(first.id)
    store.delete(first.id)
    assert len(store.list()) == 1
    store.close()


def test_prune_and_export(tmp_path):
    store = ClipboardStore(str(tmp_path / "cb.sqlite"), max_items=10)
    for i in range(15):
        store.add_text(f"note {i}")
    assert len(store.list()) == 10
    out = str(tmp_path / "export.txt")
    store.export_text(out)
    with open(out, encoding="utf-8") as fh:
        assert "note 14" in fh.read()
    store.close()
