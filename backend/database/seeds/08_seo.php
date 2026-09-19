<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Seeder;

/**
 * SEO metadata for the global site record and every static page, in both
 * languages. Editable in Admin → SEO (title, description, keywords, canonical,
 * Open Graph, Twitter card, robots directives and structured data).
 */
return static function (Seeder $seeder): void {
    $pages = Database::select('SELECT id, key_name FROM pages');

    $copy = [
        'home' => [
            'fa' => [
                'title' => 'آرش مهدوی | توسعه‌دهنده فول‌استک وب — ساخت وب‌سایت، اپلیکیشن و نرم‌افزار سفارشی',
                'description' => 'طراحی و توسعه وب‌سایت مدرن، اپلیکیشن تحت وب، فروشگاه اینترنتی، داشبورد مدیریتی و نرم‌افزار سفارشی با PHP، MySQL، React و JavaScript.',
                'keywords' => 'توسعه‌دهنده فول‌استک, طراحی وب‌سایت, اپلیکیشن وب, فروشگاه اینترنتی, PHP, React, MySQL, داشبورد مدیریتی',
            ],
            'en' => [
                'title' => 'Arash Mahdavi | Full-Stack Web Developer — websites, web apps & custom software',
                'description' => 'Full-stack developer building modern websites, web applications, e-commerce platforms, admin dashboards and custom software with PHP, MySQL, React and JavaScript.',
                'keywords' => 'full-stack developer, web development, web application, e-commerce, PHP, React, MySQL, admin dashboard',
            ],
        ],
        'about' => [
            'fa' => [
                'title' => 'درباره آرش مهدوی | ۹ سال تجربه توسعه فول‌استک',
                'description' => 'آشنایی با مسیر حرفه‌ای، فلسفه توسعه و مهارت‌های فنی آرش مهدوی؛ از سامانه‌های سازمانی تا فروشگاه‌های اینترنتی و پلتفرم‌های آموزشی.',
                'keywords' => 'درباره من, توسعه‌دهنده ارشد, تجربه کاری, فلسفه توسعه نرم‌افزار',
            ],
            'en' => [
                'title' => 'About Arash Mahdavi | 9 years of full-stack development',
                'description' => 'Career path, development philosophy and technical skills of Arash Mahdavi — from enterprise systems to e-commerce platforms and learning products.',
                'keywords' => 'about, senior developer, professional experience, software development philosophy',
            ],
        ],
        'skills' => [
            'fa' => [
                'title' => 'مهارت‌ها و تکنولوژی‌ها | PHP، React، MySQL و JavaScript',
                'description' => 'فهرست مهارت‌های فول‌استک: PHP و MySQL در بک‌اند، React و JavaScript در فرانت‌اند، طراحی API، امنیت وب و بهینه‌سازی کارایی.',
                'keywords' => 'مهارت‌ها, PHP, React, MySQL, JavaScript, HTML5, CSS3, REST API, امنیت وب',
            ],
            'en' => [
                'title' => 'Skills & technologies | PHP, React, MySQL, JavaScript',
                'description' => 'Full-stack skill set: PHP and MySQL on the back end, React and JavaScript on the front end, API design, web security and performance optimisation.',
                'keywords' => 'skills, PHP, React, MySQL, JavaScript, HTML5, CSS3, REST API, web security',
            ],
        ],
        'services' => [
            'fa' => [
                'title' => 'خدمات توسعه وب و نرم‌افزار | وب‌سایت، اپلیکیشن، فروشگاه، داشبورد',
                'description' => 'خدمات تخصصی: طراحی وب‌سایت واکنش‌گرا، توسعه اپلیکیشن وب، راه‌اندازی فروشگاه اینترنتی، نرم‌افزار سفارشی کسب‌وکار، داشبورد مدیریتی و یکپارچه‌سازی API.',
                'keywords' => 'خدمات طراحی سایت, توسعه اپلیکیشن وب, فروشگاه اینترنتی, نرم‌افزار سفارشی, داشبورد مدیریتی, توسعه API',
            ],
            'en' => [
                'title' => 'Web & software development services | websites, apps, e-commerce, dashboards',
                'description' => 'Responsive website development, web applications, e-commerce platforms, custom business software, admin dashboards and API integrations.',
                'keywords' => 'web development services, web application development, e-commerce, custom software, admin dashboard, API integration',
            ],
        ],
        'projects' => [
            'fa' => [
                'title' => 'پروژه‌ها و نمونه‌کارها | فروشگاه، سامانه مدیریتی، داشبورد',
                'description' => 'نمونه‌کارهای فول‌استک با شرح چالش، راهکار و نتیجه: فروشگاه اینترنتی دیجی‌مارکت، سامانه حسابداری حسابیار، داشبورد SalesPulse و پلتفرم آموزش مهارت.',
                'keywords' => 'نمونه کار, پروژه فول‌استک, فروشگاه اینترنتی, سامانه حسابداری, داشبورد مدیریتی, پلتفرم آموزشی',
            ],
            'en' => [
                'title' => 'Projects & case studies | e-commerce, business systems, dashboards',
                'description' => 'Full-stack case studies with the challenge, solution and measured results: DigiMarket store, Hesabyar business suite, SalesPulse dashboard and Maharat academy.',
                'keywords' => 'portfolio, full-stack projects, e-commerce, business management system, dashboard, learning platform',
            ],
        ],
        'experience' => [
            'fa' => [
                'title' => 'تجربه کاری و تحصیلات | آرش مهدوی',
                'description' => 'مسیر حرفه‌ای از توسعه‌دهنده فریلنس تا توسعه‌دهنده ارشد فول‌استک؛ مسئولیت‌ها، دستاوردها، تحصیلات دانشگاهی و گواهی‌نامه‌های تخصصی.',
                'keywords' => 'تجربه کاری, سابقه حرفه‌ای, تحصیلات, گواهی‌نامه PHP, گواهی‌نامه React',
            ],
            'en' => [
                'title' => 'Experience & education | Arash Mahdavi',
                'description' => 'Professional timeline from freelance developer to senior full-stack developer: responsibilities, achievements, education and technical certifications.',
                'keywords' => 'work experience, professional background, education, PHP certification, React certification',
            ],
        ],
        'blog' => [
            'fa' => [
                'title' => 'وبلاگ فنی | معماری، امنیت و کارایی در توسعه وب',
                'description' => 'یادداشت‌های فنی درباره معماری چندزبانه، اتصال React به API، امن‌سازی فرم‌ها، بهینه‌سازی MySQL و طراحی رابط فارسی.',
                'keywords' => 'وبلاگ فنی, معماری نرم‌افزار, امنیت وب, بهینه‌سازی MySQL, React, طراحی RTL',
            ],
            'en' => [
                'title' => 'Engineering blog | architecture, security and web performance',
                'description' => 'Technical notes on multilingual architecture, connecting React to PHP APIs, securing forms, MySQL tuning and Persian interface design.',
                'keywords' => 'engineering blog, software architecture, web security, MySQL performance, React, RTL design',
            ],
        ],
        'contact' => [
            'fa' => [
                'title' => 'تماس با آرش مهدوی | شروع همکاری در پروژه وب',
                'description' => 'برای شروع پروژه، مشاوره فنی یا فرصت شغلی پیام بدهید. پاسخ در کمتر از ۲۴ ساعت. ایمیل، تلفن و شبکه‌های اجتماعی.',
                'keywords' => 'تماس با من, همکاری در پروژه, استخدام توسعه‌دهنده, مشاوره فنی',
            ],
            'en' => [
                'title' => 'Contact Arash Mahdavi | start a web project',
                'description' => 'Get in touch for a project, technical consulting or a role. Replies within 24 hours — email, phone and social profiles.',
                'keywords' => 'contact, hire full-stack developer, project enquiry, technical consulting',
            ],
        ],
        'resume' => [
            'fa' => [
                'title' => 'رزومه آرش مهدوی | دانلود PDF رزومه فول‌استک',
                'description' => 'رزومه کامل: خلاصه حرفه‌ای، مهارت‌ها، تجربه کاری، پروژه‌ها، تحصیلات و گواهی‌نامه‌ها — قابل مشاهده آنلاین و دانلود PDF.',
                'keywords' => 'رزومه, CV, دانلود رزومه, رزومه توسعه‌دهنده فول‌استک',
            ],
            'en' => [
                'title' => 'Résumé | Arash Mahdavi — full-stack developer CV',
                'description' => 'Complete CV: professional summary, skills, work experience, projects, education and certifications — readable online and downloadable as PDF.',
                'keywords' => 'resume, CV, download resume, full-stack developer CV',
            ],
        ],
    ];

    foreach ($pages as $page) {
        $key = (string) $page['key_name'];

        if (!isset($copy[$key])) {
            continue;
        }

        $seeder->seo('page', (int) $page['id'], [
            'fa' => [
                'title' => $copy[$key]['fa']['title'],
                'description' => $copy[$key]['fa']['description'],
                'keywords' => $copy[$key]['fa']['keywords'],
            ],
            'en' => [
                'title' => $copy[$key]['en']['title'],
                'description' => $copy[$key]['en']['description'],
                'keywords' => $copy[$key]['en']['keywords'],
            ],
        ]);
    }

    /* --------------------------------------------------------------------- */
    /* Global default (used as fallback + for the WebSite/Person JSON-LD)     */
    /* --------------------------------------------------------------------- */
    $seeder->seo('global', null, [
        'fa' => [
            'title' => 'آرش مهدوی | توسعه‌دهنده فول‌استک وب',
            'description' => 'ساخت وب‌سایت مدرن، اپلیکیشن تحت وب، فروشگاه اینترنتی و نرم‌افزار سفارشی با PHP، MySQL، React و JavaScript.',
            'keywords' => 'توسعه‌دهنده فول‌استک, PHP, React, MySQL, طراحی سایت',
        ],
        'en' => [
            'title' => 'Arash Mahdavi | Full-Stack Web Developer',
            'description' => 'Modern websites, web applications, e-commerce platforms and custom software built with PHP, MySQL, React and JavaScript.',
            'keywords' => 'full-stack developer, PHP, React, MySQL, web development',
        ],
    ]);
};
