<?php

declare(strict_types=1);

use Database\Seeder;

/**
 * Skill categories, skills (kind = skill) and technologies (kind = technology),
 * plus the services catalogue. All of it is managed through
 * Admin → Skills / Technologies / Services.
 */
return static function (Seeder $seeder): void {
    /* --------------------------------------------------------------------- */
    /* Categories                                                            */
    /* --------------------------------------------------------------------- */
    $categories = [
        'frontend' => ['layers', 'Front-end development', 'توسعه فرانت‌اند', 'Interfaces, components and client-side application logic.', 'رابط کاربری، کامپوننت‌ها و منطق سمت کلاینت.'],
        'backend' => ['server', 'Back-end development', 'توسعه بک‌اند', 'Server logic, APIs, authentication and business rules.', 'منطق سرور، API، احراز هویت و قواعد کسب‌وکار.'],
        'database' => ['database', 'Databases', 'پایگاه داده', 'Data modelling, indexing and query optimisation.', 'طراحی مدل داده، ایندکس‌گذاری و بهینه‌سازی کوئری.'],
        'practices' => ['git-branch', 'Engineering practice', 'مهندسی و ابزارها', 'Architecture, security, performance and collaboration tooling.', 'معماری، امنیت، کارایی و ابزارهای همکاری.'],
    ];

    $categoryIds = [];

    foreach ($categories as $key => [$icon, $en, $fa, $enDesc, $faDesc]) {
        $categoryIds[$key] = $seeder->translated('skill_categories', 'skill_category_translations', [
            'key_name' => $key,
            'icon' => $icon,
            'is_active' => 1,
            'sort_order' => array_search($key, array_keys($categories), true) + 1,
        ], [
            'fa' => ['name' => $fa, 'description' => $faDesc],
            'en' => ['name' => $en, 'description' => $enDesc],
        ], 'category_id');
    }

    /* --------------------------------------------------------------------- */
    /* Skills (with proficiency levels, shown as bars)                       */
    /* --------------------------------------------------------------------- */
    $skills = [
        // key, category, icon, level, years, en[name, description], fa[name, description]
        ['php', 'backend', 'php', 95, '9', 'PHP', 'Primary back-end language: application architecture, REST APIs, authentication, payments and background jobs.', 'PHP', 'زبان اصلی بک‌اند: معماری اپلیکیشن، REST API، احراز هویت، پرداخت و پردازش‌های پس‌زمینه.'],
        ['mysql', 'database', 'database', 92, '9', 'MySQL', 'Schema design, normalisation, indexing strategy, query tuning and transactional integrity.', 'MySQL', 'طراحی اسکیمای پایگاه داده، نرمال‌سازی، ایندکس‌گذاری، بهینه‌سازی کوئری و یکپارچگی تراکنشی.'],
        ['react', 'frontend', 'react', 90, '5', 'React.js', 'Component architecture, hooks, context, data fetching patterns and performance profiling.', 'React.js', 'معماری کامپوننتی، هوک‌ها، Context، الگوهای دریافت داده و پروفایلینگ کارایی.'],
        ['javascript', 'frontend', 'javascript', 93, '9', 'JavaScript (ES2023+)', 'Modern JavaScript: modules, async patterns, DOM APIs, accessibility and browser performance.', 'JavaScript', 'جاوااسکریپت مدرن: ماژول‌ها، الگوهای async، DOM، دسترس‌پذیری و کارایی مرورگر.'],
        ['html5', 'frontend', 'html5', 96, '9', 'HTML5 & semantics', 'Semantic markup, accessibility landmarks, forms, SEO structure and structured data.', 'HTML5 و معنایی‌سازی', 'نشانه‌گذاری معنایی، ساختار دسترس‌پذیر، فرم‌ها، ساختار SEO و داده ساختیافته.'],
        ['css3', 'frontend', 'css3', 93, '9', 'CSS3 & responsive design', 'Design tokens, logical properties, grid/flex layouts, RTL support and animation.', 'CSS3 و طراحی واکنش‌گرا', 'توکن‌های طراحی، ویژگی‌های منطقی، Grid/Flex، پشتیبانی RTL و انیمیشن.'],
        ['rest-api', 'backend', 'api', 90, '7', 'REST API design', 'Resource modelling, authentication, versioning, pagination, validation and error contracts.', 'طراحی REST API', 'مدل‌سازی منابع، احراز هویت، نسخه‌بندی، صفحه‌بندی، اعتبارسنجی و قرارداد خطا.'],
        ['security', 'practices', 'shield', 88, '8', 'Web security', 'Prepared statements, XSS/CSRF defences, session hardening, secure uploads and auditing.', 'امنیت وب', 'کوئری آماده، دفاع در برابر XSS و CSRF، ایمن‌سازی نشست، آپلود امن و ممیزی.'],
        ['mvc', 'practices', 'architecture', 90, '8', 'MVC & layered architecture', 'Separation of concerns across routes, services, repositories and presentation layers.', 'معماری MVC و لایه‌بندی', 'جداسازی مسئولیت‌ها میان روت، سرویس، ریپازیتوری و لایه نمایش.'],
        ['git', 'practices', 'git-branch', 90, '9', 'Git & code review', 'Branching strategies, review workflows, conventional commits and release hygiene.', 'Git و بازبینی کد', 'راهبرد شاخه‌بندی، جریان بازبینی، کامیت‌های استاندارد و مدیریت انتشار.'],
        ['performance', 'practices', 'gauge', 87, '6', 'Performance optimisation', 'Query tuning, caching layers, bundle splitting, lazy loading and Core Web Vitals.', 'بهینه‌سازی کارایی', 'تیون کوئری، لایه‌های کش، تقسیم باندل، بارگذاری تنبل و Core Web Vitals.'],
        ['api-integration', 'backend', 'plug', 89, '7', 'API integration', 'Payment gateways, SMS, shipping, CRM/ERP systems, webhooks and third-party auth.', 'یکپارچه‌سازی API', 'درگاه پرداخت، پیامک، حمل‌ونقل، سیستم‌های CRM/ERP، Webhook و ورود سوم‌شخص.'],
    ];

    foreach ($skills as $index => [$key, $category, $icon, $level, $years, $enName, $enDesc, $faName, $faDesc]) {
        $seeder->translated('skills', 'skill_translations', [
            'category_id' => $categoryIds[$category],
            'kind' => 'skill',
            'icon' => $icon,
            'level' => $level,
            'proficiency' => $level >= 92 ? 'expert' : ($level >= 85 ? 'advanced' : 'proficient'),
            'years' => $years,
            'color' => null,
            'is_featured' => $index < 6 ? 1 : 0,
            'is_active' => 1,
            'sort_order' => $index + 1,
        ], [
            'fa' => ['name' => $faName, 'description' => $faDesc],
            'en' => ['name' => $enName, 'description' => $enDesc],
        ], 'skill_id');
    }

    /* --------------------------------------------------------------------- */
    /* Technologies (hero badges, marquee, project tagging)                  */
    /* --------------------------------------------------------------------- */
    $technologies = [
        ['PHP', 'php', 'backend', 1],
        ['React.js', 'react', 'frontend', 1],
        ['JavaScript', 'javascript', 'frontend', 1],
        ['MySQL', 'database', 'database', 1],
        ['HTML5', 'html5', 'frontend', 1],
        ['CSS3', 'css3', 'frontend', 1],
        ['REST API', 'api', 'backend', 1],
        ['Git', 'git-branch', 'practices', 1],
        ['Composer', 'package', 'backend', 0],
        ['Vite', 'vite', 'frontend', 0],
        ['Docker', 'docker', 'practices', 0],
        ['Redis', 'database', 'database', 0],
        ['Nginx', 'server', 'practices', 0],
        ['Linux Server', 'terminal', 'practices', 0],
        ['JSON / AJAX', 'plug', 'frontend', 0],
        ['Node.js', 'node', 'backend', 0],
    ];

    $faNames = [
        'PHP' => 'PHP', 'React.js' => 'React.js', 'JavaScript' => 'جاوااسکریپت', 'MySQL' => 'MySQL',
        'HTML5' => 'HTML5', 'CSS3' => 'CSS3', 'REST API' => 'REST API', 'Git' => 'Git',
        'Composer' => 'Composer', 'Vite' => 'Vite', 'Docker' => 'Docker', 'Redis' => 'Redis',
        'Nginx' => 'Nginx', 'Linux Server' => 'سرور لینوکس', 'JSON / AJAX' => 'JSON و AJAX', 'Node.js' => 'Node.js',
    ];

    foreach ($technologies as $index => [$name, $icon, $category, $featured]) {
        $seeder->translated('skills', 'skill_translations', [
            'category_id' => $categoryIds[$category],
            'kind' => 'technology',
            'icon' => $icon,
            'level' => 0,
            'proficiency' => null,
            'years' => null,
            'is_featured' => $featured,
            'is_active' => 1,
            'sort_order' => $index + 1,
        ], [
            'fa' => ['name' => $faNames[$name] ?? $name, 'description' => null],
            'en' => ['name' => $name, 'description' => null],
        ], 'skill_id');
    }

    /* --------------------------------------------------------------------- */
    /* Services                                                              */
    /* --------------------------------------------------------------------- */
    $services = [
        [
            'icon' => 'browser', 'accent' => 'mint', 'featured' => 1,
            'en' => [
                'title' => 'Website development',
                'slug' => 'website-development',
                'summary' => 'Modern, fast and responsive websites built around your business requirements — corporate sites, landing pages and product sites.',
                'description' => "A website is a business tool, not a brochure. I build sites that load fast, read well on every screen and are easy for your team to update through a real admin panel.\n\nEvery build ships with semantic HTML, structured data, clean URLs, bilingual support, an SEO foundation and a content management panel your marketing team can actually use.",
                'features' => [
                    'Responsive layouts for desktop, tablet and mobile',
                    'Content managed through a custom admin panel',
                    'SEO foundation: metadata, sitemap, structured data',
                    'Performance budget: optimised images and lazy loading',
                    'Bilingual (fa/en) with RTL support out of the box',
                ],
            ],
            'fa' => [
                'title' => 'طراحی و توسعه وب‌سایت',
                'slug' => 'website-development',
                'summary' => 'وب‌سایت‌های مدرن، سریع و واکنش‌گرا بر اساس نیاز واقعی کسب‌وکار؛ از سایت شرکتی تا لندینگ محصول.',
                'description' => "وب‌سایت یک ابزار کسب‌وکار است، نه یک بروشور. سایت‌هایی می‌سازم که سریع بارگذاری شوند، روی همه اندازه‌ها درست دیده شوند و تیم شما بتواند محتوایشان را از یک پنل مدیریت واقعی به‌روز کند.\n\nهر پروژه با HTML معنایی، داده ساختیافته، آدرس تمیز، پشتیبانی دوزبانه، زیرساخت SEO و پنل مدیریت محتوا تحویل داده می‌شود.",
                'features' => [
                    'چیدمان واکنش‌گرا برای دسکتاپ، تبلت و موبایل',
                    'مدیریت محتوا از طریق پنل اختصاصی',
                    'زیرساخت SEO: متادیتا، سایت‌مپ و داده ساختیافته',
                    'بودجه کارایی: تصاویر بهینه و بارگذاری تنبل',
                    'دوزبانه (فارسی/انگلیسی) با پشتیبانی کامل RTL',
                ],
            ],
        ],
        [
            'icon' => 'webapp', 'accent' => 'indigo', 'featured' => 1,
            'en' => [
                'title' => 'Web application development',
                'slug' => 'web-applications',
                'summary' => 'Custom web applications with a solid back-end, interactive React interfaces and role-based access control.',
                'description' => "When a spreadsheet is no longer enough, you need an application. I model the actual process, design the data layer, expose it through a REST API and build an interface that makes daily work faster.\n\nTypical applications: booking platforms, learning management, internal portals, customer panels and workflow tools.",
                'features' => [
                    'REST API with versioned endpoints and validation',
                    'React interface with real-time feedback and states',
                    'Role and permission management',
                    'Background jobs, notifications and audit trails',
                    'Automated tests around critical business rules',
                ],
            ],
            'fa' => [
                'title' => 'توسعه اپلیکیشن تحت وب',
                'slug' => 'web-applications',
                'summary' => 'اپلیکیشن‌های وب اختصاصی با بک‌اند قدرتمند، رابط تعاملی React و کنترل دسترسی نقش‌محور.',
                'description' => "وقتی اکسل دیگر پاسخگو نیست، به یک اپلیکیشن نیاز دارید. فرایند واقعی را مدل می‌کنم، لایه داده را طراحی می‌کنم، از طریق REST API در اختیار رابط کاربری قرار می‌دهم و رابطی می‌سازم که کار روزمره را سریع‌تر کند.\n\nنمونه‌ها: سامانه رزرو، مدیریت آموزش، پورتال داخلی سازمان، پنل مشتریان و ابزارهای گردش کار.",
                'features' => [
                    'REST API با نسخه‌بندی و اعتبارسنجی کامل',
                    'رابط React با بازخورد لحظه‌ای و حالت‌های مختلف',
                    'مدیریت نقش‌ها و سطوح دسترسی',
                    'پردازش‌های پس‌زمینه، اعلان‌ها و لاگ ممیزی',
                    'تست خودکار برای قواعد کلیدی کسب‌وکار',
                ],
            ],
        ],
        [
            'icon' => 'cart', 'accent' => 'amber', 'featured' => 1,
            'en' => [
                'title' => 'E-commerce development',
                'slug' => 'ecommerce',
                'summary' => 'Complete online stores: catalogue, variants, cart, checkout, payments, shipping and order management.',
                'description' => "An online store has to sell and it has to be operated. I build the whole pipeline: product and stock management, discount rules, payment gateway integration, delivery methods, invoices and the order dashboard your team works in every day.",
                'features' => [
                    'Product catalogue with variants, stock and pricing rules',
                    'Cart, checkout and multiple payment gateways',
                    'Order lifecycle: payment, fulfilment, returns',
                    'Coupons, shipping zones and invoicing',
                    'Sales reporting and customer management',
                ],
            ],
            'fa' => [
                'title' => 'راه‌اندازی فروشگاه اینترنتی',
                'slug' => 'ecommerce',
                'summary' => 'فروشگاه اینترنتی کامل: کاتالوگ، تنوع محصول، سبد خرید، پرداخت، ارسال و مدیریت سفارش.',
                'description' => "فروشگاه اینترنتی هم باید بفروشد و هم باید قابل اداره باشد. کل زنجیره را می‌سازم: مدیریت محصول و موجودی، قواعد تخفیف، اتصال به درگاه پرداخت، روش‌های ارسال، فاکتور و داشبورد سفارش‌هایی که تیم شما هر روز با آن کار می‌کند.",
                'features' => [
                    'کاتالوگ محصول با تنوع، موجودی و قواعد قیمت‌گذاری',
                    'سبد خرید، فرایند پرداخت و درگاه‌های متعدد',
                    'چرخه سفارش: پرداخت، آماده‌سازی، مرجوعی',
                    'کد تخفیف، مناطق ارسال و صدور فاکتور',
                    'گزارش فروش و مدیریت مشتریان',
                ],
            ],
        ],
        [
            'icon' => 'gears', 'accent' => 'mint', 'featured' => 1,
            'en' => [
                'title' => 'Custom software development',
                'slug' => 'custom-software',
                'summary' => 'Business-specific software modelled on your real operational process — not on a generic template.',
                'description' => "Off-the-shelf tools force your process into their shape. Custom software does the opposite: it encodes the way your organisation already works, then removes the bottlenecks.\n\nI start with a discovery session, map the data and the people involved, then deliver in iterations so you can use the software while it is still growing.",
                'features' => [
                    'Requirement discovery and process mapping',
                    'Data model designed for your reporting needs',
                    'Iterative delivery with usable milestones',
                    'Integration with existing systems and spreadsheets',
                    'Documentation and team handover',
                ],
            ],
            'fa' => [
                'title' => 'توسعه نرم‌افزار سفارشی',
                'slug' => 'custom-software',
                'summary' => 'نرم‌افزار متناسب با فرایند واقعی سازمان شما؛ نه یک قالب آماده که کار شما را محدود کند.',
                'description' => "ابزارهای آماده، فرایند شما را به شکل خودشان در می‌آورند. نرم‌افزار سفارشی برعکس عمل می‌کند: همان روشی را که سازمان شما کار می‌کند کدنویسی می‌کند و گلوگاه‌ها را برمی‌دارد.\n\nکار با جلسه کشف نیاز شروع می‌شود، داده و نقش‌ها نقشه‌برداری می‌شوند و تحویل به‌صورت مرحله‌ای انجام می‌شود تا از همان ابتدا قابل استفاده باشد.",
                'features' => [
                    'کشف نیاز و نقشه‌برداری فرایند',
                    'مدل داده طراحی‌شده بر اساس نیاز گزارش‌گیری',
                    'تحویل مرحله‌ای با نقاط تحویل قابل استفاده',
                    'اتصال به سیستم‌های موجود و فایل‌های اکسل',
                    'مستندسازی و آموزش تیم',
                ],
            ],
        ],
        [
            'icon' => 'dashboard', 'accent' => 'indigo', 'featured' => 1,
            'en' => [
                'title' => 'Admin dashboard development',
                'slug' => 'admin-dashboards',
                'summary' => 'Secure management dashboards with role-based access, reporting and clean data tables.',
                'description' => "The admin panel is where your team spends its day, so it deserves real design work. I build dashboards with fast filtering, bulk actions, export, background processing and clear permission boundaries — all protected by hardened authentication.",
                'features' => [
                    'Role-based access with fine-grained permissions',
                    'Data tables: filter, sort, paginate, bulk actions',
                    'Charts and KPI widgets driven by real queries',
                    'Export to Excel/CSV and printable reports',
                    'Security: session hardening, CSRF, audit log',
                ],
            ],
            'fa' => [
                'title' => 'طراحی داشبورد مدیریتی',
                'slug' => 'admin-dashboards',
                'summary' => 'داشبوردهای مدیریتی امن با دسترسی نقش‌محور، گزارش‌گیری و جدول‌های داده سریع.',
                'description' => "پنل مدیریت جایی است که تیم شما تمام روز در آن کار می‌کند؛ پس شایسته طراحی جدی است. داشبوردهایی می‌سازم با فیلتر سریع، عملیات گروهی، خروجی گرفتن، پردازش پس‌زمینه و مرزهای دسترسی شفاف — همراه با احراز هویت سخت‌گیرانه.",
                'features' => [
                    'دسترسی نقش‌محور با مجوزهای دقیق',
                    'جدول داده: فیلتر، مرتب‌سازی، صفحه‌بندی، عملیات گروهی',
                    'نمودار و ویجت‌های شاخص بر پایه کوئری واقعی',
                    'خروجی اکسل/CSV و گزارش قابل چاپ',
                    'امنیت: ایمن‌سازی نشست، CSRF و لاگ ممیزی',
                ],
            ],
        ],
        [
            'icon' => 'plug', 'accent' => 'amber', 'featured' => 0,
            'en' => [
                'title' => 'API development & integration',
                'slug' => 'api-integration',
                'summary' => 'REST APIs and integrations that connect your product to payments, messaging, shipping and ERP systems.',
                'description' => "Systems rarely live alone. I design APIs that other teams can consume (documented, versioned, with a predictable error contract) and integrate third-party services: payment gateways, SMS providers, shipping, accounting and CRM/ERP platforms.",
                'features' => [
                    'Documented REST endpoints with versioning',
                    'Token and session based authentication',
                    'Webhooks with retry, idempotency and logging',
                    'Third-party integrations (payment, SMS, shipping, CRM)',
                    'Rate limiting and monitoring hooks',
                ],
            ],
            'fa' => [
                'title' => 'توسعه و یکپارچه‌سازی API',
                'slug' => 'api-integration',
                'summary' => 'API های REST و یکپارچه‌سازی با درگاه پرداخت، پیامک، حمل‌ونقل و سیستم‌های ERP.',
                'description' => "سیستم‌ها به‌ندرت تنها کار می‌کنند. API هایی طراحی می‌کنم که تیم‌های دیگر بتوانند از آن‌ها استفاده کنند (مستند، نسخه‌بندی‌شده و با قرارداد خطای مشخص) و سرویس‌های بیرونی را متصل می‌کنم: درگاه پرداخت، پیامک، حمل‌ونقل، حسابداری و CRM/ERP.",
                'features' => [
                    'اندپوینت‌های REST مستند و نسخه‌بندی‌شده',
                    'احراز هویت توکنی و نشستی',
                    'وب‌هوک با تلاش مجدد، idempotency و لاگ',
                    'یکپارچه‌سازی با سرویس‌های بیرونی',
                    'محدودسازی نرخ درخواست و پایش',
                ],
            ],
        ],
        [
            'icon' => 'wrench', 'accent' => 'mint', 'featured' => 0,
            'en' => [
                'title' => 'Maintenance & continuous development',
                'slug' => 'maintenance',
                'summary' => 'Updates, bug fixes, performance work and new features for software that is already running.',
                'description' => "Software is never finished. I take over existing codebases, make them safe and testable, then keep improving them: dependency updates, security patches, performance tuning, UX fixes and new features delivered in small, verifiable steps.",
                'features' => [
                    'Code review and technical debt assessment',
                    'Security patches and dependency upgrades',
                    'Performance profiling and database tuning',
                    'Feature development in short iterations',
                    'Monitoring, backups and recovery plans',
                ],
            ],
            'fa' => [
                'title' => 'پشتیبانی و توسعه مستمر',
                'slug' => 'maintenance',
                'summary' => 'به‌روزرسانی، رفع باگ، بهینه‌سازی و افزودن قابلیت‌های جدید به نرم‌افزاری که در حال کار است.',
                'description' => "نرم‌افزار هرگز تمام‌شده نیست. پروژه‌های موجود را تحویل می‌گیرم، ایمن و تست‌پذیر می‌کنم و به‌تدریج بهترشان می‌کنم: به‌روزرسانی وابستگی‌ها، وصله‌های امنیتی، بهینه‌سازی کارایی، اصلاح تجربه کاربری و افزودن قابلیت‌های جدید در گام‌های کوچک و قابل راستی‌آزمایی.",
                'features' => [
                    'بازبینی کد و ارزیابی بدهی فنی',
                    'وصله امنیتی و به‌روزرسانی وابستگی‌ها',
                    'پروفایلینگ کارایی و تنظیم پایگاه داده',
                    'توسعه قابلیت‌های جدید در چرخه‌های کوتاه',
                    'پایش، پشتیبان‌گیری و برنامه بازیابی',
                ],
            ],
        ],
    ];

    foreach ($services as $index => $service) {
        $seeder->translated('services', 'service_translations', [
            'icon' => $service['icon'],
            'accent' => $service['accent'],
            'is_featured' => $service['featured'],
            'is_active' => 1,
            'sort_order' => $index + 1,
        ], [
            'fa' => [
                'title' => $service['fa']['title'],
                'slug' => $service['fa']['slug'],
                'summary' => $service['fa']['summary'],
                'description' => $service['fa']['description'],
                'features_json' => $service['fa']['features'],
            ],
            'en' => [
                'title' => $service['en']['title'],
                'slug' => $service['en']['slug'],
                'summary' => $service['en']['summary'],
                'description' => $service['en']['description'],
                'features_json' => $service['en']['features'],
            ],
        ], 'service_id');
    }
};
