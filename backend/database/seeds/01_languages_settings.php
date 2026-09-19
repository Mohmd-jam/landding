<?php

declare(strict_types=1);

use Database\Seeder;

/**
 * Languages, site settings (identity / contact / SEO / appearance / analytics)
 * and the persisted UI strings used by the React frontend.
 */
return static function (Seeder $seeder): void {
    $now = gmdate('Y-m-d H:i:s');

    /* --------------------------------------------------------------------- */
    /* Languages                                                             */
    /* --------------------------------------------------------------------- */
    \App\Core\Database::table('languages')->insertMany([
        [
            'code' => 'fa',
            'name' => 'Persian',
            'native_name' => 'فارسی',
            'direction' => 'rtl',
            'flag' => 'ir',
            'is_default' => 1,
            'is_active' => 1,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'direction' => 'ltr',
            'flag' => 'gb',
            'is_default' => 0,
            'is_active' => 1,
            'sort_order' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    /* --------------------------------------------------------------------- */
    /* Identity                                                              */
    /* --------------------------------------------------------------------- */
    $seeder->setting('site_name', 'identity', 'string', 'Arash Mahdavi', true, [
        'fa' => 'آرش مهدوی',
        'en' => 'Arash Mahdavi',
    ]);
    $seeder->setting('site_title', 'identity', 'string', 'Full-Stack Web Developer', true, [
        'fa' => 'توسعه‌دهنده فول‌استک وب',
        'en' => 'Full-Stack Web Developer',
    ]);
    $seeder->setting(
        'site_description',
        'identity',
        'text',
        'I design and develop modern websites, web applications, e-commerce platforms and custom software solutions.',
        true,
        [
            'fa' => 'وب‌سایت‌های مدرن، اپلیکیشن‌های تحت وب، فروشگاه‌های اینترنتی و نرم‌افزارهای سفارشی طراحی و پیاده‌سازی می‌کنم — از رابط کاربری تا پایگاه داده.',
            'en' => 'I design and develop modern websites, web applications, e-commerce platforms and custom software solutions — from interface to database.',
        ]
    );
    $seeder->setting('owner_name', 'identity', 'string', 'Arash Mahdavi', true, [
        'fa' => 'آرش مهدوی',
        'en' => 'Arash Mahdavi',
    ]);
    $seeder->setting('owner_title', 'identity', 'string', 'Full-Stack Developer & Web Systems Engineer', true, [
        'fa' => 'توسعه‌دهنده فول‌استک و مهندس سیستم‌های وب',
        'en' => 'Full-Stack Developer & Web Systems Engineer',
    ]);
    $seeder->setting('owner_initials', 'identity', 'string', 'AM', true, [
        'fa' => 'آم',
        'en' => 'AM',
    ]);
    $seeder->setting('owner_experience_years', 'identity', 'int', '9', true, [
        'fa' => '۹ سال',
        'en' => '9 years',
    ]);
    $seeder->setting('availability_enabled', 'identity', 'bool', '1');
    $seeder->setting('availability_status', 'identity', 'string', 'Available for new projects', true, [
        'fa' => 'آماده همکاری در پروژه‌های جدید',
        'en' => 'Available for new projects',
    ]);
    $seeder->setting('hero_code_snippet', 'identity', 'text', "final class ProjectController\n{\n    public function store(Request \$request): JsonResponse\n    {\n        \$data = \$this->validate(\$request);\n\n        \$project = \$this->projects->create(\$data);\n\n        return JsonResponse::created(\$project);\n    }\n}", false);

    /* --------------------------------------------------------------------- */
    /* Contact                                                               */
    /* --------------------------------------------------------------------- */
    $seeder->setting('contact_email', 'contact', 'string', 'hello@arashmahdavi.dev');
    $seeder->setting('contact_phone', 'contact', 'string', '+989125550180');
    $seeder->setting('contact_phone_display', 'contact', 'string', '+98 912 555 0180', true, [
        'fa' => '۰۹۱۲۵۵۵۰۱۸۰',
        'en' => '+98 912 555 0180',
    ]);
    $seeder->setting('contact_location', 'contact', 'string', 'Tehran, Iran', true, [
        'fa' => 'تهران، ایران',
        'en' => 'Tehran, Iran',
    ]);
    $seeder->setting('contact_address', 'contact', 'string', 'Tehran, Iran — remote-friendly', true, [
        'fa' => 'تهران، ایران — همکاری دورکاری با تیم‌های بین‌المللی',
        'en' => 'Tehran, Iran — working remotely with international teams',
    ]);
    $seeder->setting('working_hours', 'contact', 'string', 'Saturday–Thursday, 09:00–18:00 (GMT+3:30)', true, [
        'fa' => 'شنبه تا پنجشنبه، ۹ تا ۱۸ (به وقت تهران)',
        'en' => 'Saturday–Thursday, 09:00–18:00 (GMT+3:30)',
    ]);
    $seeder->setting('response_time', 'contact', 'string', 'Replies within 24 hours', true, [
        'fa' => 'پاسخ در کمتر از ۲۴ ساعت',
        'en' => 'Replies within 24 hours',
    ]);
    $seeder->setting('contact_notify_email', 'contact', 'string', 'hello@arashmahdavi.dev', false);
    $seeder->setting('contact_map_embed', 'contact', 'text', '', false);

    /* --------------------------------------------------------------------- */
    /* SEO                                                                   */
    /* --------------------------------------------------------------------- */
    $seeder->setting('seo_default_title', 'seo', 'string', 'Arash Mahdavi — Full-Stack Web Developer', true, [
        'fa' => 'آرش مهدوی | توسعه‌دهنده فول‌استک وب',
        'en' => 'Arash Mahdavi — Full-Stack Web Developer',
    ]);
    $seeder->setting(
        'seo_default_description',
        'seo',
        'text',
        'Full-stack developer building modern websites, web applications, e-commerce platforms and custom software with PHP, MySQL, React and JavaScript.',
        true,
        [
            'fa' => 'توسعه‌دهنده فول‌استک؛ ساخت وب‌سایت مدرن، اپلیکیشن تحت وب، فروشگاه اینترنتی و نرم‌افزار سفارشی با PHP، MySQL، React و JavaScript.',
            'en' => 'Full-stack developer building modern websites, web applications, e-commerce platforms and custom software with PHP, MySQL, React and JavaScript.',
        ]
    );
    $seeder->setting('seo_keywords', 'seo', 'string', 'full-stack developer, PHP, React, MySQL, JavaScript, web application, e-commerce, admin dashboard', true, [
        'fa' => 'توسعه‌دهنده فول‌استک، PHP، React، MySQL، جاوااسکریپت، اپلیکیشن وب، فروشگاه اینترنتی، پنل مدیریت',
        'en' => 'full-stack developer, PHP, React, MySQL, JavaScript, web application, e-commerce, admin dashboard',
    ]);
    $seeder->setting('seo_twitter_handle', 'seo', 'string', '@arashmahdavi_dev');
    $seeder->setting('seo_robots', 'seo', 'string', 'index,follow');
    $seeder->setting('seo_google_verification', 'seo', 'string', '', false);
    $seeder->setting('seo_sitemap_enabled', 'seo', 'bool', '1');

    /* --------------------------------------------------------------------- */
    /* Appearance                                                            */
    /* --------------------------------------------------------------------- */
    $seeder->setting('theme_default', 'appearance', 'string', 'dark');
    $seeder->setting('theme_toggle_enabled', 'appearance', 'bool', '1');
    $seeder->setting('accent_color', 'appearance', 'color', '#3ddc97');
    $seeder->setting('accent_secondary', 'appearance', 'color', '#6c8cff');
    $seeder->setting('hero_terminal_enabled', 'appearance', 'bool', '1');
    $seeder->setting('show_tech_marquee', 'appearance', 'bool', '1');

    /* --------------------------------------------------------------------- */
    /* Analytics & integrations                                              */
    /* --------------------------------------------------------------------- */
    $seeder->setting('internal_analytics', 'analytics', 'bool', '1');
    $seeder->setting('google_analytics_id', 'analytics', 'string', '', false);
    $seeder->setting('plausible_domain', 'analytics', 'string', '', false);
    $seeder->setting('mail_transport', 'integrations', 'string', 'log', false);
    $seeder->setting('recaptcha_site_key', 'integrations', 'string', '', false);
    $seeder->setting('recaptcha_secret_key', 'integrations', 'string', '', false);
    $seeder->setting('maintenance_mode', 'system', 'bool', '0', false);

    /* --------------------------------------------------------------------- */
    /* UI strings (editable in Admin → Languages)                            */
    /* --------------------------------------------------------------------- */
    $strings = [
        'nav.home' => ['general', ['fa' => 'خانه', 'en' => 'Home']],
        'nav.about' => ['general', ['fa' => 'درباره من', 'en' => 'About']],
        'action.view_all' => ['general', ['fa' => 'مشاهده همه', 'en' => 'View all']],
        'action.read_more' => ['general', ['fa' => 'ادامه مطلب', 'en' => 'Read more']],
        'action.view_project' => ['general', ['fa' => 'مشاهده پروژه', 'en' => 'View project']],
        'action.view_case_study' => ['general', ['fa' => 'مطالعه موردی', 'en' => 'Case study']],
        'action.contact_me' => ['general', ['fa' => 'تماس با من', 'en' => 'Contact me']],
        'action.download_resume' => ['general', ['fa' => 'دانلود رزومه', 'en' => 'Download resume']],
        'action.send_message' => ['general', ['fa' => 'ارسال پیام', 'en' => 'Send message']],
        'action.visit_site' => ['general', ['fa' => 'مشاهده سایت', 'en' => 'Visit site']],
        'action.view_code' => ['general', ['fa' => 'کد پروژه', 'en' => 'Source code']],
        'action.all' => ['general', ['fa' => 'همه', 'en' => 'All']],
        'label.technologies' => ['general', ['fa' => 'تکنولوژی‌ها', 'en' => 'Technologies']],
        'label.skills' => ['general', ['fa' => 'مهارت‌ها', 'en' => 'Skills']],
        'label.services' => ['general', ['fa' => 'خدمات', 'en' => 'Services']],
        'label.projects' => ['general', ['fa' => 'پروژه‌ها', 'en' => 'Projects']],
        'label.experience' => ['general', ['fa' => 'تجربه کاری', 'en' => 'Experience']],
        'label.education' => ['general', ['fa' => 'تحصیلات', 'en' => 'Education']],
        'label.certifications' => ['general', ['fa' => 'گواهی‌نامه‌ها', 'en' => 'Certifications']],
        'label.testimonials' => ['general', ['fa' => 'نظر مشتریان', 'en' => 'Testimonials']],
        'label.blog' => ['general', ['fa' => 'مقالات', 'en' => 'Articles']],
        'label.categories' => ['general', ['fa' => 'دسته‌بندی‌ها', 'en' => 'Categories']],
        'label.tags' => ['general', ['fa' => 'برچسب‌ها', 'en' => 'Tags']],
        'label.present' => ['general', ['fa' => 'تاکنون', 'en' => 'Present']],
        'label.featured' => ['general', ['fa' => 'شاخص', 'en' => 'Featured']],
        'label.related_projects' => ['general', ['fa' => 'پروژه‌های مرتبط', 'en' => 'Related projects']],
        'label.related_posts' => ['general', ['fa' => 'مقالات مرتبط', 'en' => 'Related articles']],
        'label.min_read' => ['general', ['fa' => 'دقیقه مطالعه', 'en' => 'min read']],
        'label.share' => ['general', ['fa' => 'اشتراک‌گذاری', 'en' => 'Share']],
        'label.overview' => ['general', ['fa' => 'نگاه کلی', 'en' => 'Overview']],
        'label.challenge' => ['general', ['fa' => 'چالش', 'en' => 'The challenge']],
        'label.solution' => ['general', ['fa' => 'راهکار', 'en' => 'The solution']],
        'label.results' => ['general', ['fa' => 'نتیجه', 'en' => 'Results']],
        'label.project_details' => ['general', ['fa' => 'مشخصات پروژه', 'en' => 'Project details']],
        'label.client' => ['general', ['fa' => 'کارفرما', 'en' => 'Client']],
        'label.completed' => ['general', ['fa' => 'تاریخ تحویل', 'en' => 'Completed']],
        'label.role' => ['general', ['fa' => 'نقش', 'en' => 'Role']],
        'label.stack' => ['general', ['fa' => 'تکنولوژی‌ها', 'en' => 'Stack']],
        'form.name' => ['form', ['fa' => 'نام و نام خانوادگی', 'en' => 'Full name']],
        'form.email' => ['form', ['fa' => 'ایمیل', 'en' => 'Email']],
        'form.phone' => ['form', ['fa' => 'شماره تماس (اختیاری)', 'en' => 'Phone (optional)']],
        'form.company' => ['form', ['fa' => 'شرکت / سازمان (اختیاری)', 'en' => 'Company (optional)']],
        'form.subject' => ['form', ['fa' => 'موضوع', 'en' => 'Subject']],
        'form.budget' => ['form', ['fa' => 'بودجه تقریبی (اختیاری)', 'en' => 'Estimated budget (optional)']],
        'form.service' => ['form', ['fa' => 'خدمت مورد نظر', 'en' => 'Service needed']],
        'form.message' => ['form', ['fa' => 'پیام', 'en' => 'Message']],
        'form.required' => ['form', ['fa' => 'این فیلد الزامی است', 'en' => 'This field is required']],
        'form.invalid_email' => ['form', ['fa' => 'ایمیل معتبر وارد کنید', 'en' => 'Enter a valid email address']],
        'form.sending' => ['form', ['fa' => 'در حال ارسال…', 'en' => 'Sending…']],
        'form.success' => ['form', ['fa' => 'پیام شما با موفقیت ارسال شد. به‌زودی پاسخ می‌دهم.', 'en' => 'Your message has been sent. I will reply shortly.']],
        'form.error' => ['form', ['fa' => 'ارسال پیام ناموفق بود. لطفاً دوباره تلاش کنید.', 'en' => 'Sending failed. Please try again.']],
        'state.loading' => ['state', ['fa' => 'در حال بارگذاری…', 'en' => 'Loading…']],
        'state.empty' => ['state', ['fa' => 'موردی یافت نشد.', 'en' => 'Nothing found.']],
        'state.error' => ['state', ['fa' => 'خطا در دریافت اطلاعات.', 'en' => 'Could not load data.']],
        'state.not_found_title' => ['state', ['fa' => 'صفحه یافت نشد', 'en' => 'Page not found']],
        'state.not_found_body' => ['state', ['fa' => 'آدرس وارد‌شده وجود ندارد یا جابه‌جا شده است.', 'en' => 'The page you requested does not exist or has moved.']],
        'state.back_home' => ['state', ['fa' => 'بازگشت به خانه', 'en' => 'Back to home']],
        'footer.quick_links' => ['footer', ['fa' => 'دسترسی سریع', 'en' => 'Quick links']],
        'footer.services' => ['footer', ['fa' => 'خدمات', 'en' => 'Services']],
        'footer.get_in_touch' => ['footer', ['fa' => 'راه‌های ارتباطی', 'en' => 'Get in touch']],
        'footer.rights' => ['footer', ['fa' => 'تمامی حقوق محفوظ است.', 'en' => 'All rights reserved.']],
        'footer.built_with' => ['footer', ['fa' => 'ساخته‌شده با PHP، MySQL و React', 'en' => 'Built with PHP, MySQL and React']],
        'admin.sign_in' => ['admin', ['fa' => 'ورود به پنل مدیریت', 'en' => 'Sign in to the admin panel']],
    ];

    foreach ($strings as $key => [$group, $values]) {
        $seeder->uiString($key, $group, $values);
    }
};
