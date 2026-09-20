"""Bilingual (FA/EN) strings. Persian is the default language."""

from __future__ import annotations

LANG_FA = "fa"
LANG_EN = "en"

STRINGS: dict[str, dict[str, str]] = {
    # ── App ──────────────────────────────────────────────
    "app_tagline": {"fa": "جعبه‌ابزار همه‌کاره فایل", "en": "All-in-One file toolbox"},
    "dashboard": {"fa": "داشبورد", "en": "Dashboard"},
    "tools": {"fa": "ابزارها", "en": "Tools"},
    "batch_center": {"fa": "مرکز پردازش گروهی", "en": "Batch Center"},
    "settings": {"fa": "تنظیمات", "en": "Settings"},
    "language": {"fa": "زبان", "en": "Language"},
    "theme": {"fa": "پوسته", "en": "Theme"},
    "theme_dark": {"fa": "تیره", "en": "Dark"},
    "theme_light": {"fa": "روشن", "en": "Light"},
    "search_tools": {"fa": "جست‌وجوی ابزار…", "en": "Search tools…"},

    # ── Tool names ───────────────────────────────────────
    "tool_rename": {"fa": "تغییر نام گروهی", "en": "Batch Rename"},
    "tool_rename_desc": {"fa": "تغییر نام ده‌ها فایل با الگو، شماره‌گذاری و پیش‌نمایش", "en": "Rename many files with patterns, numbering and live preview"},
    "tool_convert": {"fa": "تبدیل فرمت تصاویر", "en": "Image Converter"},
    "tool_convert_desc": {"fa": "تبدیل JPG ،PNG ،WebP ،BMP ،TIFF و… به یکدیگر", "en": "Convert between JPG, PNG, WebP, BMP, TIFF and more"},
    "tool_resize": {"fa": "تغییر اندازه گروهی", "en": "Batch Resize"},
    "tool_resize_desc": {"fa": "کوچک/بزرگ کردن گروهی تصاویر با حفظ نسبت", "en": "Resize many images while keeping aspect ratio"},
    "tool_watermark": {"fa": "واترمارک", "en": "Watermark"},
    "tool_watermark_desc": {"fa": "افزودن واترمارک متنی یا تصویری به عکس‌ها", "en": "Add text or image watermarks to photos"},
    "tool_compress": {"fa": "فشرده‌سازی", "en": "Compressor"},
    "tool_compress_desc": {"fa": "ساخت و باز کردن ZIP ،کم‌کردن حجم عکس و PDF", "en": "Create/extract ZIP, shrink images and PDFs"},
    "tool_pdf": {"fa": "ابزارهای PDF", "en": "PDF Tools"},
    "tool_pdf_desc": {"fa": "تبدیل به Word و عکس، استخراج متن، ادغام و تقسیم", "en": "Convert to Word/images, extract text, merge & split"},
    "tool_ocr": {"fa": "تشخیص متن (OCR)", "en": "OCR"},
    "tool_ocr_desc": {"fa": "استخراج متن فارسی/انگلیسی از عکس و PDF اسکن‌شده", "en": "Extract Persian/English text from images & scanned PDFs"},
    "tool_metadata": {"fa": "حذف متادیتا", "en": "Metadata Remover"},
    "tool_metadata_desc": {"fa": "پاک‌سازی اطلاعات مخفی عکس، PDF و فایل صوتی", "en": "Strip hidden data from images, PDFs and audio"},
    "tool_duplicates": {"fa": "فایل‌های تکراری", "en": "Duplicate Finder"},
    "tool_duplicates_desc": {"fa": "پیدا کردن فایل‌های کاملاً یکسان با هش", "en": "Find byte-identical files using hashes"},
    "tool_organizer": {"fa": "مرتب‌ساز فایل", "en": "File Organizer"},
    "tool_organizer_desc": {"fa": "دسته‌بندی خودکار دانلودها بر اساس نوع و تاریخ", "en": "Auto-sort downloads by type and date"},
    "tool_clipboard": {"fa": "مدیر کلیپ‌برد", "en": "Clipboard Manager"},
    "tool_clipboard_desc": {"fa": "تاریخچه، جست‌وجو و سنجاق کردن متن‌های کپی‌شده", "en": "History, search and pinning for copied text"},

    # ── Common actions ───────────────────────────────────
    "add_files": {"fa": "＋ افزودن فایل", "en": "＋ Add files"},
    "add_folder": {"fa": "＋ افزودن پوشه", "en": "＋ Add folder"},
    "clear": {"fa": "پاک کردن لیست", "en": "Clear list"},
    "remove_selected": {"fa": "حذف انتخاب‌شده", "en": "Remove selected"},
    "drop_hint": {"fa": "فایل‌ها را اینجا بکشید و رها کنید", "en": "Drag & drop files here"},
    "output_folder": {"fa": "پوشه خروجی", "en": "Output folder"},
    "browse": {"fa": "انتخاب…", "en": "Browse…"},
    "same_as_source": {"fa": "همان پوشه فایل‌ها", "en": "Same as source"},
    "run": {"fa": "▶ اجرا", "en": "▶ Run"},
    "cancel": {"fa": "⏹ لغو", "en": "⏹ Cancel"},
    "preview": {"fa": "پیش‌نمایش", "en": "Preview"},
    "refresh_preview": {"fa": "به‌روزرسانی پیش‌نمایش", "en": "Refresh preview"},
    "log": {"fa": "گزارش اجرا", "en": "Run log"},
    "ready": {"fa": "آماده", "en": "Ready"},
    "running": {"fa": "در حال اجرا…", "en": "Running…"},
    "done": {"fa": "انجام شد ✓", "en": "Done ✓"},
    "cancelled": {"fa": "لغو شد", "en": "Cancelled"},
    "failed": {"fa": "خطا", "en": "Failed"},
    "files_count": {"fa": "{n} فایل", "en": "{n} files"},
    "overwrite": {"fa": "جایگزینی فایل موجود", "en": "Overwrite existing"},
    "recursive": {"fa": "شامل زیرپوشه‌ها", "en": "Include subfolders"},
    "open_output": {"fa": "باز کردن پوشه خروجی", "en": "Open output folder"},
    "copy": {"fa": "کپی", "en": "Copy"},
    "delete": {"fa": "حذف", "en": "Delete"},
    "save": {"fa": "ذخیره", "en": "Save"},
    "close": {"fa": "بستن", "en": "Close"},
    "search": {"fa": "جست‌وجو…", "en": "Search…"},
    "options": {"fa": "تنظیمات ابزار", "en": "Tool options"},
    "input_files": {"fa": "فایل‌های ورودی", "en": "Input files"},

    # ── Rename ───────────────────────────────────────────
    "rn_pattern": {"fa": "الگو", "en": "Pattern"},
    "rn_pattern_hint": {"fa": "‎{name} نام اصلی • ‎{n} شماره • ‎{ext} پسوند • ‎{date} تاریخ", "en": "{name} original • {n} number • {ext} extension • {date} date"},
    "rn_start": {"fa": "شروع شماره‌گذاری", "en": "Start number"},
    "rn_pad": {"fa": "تعداد ارقام", "en": "Zero-pad digits"},
    "rn_find": {"fa": "جست‌وجو", "en": "Find"},
    "rn_replace": {"fa": "جایگزینی", "en": "Replace"},
    "rn_prefix": {"fa": "پیشوند", "en": "Prefix"},
    "rn_suffix": {"fa": "پسوند", "en": "Suffix"},
    "rn_case": {"fa": "حالت حروف", "en": "Letter case"},
    "rn_case_none": {"fa": "بدون تغییر", "en": "No change"},
    "rn_case_lower": {"fa": "حروف کوچک", "en": "lowercase"},
    "rn_case_upper": {"fa": "حروف بزرگ", "en": "UPPERCASE"},
    "rn_case_title": {"fa": "حرف اول بزرگ", "en": "Title Case"},
    "rn_old": {"fa": "نام فعلی", "en": "Current name"},
    "rn_new": {"fa": "نام جدید", "en": "New name"},
    "rn_mode_pattern": {"fa": "الگوی نام جدید", "en": "New-name pattern"},
    "rn_mode_find": {"fa": "جست‌وجو و جایگزینی", "en": "Find & replace"},

    # ── Convert / resize / watermark ─────────────────────
    "cv_format": {"fa": "فرمت مقصد", "en": "Target format"},
    "cv_quality": {"fa": "کیفیت JPEG/WebP", "en": "JPEG/WebP quality"},
    "cv_keep_meta": {"fa": "حفظ متادیتا", "en": "Keep metadata"},
    "rs_width": {"fa": "عرض", "en": "Width"},
    "rs_height": {"fa": "ارتفاع", "en": "Height"},
    "rs_keep_aspect": {"fa": "حفظ نسبت تصویر", "en": "Keep aspect ratio"},
    "rs_mode": {"fa": "حالت", "en": "Mode"},
    "rs_mode_fit": {"fa": "جای‌گیری در ابعاد (Fit)", "en": "Fit inside"},
    "rs_mode_fill": {"fa": "پر کردن ابعاد (برش)", "en": "Fill (crop)"},
    "rs_mode_exact": {"fa": "ابعاد دقیق", "en": "Exact size"},
    "rs_max_dim": {"fa": "حداکثر ضلع (پیکسل)", "en": "Max side (px)"},
    "rs_by_max": {"fa": "بر اساس حداکثر ضلع", "en": "By max side"},
    "rs_by_wh": {"fa": "بر اساس عرض/ارتفاع", "en": "By width/height"},
    "wm_text": {"fa": "متن واترمارک", "en": "Watermark text"},
    "wm_logo": {"fa": "فایل لوگو (اختیاری)", "en": "Logo file (optional)"},
    "wm_position": {"fa": "موقعیت", "en": "Position"},
    "wm_opacity": {"fa": "شفافیت", "en": "Opacity"},
    "wm_size": {"fa": "اندازه نسبی", "en": "Relative size"},
    "wm_tile": {"fa": "تکرار کاشی‌وار", "en": "Tiled repeat"},

    # ── Compress ─────────────────────────────────────────
    "cp_mode": {"fa": "عملیات", "en": "Operation"},
    "cp_zip": {"fa": "ساخت فایل ZIP", "en": "Create ZIP"},
    "cp_unzip": {"fa": "باز کردن آرشیو", "en": "Extract archive"},
    "cp_shrink_img": {"fa": "کم‌کردن حجم تصاویر", "en": "Shrink images"},
    "cp_shrink_pdf": {"fa": "کم‌کردن حجم PDF", "en": "Shrink PDF"},
    "cp_level": {"fa": "سطح فشرده‌سازی", "en": "Compression level"},
    "cp_archive_name": {"fa": "نام فایل ZIP", "en": "ZIP file name"},
    "cp_max_dim": {"fa": "حداکثر ضلع تصویر", "en": "Max image side"},
    "cp_pdf_dpi": {"fa": "کیفیت تصاویر PDF (DPI)", "en": "PDF image quality (DPI)"},

    # ── PDF ──────────────────────────────────────────────
    "pdf_op": {"fa": "عملیات", "en": "Operation"},
    "pdf_to_images": {"fa": "PDF ← تصاویر (هر صفحه یک عکس)", "en": "PDF → Images (one per page)"},
    "pdf_to_docx": {"fa": "PDF ← فایل Word", "en": "PDF → Word"},
    "pdf_to_text": {"fa": "استخراج متن PDF", "en": "Extract text"},
    "pdf_merge": {"fa": "ادغام چند PDF", "en": "Merge PDFs"},
    "pdf_split": {"fa": "تقسیم PDF (هر صفحه جدا)", "en": "Split PDF (per page)"},
    "pdf_dpi": {"fa": "دقت خروجی (DPI)", "en": "Output DPI"},
    "pdf_img_format": {"fa": "فرمت تصاویر", "en": "Image format"},
    "pdf_merged_name": {"fa": "نام فایل نهایی", "en": "Merged file name"},
    "pdf_result_text": {"fa": "متن استخراج‌شده", "en": "Extracted text"},
    "pdf_save_text": {"fa": "ذخیره متن (.txt)", "en": "Save text (.txt)"},

    # ── OCR ──────────────────────────────────────────────
    "ocr_lang": {"fa": "زبان تشخیص", "en": "Recognition language"},
    "ocr_fa_en": {"fa": "فارسی + انگلیسی", "en": "Persian + English"},
    "ocr_en": {"fa": "انگلیسی", "en": "English"},
    "ocr_fa": {"fa": "فارسی", "en": "Persian"},
    "ocr_result": {"fa": "متن شناسایی‌شده", "en": "Recognized text"},
    "ocr_save": {"fa": "ذخیره متن", "en": "Save text"},
    "ocr_dpi": {"fa": "دقت رندر PDF", "en": "PDF render DPI"},
    "ocr_hint": {"fa": "نیاز به موتور Tesseract دارد (راهنما در README).", "en": "Requires the Tesseract engine (see README)."},

    # ── Metadata ─────────────────────────────────────────
    "md_action": {"fa": "عمل", "en": "Action"},
    "md_view": {"fa": "نمایش متادیتا", "en": "View metadata"},
    "md_strip": {"fa": "حذف متادیتا (رونوشت تمیز)", "en": "Strip metadata (clean copy)"},
    "md_supported": {"fa": "پشتیبانی: JPG، PNG، WebP، TIFF ،PDF و MP3/FLAC/M4A", "en": "Supported: JPG, PNG, WebP, TIFF, PDF and MP3/FLAC/M4A"},

    # ── Duplicates ───────────────────────────────────────
    "du_scan": {"fa": "🔎 اسکن", "en": "🔎 Scan"},
    "du_groups": {"fa": "گروه‌های تکراری", "en": "Duplicate groups"},
    "du_wasted": {"fa": "حجم قابل آزادسازی", "en": "Reclaimable space"},
    "du_min_size": {"fa": "حداقل حجم فایل", "en": "Minimum file size"},
    "du_keep": {"fa": "نگه‌داشتن", "en": "Keep"},
    "du_keep_first": {"fa": "اولین فایل هر گروه", "en": "First file of each group"},
    "du_keep_newest": {"fa": "جدیدترین", "en": "Newest"},
    "du_keep_oldest": {"fa": "قدیمی‌ترین", "en": "Oldest"},
    "du_move": {"fa": "انتقال تکراری‌ها به پوشه…", "en": "Move duplicates to folder…"},
    "du_delete": {"fa": "حذف تکراری‌ها", "en": "Delete duplicates"},
    "du_confirm_delete": {"fa": "آیا از حذف {n} فایل تکراری مطمئن هستید؟", "en": "Delete {n} duplicate files?"},
    "du_select_all": {"fa": "انتخاب همه تکراری‌ها", "en": "Select all duplicates"},

    # ── Organizer ────────────────────────────────────────
    "or_source": {"fa": "پوشه مبدأ (مثلاً Downloads)", "en": "Source folder (e.g. Downloads)"},
    "or_mode": {"fa": "دسته‌بندی بر اساس", "en": "Organize by"},
    "or_by_type": {"fa": "نوع فایل", "en": "File type"},
    "or_by_ext": {"fa": "پسوند دقیق", "en": "Exact extension"},
    "or_by_date": {"fa": "نوع + ماه", "en": "Type + month"},
    "or_plan": {"fa": "مشاهده طرح", "en": "Show plan"},
    "or_apply": {"fa": "✓ اجرای مرتب‌سازی", "en": "✓ Apply"},
    "or_moves": {"fa": "انتقال‌ها", "en": "Moves"},
    "or_from": {"fa": "از", "en": "From"},
    "or_to": {"fa": "به", "en": "To"},
    "or_undo_hint": {"fa": "گزارش بازگشت در پوشه مقصد ذخیره می‌شود.", "en": "An undo log is saved next to the output."},

    # ── Clipboard ────────────────────────────────────────
    "cb_monitor": {"fa": "نظارت بر کلیپ‌برد", "en": "Monitor clipboard"},
    "cb_pin": {"fa": "سنجاق", "en": "Pin"},
    "cb_unpin": {"fa": "برداشتن سنجاق", "en": "Unpin"},
    "cb_copy_back": {"fa": "کپی مجدد", "en": "Copy again"},
    "cb_clear": {"fa": "پاک‌سازی تاریخچه", "en": "Clear history"},
    "cb_export": {"fa": "خروجی متنی", "en": "Export as text"},
    "cb_empty": {"fa": "هنوز چیزی کپی نشده است. متنی را کپی کنید تا اینجا ذخیره شود.", "en": "Nothing copied yet. Copy some text and it will appear here."},
    "cb_max_items": {"fa": "حداکثر آیتم‌ها", "en": "Max items"},

    # ── Batch center ─────────────────────────────────────
    "bt_queue": {"fa": "صف کارها", "en": "Job queue"},
    "bt_history": {"fa": "تاریخچه", "en": "History"},
    "bt_job": {"fa": "کار", "en": "Job"},
    "bt_tool": {"fa": "ابزار", "en": "Tool"},
    "bt_status": {"fa": "وضعیت", "en": "Status"},
    "bt_progress": {"fa": "پیشرفت", "en": "Progress"},
    "bt_started": {"fa": "شروع", "en": "Started"},
    "bt_finished": {"fa": "پایان", "en": "Finished"},
    "bt_detail": {"fa": "جزئیات", "en": "Detail"},
    "bt_cancel_job": {"fa": "لغو کار", "en": "Cancel job"},
    "bt_clear_done": {"fa": "پاک‌سازی انجام‌شده‌ها", "en": "Clear finished"},
    "bt_hint": {"fa": "هر ابزاری که اجرا کنید، کار آن اینجا ثبت می‌شود و می‌توانید پیشرفت و تاریخچه را ببینید.", "en": "Every tool you run registers a job here with progress and history."},

    # ── Dashboard ────────────────────────────────────────
    "db_welcome": {"fa": "به JamTools خوش آمدید 👋", "en": "Welcome to JamTools 👋"},
    "db_sub": {"fa": "۱۲ ابزار حرفه‌ای فایل، همه در یک برنامه.", "en": "12 professional file tools, all in one app."},
    "db_quick": {"fa": "دسترسی سریع", "en": "Quick access"},
    "db_recent": {"fa": "آخرین کارها", "en": "Recent jobs"},
    "db_open_batch": {"fa": "باز کردن مرکز پردازش", "en": "Open batch center"},
    "db_no_jobs": {"fa": "هنوز کاری اجرا نشده است.", "en": "No jobs yet."},

    # ── Messages ─────────────────────────────────────────
    "msg_no_files": {"fa": "اول فایل اضافه کنید.", "en": "Add files first."},
    "msg_no_output": {"fa": "پوشه خروجی را انتخاب کنید.", "en": "Choose an output folder."},
    "msg_missing_dep": {"fa": "برای این ابزار باید نصب شود: {name}\n{hint}", "en": "This tool needs: {name}\n{hint}"},
    "msg_confirm_title": {"fa": "تأیید", "en": "Confirm"},
    "msg_done_files": {"fa": "{ok} فایل موفق، {fail} خطا", "en": "{ok} succeeded, {fail} failed"},
    "msg_need_tesseract": {"fa": "موتور Tesseract پیدا نشد. آن را نصب کنید و مسیرش را در تنظیمات بدهید.", "en": "Tesseract engine not found. Install it and set its path in Settings."},
    "msg_saved": {"fa": "ذخیره شد: {path}", "en": "Saved: {path}"},

    # ── Settings dialog ──────────────────────────────────
    "st_output_default": {"fa": "پوشه خروجی پیش‌فرض", "en": "Default output folder"},
    "st_tesseract": {"fa": "مسیر tesseract.exe", "en": "tesseract.exe path"},
    "st_auto_detect": {"fa": "تشخیص خودکار", "en": "Auto-detect"},
    "st_about": {"fa": "درباره", "en": "About"},
    "st_about_text": {"fa": "ساخته‌شده با Python و PySide6 ــ نسخه {v}", "en": "Built with Python and PySide6 — v{v}"},
}


class I18n:
    """Tiny runtime translator. Call ``set_lang('fa'|'en')`` then ``tr(key)``."""

    def __init__(self, lang: str = LANG_FA) -> None:
        self.lang = lang if lang in (LANG_FA, LANG_EN) else LANG_FA

    def set_lang(self, lang: str) -> None:
        if lang in (LANG_FA, LANG_EN):
            self.lang = lang

    @property
    def rtl(self) -> bool:
        return self.lang == LANG_FA

    def tr(self, key: str, **kwargs) -> str:
        entry = STRINGS.get(key)
        if not entry:
            text = key
        else:
            text = entry.get(self.lang) or entry.get(LANG_EN) or key
        if kwargs:
            try:
                return text.format(**kwargs)
            except Exception:
                return text
        return text


_i18n = I18n()


def set_lang(lang: str) -> None:
    _i18n.set_lang(lang)


def get_lang() -> str:
    return _i18n.lang


def is_rtl() -> bool:
    return _i18n.rtl


def tr(key: str, **kwargs) -> str:
    return _i18n.tr(key, **kwargs)
