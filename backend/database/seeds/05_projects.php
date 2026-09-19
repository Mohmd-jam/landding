<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Seeder;

/**
 * Project categories and eight full case studies (bilingual), including
 * galleries, technology links and SEO copy. Managed in Admin → Projects.
 */
return static function (Seeder $seeder): void {
    /* --------------------------------------------------------------------- */
    /* Media used by the projects                                             */
    /* --------------------------------------------------------------------- */
    $media = [
        'digimarket' => $seeder->media('seed/projects/digimarket.jpg', 'projects', ['en' => 'DigiMarket storefront and admin panel', 'fa' => 'فروشگاه دیجی‌مارکت و پنل مدیریت']),
        'hesabyar' => $seeder->media('seed/projects/hesabyar.jpg', 'projects', ['en' => 'Hesabyar business management suite', 'fa' => 'سامانه مدیریت کسب‌وکار حسابیار']),
        'analytics' => $seeder->media('seed/projects/analytics.jpg', 'projects', ['en' => 'SalesPulse analytics dashboard', 'fa' => 'داشبورد تحلیلی SalesPulse']),
        'academy' => $seeder->media('seed/projects/academy.jpg', 'projects', ['en' => 'Maharat online academy platform', 'fa' => 'پلتفرم آموزش آنلاین مهارت']),
        'clinic' => $seeder->media('seed/projects/clinic.jpg', 'projects', ['en' => 'Clinic management system', 'fa' => 'سامانه مدیریت کلینیک']),
        'corporate' => $seeder->media('seed/projects/corporate.jpg', 'projects', ['en' => 'SanatMahr corporate website', 'fa' => 'وب‌سایت شرکتی صنعت‌مهر']),
        'paylink' => $seeder->media('seed/projects/paylink.jpg', 'projects', ['en' => 'PayLink payment integration platform', 'fa' => 'پلتفرم اتصال درگاه پرداخت پی‌لینک']),
        'taskflow' => $seeder->media('seed/projects/taskflow.jpg', 'projects', ['en' => 'TaskFlow project management application', 'fa' => 'اپلیکیشن مدیریت پروژه تسک‌فلو']),
        'detailDashboard' => $seeder->media('seed/projects/detail-dashboard.jpg', 'projects', ['en' => 'Reporting dashboard with KPI widgets', 'fa' => 'داشبورد گزارش‌گیری با ویجت‌های شاخص']),
        'detailMobile' => $seeder->media('seed/projects/detail-mobile.jpg', 'projects', ['en' => 'Responsive mobile layout', 'fa' => 'چیدمان واکنش‌گرای موبایل']),
        'detailCode' => $seeder->media('seed/projects/detail-code.jpg', 'projects', ['en' => 'Backend service layer', 'fa' => 'لایه سرویس بک‌اند']),
    ];

    /* --------------------------------------------------------------------- */
    /* Categories                                                            */
    /* --------------------------------------------------------------------- */
    $categories = [
        'ecommerce' => ['cart', '#ffb020', 'E-commerce', 'فروشگاه اینترنتی', 'Online stores, payments and order management.', 'فروشگاه‌های اینترنتی، پرداخت و مدیریت سفارش.'],
        'web-applications' => ['webapp', '#3ddc97', 'Web applications', 'اپلیکیشن‌های وب', 'Product platforms with accounts, roles and workflows.', 'پلتفرم‌های محصولی با حساب کاربری، نقش و گردش کار.'],
        'business-software' => ['gears', '#6c8cff', 'Business software', 'نرم‌افزار کسب‌وکار', 'ERP, accounting, inventory and operations systems.', 'سیستم‌های ERP، حسابداری، انبار و عملیات.'],
        'dashboards' => ['dashboard', '#4cc9f0', 'Dashboards', 'داشبورد مدیریتی', 'Management panels, analytics and reporting.', 'پنل‌های مدیریتی، تحلیل داده و گزارش‌گیری.'],
        'custom-systems' => ['settings', '#f472b6', 'Custom systems', 'سیستم‌های سفارشی', 'Industry-specific software built from scratch.', 'نرم‌افزارهای تخصصی صنعت که از صفر ساخته شده‌اند.'],
        'websites' => ['browser', '#a3e635', 'Websites', 'وب‌سایت‌ها', 'Corporate, marketing and content-driven websites.', 'وب‌سایت‌های شرکتی، بازاریابی و محتوامحور.'],
    ];

    $categoryIds = [];

    foreach ($categories as $key => [$icon, $color, $en, $fa, $enDesc, $faDesc]) {
        $categoryIds[$key] = $seeder->translated('project_categories', 'project_category_translations', [
            'icon' => $icon,
            'color' => $color,
            'is_active' => 1,
            'sort_order' => array_search($key, array_keys($categories), true) + 1,
        ], [
            'fa' => ['name' => $fa, 'slug' => $key, 'description' => $faDesc],
            'en' => ['name' => $en, 'slug' => $key, 'description' => $enDesc],
        ], 'category_id');
    }

    /* --------------------------------------------------------------------- */
    /* Projects                                                              */
    /* --------------------------------------------------------------------- */
    $projects = [
        [
            'key' => 'digimarket',
            'category' => 'ecommerce',
            'client' => 'DigiMarket Group',
            'project_url' => 'https://example.com/digimarket',
            'github_url' => 'https://github.com/arashmahdavi/digimarket',
            'cover' => $media['digimarket'],
            'stack' => 'PHP · MySQL · React · REST API · Payment gateway',
            'started_at' => '2024-02-01',
            'completed_at' => '2024-09-15',
            'featured' => 1,
            'gallery' => [
                [$media['digimarket'], 'Storefront'],
                [$media['detailDashboard'], 'Order management'],
                [$media['detailMobile'], 'Mobile checkout'],
            ],
            'technologies' => ['PHP', 'MySQL', 'React.js', 'JavaScript', 'REST API', 'HTML5', 'CSS3'],
            'fa' => [
                'title' => 'فروشگاه اینترنتی دیجی‌مارکت',
                'slug' => 'digimarket-ecommerce',
                'summary' => 'فروشگاه اینترنتی کامل با کاتالوگ چنددسته‌ای، سبد خرید، درگاه پرداخت، مدیریت انبار و داشبورد سفارش‌ها.',
                'description' => "دیجی‌مارکت یک فروشگاه اینترنتی با بیش از ۴٬۰۰۰ کالا و ۱۲ دسته‌بندی است که از فروش دستی در شبکه‌های اجتماعی به یک پلتفرم فروش متمرکز تبدیل شد.\n\nمن مسئولیت کل پروژه را بر عهده داشتم: مدل‌سازی داده کاتالوگ (محصول، تنوع، قیمت، موجودی)، طراحی API، پیاده‌سازی رابط کاربری با React، اتصال درگاه پرداخت، سامانه ارسال و داشبورد مدیریت سفارش.",
                'challenge' => "تیم فروش روزانه با فایل‌های اکسل کار می‌کرد و موجودی کالا در چند نقطه مختلف ثبت می‌شد؛ نتیجه‌اش فروش کالای ناموجود و گزارش‌های متناقض بود. همچنین در ساعات پرترافیک، فهرست محصولات کند بارگذاری می‌شد.",
                'solution' => "کاتالوگ را روی یک مدل داده نرمال‌شده بازسازی کردم (محصول → تنوع → انبار → قیمت) و ایندکس‌های ترکیبی برای جست‌وجو و فیلتر اضافه کردم. فهرست محصولات با صفحه‌بندی سرور و کش لایه‌ای سرو می‌شود و اتصال انبار به سفارش‌ها به‌صورت تراکنشی انجام می‌گیرد تا موجودی هرگز منفی نشود.",
                'results' => "زمان بارگذاری فهرست محصولات از ۳.۴ ثانیه به ۴۲۰ میلی‌ثانیه رسید، خطای فروش کالای ناموجود به صفر رسید و تیم فروش مدیریت سفارش‌ها را به‌طور کامل از پنل انجام می‌دهد.",
                'seo_title' => 'فروشگاه اینترنتی دیجی‌مارکت — نمونه‌کار فول‌استک',
                'seo_description' => 'طراحی و پیاده‌سازی فروشگاه اینترنتی دیجی‌مارکت با PHP، MySQL و React؛ شامل کاتالوگ، درگاه پرداخت و داشبورد سفارش.',
            ],
            'en' => [
                'title' => 'DigiMarket e-commerce platform',
                'slug' => 'digimarket-ecommerce',
                'summary' => 'A complete online store with a multi-category catalogue, cart, payment gateway, inventory management and order dashboard.',
                'description' => "DigiMarket is an online store with more than 4,000 products across 12 categories that replaced manual selling over social media with one centralised sales platform.\n\nI owned the whole project: catalogue data modelling (product, variant, price, stock), API design, the React storefront, payment gateway integration, shipping and the order management dashboard.",
                'challenge' => "The sales team worked from spreadsheets and stock was recorded in several places, which caused overselling and contradictory reports. During peak hours the product listing was also too slow.",
                'solution' => "I rebuilt the catalogue on a normalised data model (product → variant → stock → price) and added composite indexes for search and filtering. Listings are served with server-side pagination and layered caching, while stock updates happen inside the same transaction as order creation so inventory can never go negative.",
                'results' => "Product listing load time dropped from 3.4s to 420ms, overselling was eliminated and the sales team now runs the entire order pipeline from the admin panel.",
                'seo_title' => 'DigiMarket e-commerce platform — full-stack case study',
                'seo_description' => 'Case study: building a complete online store with PHP, MySQL and React — catalogue, payments, inventory and order management.',
            ],
        ],
        [
            'key' => 'hesabyar',
            'category' => 'business-software',
            'client' => 'Hesabyar Accounting',
            'project_url' => 'https://example.com/hesabyar',
            'github_url' => null,
            'cover' => $media['hesabyar'],
            'stack' => 'PHP · MySQL · React · Charts · RBAC',
            'started_at' => '2023-03-01',
            'completed_at' => '2023-12-20',
            'featured' => 1,
            'gallery' => [
                [$media['hesabyar'], 'Finance overview'],
                [$media['detailDashboard'], 'Ledger reporting'],
                [$media['detailCode'], 'Service layer'],
            ],
            'technologies' => ['PHP', 'MySQL', 'React.js', 'JavaScript', 'REST API', 'CSS3'],
            'fa' => [
                'title' => 'سامانه جامع حسابیار',
                'slug' => 'hesabyar-business-suite',
                'summary' => 'سامانه یکپارچه مالی و عملیاتی: فاکتور فروش، خرید، انبار، خزانه، گزارش سود و زیان و دسترسی نقش‌محور.',
                'description' => "حسابیار نرم‌افزار مالی یک شرکت بازرگانی با سه شعبه است. پیش از این پروژه، فاکتورها در اکسل صادر و اسناد انبار دستی ثبت می‌شد.\n\nسامانه شامل ماژول‌های فروش، خرید، انبار، خزانه و گزارش‌های مالی است، با گردش تأیید اسناد، مهر زمانی و دسترسی تفکیک‌شده برای حسابدار، انباردار و مدیرعامل.",
                'challenge' => "بیش از ۳۰٬۰۰۰ سند مالی گذشته باید به‌درستی مهاجرت می‌کرد، بدون آنکه مانده حساب‌ها به‌هم بخورد. همچنین گزارش‌های مدیریتی باید روی داده‌های چندساله در چند ثانیه اجرا می‌شد.",
                'solution' => "مهاجرت داده را با ابزار خط فرمان، اعتبارسنجی سطر‌به‌سطر و کنترل جمع‌های ترازنامه انجام دادم. برای گزارش‌ها از جدول‌های تجمیعی ماهانه و نماهای آماده (VIEW) استفاده شد تا گزارش‌های سنگین به کوئری‌های سبک تبدیل شوند.",
                'results' => "بستن حساب‌های پایان ماه از سه روز به چند ساعت کاهش یافت، تراز اسناد پس از مهاجرت بدون اختلاف تأیید شد و همه گزارش‌ها زیر یک ثانیه پاسخ می‌دهند.",
                'seo_title' => 'سامانه حسابداری و مدیریت کسب‌وکار حسابیار',
                'seo_description' => 'طراحی سامانه یکپارچه مالی، انبار و فروش با PHP و MySQL؛ مهاجرت داده، دسترسی نقش‌محور و گزارش‌های تحلیلی سریع.',
            ],
            'en' => [
                'title' => 'Hesabyar business management suite',
                'slug' => 'hesabyar-business-suite',
                'summary' => 'An integrated finance and operations system: sales and purchase invoices, inventory, treasury, P&L reporting and role-based access.',
                'description' => "Hesabyar is the finance system of a trading company with three branches. Before this project, invoices were issued in spreadsheets and warehouse documents were recorded by hand.\n\nThe suite covers sales, purchasing, inventory, treasury and financial reporting, with document approval flows, timestamps and separated access for accountants, warehouse staff and management.",
                'challenge' => "More than 30,000 historic financial documents had to migrate without breaking a single account balance, and management reports had to run over multiple years of data in seconds.",
                'solution' => "I migrated the data with a CLI tool, row-level validation and balance-sheet reconciliation checks. Reporting uses monthly aggregate tables and database views so heavy reports become light queries.",
                'results' => "Month-end closing dropped from three days to a few hours, the migrated ledger reconciled with zero difference and every report responds in under a second.",
                'seo_title' => 'Hesabyar business management suite — full-stack case study',
                'seo_description' => 'Building an integrated finance, inventory and sales system with PHP and MySQL: data migration, RBAC and fast analytics.',
            ],
        ],
        [
            'key' => 'salespulse',
            'category' => 'dashboards',
            'client' => 'Pulse Retail',
            'project_url' => null,
            'github_url' => 'https://github.com/arashmahdavi/salespulse',
            'cover' => $media['analytics'],
            'stack' => 'PHP · MySQL · React · SVG charts',
            'started_at' => '2023-06-01',
            'completed_at' => '2023-10-10',
            'featured' => 1,
            'gallery' => [
                [$media['analytics'], 'KPI overview'],
                [$media['detailDashboard'], 'Cohort analysis'],
                [$media['detailMobile'], 'Mobile KPI view'],
            ],
            'technologies' => ['PHP', 'MySQL', 'React.js', 'JavaScript', 'CSS3'],
            'fa' => [
                'title' => 'داشبورد تحلیلی SalesPulse',
                'slug' => 'salespulse-analytics-dashboard',
                'summary' => 'داشبورد تحلیل فروش با نمودارهای تعاملی، شاخص‌های کلیدی، مقایسه دوره‌ای و خروجی گرفتن از گزارش‌ها.',
                'description' => "SalesPulse برای یک مجموعه خرده‌فروشی با چند شعبه ساخته شد تا مدیران به‌جای گزارش‌های دستی، تصویر لحظه‌ای فروش را ببینند.\n\nنمودارها بدون کتابخانه سنگین و به‌صورت SVG سفارشی پیاده‌سازی شده‌اند تا حجم جاوااسکریپت پایین بماند و روی موبایل هم روان کار کند.",
                'challenge' => "داده‌های فروش در سه سیستم مختلف بود و گزارش‌گیری ماهانه چند روز طول می‌کشد. نسخه اول داشبورد با کتابخانه نمودار سنگین روی موبایل کند بود.",
                'solution' => "لایه تجمیع داده طراحی کردم که فروش هر شب از سیستم‌ها به جداول تحلیلی منتقل می‌شود. نمودارها با SVG و بدون وابستگی خارجی ساخته شدند و کوئری‌ها روی جدول‌های تجمیعی با ایندکس تاریخ و شعبه اجرا می‌شوند.",
                'results' => "زمان تهیه گزارش ماهانه از چند روز به چند ثانیه رسید، حجم باندل بین ۶۰ تا ۷۰ درصد کاهش یافت و داشبورد روی موبایل نیز بدون کندی کار می‌کند.",
                'seo_title' => 'داشبورد تحلیلی فروش SalesPulse — نمونه‌کار',
                'seo_description' => 'پیاده‌سازی داشبورد تحلیل فروش با React، PHP و MySQL؛ نمودارهای SVG سبک، تجمیع داده شبانه و گزارش‌های تعاملی.',
            ],
            'en' => [
                'title' => 'SalesPulse analytics dashboard',
                'slug' => 'salespulse-analytics-dashboard',
                'summary' => 'A sales analytics dashboard with interactive charts, KPI widgets, period comparisons and exportable reports.',
                'description' => "SalesPulse was built for a multi-branch retail group so managers could see the live sales picture instead of waiting for manual reports.\n\nCharts are implemented as custom SVG instead of a heavy charting library, keeping the JavaScript payload small and the interface smooth on mobile.",
                'challenge' => "Sales data lived in three different systems and monthly reporting took days. The first dashboard iteration, built on a large charting library, was sluggish on mobile devices.",
                'solution' => "I designed a data aggregation layer that moves each night's sales into analytical tables. Charts are hand-built SVG with no external dependency, and queries run against aggregate tables indexed by date and branch.",
                'results' => "Monthly reporting went from days to seconds, the dashboard bundle shrank by roughly 65% and the interface stays smooth on mobile.",
                'seo_title' => 'SalesPulse analytics dashboard — full-stack case study',
                'seo_description' => 'Building a sales analytics dashboard with React, PHP and MySQL: lightweight SVG charts, nightly aggregation and interactive reports.',
            ],
        ],
        [
            'key' => 'academy',
            'category' => 'web-applications',
            'client' => 'Maharat Academy',
            'project_url' => 'https://example.com/academy',
            'github_url' => null,
            'cover' => $media['academy'],
            'stack' => 'PHP · MySQL · React · Video streaming · Subscriptions',
            'started_at' => '2022-05-01',
            'completed_at' => '2023-02-28',
            'featured' => 1,
            'gallery' => [
                [$media['academy'], 'Course catalogue'],
                [$media['detailMobile'], 'Lesson player'],
                [$media['detailDashboard'], 'Instructor dashboard'],
            ],
            'technologies' => ['PHP', 'MySQL', 'React.js', 'JavaScript', 'REST API', 'HTML5'],
            'fa' => [
                'title' => 'پلتفرم آموزش آنلاین مهارت',
                'slug' => 'maharat-learning-platform',
                'summary' => 'پلتفرم آموزش آنلاین با فروش دوره، پخش ویدیو، آزمون، پیشرفت دانشجو و پنل مدرس.',
                'description' => "آکادمی مهارت بیش از ۱۲۰ دوره ویدیویی دارد. پلتفرم شامل فروش دوره با اشتراک و خرید تک‌دوره، پخش ویدیو با کنترل دسترسی، آزمون‌های چندگزینه‌ای، گواهی پایان دوره و پنل مدرس برای مدیریت درس‌ها است.",
                'challenge' => "محتوای ویدیویی باید در برابر دانلود غیرمجاز و اشتراک لینک محافظت می‌شد، و پخش باید روی اینترنت متوسط ایران هم بدون وقفه کار می‌کرد.",
                'solution' => "آدرس پخش ویدیو با توکن موقت و اعتبارسنجی سرور تولید می‌شود، پخش تکه‌تکه (HLS) و کیفیت‌های چندگانه فعال شد و دسترسی هر درس بر اساس ثبت‌نام کاربر بررسی می‌شود.",
                'results' => "نرخ تکمیل دوره ۳۴٪ افزایش یافت، نرخ رهاشدن پخش ویدیو به‌شکل محسوسی کم شد و مدرسان بدون نیاز به تیم فنی محتوای خود را منتشر می‌کنند.",
                'seo_title' => 'پلتفرم آموزش آنلاین مهارت — نمونه‌کار',
                'seo_description' => 'طراحی پلتفرم آموزش آنلاین با PHP، MySQL و React؛ پخش ویدیو محافظت‌شده، اشتراک، آزمون و پنل مدرس.',
            ],
            'en' => [
                'title' => 'Maharat online learning platform',
                'slug' => 'maharat-learning-platform',
                'summary' => 'An online learning platform with course sales, protected video streaming, quizzes, student progress and an instructor panel.',
                'description' => "Maharat Academy hosts more than 120 video courses. The platform covers subscription and single-course sales, token-protected video delivery, multiple-choice assessments, completion certificates and an instructor panel for managing lessons.",
                'challenge' => "Video content needed protection against unauthorised downloads and link sharing, and playback had to remain smooth on average connection speeds.",
                'solution' => "Playback URLs are generated with short-lived tokens validated on the server, streaming uses segmented HLS with multiple quality levels and every lesson access is authorised against the user's enrolment.",
                'results' => "Course completion rose by 34%, video drop-off fell significantly and instructors publish their own content without technical help.",
                'seo_title' => 'Maharat online learning platform — full-stack case study',
                'seo_description' => 'Building an online learning platform with PHP, MySQL and React: protected streaming, subscriptions, assessments and instructor tools.',
            ],
        ],
        [
            'key' => 'clinic',
            'category' => 'custom-systems',
            'client' => 'Mehr Clinic',
            'project_url' => null,
            'github_url' => null,
            'cover' => $media['clinic'],
            'stack' => 'PHP · MySQL · React · SMS · Scheduling',
            'started_at' => '2022-01-10',
            'completed_at' => '2022-07-30',
            'featured' => 0,
            'gallery' => [
                [$media['clinic'], 'Appointments'],
                [$media['detailMobile'], 'Patient record (mobile)'],
            ],
            'technologies' => ['PHP', 'MySQL', 'React.js', 'JavaScript', 'REST API'],
            'fa' => [
                'title' => 'سامانه مدیریت کلینیک مهر',
                'slug' => 'mehr-clinic-system',
                'summary' => 'نوبت‌دهی، پرونده الکترونیک بیمار، داروخانه، صورتحساب و یادآوری پیامکی برای یک کلینیک چندمتخصصی.',
                'description' => "کلینیک مهر روزانه بیش از ۱۵۰ بیمار را پذیرش می‌کند. سامانه جدید پذیرش، نوبت‌دهی، پرونده بیمار، نسخه، صورتحساب و بیمه را یکپارچه کرد.\n\nمنشی‌ها از پنل تحت وب استفاده می‌کنند، پزشکان پرونده و تاریخچه بیمار را می‌بینند و بیماران پیامک یادآوری نوبت دریافت می‌کنند.",
                'challenge' => "تداخل نوبت‌ها، نبود تاریخچه منظم بیمار و کاغذبازی بیمه، زمان انتظار بیماران را طولانی کرده بود. سیستم نباید در ساعات پرمراجعه کند می‌شد.",
                'solution' => "برای نوبت‌دهی از کنترل قفل‌گذاری روی بازه‌های زمانی استفاده شد تا دو منشی نتوانند یک اسلات را رزرو کنند. پرونده بیمار با ساختار نسخه‌پذیر طراحی شد و اتصال به سامانه پیامک برای یادآوری خودکار انجام گرفت.",
                'results' => "میانگین زمان پذیرش هر بیمار ۴۰٪ کاهش یافت، تداخل نوبت‌ها به صفر رسید و نرخ عدم‌حضور با یادآوری پیامکی ۲۷٪ کم شد.",
                'seo_title' => 'سامانه مدیریت کلینیک — نرم‌افزار سفارشی',
                'seo_description' => 'طراحی نرم‌افزار سفارشی کلینیک: نوبت‌دهی بدون تداخل، پرونده الکترونیک، صورتحساب و یادآوری پیامکی.',
            ],
            'en' => [
                'title' => 'Mehr clinic management system',
                'slug' => 'mehr-clinic-system',
                'summary' => 'Appointments, electronic patient records, pharmacy, billing and SMS reminders for a multi-specialist clinic.',
                'description' => "Mehr Clinic receives more than 150 patients a day. The new system unified reception, scheduling, patient records, prescriptions, billing and insurance.\n\nReceptionists work in the web panel, doctors see full patient history and patients receive SMS appointment reminders.",
                'challenge' => "Double-booked appointments, unstructured patient history and paper-based insurance claims had stretched waiting times. The system also had to stay responsive during peak hours.",
                'solution' => "Appointment slots use locking so two receptionists cannot book the same slot. Patient records were modelled with a versioned structure and an SMS gateway integration handles automatic reminders.",
                'results' => "Average reception time fell by 40%, appointment collisions reached zero and missed appointments dropped 27% thanks to reminders.",
                'seo_title' => 'Clinic management system — custom software case study',
                'seo_description' => 'Designing custom clinic software: conflict-free scheduling, electronic records, billing and SMS reminders.',
            ],
        ],
        [
            'key' => 'sanatmahr',
            'category' => 'websites',
            'client' => 'SanatMahr Industrial Group',
            'project_url' => 'https://example.com/sanatmahr',
            'github_url' => null,
            'cover' => $media['corporate'],
            'stack' => 'PHP · MySQL · React · SEO · Multilingual',
            'started_at' => '2024-01-05',
            'completed_at' => '2024-03-22',
            'featured' => 0,
            'gallery' => [
                [$media['corporate'], 'Homepage'],
                [$media['detailMobile'], 'Mobile navigation'],
            ],
            'technologies' => ['PHP', 'MySQL', 'React.js', 'HTML5', 'CSS3', 'JavaScript'],
            'fa' => [
                'title' => 'وب‌سایت شرکتی صنعت‌مهر',
                'slug' => 'sanatmahr-corporate-website',
                'summary' => 'وب‌سایت دوزبانه شرکت صنعتی با کاتالوگ محصولات، اخبار، فرم درخواست همکاری و پنل مدیریت محتوا.',
                'description' => "صنعت‌مهر برای حضور بین‌المللی به وب‌سایتی دوزبانه نیاز داشت که بتواند محصولات، گواهی‌نامه‌ها و اخبار شرکت را بدون دخالت برنامه‌نویس به‌روز کند.\n\nساختار اطلاعاتی بر پایه دسته‌بندی محصولات و بازارهای هدف طراحی شد و پنل مدیریت امکان مدیریت هر بخش را فراهم می‌کند.",
                'challenge' => "نسخه قبلی سایت با HTML ثابت ساخته شده بود؛ هر تغییر کوچک نیازمند برنامه‌نویس بود و نسخه انگلیسی به‌روز نگه داشته نمی‌شد.",
                'solution' => "سایت را روی معماری محتوامحور با دوزبانه‌سازی از پایه بازنویسی کردم. هر بخش (محصول، اخبار، گواهی‌نامه) مدل داده اختصاصی دارد و متادیتای SEO و نقشه سایت به‌صورت خودکار تولید می‌شود.",
                'results' => "تیم بازاریابی امروز همه محتوای دو زبان را خودش مدیریت می‌کند، سرعت سایت از ۵.۶ ثانیه به ۱.۲ ثانیه رسید و تعداد صفحات ایندکس‌شده در گوگل دو برابر شد.",
                'seo_title' => 'وب‌سایت شرکتی صنعت‌مهر — طراحی دوزبانه',
                'seo_description' => 'طراحی وب‌سایت شرکتی دوزبانه با PHP و React؛ کاتالوگ محصولات، اخبار، فرم همکاری و پنل مدیریت محتوا.',
            ],
            'en' => [
                'title' => 'SanatMahr corporate website',
                'slug' => 'sanatmahr-corporate-website',
                'summary' => 'A bilingual industrial corporate site with a product catalogue, news, partnership forms and a content management panel.',
                'description' => "SanatMahr needed a bilingual web presence for international clients and a way to update products, certifications and news without a developer.\n\nInformation architecture was built around product families and target markets, and the admin panel gives the team full control of every section.",
                'challenge' => "The previous site was static HTML: every small change required a developer and the English version was constantly out of date.",
                'solution' => "I rebuilt the site on a content-driven architecture with bilingual support from the ground up. Every section (product, news, certificate) has its own data model, and SEO metadata plus the sitemap are generated automatically.",
                'results' => "The marketing team now manages both languages itself, load time improved from 5.6s to 1.2s and indexed pages in search engines doubled.",
                'seo_title' => 'SanatMahr corporate website — bilingual build',
                'seo_description' => 'Designing a bilingual corporate website with PHP and React: product catalogue, news, partnership forms and a CMS.',
            ],
        ],
        [
            'key' => 'paylink',
            'category' => 'web-applications',
            'client' => 'PayLink',
            'project_url' => null,
            'github_url' => 'https://github.com/arashmahdavi/paylink',
            'cover' => $media['paylink'],
            'stack' => 'PHP · MySQL · REST API · Webhooks · React',
            'started_at' => '2024-04-01',
            'completed_at' => '2024-08-05',
            'featured' => 0,
            'gallery' => [
                [$media['paylink'], 'Merchant dashboard'],
                [$media['detailCode'], 'Webhook pipeline'],
            ],
            'technologies' => ['PHP', 'MySQL', 'REST API', 'React.js', 'JavaScript'],
            'fa' => [
                'title' => 'پلتفرم یکپارچه‌سازی درگاه پرداخت پی‌لینک',
                'slug' => 'paylink-payment-platform',
                'summary' => 'لایه واسط اتصال چند درگاه پرداخت با تسویه خودکار، مدیریت تراکنش‌ها و وب‌هوک مقاوم در برابر خطا.',
                'description' => "پی‌لینک به فروشگاه‌ها اجازه می‌دهد بدون تغییر کد، بین چند درگاه پرداخت جابه‌جا شوند و تراکنش‌ها را در یک داشبورد ببینند.\n\nسامانه شامل API یکپارچه، صف وب‌هوک با تلاش مجدد، تطبیق خودکار تراکنش‌ها و گزارش تسویه است.",
                'challenge' => "هر درگاه پرداخت قرارداد داده متفاوتی دارد و قطعی موقت درگاه‌ها باعث از دست رفتن سفارش‌ها می‌شد.",
                'solution' => "یک لایه انتزاعی برای درگاه‌ها طراحی کردم که تفاوت‌ها را یکسان می‌کند، مسیر پرداخت با «تلاش مجدد + درگاه جایگزین» پیاده شد و رویدادهای وب‌هوک با امضای دیجیتال و idempotency پردازش می‌شوند.",
                'results' => "نرخ تراکنش‌های ناموفق ۴۱٪ کاهش یافت، تطبیق دستی تراکنش‌ها حذف شد و افزودن درگاه جدید به کمتر از دو ساعت زمان نیاز دارد.",
                'seo_title' => 'پلتفرم پرداخت پی‌لینک — یکپارچه‌سازی API',
                'seo_description' => 'توسعه لایه واسط درگاه‌های پرداخت با PHP و MySQL؛ وب‌هوک مقاوم، تسویه خودکار و داشبورد تراکنش.',
            ],
            'en' => [
                'title' => 'PayLink payment integration platform',
                'slug' => 'paylink-payment-platform',
                'summary' => 'A gateway abstraction layer with automatic settlement, transaction management and failure-tolerant webhooks.',
                'description' => "PayLink lets stores switch between multiple payment providers without code changes and monitor every transaction in one dashboard.\n\nThe platform provides a unified API, a retrying webhook queue, automatic reconciliation and settlement reporting.",
                'challenge' => "Every provider exposes a different contract and short provider outages were causing lost orders.",
                'solution' => "I built an abstraction layer that normalises provider differences, a checkout flow with retry and fallback provider, and webhook processing with signature verification and idempotency keys.",
                'results' => "Failed transactions dropped 41%, manual reconciliation disappeared and onboarding a new provider now takes under two hours.",
                'seo_title' => 'PayLink payment platform — API integration case study',
                'seo_description' => 'Building a payment gateway abstraction with PHP and MySQL: resilient webhooks, automatic settlement and transaction reporting.',
            ],
        ],
        [
            'key' => 'taskflow',
            'category' => 'web-applications',
            'client' => 'Internal product',
            'project_url' => null,
            'github_url' => 'https://github.com/arashmahdavi/taskflow',
            'cover' => $media['taskflow'],
            'stack' => 'React · PHP · MySQL · WebSockets-ready',
            'started_at' => '2023-09-01',
            'completed_at' => '2024-01-30',
            'featured' => 0,
            'gallery' => [
                [$media['taskflow'], 'Kanban board'],
                [$media['detailMobile'], 'Task view (mobile)'],
            ],
            'technologies' => ['React.js', 'PHP', 'MySQL', 'JavaScript', 'REST API', 'CSS3'],
            'fa' => [
                'title' => 'تسک‌فلو — مدیریت پروژه تیمی',
                'slug' => 'taskflow-project-management',
                'summary' => 'ابزار مدیریت پروژه با برد کانبان، تخصیص وظیفه، ثبت زمان، بحث تیمی و گزارش پیشرفت.',
                'description' => "تسک‌فلو ابزار داخلی تیم‌های توسعه است: وظیفه‌ها روی برد کانبان جابه‌جا می‌شوند، زمان صرف‌شده ثبت می‌شود و گزارش پیشرفت به‌صورت خودکار ساخته می‌شود.\n\nرابط کاربری با React و به‌صورت خوش‌بینانه (optimistic) به‌روزرسانی می‌شود تا جابه‌جایی کارت‌ها بی‌وقفه به‌نظر برسد.",
                'challenge' => "ابزارهای آماده برای گردش کار تیم مناسب نبودند و جابه‌جایی کارت‌ها در بردهای پرکاربر کند بود.",
                'solution' => "به‌روزرسانی خوش‌بینانه با بازگشت خودکار در صورت خطا، ذخیره ترتیب کارت‌ها با مرتب‌سازی عددی فاصله‌دار و ایندکس‌گذاری مناسب روی جدول وظایف پیاده شد.",
                'results' => "زمان پاسخ جابه‌جایی کارت زیر ۱۰۰ میلی‌ثانیه است، گزارش‌های پیشرفت خودکار شدند و ابزار به‌طور روزانه توسط ۳ تیم استفاده می‌شود.",
                'seo_title' => 'تسک‌فلو — اپلیکیشن مدیریت پروژه',
                'seo_description' => 'ساخت اپلیکیشن مدیریت پروژه با React، PHP و MySQL؛ برد کانبان، ثبت زمان و گزارش پیشرفت.',
            ],
            'en' => [
                'title' => 'TaskFlow team project management',
                'slug' => 'taskflow-project-management',
                'summary' => 'A project tool with a kanban board, assignments, time tracking, team discussion and progress reporting.',
                'description' => "TaskFlow is the internal tooling for development teams: tasks move across a kanban board, time is tracked and progress reports are generated automatically.\n\nThe React interface updates optimistically so dragging cards feels instant.",
                'challenge' => "Off-the-shelf tools did not match the team workflow, and card movement on busy boards was slow.",
                'solution' => "I implemented optimistic updates with automatic rollback on failure, fractional ordering for card positions and targeted indexes on the task table.",
                'results' => "Card movement responds in under 100ms, progress reporting became automatic and three teams now use the tool daily.",
                'seo_title' => 'TaskFlow project management app — case study',
                'seo_description' => 'Building a project management application with React, PHP and MySQL: kanban board, time tracking and progress reports.',
            ],
        ],
    ];

    foreach ($projects as $index => $project) {
        $projectId = $seeder->translated('projects', 'project_translations', [
            'category_id' => $categoryIds[$project['category']],
            'client' => $project['client'],
            'project_url' => $project['project_url'],
            'github_url' => $project['github_url'],
            'cover_media_id' => $project['cover'],
            'tech_stack' => $project['stack'],
            'started_at' => $project['started_at'],
            'completed_at' => $project['completed_at'],
            'is_featured' => $project['featured'],
            'is_active' => 1,
            'sort_order' => $index + 1,
            'views' => random_int(120, 980),
        ], [
            'fa' => [
                'title' => $project['fa']['title'],
                'slug' => $project['fa']['slug'],
                'summary' => $project['fa']['summary'],
                'description' => $project['fa']['description'],
                'challenge' => $project['fa']['challenge'],
                'solution' => $project['fa']['solution'],
                'results' => $project['fa']['results'],
                'seo_title' => $project['fa']['seo_title'],
                'seo_description' => $project['fa']['seo_description'],
            ],
            'en' => [
                'title' => $project['en']['title'],
                'slug' => $project['en']['slug'],
                'summary' => $project['en']['summary'],
                'description' => $project['en']['description'],
                'challenge' => $project['en']['challenge'],
                'solution' => $project['en']['solution'],
                'results' => $project['en']['results'],
                'seo_title' => $project['en']['seo_title'],
                'seo_description' => $project['en']['seo_description'],
            ],
        ], 'project_id');

        // Gallery
        foreach ($project['gallery'] as $position => [$mediaId, $caption]) {
            Database::table('project_images')->insert([
                'project_id' => $projectId,
                'media_id' => $mediaId,
                'caption' => $caption,
                'sort_order' => $position + 1,
                'created_at' => now_utc(),
                'updated_at' => now_utc(),
            ]);
        }

        // Technology links (join table → also powers "related projects")
        foreach ($project['technologies'] as $position => $technologyName) {
            $skillId = $seeder->skillId($technologyName);

            if ($skillId === null) {
                continue;
            }

            Database::table('project_technologies')->insert([
                'project_id' => $projectId,
                'skill_id' => $skillId,
                'sort_order' => $position + 1,
                'created_at' => now_utc(),
                'updated_at' => now_utc(),
            ]);
        }

        // Per-project SEO rows
        $seeder->seo('project', $projectId, [
            'fa' => ['title' => $project['fa']['seo_title'], 'description' => $project['fa']['seo_description']],
            'en' => ['title' => $project['en']['seo_title'], 'description' => $project['en']['seo_description']],
        ]);
    }
};
