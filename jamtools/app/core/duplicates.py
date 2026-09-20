"""Duplicate-file finder: group by size, then partial hash, then full SHA-256."""

from __future__ import annotations

import hashlib
import os
from dataclasses import dataclass, field

from .common import ensure_dir, unique_path

PARTIAL_BYTES = 1024 * 256  # 256 KB
CHUNK = 1024 * 1024         # 1 MB


@dataclass
class DuplicateGroup:
    hash: str
    size: int
    files: list[str] = field(default_factory=list)

    @property
    def wasted(self) -> int:
        return self.size * max(0, len(self.files) - 1)


def _hash_file(path: str, full: bool) -> str:
    h = hashlib.sha256()
    with open(path, "rb") as fh:
        if not full:
            h.update(fh.read(PARTIAL_BYTES))
        else:
            while True:
                chunk = fh.read(CHUNK)
                if not chunk:
                    break
                h.update(chunk)
    return h.hexdigest()


def find_duplicates(paths: list[str], recursive: bool = True, min_size: int = 1,
                    on_progress=None, cancel=None) -> list[DuplicateGroup]:
    # 1) collect files
    all_files: list[str] = []
    for p in paths:
        if cancel and cancel():
            return []
        if os.path.isfile(p):
            all_files.append(p)
        elif os.path.isdir(p):
            if recursive:
                for root, _d, files in os.walk(p):
                    for f in files:
                        all_files.append(os.path.join(root, f))
            else:
                for f in os.listdir(p):
                    fp = os.path.join(p, f)
                    if os.path.isfile(fp):
                        all_files.append(fp)

    # 2) group by size (cheap)
    by_size: dict[int, list[str]] = {}
    for f in all_files:
        try:
            sz = os.path.getsize(f)
        except OSError:
            continue
        if sz < min_size:
            continue
        by_size.setdefault(sz, []).append(f)
    candidates = [g for g in by_size.values() if len(g) > 1]

    # 3) group by partial hash
    groups: list[DuplicateGroup] = []
    total = sum(len(g) for g in candidates) or 1
    done = 0
    for same_size in candidates:
        by_partial: dict[str, list[str]] = {}
        for f in same_size:
            if cancel and cancel():
                return groups
            try:
                by_partial.setdefault(_hash_file(f, full=False), []).append(f)
            except OSError:
                pass
            done += 1
            if on_progress:
                on_progress(done, total * 2, f)
        # 4) confirm with full hash
        for _ph, files in by_partial.items():
            if len(files) < 2:
                continue
            by_full: dict[str, list[str]] = {}
            for f in files:
                if cancel and cancel():
                    return groups
                try:
                    by_full.setdefault(_hash_file(f, full=True), []).append(f)
                except OSError:
                    pass
                done += 1
                if on_progress:
                    on_progress(min(done, total * 2), total * 2, f)
            for h, files2 in by_full.items():
                if len(files2) > 1:
                    groups.append(DuplicateGroup(hash=h, size=os.path.getsize(files2[0]),
                                                 files=sorted(files2)))
    groups.sort(key=lambda g: g.wasted, reverse=True)
    return groups


def pick_victims(groups: list[DuplicateGroup], keep: str = "first") -> list[str]:
    """Return the files to remove, keeping one per group.

    keep: first | newest | oldest
    """
    victims: list[str] = []
    for g in groups:
        files = list(g.files)
        if keep == "newest":
            files.sort(key=lambda f: os.path.getmtime(f))
        elif keep == "oldest":
            files.sort(key=lambda f: os.path.getmtime(f), reverse=True)
        victims.extend(files[1:])
    return victims


def move_files(files: list[str], dest_dir: str, on_progress=None, cancel=None) -> tuple[int, list[str]]:
    ensure_dir(dest_dir)
    ok, errors = 0, []
    for i, f in enumerate(files):
        if cancel and cancel():
            break
        try:
            dst = unique_path(os.path.join(dest_dir, os.path.basename(f)))
            os.rename(f, dst)
            ok += 1
        except Exception as exc:
            errors.append(f"{f}: {exc}")
        if on_progress:
            on_progress(i + 1, len(files), f)
    return ok, errors


def delete_files(files: list[str], on_progress=None, cancel=None) -> tuple[int, list[str]]:
    ok, errors = 0, []
    for i, f in enumerate(files):
        if cancel and cancel():
            break
        try:
            os.remove(f)
            ok += 1
        except Exception as exc:
            errors.append(f"{f}: {exc}")
        if on_progress:
            on_progress(i + 1, len(files), f)
    return ok, errors
