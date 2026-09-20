"""Batch-rename engine: build a (src -> dst) plan, preview it, then execute."""

from __future__ import annotations

import datetime
import os
import re
from dataclasses import dataclass, field

from .common import safe_filename


@dataclass
class RenameOptions:
    mode: str = "pattern"          # pattern | find
    pattern: str = "{name}_{n}"    # tokens: {name} {n} {ext} {date} {time}
    start: int = 1
    pad: int = 3                   # zero-pad digits for {n}; 0 = no padding
    find: str = ""
    replace: str = ""
    use_regex: bool = False
    prefix: str = ""
    suffix: str = ""
    case: str = "none"             # none | lower | upper | title
    keep_extension: bool = True


@dataclass
class RenameItem:
    src: str
    dst: str
    error: str = ""
    skipped: bool = False


def _apply_case(name: str, case: str) -> str:
    if case == "lower":
        return name.lower()
    if case == "upper":
        return name.upper()
    if case == "title":
        return name.title()
    return name


def build_plan(files: list[str], opt: RenameOptions) -> list[RenameItem]:
    """Create the rename plan without touching the disk."""
    now = datetime.datetime.now()
    items: list[RenameItem] = []
    seen: set[str] = set()  # lowercase dst (same folder) -> collision detection

    for i, src in enumerate(files):
        folder = os.path.dirname(src)
        base = os.path.basename(src)
        stem, ext = os.path.splitext(base)
        number = opt.start + i
        num = str(number).zfill(opt.pad) if opt.pad > 0 else str(number)

        if opt.mode == "find" and opt.find:
            try:
                if opt.use_regex:
                    new_stem = re.sub(opt.find, opt.replace, stem)
                else:
                    new_stem = stem.replace(opt.find, opt.replace)
            except re.error as exc:
                items.append(RenameItem(src, src, error=f"Regex: {exc}", skipped=True))
                continue
            new_name = new_stem
            if opt.keep_extension:
                new_name += ext
        else:
            try:
                new_name = (
                    opt.pattern.replace("{name}", stem)
                    .replace("{n}", num)
                    .replace("{ext}", ext.lstrip("."))
                    .replace("{date}", now.strftime("%Y-%m-%d"))
                    .replace("{time}", now.strftime("%H-%M-%S"))
                )
            except Exception as exc:  # pragma: no cover - defensive
                items.append(RenameItem(src, src, error=str(exc), skipped=True))
                continue
            if opt.keep_extension and not new_name.lower().endswith(ext.lower()):
                new_name += ext

        if opt.prefix or opt.suffix:
            s, e = os.path.splitext(new_name)
            new_name = f"{opt.prefix}{s}{opt.suffix}{e}"

        s, e = os.path.splitext(new_name)
        new_name = _apply_case(s, opt.case) + e

        new_name = safe_filename(new_name, fallback=base)
        dst = os.path.join(folder, new_name)

        item = RenameItem(src, dst)
        key = dst.lower()
        if dst.lower() == src.lower() and dst != src:
            # case-only change on Windows needs a temp hop; still allowed
            pass
        if dst == src:
            item.skipped = True
            item.error = "no change"
        elif key in seen:
            item.skipped = True
            item.error = "duplicate target"
        elif os.path.exists(dst) and dst.lower() != src.lower():
            item.skipped = True
            item.error = "target exists"
        seen.add(key)
        items.append(item)
    return items


def execute_plan(items: list[RenameItem], on_progress=None, cancel=None) -> tuple[int, int, list[str]]:
    """Run the plan. Returns (ok, failed, errors). Two-phase to avoid clobbering."""
    import uuid

    ok, fail = 0, 0
    errors: list[str] = []
    # Phase 1: move every non-skipped file to a unique temp name in the same folder.
    staged: list[tuple[str, str, RenameItem]] = []
    total = sum(1 for it in items if not it.skipped)
    for it in items:
        if cancel and cancel():
            break
        if it.skipped:
            continue
        tmp = os.path.join(os.path.dirname(it.src), f".jamtools-{uuid.uuid4().hex}.tmp")
        try:
            os.rename(it.src, tmp)
            staged.append((tmp, it.dst, it))
        except Exception as exc:
            fail += 1
            errors.append(f"{os.path.basename(it.src)}: {exc}")
    # Phase 2: temp -> final destination.
    for idx, (tmp, dst, it) in enumerate(staged):
        if cancel and cancel():
            # roll back: move temp files back to their original names
            for t2, _d2, it2 in staged[idx:]:
                try:
                    os.rename(t2, it2.src)
                except OSError:
                    pass
            break
        try:
            os.rename(tmp, dst)
            ok += 1
        except Exception as exc:
            fail += 1
            errors.append(f"{os.path.basename(it.src)}: {exc}")
            try:
                os.rename(tmp, it.src)  # try to restore
            except OSError:
                pass
        if on_progress:
            on_progress(idx + 1, max(total, 1), dst)
    return ok, fail, errors
