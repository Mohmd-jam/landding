<?php

declare(strict_types=1);

use Database\Seeder;

/**
 * Navigation menus (header + footer columns), social profiles and the
 * downloadable résumé entries.
 */
return static function (Seeder $seeder): void {
    /* --------------------------------------------------------------------- */
    /* Header navigation                                                     */
    /* --------------------------------------------------------------------- */
    $header = [
        ['/', 'Home', 'خانه'],
        ['/about', 'About', 'درباره من'],
        ['/skills', 'Skills', 'مهارت‌ها'],
        ['/services', 'Services', 'خدمات'],
        ['/projects', 'Projects', 'پروژه‌ها'],
        ['/experience', 'Experience', 'تجربه کاری'],
        ['/blog', 'Blog', 'وبلاگ'],
        ['/contact', 'Contact', 'تماس با من'],
    ];

    foreach ($header as $index => [$url, $en, $fa]) {
        $seeder->translated('navigation', 'navigation_translations', [
            'location' => 'header',
            'parent_id' => null,
            'type_name' => 'route',
            'target_value' => $url,
            'icon' => null,
            'open_in_new_tab' => 0,
            'is_active' => 1,
            'sort_order' => $index + 1,
        ], [
            'fa' => ['label' => $fa],
            'en' => ['label' => $en],
        ], 'navigation_id');
    }

    /* --------------------------------------------------------------------- */
    /* Footer columns                                                        */
    /* --------------------------------------------------------------------- */
    $footerQuick = [
        ['/about', 'About me', 'درباره من'],
        ['/projects', 'Projects', 'پروژه‌ها'],
        ['/experience', 'Experience', 'تجربه کاری'],
        ['/blog', 'Articles', 'مقالات'],
        ['/resume', 'Résumé', 'رزومه'],
        ['/contact', 'Contact', 'تماس'],
    ];

    foreach ($footerQuick as $index => [$url, $en, $fa]) {
        $seeder->translated('navigation', 'navigation_translations', [
            'location' => 'footer_quick',
            'parent_id' => null,
            'type_name' => 'route',
            'target_value' => $url,
            'open_in_new_tab' => 0,
            'is_active' => 1,
            'sort_order' => $index + 1,
        ], [
            'fa' => ['label' => $fa],
            'en' => ['label' => $en],
        ], 'navigation_id');
    }

    $footerServices = [
        ['/services#website-development', 'Website development', 'طراحی وب‌سایت'],
        ['/services#web-applications', 'Web applications', 'اپلیکیشن‌های وب'],
        ['/services#ecommerce', 'E-commerce', 'فروشگاه اینترنتی'],
        ['/services#custom-software', 'Custom software', 'نرم‌افزار سفارشی'],
        ['/services#admin-dashboards', 'Admin dashboards', 'داشبورد مدیریتی'],
        ['/services#api-integration', 'API development', 'توسعه API'],
        ['/services#maintenance', 'Maintenance & support', 'پشتیبانی و نگهداری'],
    ];

    foreach ($footerServices as $index => [$url, $en, $fa]) {
        $seeder->translated('navigation', 'navigation_translations', [
            'location' => 'footer_services',
            'parent_id' => null,
            'type_name' => 'url',
            'target_value' => $url,
            'open_in_new_tab' => 0,
            'is_active' => 1,
            'sort_order' => $index + 1,
        ], [
            'fa' => ['label' => $fa],
            'en' => ['label' => $en],
        ], 'navigation_id');
    }

    /* --------------------------------------------------------------------- */
    /* Social profiles                                                       */
    /* --------------------------------------------------------------------- */
    $socials = [
        ['github', 'https://github.com/arashmahdavi', 'github', 'GitHub', 'گیت‌هاب', '@arashmahdavi'],
        ['linkedin', 'https://www.linkedin.com/in/arashmahdavi', 'linkedin', 'LinkedIn', 'لینکدین', 'in/arashmahdavi'],
        ['telegram', 'https://t.me/arashmahdavi', 'telegram', 'Telegram', 'تلگرام', '@arashmahdavi'],
        ['instagram', 'https://instagram.com/arashmahdavi.dev', 'instagram', 'Instagram', 'اینستاگرام', '@arashmahdavi.dev'],
        ['email', 'mailto:hello@arashmahdavi.dev', 'mail', 'Email', 'ایمیل', 'hello@arashmahdavi.dev'],
    ];

    foreach ($socials as $index => [$platform, $url, $icon, $en, $fa, $handle]) {
        $seeder->translated('social_links', 'social_link_translations', [
            'platform' => $platform,
            'url' => $url,
            'icon' => $icon,
            'color' => null,
            'is_active' => 1,
            'sort_order' => $index + 1,
        ], [
            'fa' => ['label' => $fa, 'handle' => $handle],
            'en' => ['label' => $en, 'handle' => $handle],
        ], 'social_link_id');
    }

    /* --------------------------------------------------------------------- */
    /* Résumé files                                                          */
    /* --------------------------------------------------------------------- */
    // file_path stays null until the administrator uploads a PDF in
    // Admin → Résumé. The public page always offers a print-optimised view
    // (browser "Save as PDF"), so the download action never dead-ends.
    \App\Core\Database::table('resumes')->insertMany([
        [
            'lang' => 'fa',
            'title' => 'رزومه آرش مهدوی — توسعه‌دهنده فول‌استک',
            'file_media_id' => null,
            'file_path' => null,
            'version' => 'v2026.1',
            'is_primary' => 1,
            'is_active' => 1,
            'downloads' => 0,
            'created_at' => now_utc(),
            'updated_at' => now_utc(),
        ],
        [
            'lang' => 'en',
            'title' => 'Arash Mahdavi — Full-Stack Developer Résumé',
            'file_media_id' => null,
            'file_path' => null,
            'version' => 'v2026.1',
            'is_primary' => 1,
            'is_active' => 1,
            'downloads' => 0,
            'created_at' => now_utc(),
            'updated_at' => now_utc(),
        ],
    ]);
};
