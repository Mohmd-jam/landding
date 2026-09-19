<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Seeder;

/**
 * Client testimonials and the blog (categories, tags, six bilingual articles).
 * Managed in Admin → Testimonials / Blog.
 */
return static function (Seeder $seeder): void {
    /* --------------------------------------------------------------------- */
    /* Testimonials                                                          */
    /* --------------------------------------------------------------------- */
    $testimonials = [
        [
            'rating' => 5, 'lang' => 'fa', 'featured' => 1,
            'fa' => [
                'client_name' => 'نازنین رستمی', 'client_position' => 'مدیر محصول', 'company' => 'دیجی‌مارکت',
                'quote' => 'آرش تنها توسعه‌دهنده‌ای بود که قبل از کدنویسی، فرایند فروش ما را به‌طور کامل بررسی کرد. نتیجه کار، فروشگاهی است که تیم ما هر روز از آن استفاده می‌کند و هیچ‌وقت ما را در ساعات پرترافیک تنها نگذاشته است.',
            ],
            'en' => [
                'client_name' => 'Nazanin Rostami', 'client_position' => 'Product Manager', 'company' => 'DigiMarket',
                'quote' => 'Arash was the only developer who studied our entire sales process before writing code. The result is a store our team uses daily — and it has never let us down during peak traffic.',
            ],
        ],
        [
            'rating' => 5, 'lang' => 'fa', 'featured' => 1,
            'fa' => [
                'client_name' => 'مهدی کاظمی', 'client_position' => 'مدیرعامل', 'company' => 'شرکت بازرگانی آرمان',
                'quote' => 'مهاجرت ۳۰ هزار سند مالی، کاری بود که هیچ‌کس قبول نمی‌کرد. آرش با یک برنامه دقیق انجامش داد و همه مانده‌ها بدون اختلاف تأیید شد. گزارش‌هایی که قبلاً سه روز طول می‌کشید، الآن در چند ثانیه آماده است.',
            ],
            'en' => [
                'client_name' => 'Mehdi Kazemi', 'client_position' => 'CEO', 'company' => 'Arman Trading',
                'quote' => 'Migrating 30,000 financial records was a job nobody wanted to take. Arash planned it precisely, every balance reconciled, and reports that used to take three days now take seconds.',
            ],
        ],
        [
            'rating' => 5, 'lang' => 'en', 'featured' => 1,
            'fa' => [
                'client_name' => 'Sofia Meyer', 'client_position' => 'Head of Operations', 'company' => 'Pulse Retail',
                'quote' => 'شفافیت ارتباط آرش عالی است: هر هفته پیشرفت را می‌بینیم و هر تصمیم فنی با دلیل مستند می‌شود. داشبوردی که ساخت، به ابزار اصلی جلسات مدیریتی ما تبدیل شده است.',
            ],
            'en' => [
                'client_name' => 'Sofia Meyer', 'client_position' => 'Head of Operations', 'company' => 'Pulse Retail',
                'quote' => 'Arash communicates with rare clarity: we see progress every week and every technical decision is documented with its reasoning. The dashboard he built is now the centrepiece of our management meetings.',
            ],
        ],
        [
            'rating' => 4, 'lang' => 'fa', 'featured' => 0,
            'fa' => [
                'client_name' => 'سارا امینی', 'client_position' => 'مدیر آکادمی', 'company' => 'آکادمی مهارت',
                'quote' => 'پلتفرم آموزشی ما باید سریع، امن و قابل توسعه می‌بود. آرش هر سه را رعایت کرد و نکته مهم‌تر، آموزش تیم ما برای مدیریت محتوا بود؛ الآن بدون کمک کسی دوره‌های جدید را منتشر می‌کنیم.',
            ],
            'en' => [
                'client_name' => 'Sara Amini', 'client_position' => 'Academy Director', 'company' => 'Maharat Academy',
                'quote' => 'Our learning platform had to be fast, secure and extensible. Arash delivered all three — and even better, he trained our team so we now publish new courses without any technical help.',
            ],
        ],
        [
            'rating' => 5, 'lang' => 'en', 'featured' => 0,
            'fa' => [
                'client_name' => 'Daniel Fisher', 'client_position' => 'CTO', 'company' => 'PayLink',
                'quote' => 'کار با آرش روی لایه یکپارچه‌سازی پرداخت، تجربه‌ای حرفه‌ای بود. وب‌هوک‌هایش در برابر خطا مقاوم است و امروز افزودن یک درگاه جدید کمتر از دو ساعت زمان می‌برد.',
            ],
            'en' => [
                'client_name' => 'Daniel Fisher', 'client_position' => 'CTO', 'company' => 'PayLink',
                'quote' => 'Working with Arash on our payment abstraction layer was a professional experience. His webhook pipeline is failure-tolerant and onboarding a new provider now takes under two hours.',
            ],
        ],
    ];

    foreach ($testimonials as $index => $testimonial) {
        $seeder->translated('testimonials', 'testimonial_translations', [
            'avatar_media_id' => null,
            'rating' => $testimonial['rating'],
            'lang' => $testimonial['lang'],
            'is_featured' => $testimonial['featured'],
            'is_active' => 1,
            'sort_order' => $index + 1,
        ], [
            'fa' => [
                'client_name' => $testimonial['fa']['client_name'],
                'client_position' => $testimonial['fa']['client_position'],
                'company' => $testimonial['fa']['company'],
                'quote' => $testimonial['fa']['quote'],
            ],
            'en' => [
                'client_name' => $testimonial['en']['client_name'],
                'client_position' => $testimonial['en']['client_position'],
                'company' => $testimonial['en']['company'],
                'quote' => $testimonial['en']['quote'],
            ],
        ], 'testimonial_id');
    }

    /* --------------------------------------------------------------------- */
    /* Blog categories & tags                                                */
    /* --------------------------------------------------------------------- */
    $categories = [
        'backend' => ['server', '#3ddc97', 'Back-end', 'بک‌اند', 'PHP, APIs and server-side architecture.', 'PHP، API و معماری سمت سرور.'],
        'frontend' => ['react', '#6c8cff', 'Front-end', 'فرانت‌اند', 'React, JavaScript and interface engineering.', 'React، جاوااسکریپت و مهندسی رابط کاربری.'],
        'database' => ['database', '#4cc9f0', 'Database', 'پایگاه داده', 'MySQL modelling, indexing and performance.', 'مدل‌سازی MySQL، ایندکس‌گذاری و کارایی.'],
        'security' => ['shield', '#ffb020', 'Security', 'امنیت', 'Protecting web applications and their users.', 'محافظت از اپلیکیشن‌های وب و کاربران آن‌ها.'],
        'engineering' => ['git-branch', '#f472b6', 'Engineering', 'مهندسی نرم‌افزار', 'Process, architecture and code quality.', 'فرایند، معماری و کیفیت کد.'],
    ];

    $categoryIds = [];

    foreach ($categories as $key => [$icon, $color, $en, $fa, $enDesc, $faDesc]) {
        $categoryIds[$key] = $seeder->translated('blog_categories', 'blog_category_translations', [
            'icon' => $icon,
            'color' => $color,
            'is_active' => 1,
            'sort_order' => array_search($key, array_keys($categories), true) + 1,
        ], [
            'fa' => ['name' => $fa, 'slug' => $key, 'description' => $faDesc],
            'en' => ['name' => $en, 'slug' => $key, 'description' => $enDesc],
        ], 'category_id');
    }

    $tags = [
        'mysql' => ['MySQL', 'MySQL'],
        'architecture' => ['Architecture', 'معماری'],
        'security' => ['Security', 'امنیت'],
        'performance' => ['Performance', 'کارایی'],
        'react' => ['React', 'React'],
        'php' => ['PHP', 'PHP'],
        'rest-api' => ['REST API', 'REST API'],
        'i18n' => ['i18n', 'چندزبانه'],
        'ux' => ['UX', 'تجربه کاربری'],
    ];

    $tagIds = [];

    foreach ($tags as $key => [$en, $fa]) {
        $tagIds[$key] = $seeder->translated('blog_tags', 'blog_tag_translations', [
            'is_active' => 1,
        ], [
            'fa' => ['name' => $fa, 'slug' => $key],
            'en' => ['name' => $en, 'slug' => $key],
        ], 'tag_id');
    }

    /* --------------------------------------------------------------------- */
    /* Blog posts                                                            */
    /* --------------------------------------------------------------------- */
    $blogMedia = [
        'multilingual' => $seeder->media('seed/blog/multilingual.jpg', 'blog', ['en' => 'Multilingual database architecture', 'fa' => 'معماری پایگاه داده چندزبانه']),
        'reactphp' => $seeder->media('seed/blog/react-php.jpg', 'blog', ['en' => 'React and PHP API integration', 'fa' => 'اتصال React به API PHP']),
        'security' => $seeder->media('seed/blog/security.jpg', 'blog', ['en' => 'Securing public forms', 'fa' => 'امن‌سازی فرم‌های عمومی']),
        'performance' => $seeder->media('seed/blog/performance.jpg', 'blog', ['en' => 'MySQL performance tuning', 'fa' => 'بهینه‌سازی کارایی MySQL']),
    ];

    $posts = [
        [
            'category' => 'backend',
            'cover' => $blogMedia['multilingual'],
            'published_at' => '2026-08-24 08:30:00',
            'featured' => 1,
            'tags' => ['architecture', 'i18n', 'mysql'],
            'fa' => [
                'title' => 'معماری چندزبانه در MySQL بدون تکرار جدول‌ها',
                'slug' => 'multilingual-mysql-architecture',
                'excerpt' => 'الگویی عملی برای مدیریت محتوای فارسی و انگلیسی در یک پایگاه داده واحد؛ با جدول‌های ترجمه، رفع تکرار و پشتیبانی از افزودن زبان جدید.',
                'content' => "وقتی یک وب‌سایت باید دوزبانه باشد، اولین تصمیم معماری این است که محتوا را کجا نگه داریم. سه راه رایج وجود دارد و هر سه در پروژه‌های واقعی دیده می‌شوند.\n\n## سه راه‌حل رایج\n\n**۱. ستون‌های جدا برای هر زبان** — `title_fa`، `title_en`. ساده است، اما با افزودن زبان سوم جدول منفجر می‌شود.\n\n**۲. تکرار رکورد برای هر زبان** — هر پروژه دو ردیف دارد. کدنویسی را سخت می‌کند: کدام ردیف «اصلی» است؟ شناسه‌ها چطور به هم وصل می‌شوند؟\n\n**۳. جدول ترجمه** — داده ساختاری در جدول اصلی، داده انسانی در جدول ترجمه. این همان الگویی است که در این پروژه استفاده شده است.\n\n```sql\nCREATE TABLE projects (\n  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,\n  category_id BIGINT UNSIGNED,\n  is_active TINYINT(1) DEFAULT 1\n);\n\nCREATE TABLE project_translations (\n  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,\n  project_id BIGINT UNSIGNED NOT NULL,\n  lang VARCHAR(8) NOT NULL,\n  title VARCHAR(200) NOT NULL,\n  slug VARCHAR(200) NOT NULL,\n  UNIQUE KEY uniq_project_lang (project_id, lang),\n  UNIQUE KEY uniq_lang_slug (lang, slug)\n);\n```\n\n## چرا این الگو بهتر است؟\n\n**افزودن زبان، بدون مهاجرت.** زبان جدید یعنی یک ردیف در جدول `languages`، نه یک `ALTER TABLE` روی ۲۰ جدول.\n\n**داده یکتا می‌ماند.** `id` پروژه در همه زبان‌ها یکی است؛ پس آمار بازدید، سفارش‌ها و لینک‌های داخلی گم نمی‌شوند.\n\n**قواعد یکپارچه.** وضعیت انتشار، ترتیب نمایش و رسانه‌ها یک بار تعریف می‌شوند.\n\n## نکته‌های عملی\n\n۱. همیشه `UNIQUE(project_id, lang)` بگذارید؛ بدون آن، داده تکراری به‌سرعت وارد پایگاه می‌شود.\n\n۲. برای هر زبان یک ایندکس روی `(lang, slug)` بسازید تا جست‌وجوی آدرس‌ها ایندکس‌محور باشد.\n\n۳. در کوئری‌ها از `COALESCE` استفاده کنید تا اگر ترجمه‌ای ناقص بود، نسخه زبان پیش‌فرض نمایش داده شود و صفحه خالی نماند.\n\n۴. ترجمه‌ها را در همان تراکنش نوشتن رکورد اصلی ذخیره کنید؛ نیم‌ترجمه‌مانده بدترین حالت است.\n\nاین ساختار امروز در همین وب‌سایت استفاده می‌شود: هر پروژه، مقاله و بخش صفحه، یک ردیف پایه و یک ردیف ترجمه برای هر زبان دارد.",
            ],
            'en' => [
                'title' => 'Multilingual content in MySQL without duplicating tables',
                'slug' => 'multilingual-mysql-architecture',
                'excerpt' => 'A practical pattern for managing Persian and English content in a single database — translation tables, no duplication and room for more languages later.',
                'content' => "When a website has to speak two languages, the first architectural decision is where content lives. There are three common approaches and you will meet all three in production code.\n\n## The three approaches\n\n**1. Columns per language** — `title_fa`, `title_en`. Simple, until the third language explodes the table width.\n\n**2. Duplicate rows per language** — every project has two rows. Programming gets awkward: which row is canonical? How do the ids relate?\n\n**3. Translation tables** — structural data in the base table, human-readable data in a translation table. This is the pattern used in this project.\n\n```sql\nCREATE TABLE projects (\n  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,\n  category_id BIGINT UNSIGNED,\n  is_active TINYINT(1) DEFAULT 1\n);\n\nCREATE TABLE project_translations (\n  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,\n  project_id BIGINT UNSIGNED NOT NULL,\n  lang VARCHAR(8) NOT NULL,\n  title VARCHAR(200) NOT NULL,\n  slug VARCHAR(200) NOT NULL,\n  UNIQUE KEY uniq_project_lang (project_id, lang),\n  UNIQUE KEY uniq_lang_slug (lang, slug)\n);\n```\n\n## Why it wins\n\n**Adding a language needs no migration.** A new language is one row in `languages`, not an `ALTER TABLE` across twenty tables.\n\n**Identity stays stable.** A project's `id` is the same in every language, so view counts, orders and internal links survive translation.\n\n**Rules are defined once.** Publication status, ordering and media relationships live in the base table.\n\n## Practical rules\n\n1. Always add `UNIQUE(project_id, lang)` — without it duplicate content appears fast.\n\n2. Index `(lang, slug)` so URL lookups stay index-driven.\n\n3. Use `COALESCE` in queries so a missing translation falls back to the default language instead of rendering an empty page.\n\n4. Write translations in the same transaction as the base row; half-translated records are the worst case.\n\nThis structure runs the website you are reading right now: every project, article and page section is one base row plus one translation row per language.",
            ],
        ],
        [
            'category' => 'frontend',
            'cover' => $blogMedia['reactphp'],
            'published_at' => '2026-07-12 10:00:00',
            'featured' => 1,
            'tags' => ['react', 'php', 'rest-api'],
            'fa' => [
                'title' => 'اتصال React به API نوشته‌شده با PHP؛ از قرارداد داده تا مدیریت خطا',
                'slug' => 'react-with-php-rest-api',
                'excerpt' => 'قرارداد داده، مدیریت خطا، حالت‌های بارگذاری و کش سمت کلاینت؛ چیزهایی که تفاوت بین یک پروژه نمایشی و یک محصول واقعی است.',
                'content' => "ترکیب React و PHP یکی از رایج‌ترین معماری‌های وب امروز است: رابط کاربری مدرن در مرورگر، منطق کسب‌وکار و داده در سرور. اما کیفیت نتیجه به قرارداد بین این دو بستگی دارد.\n\n## ۱. قرارداد پاسخ را ثابت کنید\n\nهمه پاسخ‌ها یک شکل داشته باشند:\n\n```json\n{ \"data\": { ... }, \"meta\": { \"page\": 1, \"total\": 42 } }\n```\n\nو خطاها همیشه یک شکل مشخص:\n\n```json\n{ \"error\": { \"code\": 422, \"message\": \"…\", \"fields\": { \"email\": [\"…\"] } } }\n```\n\nبا این کار، لایه `api.js` در فرانت‌اند می‌تواند همه خطاها را یکسان مدیریت کند و کامپوننت‌ها فقط داده را رندر کنند.\n\n## ۲. اعتبارسنجی را سمت سرور تکرار کنید\n\nاعتبارسنجی مرورگر تجربه کاربری است، نه امنیت. هر فرم باید در PHP دوباره بررسی شود و خطاهای فیلدی به فرانت‌اند برگردد تا زیر همان فیلد نمایش داده شود.\n\n## ۳. حالت‌های رابط را جدی بگیرید\n\nهر بخش داده‌محور حداقل چهار حالت دارد: در حال بارگذاری، خطا، خالی و پر. اگر حالت «خالی» را طراحی نکنید، کاربر با صفحه سفید روبه‌رو می‌شود.\n\n```jsx\nif (status === 'loading') return <Skeleton rows={3} />;\nif (status === 'error') return <ErrorState onRetry={reload} />;\nif (!items.length) return <EmptyState label=\"…\" />;\nreturn items.map(renderItem);\n```\n\n## ۴. کش کوچک ولی مؤثر\n\nیک کش درون‌حافظه‌ای برای داده‌هایی که در چند کامپوننت استفاده می‌شوند (تنظیمات سایت، منو، دسته‌بندی‌ها) کافی است. نیازی به کتابخانه سنگین نیست؛ یک `Map` با کلید `url + lang` و یک زمان انقضا.\n\n## ۵. زبان را همیشه در درخواست بفرستید\n\nدر سایت چندزبانه، زبان بخشی از هویت درخواست است. پارامتر `lang` را در همه فراخوانی‌ها بفرستید و همان کلید را در کش دخالت دهید تا پاسخ فارسی و انگلیسی قاطی نشوند.\n\nرعایت این پنج نکته باعث می‌شود پروژه شما از «دمویی که کار می‌کند» به «محصولی که قابل نگهداری است» تبدیل شود.",
            ],
            'en' => [
                'title' => 'Connecting React to a PHP REST API properly',
                'slug' => 'react-with-php-rest-api',
                'excerpt' => 'Response contracts, error handling, loading states and client-side caching — the difference between a demo and a product.',
                'content' => "React on the client plus PHP on the server is one of the most common architectures on the web today: a modern interface in the browser, business logic and data on the server. The quality of the result depends almost entirely on the contract between the two.\n\n## 1. Freeze the response contract\n\nEvery response has the same shape:\n\n```json\n{ \"data\": { ... }, \"meta\": { \"page\": 1, \"total\": 42 } }\n```\n\nAnd errors always look like this:\n\n```json\n{ \"error\": { \"code\": 422, \"message\": \"…\", \"fields\": { \"email\": [\"…\"] } } }\n```\n\nWith that in place, a thin `api.js` layer can normalise everything and components only render data.\n\n## 2. Validate twice\n\nBrowser validation is user experience, not security. Every form is validated again in PHP, and field-level errors travel back so they can be rendered next to the input that caused them.\n\n## 3. Treat interface states as first-class\n\nEvery data-driven section has at least four states: loading, error, empty and populated. Skip the empty state and your user meets white space.\n\n```jsx\nif (status === 'loading') return <Skeleton rows={3} />;\nif (status === 'error') return <ErrorState onRetry={reload} />;\nif (!items.length) return <EmptyState label=\"…\" />;\nreturn items.map(renderItem);\n```\n\n## 4. Cache small, cache smart\n\nAn in-memory cache for data reused across components (site settings, navigation, categories) is enough. No heavy library needed: a `Map` keyed by `url + lang` with a short TTL.\n\n## 5. Always send the language\n\nOn a multilingual site the locale is part of the request identity. Pass `lang` on every call and include it in the cache key so Persian and English responses never mix.\n\nThese five habits turn a project from “a demo that works” into “a product you can maintain”.",
            ],
        ],
        [
            'category' => 'security',
            'cover' => $blogMedia['security'],
            'published_at' => '2026-06-03 09:15:00',
            'featured' => 0,
            'tags' => ['security', 'php'],
            'fa' => [
                'title' => 'امن‌سازی فرم تماس در برابر اسپم و حمله‌های رایج',
                'slug' => 'securing-contact-forms',
                'excerpt' => 'ترکیبی از اعتبارسنجی سمت سرور، توکن امضاشده، تله اسپم و محدودسازی نرخ — بدون نیاز به سرویس خارجی.',
                'content' => "فرم تماس دروازه ورود داده غیرقابل‌اعتماد به سیستم شماست. هرچه آن را ساده‌تر بگیرید، هزینه‌اش را جای دیگری می‌پردازید.\n\n## لایه اول: اعتبارسنجی سمت سرور\n\nهیچ داده‌ای را از مرورگر قابل اعتماد ندانید. طول، نوع و قالب هر فیلد را در سرور بررسی کنید:\n\n```php\n\$validated = Validator::make(\$request->body(), [\n    'name'    => 'required|string|min:2|max:120',\n    'email'   => 'required|email|max:190',\n    'subject' => 'required|string|min:3|max:200',\n    'message' => 'required|string|min:10|max:5000',\n])->validate();\n```\n\n## لایه دوم: تله اسپم (Honeypot)\n\nیک فیلد مخفی در فرم بگذارید که کاربر واقعی آن را نمی‌بیند. اگر پر شد، ارسال را رد کنید. ساده، بدون هزینه و مؤثر در برابر ربات‌های ساده.\n\n```html\n<input type=\"text\" name=\"website\" tabindex=\"-1\" autocomplete=\"off\" aria-hidden=\"true\" hidden>\n```\n\n## لایه سوم: توکن امضاشده و محدودیت زمان\n\nفرم را با یک توکن HMAC امضا کنید که زمان صدور دارد. ارسال‌های خیلی سریع (کمتر از سه ثانیه) یا توکن منقضی‌شده رد می‌شوند:\n\n```php\n\$payload = ['form' => 'contact', 'exp' => time() + 7200];\n\$token = \$security->sign(\$payload);\n```\n\n## لایه چهارم: محدودسازی نرخ\n\nهر IP یا ایمیل فقط چند ارسال در بازه مشخص داشته باشد. این کار جلوی ارسال انبوه و هزینه سرور را می‌گیرد.\n\n```php\nRateLimiter::enforce('contact', \$request->ip(), 5, 600);\n```\n\n## و در پایان: خروجی امن\n\nهرجا پیام کاربر را نمایش می‌دهید (پنل مدیریت یا ایمیل)، آن را escape کنید. پیام کاربر هرگز نباید به‌صورت HTML خام رندر شود.\n\nبا این چهار لایه، درصد بسیار بالایی از ارسال‌های خودکار حذف می‌شود، بدون آنکه کاربر واقعی حتی متوجه یک کپچا شود.",
            ],
            'en' => [
                'title' => 'Securing a contact form against spam and common attacks',
                'slug' => 'securing-contact-forms',
                'excerpt' => 'Server-side validation, a signed token, a honeypot and rate limiting — no third-party service required.',
                'content' => "A contact form is a doorway for untrusted data. The more casually you treat it, the more you pay for it somewhere else.\n\n## Layer one: server-side validation\n\nNever trust the browser. Check length, type and format for every field on the server:\n\n```php\n\$validated = Validator::make(\$request->body(), [\n    'name'    => 'required|string|min:2|max:120',\n    'email'   => 'required|email|max:190',\n    'subject' => 'required|string|min:3|max:200',\n    'message' => 'required|string|min:10|max:5000',\n])->validate();\n```\n\n## Layer two: a honeypot\n\nAdd a hidden field that a real user never sees. If it arrives filled, reject the submission. Free, simple and effective against basic bots.\n\n```html\n<input type=\"text\" name=\"website\" tabindex=\"-1\" autocomplete=\"off\" aria-hidden=\"true\" hidden>\n```\n\n## Layer three: a signed, time-bound token\n\nSign the form with an HMAC token that carries an issue time. Submissions that arrive suspiciously fast (under three seconds) or with an expired token are rejected:\n\n```php\n\$payload = ['form' => 'contact', 'exp' => time() + 7200];\n\$token = \$security->sign(\$payload);\n```\n\n## Layer four: rate limiting\n\nAllow only a handful of submissions per IP or email within a time window. This caps both abuse and server cost:\n\n```php\nRateLimiter::enforce('contact', \$request->ip(), 5, 600);\n```\n\n## Finally: escape on output\n\nWherever you display a user message — admin panel or email — escape it. User content must never be rendered as raw HTML.\n\nTogether these four layers remove a very large share of automated submissions without asking a real visitor to solve a single captcha.",
            ],
        ],
        [
            'category' => 'database',
            'cover' => $blogMedia['performance'],
            'published_at' => '2026-05-08 07:45:00',
            'featured' => 0,
            'tags' => ['mysql', 'performance', 'architecture'],
            'fa' => [
                'title' => 'بهینه‌سازی کوئری‌های MySQL در داشبوردهای مدیریتی',
                'slug' => 'mysql-query-performance-dashboards',
                'excerpt' => 'چرا داشبورد کند می‌شود، چگونه با EXPLAIN مسئله را پیدا کنیم و چه زمانی جدول تجمیعی پاسخ درست است.',
                'content' => "داشبورد مدیریتی معمولاً کند است، نه به‌خاطر رابط کاربری، بلکه به‌خاطر کوئری‌هایی که روی میلیون‌ها ردیف اجرا می‌شوند.\n\n## اول اندازه‌گیری، بعد بهینه‌سازی\n\nهر کوئری کند را با `EXPLAIN` بررسی کنید. سه نشانه کلاسیک:\n\n- `type: ALL` → اسکن کامل جدول\n- `rows: 400000` → تخمین تعداد ردیف خوانده‌شده\n- `Using filesort` یا `Using temporary` → مرتب‌سازی/گروه‌بندی پرهزینه\n\n## ایندکس درست، نه ایندکس زیاد\n\nایندکس باید با ترتیب شرط‌های کوئری هم‌خوان باشد. اگر بیشتر کوئری‌ها این شکل هستند:\n\n```sql\nSELECT ... FROM orders\nWHERE status = 'paid' AND created_at >= '2026-01-01'\nORDER BY created_at DESC\nLIMIT 50;\n```\n\nایندکس `(status, created_at)` از ایندکس تک‌ستونی `created_at` بسیار مؤثرتر است، چون هر دو شرط را پوشش می‌دهد و مرتب‌سازی را هم از بین می‌برد.\n\n## جدول تجمیعی برای گزارش‌های سنگین\n\nوقتی گزارش باید روی چند سال داده اجرا شود، بهتر است شبانه یک جدول تجمیعی پر شود:\n\n```sql\nINSERT INTO sales_daily (day, branch_id, orders, revenue)\nSELECT DATE(created_at), branch_id, COUNT(*), SUM(total)\nFROM orders\nWHERE created_at >= CURDATE() - INTERVAL 1 DAY\nGROUP BY 1, 2;\n```\n\nگزارش ماهانه بعد از این کار روی چند صد ردیف اجرا می‌شود، نه چند میلیون.\n\n## صفحه‌بندی همیشه سمت سرور\n\n`LIMIT/OFFSET` روی جدول بزرگ کند می‌شود. برای فهرست‌های بلند، صفحه‌بندی مبتنی بر کلید (cursor) بهتر است: `WHERE id < :last_id ORDER BY id DESC LIMIT 50`.\n\n## نکته آخر: پرهیز از N+1\n\nاگر برای هر ردیف یک کوئری جدا اجرا می‌کنید، حتی یک صفحه ساده هم به ۱۰۰ کوئری تبدیل می‌شود. داده را با `JOIN` یا `WHERE IN` یک‌جا بیاورید و در کد به هم وصل کنید.",
            ],
            'en' => [
                'title' => 'Tuning MySQL queries in admin dashboards',
                'slug' => 'mysql-query-performance-dashboards',
                'excerpt' => 'Why dashboards get slow, how to find the culprit with EXPLAIN and when an aggregate table is the right answer.',
                'content' => "Admin dashboards are usually slow because of the queries, not the interface.\n\n## Measure first\n\nRun `EXPLAIN` on every slow query. Three classic signals:\n\n- `type: ALL` → full table scan\n- `rows: 400000` → estimated rows read\n- `Using filesort` / `Using temporary` → expensive sorting or grouping\n\n## The right index, not many indexes\n\nAn index has to match the order of your predicates. If most queries look like this:\n\n```sql\nSELECT ... FROM orders\nWHERE status = 'paid' AND created_at >= '2026-01-01'\nORDER BY created_at DESC\nLIMIT 50;\n```\n\nThen `(status, created_at)` dramatically outperforms a single-column index — it covers both predicates and removes the sort.\n\n## Aggregate tables for heavy reports\n\nWhen a report spans years of data, populate a daily aggregate table overnight:\n\n```sql\nINSERT INTO sales_daily (day, branch_id, orders, revenue)\nSELECT DATE(created_at), branch_id, COUNT(*), SUM(total)\nFROM orders\nWHERE created_at >= CURDATE() - INTERVAL 1 DAY\nGROUP BY 1, 2;\n```\n\nThe monthly report then scans a few hundred rows instead of millions.\n\n## Always paginate on the server\n\n`LIMIT/OFFSET` degrades on large tables. For long lists, key-based pagination wins: `WHERE id < :last_id ORDER BY id DESC LIMIT 50`.\n\n## Last but not least: avoid N+1\n\nQuerying inside a loop turns a simple page into a hundred queries. Fetch related data with a `JOIN` or `WHERE IN` and stitch it in code.",
            ],
        ],
        [
            'category' => 'engineering',
            'cover' => $blogMedia['security'],
            'published_at' => '2026-03-19 12:20:00',
            'featured' => 0,
            'tags' => ['architecture', 'php', 'ux'],
            'fa' => [
                'title' => 'چرا هر وب‌سایت شخصی به یک CMS واقعی نیاز دارد',
                'slug' => 'why-personal-sites-need-a-real-cms',
                'excerpt' => 'اگر برای افزودن یک پروژه جدید باید کد را باز کنید، مشکل معماری دارید نه مشکل محتوا.',
                'content' => "وب‌سایت شخصی، اولین محصول یک توسعه‌دهنده است که مشتری بالقوه می‌بیند. اگر برای افزودن یک پروژه یا تغییر متن «درباره من» باید فایل‌ها را دستی ویرایش کنید، عملاً محصولی ساخته‌اید که خودتان هم به‌سختی از آن استفاده می‌کنید.\n\n## محتوا از کد جدا باشد\n\nقاعده ساده است: هر چیزی که ممکن است تغییر کند، نباید در کد باشد. عنوان صفحه، متن خدمات، فهرست مهارت‌ها، لینک‌های شبکه‌های اجتماعی و حتی آیتم‌های منو، همه داده هستند.\n\n## نتیجه: زنجیره‌ای از مزیت‌ها\n\n**سرعت به‌روزرسانی.** افزودن پروژه جدید چند دقیقه است، نه یک انتشار جدید.\n\n**چندزبانه بودن طبیعی.** وقتی محتوا در پایگاه داده با ساختار ترجمه است، نسخه انگلیسی یک بازنویسی نیست؛ یک ردیف دیگر است.\n\n**SEO قابل مدیریت.** عنوان، توضیح و تصویر شبکه‌های اجتماعی را همان‌جا که محتوا را می‌نویسید تنظیم می‌کنید.\n\n**نمایش مهارت واقعی.** یک پنل مدیریت با احراز هویت، نقش‌ها و CRUD کامل، به‌خودی‌خود بخشی از نمونه‌کار شماست.\n\n## هزینه واقعی چیست؟\n\nساخت یک CMS اختصاصی به‌اندازه یک پروژه جدی زمان می‌برد: مدل داده، API، احراز هویت، مدیریت رسانه و رابط پنل. اما این هزینه یک‌بار پرداخت می‌شود، در حالی که ویرایش دستی کد هر بار تکرار می‌شود.\n\nبرای این سایت، همان معماری‌ای استفاده شده که در پروژه‌های مشتریان: لایه‌بندی روشن، پایگاه داده رابطه‌ای، API نسخه‌بندی‌شده و پنل مدیریت مستقل. تفاوت اینجاست که این بار، محصول خودم است.",
            ],
            'en' => [
                'title' => 'Why every personal site deserves a real CMS',
                'slug' => 'why-personal-sites-need-a-real-cms',
                'excerpt' => 'If adding a project means opening the code editor, you have an architecture problem — not a content problem.',
                'content' => "A personal site is a developer's first product that a prospective client actually sees. If adding a project or rewording the about page means editing files by hand, you have built something you yourself would rather not maintain.\n\n## Separate content from code\n\nThe rule is simple: anything that may change should not live in code. Page titles, service copy, skill lists, social links — even menu items — are data.\n\n## The compounding benefits\n\n**Faster updates.** Adding a project takes minutes instead of a release.\n\n**Multilingual by construction.** When content lives in a database with a translation structure, the English version is not a rewrite — it is another row.\n\n**Manageable SEO.** Titles, descriptions and social images are edited where the content is written.\n\n**Proof of real skill.** An authenticated admin panel with roles and full CRUD is itself part of the portfolio.\n\n## What does it cost?\n\nBuilding a bespoke CMS takes as long as any serious project: data model, API, authentication, media handling, interface. But you pay that cost once, whereas editing code manually is a recurring tax.\n\nThis site runs on the same architecture I use for client work: clear layering, a relational database, a versioned API and a separate admin application. The only difference is that this time the product is mine.",
            ],
        ],
        [
            'category' => 'frontend',
            'cover' => $blogMedia['multilingual'],
            'published_at' => '2026-02-11 11:05:00',
            'featured' => 0,
            'tags' => ['react', 'i18n', 'ux'],
            'fa' => [
                'title' => 'طراحی رابط کاربری فارسی: از چیدمان راست‌به‌چپ تا تایپوگرافی',
                'slug' => 'persian-ui-design-rtl',
                'excerpt' => 'قواعد عملی برای ساختن رابط فارسی: ویژگی‌های منطقی CSS، مقیاس فاصله‌ها، اعداد فارسی و انتخاب فونت.',
                'content' => "ساختن رابط فارسی، فقط چرخاندن صفحه به راست نیست. چند تصمیم طراحی وجود دارد که اگر از ابتدا گرفته شوند، نسخه فارسی و انگلیسی هر دو درست دیده می‌شوند.\n\n## از ویژگی‌های منطقی CSS استفاده کنید\n\nبه‌جای `margin-left` و `padding-right` از `margin-inline-start` و `padding-inline-end` استفاده کنید. با تغییر `dir`، چیدمان به‌صورت خودکار آینه می‌شود.\n\n```css\n.card {\n  padding-inline: 24px;\n  border-inline-start: 3px solid var(--accent);\n  text-align: start;\n}\n```\n\n## فونت را جدی بگیرید\n\nمتن فارسی به ارتفاع خط بیشتری نیاز دارد. مقدار `line-height` بین ۱.۸ تا ۲ برای متن اصلی راحت‌تر خوانده می‌شود. برای عنوان‌ها هم فاصله حروف کمتر و اندازه بزرگ‌تر.\n\nیک فونت فارسی با وزن متغیر انتخاب کنید تا نیازی به دانلود چند فایل نباشد. برای این سایت از یک فونت فارسی خودمیزبان استفاده شده که با `font-display: swap` بارگذاری می‌شود.\n\n## اعداد و جهت\n\nاعداد فارسی در متن فارسی طبیعی‌اند، اما در قیمت‌ها، کد و شماره تلفن‌ها گاهی نسخه لاتین خوانا‌تر است. تصمیم بگیرید و در کل سایت یکسان عمل کنید.\n\nنکته مهم: هر عنصر لاتین (نام تکنولوژی، ایمیل، لینک) باید `dir=\"ltr\"` داشته باشد تا در متن راست‌به‌چپ جابه‌جا نشود.\n\n## تست واقعی\n\nیک صفحه را در دو زبان باز کنید و ارتفاع‌ها را مقایسه کنید. اگر نسخه فارسی دو برابر بلندتر شد، مقیاس فاصله‌ها را دوباره تنظیم کنید. تفاوت ارتفاع، هیچ‌وقت دلیل تغییر اندازه فونت نباشد؛ تغییر فاصله خطوط راه درست‌تری است.",
            ],
            'en' => [
                'title' => 'Designing for Persian interfaces: RTL layout and typography',
                'slug' => 'persian-ui-design-rtl',
                'excerpt' => 'Practical rules for building a Persian interface: logical CSS properties, spacing scale, numerals and font choice.',
                'content' => "Building a Persian interface is not just flipping the page. A few decisions, made early, keep both the Persian and English versions correct.\n\n## Use logical CSS properties\n\nReplace `margin-left` and `padding-right` with `margin-inline-start` and `padding-inline-end`. Changing `dir` then mirrors the layout automatically.\n\n```css\n.card {\n  padding-inline: 24px;\n  border-inline-start: 3px solid var(--accent);\n  text-align: start;\n}\n```\n\n## Take typography seriously\n\nPersian text needs more line height. A `line-height` between 1.8 and 2 reads comfortably in body copy, while headings want tighter tracking and a larger size.\n\nChoose a variable-weight Persian font so a single file covers every weight. This site self-hosts a Persian font and loads it with `font-display: swap`.\n\n## Numerals and direction\n\nPersian digits feel natural in Persian prose, but prices, code samples and phone numbers are sometimes clearer in Latin form. Decide once and stay consistent.\n\nImportant detail: every Latin element (technology names, emails, links) should carry `dir=\"ltr\"` so it does not reorder inside right-to-left text.\n\n## Test with real content\n\nOpen a page in both languages and compare heights. If the Persian version is twice as tall, revisit your spacing scale. Height differences should never be fixed by shrinking the font — adjusting line height and spacing is the honest fix.",
            ],
        ],
    ];

    foreach ($posts as $index => $post) {
        $postId = $seeder->translated('blog_posts', 'blog_post_translations', [
            'category_id' => $categoryIds[$post['category']],
            'author_id' => 1,
            'cover_media_id' => $post['cover'],
            'og_image_media_id' => $post['cover'],
            'status' => 'published',
            'published_at' => $post['published_at'],
            'is_featured' => $post['featured'],
            'allow_comments' => 0,
            'views' => random_int(180, 2400),
            'reading_time' => \App\Core\Str::readingTime($post['en']['content']),
            'canonical_url' => null,
            'sort_order' => $index + 1,
        ], [
            'fa' => [
                'title' => $post['fa']['title'],
                'slug' => $post['fa']['slug'],
                'excerpt' => $post['fa']['excerpt'],
                'content' => $post['fa']['content'],
                'seo_title' => $post['fa']['title'] . ' | آرش مهدوی',
                'seo_description' => $post['fa']['excerpt'],
                'keywords' => 'توسعه وب, PHP, MySQL, React, ' . $post['fa']['title'],
            ],
            'en' => [
                'title' => $post['en']['title'],
                'slug' => $post['en']['slug'],
                'excerpt' => $post['en']['excerpt'],
                'content' => $post['en']['content'],
                'seo_title' => $post['en']['title'] . ' | Arash Mahdavi',
                'seo_description' => $post['en']['excerpt'],
                'keywords' => 'web development, PHP, MySQL, React, ' . $post['en']['title'],
            ],
        ], 'post_id');

        foreach ($post['tags'] as $tagKey) {
            Database::table('blog_post_tags')->insert([
                'post_id' => $postId,
                'tag_id' => $tagIds[$tagKey],
                'created_at' => now_utc(),
                'updated_at' => now_utc(),
            ]);
        }

        $seeder->seo('post', $postId, [
            'fa' => ['title' => $post['fa']['seo_title'] ?? $post['fa']['title'], 'description' => $post['fa']['excerpt'], 'keywords' => 'توسعه وب, PHP, MySQL, React'],
            'en' => ['title' => $post['en']['seo_title'] ?? $post['en']['title'], 'description' => $post['en']['excerpt'], 'keywords' => 'web development, PHP, MySQL, React'],
        ]);
    }
};
