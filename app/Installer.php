<?php
/**
 * Creates tables (MySQL or SQLite) and seeds demo content + admin user.
 */
class Installer
{
    public function __construct(private Database $db) {}

    private function ddl(): array
    {
        $my = $this->db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $now = $my ? 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' : 'TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $T = fn($n, $c) => "CREATE TABLE IF NOT EXISTS `$n` ($c)$tail";

        return [
            $T('users', "`id` $pk, `name` VARCHAR(100) NOT NULL, `email` VARCHAR(190) NOT NULL UNIQUE, `password` VARCHAR(255) NOT NULL, `created_at` $now"),
            $T('settings', "`id` $pk, `key` VARCHAR(100) NOT NULL, `lang` VARCHAR(5) NOT NULL DEFAULT '', `value` TEXT NULL, UNIQUE (`key`,`lang`)"),
            $T('services', "`id` $pk, `icon` VARCHAR(50) NOT NULL DEFAULT 'code', `title_fa` VARCHAR(190) NOT NULL, `title_en` VARCHAR(190) NOT NULL, `desc_fa` TEXT NULL, `desc_en` TEXT NULL, `tags_fa` VARCHAR(255) NULL, `tags_en` VARCHAR(255) NULL, `sort` INT NOT NULL DEFAULT 0, `active` TINYINT NOT NULL DEFAULT 1"),
            $T('projects', "`id` $pk, `slug` VARCHAR(190) NOT NULL UNIQUE, `title_fa` VARCHAR(190) NOT NULL, `title_en` VARCHAR(190) NOT NULL, `category_fa` VARCHAR(100) NULL, `category_en` VARCHAR(100) NULL, `summary_fa` VARCHAR(500) NULL, `summary_en` VARCHAR(500) NULL, `body_fa` LONGTEXT NULL, `body_en` LONGTEXT NULL, `stack` VARCHAR(255) NULL, `client` VARCHAR(190) NULL, `year` VARCHAR(10) NULL, `url` VARCHAR(255) NULL, `image` VARCHAR(255) NULL, `color` VARCHAR(20) NOT NULL DEFAULT '#e8ff47', `featured` TINYINT NOT NULL DEFAULT 0, `sort` INT NOT NULL DEFAULT 0, `active` TINYINT NOT NULL DEFAULT 1, `created_at` $now"),
            $T('skills', "`id` $pk, `name` VARCHAR(100) NOT NULL, `group_fa` VARCHAR(100) NULL, `group_en` VARCHAR(100) NULL, `level` INT NOT NULL DEFAULT 80, `sort` INT NOT NULL DEFAULT 0"),
            $T('testimonials', "`id` $pk, `name_fa` VARCHAR(190) NOT NULL, `name_en` VARCHAR(190) NOT NULL, `role_fa` VARCHAR(190) NULL, `role_en` VARCHAR(190) NULL, `text_fa` TEXT NULL, `text_en` TEXT NULL, `avatar` VARCHAR(255) NULL, `sort` INT NOT NULL DEFAULT 0, `active` TINYINT NOT NULL DEFAULT 1"),
            $T('posts', "`id` $pk, `slug` VARCHAR(190) NOT NULL UNIQUE, `title_fa` VARCHAR(190) NOT NULL, `title_en` VARCHAR(190) NOT NULL, `excerpt_fa` VARCHAR(500) NULL, `excerpt_en` VARCHAR(500) NULL, `body_fa` LONGTEXT NULL, `body_en` LONGTEXT NULL, `image` VARCHAR(255) NULL, `published` TINYINT NOT NULL DEFAULT 1, `created_at` $now"),
            $T('messages', "`id` $pk, `name` VARCHAR(190) NOT NULL, `email` VARCHAR(190) NOT NULL, `phone` VARCHAR(50) NULL, `subject` VARCHAR(190) NULL, `budget` VARCHAR(50) NULL, `message` TEXT NOT NULL, `ip` VARCHAR(45) NULL, `is_read` TINYINT NOT NULL DEFAULT 0, `created_at` $now"),
        ];
    }

    public function isInstalled(): bool
    {
        return $this->db->tableExists('users') && (int)$this->db->val('SELECT COUNT(*) FROM users') > 0;
    }

    public function install(string $adminName, string $adminEmail, string $adminPass, bool $seed = true): void
    {
        foreach ($this->ddl() as $sql) $this->db->run($sql);

        $this->db->insert('users', [
            'name' => $adminName, 'email' => $adminEmail,
            'password' => password_hash($adminPass, PASSWORD_DEFAULT),
        ]);
        if ($seed) $this->seed();
    }

    private function set(string $key, string $lang, string $value): void
    {
        $this->db->insert('settings', ['key' => $key, 'lang' => $lang, 'value' => $value]);
    }

    public function seed(): void
    {
        $db = $this->db;

        // ---------- Settings ----------
        $common = [
            'brand'        => 'JamSoft',
            'email'        => 'hello@jamsoft.ir',
            'phone'        => '+98 912 000 0000',
            'telegram'     => 'https://t.me/jamsoft',
            'github'       => 'https://github.com/jamsoft',
            'linkedin'     => 'https://linkedin.com/company/jamsoft',
            'instagram'    => 'https://instagram.com/jamsoft',
            'accent'       => '#e8ff47',
            'stat_years'   => '8',
            'stat_projects'=> '64',
            'stat_clients' => '40',
            'stat_uptime'  => '99.9',
            'show_blog'    => '1',
            'show_testimonials' => '1',
        ];
        foreach ($common as $k => $v) $this->set($k, '', $v);

        $fa = [
            'site_title'   => 'جم سافت | طراحی وب‌اپلیکیشن، فروشگاه اینترنتی و سیستم‌های SaaS',
            'meta_desc'    => 'جم سافت؛ طراحی و توسعه‌ی نرم‌افزارهای تحت وب، فروشگاه اینترنتی، اتوماسیون اداری و سیستم‌های SaaS با تمرکز بر کارایی و مقیاس‌پذیری.',
            'hero_kicker'  => 'استودیو توسعه‌ی نرم‌افزار',
            'hero_title'   => 'نرم‌افزارِ تحت وب که کسب‌وکارت را جلو می‌برد.',
            'hero_sub'     => 'از ایده تا محصول؛ وب‌اپلیکیشن، فروشگاه اینترنتی، اتوماسیون و پلتفرم‌های SaaS را با معماری تمیز و قابل توسعه می‌سازیم.',
            'hero_cta'     => 'شروع یک پروژه',
            'hero_cta2'    => 'دیدن نمونه‌کارها',
            'about_title'  => 'کدی که سال‌ها بعد هم قابل نگهداری است.',
            'about_text'   => "جم سافت یک استودیوی کوچک و متمرکز است. ما به جای قالب‌های آماده، برای هر کسب‌وکار راه‌حل اختصاصی طراحی می‌کنیم؛ از تحلیل نیاز و طراحی پایگاه‌داده تا استقرار و پشتیبانی.\n\nباور ما این است که نرم‌افزار خوب ساده است، سریع اجرا می‌شود و با رشد شما رشد می‌کند.",
            'process_title'=> 'چطور کار می‌کنیم',
            'cta_title'    => 'ایده‌ای در سر داری؟',
            'cta_text'     => 'یک پیام بفرست؛ ظرف ۲۴ ساعت با برآورد اولیه‌ی زمان و هزینه پاسخ می‌دهیم.',
            'footer_text'  => 'طراحی و توسعه‌ی نرم‌افزارهای تحت وب برای کسب‌وکارهایی که به رشد فکر می‌کنند.',
            'address'      => 'ایران، تهران',
        ];
        foreach ($fa as $k => $v) $this->set($k, 'fa', $v);

        $en = [
            'site_title'   => 'JamSoft | Web Apps, E‑commerce & SaaS Development',
            'meta_desc'    => 'JamSoft designs and builds web applications, online stores, business automation and SaaS platforms with a focus on performance and scalability.',
            'hero_kicker'  => 'Software Development Studio',
            'hero_title'   => 'Web software that moves your business forward.',
            'hero_sub'     => 'From idea to product — we build web apps, e‑commerce stores, automation tools and SaaS platforms with clean, scalable architecture.',
            'hero_cta'     => 'Start a project',
            'hero_cta2'    => 'See our work',
            'about_title'  => 'Code that is still maintainable years later.',
            'about_text'   => "JamSoft is a small, focused studio. Instead of off‑the‑shelf templates we design tailored solutions for each business — from requirements and database design to deployment and support.\n\nWe believe good software is simple, runs fast and grows with you.",
            'process_title'=> 'How we work',
            'cta_title'    => 'Have an idea in mind?',
            'cta_text'     => 'Drop us a line — we reply within 24 hours with a first estimate of time and cost.',
            'footer_text'  => 'Designing and developing web software for businesses that think about growth.',
            'address'      => 'Tehran, Iran',
        ];
        foreach ($en as $k => $v) $this->set($k, 'en', $v);

        // ---------- Services ----------
        $services = [
            ['layout', 'برنامه‌ی تحت وب', 'Web Applications',
             'طراحی و توسعه‌ی نرم‌افزارهای سفارشی تحت وب؛ سریع، امن و متناسب با فرآیندهای واقعی کسب‌وکار شما.',
             'Custom web software tailored to your real business processes — fast, secure and built to last.',
             'PHP, Laravel, MySQL, API', 'PHP, Laravel, MySQL, API'],
            ['cart', 'فروشگاه اینترنتی', 'E‑commerce',
             'فروشگاه آنلاین با مدیریت محصول، درگاه پرداخت، انبار و گزارش فروش؛ آماده‌ی مقیاس‌پذیری.',
             'Online stores with product management, payment gateways, inventory and sales reports — ready to scale.',
             'درگاه پرداخت, انبار, پنل فروشنده', 'Payments, Inventory, Vendor panel'],
            ['zap', 'اتوماسیون اداری', 'Business Automation',
             'دیجیتالی‌کردن فرآیندهای کاغذی؛ گردش کار، فرم‌ساز، کارتابل و گزارش‌های مدیریتی.',
             'Digitize paper processes — workflows, form builders, task inboxes and management dashboards.',
             'گردش کار, فرم‌ساز, داشبورد', 'Workflow, Forms, Dashboards'],
            ['cloud', 'سیستم‌های SaaS', 'SaaS Platforms',
             'پلتفرم‌های چندمستأجری با اشتراک، صورت‌حساب و پنل مدیریت؛ از MVP تا محصول کامل.',
             'Multi‑tenant platforms with subscriptions, billing and admin panels — from MVP to full product.',
             'Multi-tenant, Billing, REST API', 'Multi-tenant, Billing, REST API'],
            ['smartphone', 'وب‌اپلیکیشن PWA', 'Progressive Web Apps',
             'تجربه‌ی موبایل بدون نصب از استور؛ آفلاین، سریع و قابل نصب روی صفحه‌ی گوشی.',
             'App‑like mobile experience without app stores — offline, fast and installable.',
             'PWA, Offline, Push', 'PWA, Offline, Push'],
            ['shield', 'پشتیبانی و توسعه', 'Support & Maintenance',
             'نگهداری، بهینه‌سازی و توسعه‌ی سیستم‌های موجود؛ مانیتورینگ و پشتیبان‌گیری منظم.',
             'Maintenance, optimization and extension of existing systems — monitoring and regular backups.',
             'مانیتورینگ, بکاپ, بهینه‌سازی', 'Monitoring, Backups, Optimization'],
        ];
        foreach ($services as $i => $s) {
            $db->insert('services', ['icon' => $s[0], 'title_fa' => $s[1], 'title_en' => $s[2],
                'desc_fa' => $s[3], 'desc_en' => $s[4], 'tags_fa' => $s[5], 'tags_en' => $s[6], 'sort' => $i]);
        }

        // ---------- Projects ----------
        $projects = [
            ['novin-shop', 'فروشگاه نوین', 'Novin Store', 'فروشگاه اینترنتی', 'E‑commerce',
             'فروشگاه چندفروشنده با ۱۲ هزار محصول، درگاه پرداخت و پنل فروشنده.',
             'Multi‑vendor store with 12k products, payment gateway and vendor panel.',
             'PHP, Laravel, MySQL, Redis, Vue', 'Novin Co.', '2025', '#e8ff47', 1],
            ['tejarat-automation', 'اتوماسیون تجارت‌گستر', 'Tejarat Automation', 'اتوماسیون', 'Automation',
             'سیستم گردش کار و کارتابل برای ۳۰۰ کاربر با فرم‌ساز پویا.',
             'Workflow & task inbox system for 300 users with dynamic form builder.',
             'PHP, MySQL, Alpine.js', 'Tejarat Gostar', '2024', '#7c9cff', 1],
            ['clinicly', 'کلینیکلی', 'Clinicly', 'SaaS', 'SaaS',
             'پلتفرم SaaS نوبت‌دهی و مدیریت مطب با اشتراک ماهانه.',
             'Appointment & clinic management SaaS with monthly subscriptions.',
             'Laravel, MySQL, Stripe, Livewire', 'Clinicly', '2024', '#ff8a5c', 1],
            ['inventory-pwa', 'انباردار PWA', 'Inventory PWA', 'وب‌اپلیکیشن', 'Web App',
             'وب‌اپلیکیشن آفلاین انبارداری با اسکن بارکد.',
             'Offline‑first inventory web app with barcode scanning.',
             'PHP, MySQL, PWA, IndexedDB', 'Pars Logistics', '2023', '#5ce0c6', 0],
            ['crm-lite', 'CRM سبک', 'CRM Lite', 'وب‌اپلیکیشن', 'Web App',
             'مدیریت مشتریان و پیگیری فروش برای تیم‌های کوچک.',
             'Customer management and sales pipeline for small teams.',
             'PHP, MySQL, HTMX', 'Internal', '2023', '#f472b6', 0],
            ['edu-platform', 'پلتفرم آموزش آنلاین', 'Online Learning Platform', 'SaaS', 'SaaS',
             'دوره‌های ویدیویی، آزمون و صدور گواهی با پنل مدرس.',
             'Video courses, quizzes and certificates with instructor panel.',
             'Laravel, MySQL, FFmpeg, S3', 'Daneshjoo', '2022', '#c084fc', 0],
        ];
        foreach ($projects as $i => $p) {
            $db->insert('projects', ['slug' => $p[0], 'title_fa' => $p[1], 'title_en' => $p[2],
                'category_fa' => $p[3], 'category_en' => $p[4], 'summary_fa' => $p[5], 'summary_en' => $p[6],
                'body_fa' => $p[5] . "\n\nچالش اصلی این پروژه مقیاس‌پذیری و سرعت بود. با طراحی دقیق پایگاه‌داده و کش‌گذاری لایه‌ای، زمان پاسخ به زیر ۲۰۰ میلی‌ثانیه رسید.",
                'body_en' => $p[6] . "\n\nThe main challenge was scale and speed. Careful database design and layered caching brought response time under 200ms.",
                'stack' => $p[7], 'client' => $p[8], 'year' => $p[9], 'color' => $p[10], 'featured' => $p[11], 'sort' => $i]);
        }

        // ---------- Skills ----------
        $skills = [
            ['PHP 8', 'بک‌اند', 'Backend', 95], ['Laravel', 'بک‌اند', 'Backend', 92], ['MySQL', 'بک‌اند', 'Backend', 90],
            ['REST / GraphQL API', 'بک‌اند', 'Backend', 88], ['Redis', 'بک‌اند', 'Backend', 80],
            ['JavaScript', 'فرانت‌اند', 'Frontend', 88], ['Vue / Alpine', 'فرانت‌اند', 'Frontend', 85], ['Tailwind / CSS', 'فرانت‌اند', 'Frontend', 90],
            ['Linux / Nginx', 'زیرساخت', 'DevOps', 82], ['Docker', 'زیرساخت', 'DevOps', 78], ['CI/CD', 'زیرساخت', 'DevOps', 75],
        ];
        foreach ($skills as $i => $s) $db->insert('skills', ['name' => $s[0], 'group_fa' => $s[1], 'group_en' => $s[2], 'level' => $s[3], 'sort' => $i]);

        // ---------- Testimonials ----------
        $t = [
            ['علی رضایی', 'Ali Rezaei', 'مدیرعامل نوین', 'CEO, Novin Co.',
             'فروشگاه ما در زمان تعیین‌شده تحویل شد و از روز اول بدون مشکل زیر بار رفت. پشتیبانی بعد از تحویل عالی بود.',
             'Our store was delivered on time and handled real traffic from day one. Post‑launch support has been excellent.'],
            ['سارا محمدی', 'Sara Mohammadi', 'مدیر عملیات تجارت‌گستر', 'Operations Manager, Tejarat Gostar',
             'اتوماسیون جم سافت فرآیندهای کاغذی ما را حذف کرد و زمان تأیید درخواست‌ها از ۳ روز به چند ساعت رسید.',
             'JamSoft\'s automation removed our paper processes; approval time dropped from 3 days to a few hours.'],
            ['دکتر حسینی', 'Dr. Hosseini', 'بنیان‌گذار کلینیکلی', 'Founder, Clinicly',
             'از MVP تا محصول نهایی همراه ما بودند. معماری تمیز باعث شد اضافه‌کردن امکانات جدید ساده باشد.',
             'They were with us from MVP to final product. The clean architecture made adding features easy.'],
        ];
        foreach ($t as $i => $r) $db->insert('testimonials', ['name_fa' => $r[0], 'name_en' => $r[1], 'role_fa' => $r[2], 'role_en' => $r[3], 'text_fa' => $r[4], 'text_en' => $r[5], 'sort' => $i]);

        // ---------- Posts ----------
        $posts = [
            ['why-saas-multitenant', 'چرا معماری چندمستأجری برای SaaS مهم است؟', 'Why multi‑tenancy matters for SaaS',
             'مقایسه‌ی رویکردهای دیتابیس مشترک و جدا برای پلتفرم‌های SaaS.',
             'Comparing shared vs. separate database approaches for SaaS platforms.'],
            ['mysql-indexing-tips', '۷ نکته‌ی ایندکس‌گذاری MySQL که سرعت را چند برابر می‌کند', '7 MySQL indexing tips that multiply speed',
             'از ایندکس ترکیبی تا EXPLAIN؛ نکات عملی برای پروژه‌های واقعی.',
             'From composite indexes to EXPLAIN — practical tips for real projects.'],
            ['pwa-for-business', 'PWA؛ جایگزین ارزان اپلیکیشن موبایل برای کسب‌وکارها', 'PWA: the affordable alternative to native apps',
             'چه زمانی PWA کافی است و چه زمانی سراغ اپ نیتیو برویم.',
             'When a PWA is enough — and when to go native.'],
        ];
        foreach ($posts as $p) {
            $db->insert('posts', ['slug' => $p[0], 'title_fa' => $p[1], 'title_en' => $p[2], 'excerpt_fa' => $p[3], 'excerpt_en' => $p[4],
                'body_fa' => $p[3] . "\n\nاین یک متن نمونه است. از پنل مدیریت می‌توانید محتوای کامل مقاله را ویرایش کنید.",
                'body_en' => $p[4] . "\n\nThis is sample content. Edit the full article from the admin panel."]);
        }
    }
}
