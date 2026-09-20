"""Batch-rename page with live preview table."""

from __future__ import annotations

import os

from PySide6.QtWidgets import (
    QCheckBox, QComboBox, QHBoxLayout, QLineEdit, QPushButton, QSpinBox,
    QTableWidget, QTableWidgetItem, QTabWidget, QVBoxLayout, QWidget,
)

from ...core.rename import RenameOptions, build_plan, execute_plan
from ...i18n import tr
from ..widgets import make_card
from .base import ToolPage


class RenamePage(ToolPage):
    tool_key = "tool_rename"
    desc_key = "tool_rename_desc"
    allow_folders = True

    def build_options(self) -> None:
        # extra: preview card is added after options; build tabbed options here
        self.tabs = QTabWidget()

        # — pattern tab —
        pat = QWidget()
        pl = QVBoxLayout(pat)
        self.ed_pattern = QLineEdit("{name}_{n}")
        self.ed_pattern.setPlaceholderText("{name}_{n}")
        self.ed_pattern.textChanged.connect(lambda *_: self.refresh_preview())
        pl.addWidget(self.ed_pattern)
        hint = QLineEdit(tr("rn_pattern_hint"))
        hint.setReadOnly(True)
        hint.setEnabled(False)
        pl.addWidget(hint)
        row = QHBoxLayout()
        self.sp_start = QSpinBox()
        self.sp_start.setRange(0, 999999)
        self.sp_start.setValue(1)
        self.sp_pad = QSpinBox()
        self.sp_pad.setRange(0, 6)
        self.sp_pad.setValue(3)
        for w in (self.sp_start, self.sp_pad):
            w.valueChanged.connect(lambda *_: self.refresh_preview())
        row.addWidget(self._lab("rn_start"))
        row.addWidget(self.sp_start)
        row.addWidget(self._lab("rn_pad"))
        row.addWidget(self.sp_pad)
        row.addStretch(1)
        pl.addLayout(row)
        self.tabs.addTab(pat, tr("rn_mode_pattern"))

        # — find/replace tab —
        frep = QWidget()
        fl = QVBoxLayout(frep)
        self.ed_find = QLineEdit()
        self.ed_find.setPlaceholderText(tr("rn_find"))
        self.ed_replace = QLineEdit()
        self.ed_replace.setPlaceholderText(tr("rn_replace"))
        for w in (self.ed_find, self.ed_replace):
            w.textChanged.connect(lambda *_: self.refresh_preview())
        fl.addWidget(self.ed_find)
        fl.addWidget(self.ed_replace)
        self.ck_regex = QCheckBox("Regex")
        self.ck_regex.toggled.connect(lambda *_: self.refresh_preview())
        fl.addWidget(self.ck_regex)
        self.tabs.addTab(frep, tr("rn_mode_find"))
        self.tabs.currentChanged.connect(lambda *_: self.refresh_preview())
        self.form.addRow(self.tabs)

        # prefix / suffix / case
        row2 = QHBoxLayout()
        self.ed_prefix = QLineEdit()
        self.ed_prefix.setPlaceholderText(tr("rn_prefix"))
        self.ed_suffix = QLineEdit()
        self.ed_suffix.setPlaceholderText(tr("rn_suffix"))
        self.cb_case = QComboBox()
        self.cb_case.addItems([tr("rn_case_none"), tr("rn_case_lower"), tr("rn_case_upper"), tr("rn_case_title")])
        for w in (self.ed_prefix, self.ed_suffix):
            w.textChanged.connect(lambda *_: self.refresh_preview())
        self.cb_case.currentIndexChanged.connect(lambda *_: self.refresh_preview())
        row2.addWidget(self.ed_prefix)
        row2.addWidget(self.ed_suffix)
        row2.addWidget(self._lab("rn_case"))
        row2.addWidget(self.cb_case)
        self.form.addRow(row2)

        # preview card (insert before run card)
        prev_card, prev_lay = make_card("preview")
        self.table = QTableWidget(0, 2)
        self.table.setHorizontalHeaderLabels([tr("rn_old"), tr("rn_new")])
        self.table.horizontalHeader().setStretchLastSection(True)
        self.table.verticalHeader().setVisible(False)
        self.table.setMinimumHeight(150)
        prev_lay.addWidget(self.table)
        btn_row = QHBoxLayout()
        btn = QPushButton(tr("refresh_preview"))
        btn.setObjectName("ghost")
        btn.clicked.connect(self.refresh_preview)
        btn_row.addWidget(btn)
        btn_row.addStretch(1)
        prev_lay.addLayout(btn_row)
        # shown right above the run card (see ToolPage.__init__)
        self._extra_cards.append(prev_card)

        self.drop.changed.connect(self.refresh_preview)
        self.refresh_preview()

    def _lab(self, key):
        from PySide6.QtWidgets import QLabel

        return QLabel(tr(key))

    def collect(self) -> RenameOptions:
        cases = ["none", "lower", "upper", "title"]
        return RenameOptions(
            mode="pattern" if self.tabs.currentIndex() == 0 else "find",
            pattern=self.ed_pattern.text().strip() or "{name}_{n}",
            start=self.sp_start.value(),
            pad=self.sp_pad.value(),
            find=self.ed_find.text(),
            replace=self.ed_replace.text(),
            use_regex=self.ck_regex.isChecked(),
            prefix=self.ed_prefix.text(),
            suffix=self.ed_suffix.text(),
            case=cases[self.cb_case.currentIndex()],
        )

    def _expand(self, files):
        from ...core.common import iter_files

        return iter_files(files, recursive=False)

    def refresh_preview(self) -> None:
        try:
            files = self._expand(self.drop.files())
            plan = build_plan(files, self.collect())
        except Exception:
            return
        self.table.setRowCount(len(plan))
        for i, item in enumerate(plan):
            old, new = os.path.basename(item.src), os.path.basename(item.dst)
            if item.skipped and item.error:
                new = f"⚠ {new} ({item.error})"
            self.table.setItem(i, 0, QTableWidgetItem(old))
            self.table.setItem(i, 1, QTableWidgetItem(new))

    def validate(self, files, output) -> str:
        if not self._expand(files):
            return tr("msg_no_files")
        return ""

    def make_task(self, files, output):
        opt = self.collect()
        real_files = self._expand(files)

        def task(progress, log, cancelled):
            plan = build_plan(real_files, opt)
            total = sum(1 for i in plan if not i.skipped) or 1

            def on_prog(done, _t, dst):
                progress(int(done / total * 100), os.path.basename(dst))
                self.log(f"→ {os.path.basename(dst)}")

            ok, fail, errors = execute_plan(plan, on_progress=on_prog,
                                            cancel=lambda: bool(cancelled and cancelled()))
            for e in errors[:20]:
                self.log(f"✕ {e}")
            skipped = sum(1 for i in plan if i.skipped)
            if skipped:
                self.log(f"⏭ {skipped} skipped")
            self.refresh_signal_emit()
            return self.fmt_result(ok, fail)

        return self.guard(task)

    def refresh_signal_emit(self):
        # refresh preview in GUI thread after rename
        self.ui_call(self.refresh_preview)
