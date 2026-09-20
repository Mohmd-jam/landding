"""SQLite-backed clipboard history (text + optional image blobs)."""

from __future__ import annotations

import hashlib
import os
import sqlite3
import time
from dataclasses import dataclass


@dataclass
class ClipItem:
    id: int
    kind: str          # text | image
    preview: str       # text content or "" for images
    pinned: bool
    created_at: float
    size: int = 0


class ClipboardStore:
    def __init__(self, db_path: str, max_items: int = 300) -> None:
        self.db_path = db_path
        self.max_items = max_items
        os.makedirs(os.path.dirname(db_path) or ".", exist_ok=True)
        self._conn = sqlite3.connect(db_path)
        self._conn.execute(
            """CREATE TABLE IF NOT EXISTS clips(
                   id INTEGER PRIMARY KEY AUTOINCREMENT,
                   kind TEXT NOT NULL DEFAULT 'text',
                   content TEXT DEFAULT '',
                   image BLOB,
                   digest TEXT DEFAULT '',
                   pinned INTEGER DEFAULT 0,
                   created_at REAL DEFAULT 0)"""
        )
        self._conn.execute("CREATE INDEX IF NOT EXISTS ix_clips_time ON clips(created_at)")
        self._conn.commit()

    def close(self) -> None:
        try:
            self._conn.close()
        except Exception:
            pass

    @staticmethod
    def _digest(data: bytes) -> str:
        return hashlib.sha256(data).hexdigest()

    def add_text(self, text: str) -> int | None:
        text = text or ""
        if not text.strip():
            return None
        digest = self._digest(text.encode("utf-8", "replace"))
        row = self._conn.execute("SELECT id FROM clips WHERE digest=? AND kind='text'", (digest,)).fetchone()
        if row:  # refresh timestamp instead of duplicating
            self._conn.execute("UPDATE clips SET created_at=? WHERE id=?", (time.time(), row[0]))
            self._conn.commit()
            return row[0]
        cur = self._conn.execute(
            "INSERT INTO clips(kind, content, digest, created_at) VALUES('text', ?, ?, ?)",
            (text[:100000], digest, time.time()),
        )
        self._conn.commit()
        self._prune()
        return cur.lastrowid

    def add_image(self, png_bytes: bytes) -> int | None:
        if not png_bytes:
            return None
        digest = self._digest(png_bytes)
        row = self._conn.execute("SELECT id FROM clips WHERE digest=? AND kind='image'", (digest,)).fetchone()
        if row:
            self._conn.execute("UPDATE clips SET created_at=? WHERE id=?", (time.time(), row[0]))
            self._conn.commit()
            return row[0]
        cur = self._conn.execute(
            "INSERT INTO clips(kind, image, digest, created_at) VALUES('image', ?, ?, ?)",
            (png_bytes, digest, time.time()),
        )
        self._conn.commit()
        self._prune()
        return cur.lastrowid

    def _prune(self) -> None:
        self._conn.execute(
            """DELETE FROM clips WHERE pinned=0 AND id NOT IN (
                   SELECT id FROM clips WHERE pinned=0 ORDER BY created_at DESC LIMIT ?)""",
            (max(10, self.max_items),),
        )
        self._conn.commit()

    def list(self, query: str = "", limit: int = 500) -> list[ClipItem]:
        if query:
            rows = self._conn.execute(
                """SELECT id, kind, substr(content,1,160), pinned, created_at, length(content)
                   FROM clips WHERE content LIKE ? ORDER BY pinned DESC, created_at DESC LIMIT ?""",
                (f"%{query}%", limit),
            ).fetchall()
        else:
            rows = self._conn.execute(
                """SELECT id, kind, substr(content,1,160), pinned, created_at, length(content)
                   FROM clips ORDER BY pinned DESC, created_at DESC LIMIT ?""",
                (limit,),
            ).fetchall()
        return [ClipItem(id=r[0], kind=r[1], preview=(r[2] or "").replace("\n", " ⏎ "),
                         pinned=bool(r[3]), created_at=r[4], size=r[5] or 0) for r in rows]

    def get_text(self, clip_id: int) -> str:
        row = self._conn.execute("SELECT content FROM clips WHERE id=?", (clip_id,)).fetchone()
        return row[0] if row else ""

    def get_image(self, clip_id: int) -> bytes:
        row = self._conn.execute("SELECT image FROM clips WHERE id=?", (clip_id,)).fetchone()
        return row[0] if row and row[0] else b""

    def set_pinned(self, clip_id: int, pinned: bool) -> None:
        self._conn.execute("UPDATE clips SET pinned=? WHERE id=?", (1 if pinned else 0, clip_id))
        self._conn.commit()

    def delete(self, clip_id: int) -> None:
        self._conn.execute("DELETE FROM clips WHERE id=?", (clip_id,))
        self._conn.commit()

    def clear(self, keep_pinned: bool = True) -> None:
        if keep_pinned:
            self._conn.execute("DELETE FROM clips WHERE pinned=0")
        else:
            self._conn.execute("DELETE FROM clips")
        self._conn.commit()

    def export_text(self, path: str) -> str:
        rows = self._conn.execute(
            "SELECT content, created_at FROM clips WHERE kind='text' ORDER BY created_at").fetchall()
        import datetime

        with open(path, "w", encoding="utf-8") as fh:
            for content, ts in rows:
                when = datetime.datetime.fromtimestamp(ts).strftime("%Y-%m-%d %H:%M")
                fh.write(f"----- {when} -----\n{content}\n\n")
        return path
