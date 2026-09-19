<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Seeder;

/**
 * Pages and the section builder content (homepage + about + skills pages).
 * Everything here is editable in Admin → Homepage / About / Skills.
 */
return static function (Seeder $seeder): void {
    /* --------------------------------------------------------------------- */
    /* Statistics used by the counters strip                                 */
    /* --------------------------------------------------------------------- */
    $seeder->setting('stat_projects', 'statistics', 'int', '64');
    $seeder->setting('stat_clients', 'statistics', 'int', '41');
    $seeder->setting('stat_satisfaction', 'statistics', 'int', '98');
    $seeder->setting('stat_years', 'statistics', 'int', '9');

    /* --------------------------------------------------------------------- */
    /* Pages                                                                 */
    /* --------------------------------------------------------------------- */
    $pages = [
        'home' => [
            'template' => 'home', 'icon' => 'home', 'sort' => 1,
            'fa' => ['خانه', 'توسعه‌دهنده فول‌استک وب', 'وب‌سایت، اپلیکیشن و نرم‌افزار سفارشی که برای رشد کسب‌وکار شما ساخته می‌شود.', 'آرش مهدوی | توسعه‌دهنده فول‌استک وب', 'ساخت وب‌سایت مدرن، اپلیکیشن تحت وب، فروشگاه اینترنتی و نرم‌افزار سفارشی با PHP، MySQL، React و JavaScript.'],
            'en' => ['Home', 'Full-stack developer', 'Websites, web applications and custom software built to move your business forward.', 'Arash Mahdavi — Full-Stack Web Developer', 'Modern websites, web applications, e-commerce platforms and custom software built with PHP, MySQL, React and JavaScript.'],
        ],
        'about' => [
            'template' => 'about', 'icon' => 'user', 'sort' => 2,
            'fa' => ['درباره من', 'توسعه‌دهنده‌ای که محصول می‌سازد', 'از تحلیل نیاز کسب‌وکار تا تحویل نسخه نهایی: معماری، پیاده‌سازی، تست و استقرار.', 'درباره آرش مهدوی — توسعه‌دهنده فول‌استک', 'آشنایی با مسیر حرفه‌ای، فلسفه توسعه و مهارت‌های فنی آرش مهدوی، توسعه‌دهنده فول‌استک.'],
            'en' => ['About', 'A developer who ships products', 'From business requirement to production release: architecture, implementation, testing and deployment.', 'About Arash Mahdavi — Full-Stack Developer', 'Career path, development philosophy and technical skills of Arash Mahdavi, full-stack web developer.'],
        ],
        'skills' => [
            'template' => 'skills', 'icon' => 'layers', 'sort' => 3,
            'fa' => ['مهارت‌ها', 'ابزارها و تکنولوژی‌ها', 'مجموعه‌ای از تکنولوژی‌هایی که هر روز با آن‌ها محصول واقعی می‌سازم.', 'مهارت‌ها و تکنولوژی‌ها | آرش مهدوی', 'مهارت‌های فول‌استک: PHP، React، JavaScript، MySQL، HTML5، CSS3، REST API، امنیت و معماری MVC.'],
            'en' => ['Skills', 'Tools and technologies', 'The stack I use daily to design, build and ship real products.', 'Skills & Technologies | Arash Mahdavi', 'Full-stack skills: PHP, React, JavaScript, MySQL, HTML5, CSS3, REST APIs, security and MVC architecture.'],
        ],
        'services' => [
            'template' => 'services', 'icon' => 'grid', 'sort' => 4,
            'fa' => ['خدمات', 'خدماتی که ارائه می‌دهم', 'از وب‌سایت شرکتی تا نرم‌افزار اختصاصی کسب‌وکار و فروشگاه اینترنتی کامل.', 'خدمات توسعه وب و نرم‌افزار', 'طراحی وب‌سایت، توسعه اپلیکیشن وب، فروشگاه اینترنتی، نرم‌افزار سفارشی و داشبورد مدیریتی.'],
            'en' => ['Services', 'What I can build for you', 'From corporate websites to business-specific software and complete online stores.', 'Web & software development services', 'Website development, web applications, e-commerce platforms, custom software and admin dashboards.'],
        ],
        'projects' => [
            'template' => 'projects', 'icon' => 'briefcase', 'sort' => 5,
            'fa' => ['پروژه‌ها', 'نمونه‌کارهای واقعی', 'پروژه‌هایی که از صفر طراحی، پیاده‌سازی و به بهره‌برداری رسیده‌اند.', 'پروژه‌ها و نمونه‌کارها | آرش مهدوی', 'نمونه‌کارهای فول‌استک: فروشگاه اینترنتی، سامانه مدیریت کسب‌وکار، داشبورد تحلیل، پلتفرم آموزش و سیستم‌های سفارشی.'],
            'en' => ['Projects', 'Selected work', 'Real projects designed, built and shipped end to end.', 'Projects & portfolio | Arash Mahdavi', 'Full-stack case studies: e-commerce platforms, business management systems, analytics dashboards, learning platforms and custom software.'],
        ],
        'experience' => [
            'template' => 'experience', 'icon' => 'timeline', 'sort' => 6,
            'fa' => ['تجربه کاری', 'مسیر حرفه‌ای', 'همکاری با تیم‌های محصول، استودیوهای وب و کارفرمایان بین‌المللی.', 'تجربه کاری و تحصیلات | آرش مهدوی', 'سابقه همکاری حرفه‌ای، مسئولیت‌ها، دستاوردها، تحصیلات و گواهی‌نامه‌ها.'],
            'en' => ['Experience', 'Professional timeline', 'Working with product teams, web studios and international clients.', 'Experience & education | Arash Mahdavi', 'Professional history, responsibilities, achievements, education and certifications.'],
        ],
        'blog' => [
            'template' => 'blog', 'icon' => 'file-text', 'sort' => 7,
            'fa' => ['وبلاگ', 'یادداشت‌های فنی', 'تجربه‌های واقعی از معماری، امنیت، کارایی و توسعه محصول.', 'مقالات فنی درباره PHP، React و MySQL', 'مقالات و آموزش‌های فنی درباره معماری نرم‌افزار، امنیت، کارایی پایگاه داده و توسعه رابط کاربری.'],
            'en' => ['Blog', 'Engineering notes', 'Practical writing about architecture, security, performance and product development.', 'Articles on PHP, React and MySQL', 'Technical articles about software architecture, security, database performance and front-end engineering.'],
        ],
        'contact' => [
            'template' => 'contact', 'icon' => 'mail', 'sort' => 8,
            'fa' => ['تماس با من', 'شروع یک همکاری', 'پروژه، ایده یا فرصت شغلی دارید؟ بنویسید تا در کمتر از ۲۴ ساعت پاسخ بگیرید.', 'تماس با آرش مهدوی', 'فرم تماس، ایمیل، تلفن و شبکه‌های اجتماعی برای شروع همکاری در پروژه وب.'],
            'en' => ['Contact', 'Let’s start something', 'Have a project, an idea or a role in mind? Write to me and get a reply within 24 hours.', 'Contact Arash Mahdavi', 'Contact form, email, phone and social profiles to start working on your web project.'],
        ],
        'resume' => [
            'template' => 'resume', 'icon' => 'download', 'sort' => 9,
            'fa' => ['رزومه', 'رزومه کامل حرفه‌ای', 'خلاصه حرفه‌ای، مهارت‌ها، تجربه‌ها، پروژه‌ها، تحصیلات و گواهی‌نامه‌ها در یک صفحه.', 'رزومه آرش مهدوی — دانلود PDF', 'رزومه حرفه‌ای توسعه‌دهنده فول‌استک؛ قابل مشاهده آنلاین و دانلود در قالب PDF.'],
            'en' => ['Résumé', 'Complete professional CV', 'Summary, skills, experience, projects, education and certifications on one page.', 'Résumé — Arash Mahdavi (PDF)', 'Professional CV of a full-stack developer: available online and as a downloadable PDF.'],
        ],
    ];

    $pageIds = [];

    foreach ($pages as $key => $definition) {
        $pageIds[$key] = $seeder->translated('pages', 'page_translations', [
            'key_name' => $key,
            'template' => $definition['template'],
            'icon' => $definition['icon'],
            'is_active' => 1,
            'sort_order' => $definition['sort'],
        ], [
            'fa' => [
                'title' => $definition['fa'][0],
                'subtitle' => $definition['fa'][1],
                'content' => $definition['fa'][2],
                'seo_title' => $definition['fa'][3],
                'seo_description' => $definition['fa'][4],
            ],
            'en' => [
                'title' => $definition['en'][0],
                'subtitle' => $definition['en'][1],
                'content' => $definition['en'][2],
                'seo_title' => $definition['en'][3],
                'seo_description' => $definition['en'][4],
            ],
        ], 'page_id');
    }

    /* --------------------------------------------------------------------- */
    /* Homepage sections                                                     */
    /* --------------------------------------------------------------------- */
    $sections = [
        [
            'key' => 'hero', 'type' => 'hero', 'sort' => 1,
            'settings' => ['layout' => 'split', 'show_terminal' => true, 'show_stats' => true, 'align' => 'start'],
            'fa' => [
                'eyebrow' => 'توسعه‌دهنده فول‌استک وب',
                'title' => 'وب‌سایت و نرم‌افزارهایی می‌سازم که کسب‌وکار شما را جلو می‌برند.',
                'subtitle' => 'از فروشگاه اینترنتی و سامانه مدیریت کسب‌وکار تا داشبورد مدیریتی و API؛ محصول کامل را از رابط کاربری تا پایگاه داده طراحی و پیاده‌سازی می‌کنم.',
                'cta_label' => 'مشاهده پروژه‌ها', 'cta_url' => '/projects',
                'cta2_label' => 'دانلود رزومه', 'cta2_url' => '/resume',
                'items' => [
                    ['value' => 'PHP و MySQL'],
                    ['value' => 'React و JavaScript'],
                    ['value' => 'فروشگاه اینترنتی'],
                    ['value' => 'داشبورد مدیریتی'],
                    ['value' => 'API و یکپارچه‌سازی'],
                ],
            ],
            'en' => [
                'eyebrow' => 'Full-Stack Web Developer',
                'title' => 'I build web products that move your business forward.',
                'subtitle' => 'From e-commerce and business management systems to admin dashboards and APIs — I design and ship the complete product, from interface to database.',
                'cta_label' => 'View my projects', 'cta_url' => '/projects',
                'cta2_label' => 'Download résumé', 'cta2_url' => '/resume',
                'items' => [
                    ['value' => 'PHP & MySQL'],
                    ['value' => 'React & JavaScript'],
                    ['value' => 'E-commerce'],
                    ['value' => 'Admin dashboards'],
                    ['value' => 'APIs & integrations'],
                ],
            ],
        ],
        [
            'key' => 'stats', 'type' => 'stats', 'sort' => 2,
            'settings' => ['source' => 'statistics'],
            'fa' => [
                'eyebrow' => 'در یک نگاه',
                'title' => 'تجربه‌ای که به محصول قابل اتکا تبدیل می‌شود',
                'subtitle' => null,
                'items' => [
                    ['key' => 'stat_years', 'label' => 'سال تجربه حرفه‌ای'],
                    ['key' => 'stat_projects', 'label' => 'پروژه تحویل‌شده'],
                    ['key' => 'stat_clients', 'label' => 'کارفرمای راضی'],
                    ['key' => 'stat_satisfaction', 'label' => 'درصد رضایت', 'suffix' => '٪'],
                ],
            ],
            'en' => [
                'eyebrow' => 'At a glance',
                'title' => 'Experience that turns into dependable products',
                'subtitle' => null,
                'items' => [
                    ['key' => 'stat_years', 'label' => 'Years of professional experience'],
                    ['key' => 'stat_projects', 'label' => 'Projects delivered'],
                    ['key' => 'stat_clients', 'label' => 'Happy clients'],
                    ['key' => 'stat_satisfaction', 'label' => 'Satisfaction rate', 'suffix' => '%'],
                ],
            ],
        ],
        [
            'key' => 'about', 'type' => 'about', 'sort' => 3,
            'settings' => ['show_photo' => true, 'show_strengths' => true],
            'fa' => [
                'eyebrow' => 'درباره من',
                'title' => 'از نیاز کسب‌وکار تا نرم‌افزار کارآمد',
                'subtitle' => 'بیش از ۹ سال است که وب‌سایت، اپلیکیشن و نرم‌افزارهای سفارشی می‌سازم؛ با تمرکز بر کدی که قابل نگهداری، امن و سریع باشد.',
                'body' => "کارم را با طراحی و پیاده‌سازی سامانه‌های داخلی شرکت‌ها شروع کردم: انبار، حسابداری، سفارش‌ها و گزارش‌ها. همان تجربه باعث شد یاد بگیرم نرم‌افزار خوب نرم‌افزاری است که فرایند واقعی کسب‌وکار را ساده کند، نه اینکه پیچیده‌ترش کند.\n\nامروز پروژه‌ها را به‌صورت کامل می‌برم جلو: تحلیل نیاز، معماری پایگاه داده، طراحی API، پیاده‌سازی رابط کاربری با React، پنل مدیریت، تست، بهینه‌سازی و استقرار. برای من تحویل پروژه پایان کار نیست؛ پشتیبانی و توسعه مرحله‌به‌مرحله بخشی از کار است.",
                'cta_label' => 'بیشتر درباره من', 'cta_url' => '/about',
                'items' => [
                    ['title' => 'توسعه فول‌استک', 'body' => 'رابط کاربری، منطق سرور، پایگاه داده و استقرار — همه در یک تیم یک‌نفره منسجم.'],
                    ['title' => 'نرم‌افزار کسب‌وکار', 'body' => 'سامانه‌های سفارشی بر اساس فرایند واقعی سازمان، با گزارش‌گیری و کنترل دسترسی.'],
                    ['title' => 'تجارت الکترونیک', 'body' => 'فروشگاه اینترنتی کامل: محصولات، سبد خرید، پرداخت، سفارش‌ها و پنل مدیریت.'],
                    ['title' => 'کارایی و امنیت', 'body' => 'کوئری‌های بهینه، اعتبارسنجی سمت سرور و محافظت در برابر حمله‌های رایج وب.'],
                ],
            ],
            'en' => [
                'eyebrow' => 'About me',
                'title' => 'From business requirement to software that works',
                'subtitle' => 'For more than 9 years I have been building websites, web applications and custom software — with a focus on code that stays maintainable, secure and fast.',
                'body' => "I started out building internal tools for companies: inventory, invoicing, orders and reporting. That experience taught me that good software simplifies a real business process instead of complicating it.\n\nToday I take projects from start to finish: requirement analysis, database architecture, API design, the React interface, the admin panel, testing, performance work and deployment. Shipping is not the end of the project — support and iteration are part of the job.",
                'cta_label' => 'More about me', 'cta_url' => '/about',
                'items' => [
                    ['title' => 'Full-stack delivery', 'body' => 'Interface, server logic, database and deployment — one coherent, accountable developer.'],
                    ['title' => 'Business software', 'body' => 'Custom systems modelled on real operational processes, with reporting and role-based access.'],
                    ['title' => 'E-commerce', 'body' => 'Complete online stores: catalogue, cart, payments, order pipeline and management panel.'],
                    ['title' => 'Performance & security', 'body' => 'Optimised queries, server-side validation and protection against the common web threats.'],
                ],
            ],
        ],
        [
            'key' => 'skills', 'type' => 'skills', 'sort' => 4,
            'settings' => ['limit' => 12, 'group_by_category' => true, 'show_levels' => true],
            'fa' => [
                'eyebrow' => 'مهارت‌ها و تکنولوژی‌ها',
                'title' => 'استکی که هر روز با آن کار می‌کنم',
                'subtitle' => 'ابزارها بر اساس نوع پروژه انتخاب می‌شوند؛ این‌ها تکنولوژی‌هایی هستند که در تولید واقعی از آن‌ها استفاده کرده‌ام.',
                'cta_label' => 'همه مهارت‌ها', 'cta_url' => '/skills',
            ],
            'en' => [
                'eyebrow' => 'Skills & technologies',
                'title' => 'The stack I work with every day',
                'subtitle' => 'Tools are chosen per project — these are the technologies I have used in production.',
                'cta_label' => 'All skills', 'cta_url' => '/skills',
            ],
        ],
        [
            'key' => 'services', 'type' => 'services', 'sort' => 5,
            'settings' => ['limit' => 6, 'show_features' => true],
            'fa' => [
                'eyebrow' => 'خدمات',
                'title' => 'چه چیزی می‌توانم برای شما بسازم؟',
                'subtitle' => 'از یک وب‌سایت سریع تا سامانه نرم‌افزاری اختصاصی؛ پروژه را بر اساس نیاز واقعی کسب‌وکار طراحی می‌کنم.',
                'cta_label' => 'جزئیات خدمات', 'cta_url' => '/services',
            ],
            'en' => [
                'eyebrow' => 'Services',
                'title' => 'What can I build for you?',
                'subtitle' => 'From a fast marketing website to a bespoke business system — each project is designed around the real requirement.',
                'cta_label' => 'Service details', 'cta_url' => '/services',
            ],
        ],
        [
            'key' => 'projects', 'type' => 'projects', 'sort' => 6,
            'settings' => ['limit' => 6, 'featured_only' => true, 'layout' => 'grid'],
            'fa' => [
                'eyebrow' => 'پروژه‌ها',
                'title' => 'کارهایی که واقعاً به بهره‌برداری رسیده‌اند',
                'subtitle' => 'هر پروژه با هدف مشخص، معماری مستند و نتیجه قابل اندازه‌گیری تحویل داده شده است.',
                'cta_label' => 'همه پروژه‌ها', 'cta_url' => '/projects',
            ],
            'en' => [
                'eyebrow' => 'Projects',
                'title' => 'Work that actually shipped',
                'subtitle' => 'Every project has a defined goal, documented architecture and a measurable outcome.',
                'cta_label' => 'All projects', 'cta_url' => '/projects',
            ],
        ],
        [
            'key' => 'experience', 'type' => 'experience', 'sort' => 7,
            'settings' => ['limit' => 4],
            'fa' => [
                'eyebrow' => 'تجربه کاری',
                'title' => 'مسیر حرفه‌ای من',
                'subtitle' => 'همکاری با تیم‌های محصول، شرکت‌های فناوری و کارفرمایان بین‌المللی.',
                'cta_label' => 'جزئیات کامل', 'cta_url' => '/experience',
            ],
            'en' => [
                'eyebrow' => 'Experience',
                'title' => 'My professional timeline',
                'subtitle' => 'Working with product teams, technology companies and international clients.',
                'cta_label' => 'Full timeline', 'cta_url' => '/experience',
            ],
        ],
        [
            'key' => 'testimonials', 'type' => 'testimonials', 'sort' => 8,
            'settings' => ['limit' => 6, 'autoplay' => true],
            'fa' => [
                'eyebrow' => 'نظر مشتریان',
                'title' => 'کارفرمایان چه می‌گویند',
                'subtitle' => 'چند نظر از مدیران محصول و صاحبان کسب‌وکارهایی که با آن‌ها همکاری کرده‌ام.',
            ],
            'en' => [
                'eyebrow' => 'Testimonials',
                'title' => 'What clients say',
                'subtitle' => 'A few words from product managers and business owners I have worked with.',
            ],
        ],
        [
            'key' => 'blog', 'type' => 'blog', 'sort' => 9,
            'settings' => ['limit' => 3, 'order' => 'latest'],
            'fa' => [
                'eyebrow' => 'وبلاگ',
                'title' => 'یادداشت‌های فنی',
                'subtitle' => 'آنچه در پروژه‌های واقعی یاد می‌گیرم: معماری، امنیت، کارایی و تجربه توسعه.',
                'cta_label' => 'همه مقالات', 'cta_url' => '/blog',
            ],
            'en' => [
                'eyebrow' => 'Blog',
                'title' => 'Engineering notes',
                'subtitle' => 'What real projects teach me: architecture, security, performance and developer experience.',
                'cta_label' => 'All articles', 'cta_url' => '/blog',
            ],
        ],
        [
            'key' => 'cta', 'type' => 'cta', 'sort' => 10,
            'settings' => ['variant' => 'accent'],
            'fa' => [
                'eyebrow' => 'همکاری',
                'title' => 'پروژه‌ای در ذهن دارید؟',
                'subtitle' => 'نیازتان را توضیح دهید تا با پیشنهاد فنی و زمان‌بندی واقع‌بینانه پاسخ بدهم.',
                'cta_label' => 'شروع گفتگو', 'cta_url' => '/contact',
                'cta2_label' => 'ارسال ایمیل', 'cta2_url' => 'mailto:hello@arashmahdavi.dev',
            ],
            'en' => [
                'eyebrow' => 'Let’s work together',
                'title' => 'Have a project in mind?',
                'subtitle' => 'Describe what you need and you will get a technical proposal with a realistic timeline.',
                'cta_label' => 'Start a conversation', 'cta_url' => '/contact',
                'cta2_label' => 'Send an email', 'cta2_url' => 'mailto:hello@arashmahdavi.dev',
            ],
        ],
    ];

    foreach ($sections as $definition) {
        $seeder->translated('sections', 'section_translations', [
            'page_id' => $pageIds['home'],
            'key_name' => $definition['key'],
            'type_name' => $definition['type'],
            'is_active' => 1,
            'sort_order' => $definition['sort'],
            'settings_json' => $definition['settings'],
        ], [
            'fa' => [
                'eyebrow' => $definition['fa']['eyebrow'] ?? null,
                'title' => $definition['fa']['title'] ?? null,
                'subtitle' => $definition['fa']['subtitle'] ?? null,
                'body' => $definition['fa']['body'] ?? null,
                'cta_label' => $definition['fa']['cta_label'] ?? null,
                'cta_url' => $definition['fa']['cta_url'] ?? null,
                'cta2_label' => $definition['fa']['cta2_label'] ?? null,
                'cta2_url' => $definition['fa']['cta2_url'] ?? null,
                'items_json' => $definition['fa']['items'] ?? null,
            ],
            'en' => [
                'eyebrow' => $definition['en']['eyebrow'] ?? null,
                'title' => $definition['en']['title'] ?? null,
                'subtitle' => $definition['en']['subtitle'] ?? null,
                'body' => $definition['en']['body'] ?? null,
                'cta_label' => $definition['en']['cta_label'] ?? null,
                'cta_url' => $definition['en']['cta_url'] ?? null,
                'cta2_label' => $definition['en']['cta2_label'] ?? null,
                'cta2_url' => $definition['en']['cta2_url'] ?? null,
                'items_json' => $definition['en']['items'] ?? null,
            ],
        ], 'section_id');
    }

    /* --------------------------------------------------------------------- */
    /* About page sections                                                   */
    /* --------------------------------------------------------------------- */
    $aboutSections = [
        [
            'key' => 'story', 'type' => 'about', 'sort' => 1,
            'settings' => ['show_photo' => true],
            'body_fa' => "سال ۱۳۹۳ با ساخت یک سامانه انبارداری ساده برای یک شرکت بازرگانی وارد دنیای نرم‌افزار شدم. آن پروژه به من یاد داد که نوشتن کد فقط بخشی از کار است؛ درک فرایند کسب‌وکار، بخش مهم‌تر آن است.\n\nاز آن زمان تا امروز، بیش از ۶۰ پروژه در حوزه‌های فروشگاه اینترنتی، سامانه‌های مدیریتی، پلتفرم‌های آموزشی و داشبوردهای تحلیلی تحویل داده‌ام. تمرکز من روی معماری تمیز، پایگاه داده منظم و رابط کاربری‌ای است که کار با آن خسته‌کننده نباشد.\n\nبا تیم‌های محصول به‌صورت دورکاری همکاری می‌کنم، در جلسات کشف نیاز شرکت می‌کنم و مستندسازی فنی را جزو تحویل‌دادنی‌های هر پروژه می‌دانم.",
            'body_en' => "I entered software development in 2014 by building a small inventory system for a trading company. That project taught me that writing code is only part of the job — understanding the business process matters more.\n\nSince then I have delivered more than 60 projects across e-commerce, management systems, learning platforms and analytics dashboards. My focus is clean architecture, a well-modelled database and an interface that is genuinely pleasant to work with.\n\nI collaborate remotely with product teams, take part in requirement discovery and treat technical documentation as a deliverable, not an afterthought.",
            'fa' => ['مسیر من', 'نُه سال ساخت محصول واقعی', 'از یک سامانه انبارداری ساده تا پلتفرم‌های تجاری چندزبانه.'],
            'en' => ['My path', 'Nine years of shipping real products', 'From a simple inventory system to multi-language commercial platforms.'],
            'items_fa' => [
                ['title' => 'تحلیل و مشاوره فنی', 'body' => 'بررسی نیاز، انتخاب معماری، برآورد واقع‌بینانه و مستندسازی تصمیم‌ها.'],
                ['title' => 'معماری پایگاه داده', 'body' => 'مدل داده نرمال‌شده، ایندکس‌گذاری هدفمند و کوئری‌های بهینه.'],
                ['title' => 'توسعه فول‌استک', 'body' => 'PHP و MySQL در سرور، React و JavaScript در مرورگر؛ یکپارچه و تست‌شده.'],
                ['title' => 'تحویل و پشتیبانی', 'body' => 'استقرار، آموزش پنل مدیریت و توسعه مرحله‌ای پس از تحویل.'],
            ],
            'items_en' => [
                ['title' => 'Discovery & consulting', 'body' => 'Requirement analysis, architecture choice, realistic estimates and documented decisions.'],
                ['title' => 'Database design', 'body' => 'Normalised data models, purposeful indexing and optimised queries.'],
                ['title' => 'Full-stack development', 'body' => 'PHP and MySQL on the server, React and JavaScript in the browser, integrated and tested.'],
                ['title' => 'Delivery & support', 'body' => 'Deployment, admin training and iterative development after launch.'],
            ],
        ],
        [
            'key' => 'philosophy', 'type' => 'custom', 'sort' => 2,
            'settings' => ['variant' => 'principles'],
            'fa' => ['فلسفه توسعه', 'چطور کار می‌کنم', 'اصولی که در همه پروژه‌ها یکسان است.'],
            'en' => ['Development philosophy', 'How I work', 'The principles that stay the same across every project.'],
            'items_fa' => [
                ['title' => 'اول سادگی', 'body' => 'ساده‌ترین راه‌حلی که نیاز را برطرف کند، معمولاً بهترین راه‌حل است.'],
                ['title' => 'امنیت از ابتدا', 'body' => 'اعتبارسنجی، Escaping و کنترل دسترسی در همان روز اول نوشته می‌شوند.'],
                ['title' => 'کارایی قابل اندازه‌گیری', 'body' => 'هر بهینه‌سازی با اندازه‌گیری قبل و بعد همراه است، نه با حدس.'],
                ['title' => 'کد خوانا برای انسان', 'body' => 'نام‌گذاری روشن، توابع کوچک و مرز روشن بین لایه‌ها.'],
            ],
            'items_en' => [
                ['title' => 'Simplicity first', 'body' => 'The simplest solution that satisfies the requirement is usually the right one.'],
                ['title' => 'Security by default', 'body' => 'Validation, escaping and access control are written on day one, not bolted on later.'],
                ['title' => 'Measurable performance', 'body' => 'Every optimisation comes with a before/after measurement, never a guess.'],
                ['title' => 'Human-readable code', 'body' => 'Clear naming, small functions and a crisp boundary between layers.'],
            ],
        ],
    ];

    foreach ($aboutSections as $definition) {
        $seeder->translated('sections', 'section_translations', [
            'page_id' => $pageIds['about'],
            'key_name' => $definition['key'],
            'type_name' => $definition['type'],
            'is_active' => 1,
            'sort_order' => $definition['sort'],
            'settings_json' => $definition['settings'],
        ], [
            'fa' => [
                'eyebrow' => $definition['fa'][0],
                'title' => $definition['fa'][1],
                'subtitle' => $definition['fa'][2],
                'body' => $definition['body_fa'] ?? null,
                'items_json' => $definition['items_fa'],
            ],
            'en' => [
                'eyebrow' => $definition['en'][0],
                'title' => $definition['en'][1],
                'subtitle' => $definition['en'][2],
                'body' => $definition['body_en'] ?? null,
                'items_json' => $definition['items_en'],
            ],
        ], 'section_id');
    }
};
