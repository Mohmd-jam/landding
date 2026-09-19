<?php

declare(strict_types=1);

use Database\Seeder;

/**
 * Professional timeline: employment history, education and certifications.
 * Managed in Admin → Experience / Education / Certifications.
 */
return static function (Seeder $seeder): void {
    /* --------------------------------------------------------------------- */
    /* Experience                                                            */
    /* --------------------------------------------------------------------- */
    $experiences = [
        [
            'company' => 'Dadeh Gostar Technology',
            'company_url' => 'https://example.com',
            'location' => 'Tehran, Iran',
            'type' => 'full_time',
            'start' => '2021-04-01',
            'end' => null,
            'current' => 1,
            'fa' => [
                'position' => 'توسعه‌دهنده ارشد فول‌استک',
                'description' => 'طراحی و توسعه محصولات تحت وب شرکت؛ از معماری پایگاه داده و API تا رابط کاربری React و پنل‌های مدیریتی.',
                'responsibilities' => [
                    'طراحی معماری و مدل داده برای محصولات جدید',
                    'پیاده‌سازی REST API و مستندسازی آن برای تیم‌های داخلی',
                    'توسعه رابط کاربری React و کامپوننت‌های مشترک',
                    'بازبینی کد و راهنمایی توسعه‌دهندگان جوان‌تر',
                    'بهینه‌سازی کارایی کوئری‌ها و کاهش زمان پاسخ سرویس‌ها',
                ],
                'achievements' => [
                    'کاهش ۴۵٪ زمان پاسخ APIها با بازطراحی کوئری‌ها و ایندکس‌گذاری',
                    'یکپارچه‌سازی سه سرویس بیرونی از طریق لایه API مشترک',
                    'راه‌اندازی فرایند بازبینی کد و محیط staging',
                ],
            ],
            'en' => [
                'position' => 'Senior Full-Stack Developer',
                'description' => 'Designing and building the company web products — from database architecture and APIs to React interfaces and admin panels.',
                'responsibilities' => [
                    'Architecture and data modelling for new products',
                    'Building and documenting REST APIs for internal teams',
                    'Developing React interfaces and the shared component library',
                    'Code review and mentoring junior developers',
                    'Query and service performance optimisation',
                ],
                'achievements' => [
                    'Cut API response time by 45% through query redesign and indexing',
                    'Integrated three external services behind one shared API layer',
                    'Introduced a code review process and a staging environment',
                ],
            ],
        ],
        [
            'company' => 'ParsAra Web Studio',
            'company_url' => 'https://example.com',
            'location' => 'Tehran, Iran',
            'type' => 'full_time',
            'start' => '2018-06-01',
            'end' => '2021-03-15',
            'current' => 0,
            'fa' => [
                'position' => 'توسعه‌دهنده فول‌استک',
                'description' => 'توسعه وب‌سایت و فروشگاه اینترنتی برای مشتریان استودیو؛ از تحلیل نیاز تا تحویل و پشتیبانی.',
                'responsibilities' => [
                    'پیاده‌سازی بیش از ۲۰ وب‌سایت و فروشگاه اینترنتی',
                    'طراحی پایگاه داده MySQL و مدیریت مهاجرت داده مشتریان',
                    'اتصال درگاه پرداخت، پیامک و سامانه‌های ارسال',
                    'آموزش تیم‌های مشتری برای کار با پنل مدیریت',
                ],
                'achievements' => [
                    'کتابخانه کامپوننت‌های مشترک برای کاهش ۳۰٪ زمان توسعه پروژه‌ها',
                    'استانداردسازی چک‌لیست امنیتی پیش از تحویل هر پروژه',
                    'افزایش میانگین امتیاز رضایت مشتریان به ۴.۸ از ۵',
                ],
            ],
            'en' => [
                'position' => 'Full-Stack Developer',
                'description' => 'Building websites and online stores for studio clients — from requirement analysis to delivery and support.',
                'responsibilities' => [
                    'Delivered more than 20 websites and e-commerce projects',
                    'MySQL database design and client data migrations',
                    'Payment gateway, SMS and shipping integrations',
                    'Training client teams on the admin panel',
                ],
                'achievements' => [
                    'Built a shared component library that cut delivery time by 30%',
                    'Standardised a security checklist before every release',
                    'Raised average client satisfaction to 4.8 / 5',
                ],
            ],
        ],
        [
            'company' => 'Arman Trading Company',
            'company_url' => null,
            'location' => 'Isfahan, Iran',
            'type' => 'full_time',
            'start' => '2016-02-01',
            'end' => '2018-05-30',
            'current' => 0,
            'fa' => [
                'position' => 'توسعه‌دهنده وب و نرم‌افزار داخلی',
                'description' => 'ساخت و نگهداری سامانه‌های داخلی شرکت: انبار، فاکتور، گزارش فروش و وب‌سایت شرکتی.',
                'responsibilities' => [
                    'توسعه سامانه انبارداری و صدور فاکتور',
                    'ساخت گزارش‌های تحلیلی برای مدیریت',
                    'نگهداری و پشتیبانی وب‌سایت شرکتی',
                    'آموزش کاربران داخلی و مستندسازی سامانه‌ها',
                ],
                'achievements' => [
                    'حذف ثبت دستی انبار و کاهش ۶۰٪ خطای موجودی',
                    'خودکارسازی گزارش فروش ماهانه مدیریت',
                ],
            ],
            'en' => [
                'position' => 'Web & internal software developer',
                'description' => 'Building and maintaining the company internal systems: inventory, invoicing, sales reporting and the corporate website.',
                'responsibilities' => [
                    'Developed the inventory and invoicing system',
                    'Built analytical reports for management',
                    'Maintained and supported the corporate website',
                    'Trained internal users and documented the systems',
                ],
                'achievements' => [
                    'Removed manual stock entry and cut inventory errors by 60%',
                    'Automated the monthly management sales report',
                ],
            ],
        ],
        [
            'company' => 'Freelance',
            'company_url' => null,
            'location' => 'Remote',
            'type' => 'freelance',
            'start' => '2014-01-01',
            'end' => '2016-01-30',
            'current' => 0,
            'fa' => [
                'position' => 'توسعه‌دهنده وب (فریلنس)',
                'description' => 'همکاری مستقیم با کسب‌وکارهای کوچک برای ساخت وب‌سایت، فروشگاه کوچک و ابزارهای داخلی.',
                'responsibilities' => [
                    'طراحی و پیاده‌سازی وب‌سایت‌های کسب‌وکارهای کوچک',
                    'پیاده‌سازی قالب‌های واکنش‌گرا با HTML5 و CSS3',
                    'ساخت فرم‌ها و ابزارهای داخلی با PHP و MySQL',
                ],
                'achievements' => [
                    'تحویل بیش از ۱۵ پروژه کوچک به‌صورت مستقل',
                    'یادگیری عملی چرخه کامل پروژه: تحلیل، اجرا، تحویل، پشتیبانی',
                ],
            ],
            'en' => [
                'position' => 'Freelance web developer',
                'description' => 'Working directly with small businesses on websites, small stores and internal tools.',
                'responsibilities' => [
                    'Designed and built websites for small businesses',
                    'Implemented responsive templates with HTML5 and CSS3',
                    'Built forms and internal tools with PHP and MySQL',
                ],
                'achievements' => [
                    'Delivered 15+ small projects independently',
                    'Learned the complete project cycle hands-on: analysis, build, delivery, support',
                ],
            ],
        ],
    ];

    foreach ($experiences as $index => $experience) {
        $seeder->translated('experiences', 'experience_translations', [
            'company' => $experience['company'],
            'company_url' => $experience['company_url'],
            'company_logo_media_id' => null,
            'location' => $experience['location'],
            'employment_type' => $experience['type'],
            'start_date' => $experience['start'],
            'end_date' => $experience['end'],
            'is_current' => $experience['current'],
            'is_active' => 1,
            'sort_order' => $index + 1,
        ], [
            'fa' => [
                'position' => $experience['fa']['position'],
                'description' => $experience['fa']['description'],
                'responsibilities_json' => $experience['fa']['responsibilities'],
                'achievements_json' => $experience['fa']['achievements'],
            ],
            'en' => [
                'position' => $experience['en']['position'],
                'description' => $experience['en']['description'],
                'responsibilities_json' => $experience['en']['responsibilities'],
                'achievements_json' => $experience['en']['achievements'],
            ],
        ], 'experience_id');
    }

    /* --------------------------------------------------------------------- */
    /* Education                                                             */
    /* --------------------------------------------------------------------- */
    $education = [
        [
            'institution' => 'Islamic Azad University, Tehran',
            'location' => 'Tehran, Iran',
            'start' => '2012-09-01',
            'end' => '2016-07-01',
            'gpa' => '17.2 / 20',
            'fa' => ['degree' => 'کارشناسی مهندسی کامپیوتر — نرم‌افزار', 'field' => 'مهندسی نرم‌افزار', 'description' => 'پروژه پایانی: طراحی و پیاده‌سازی سامانه مدیریت انبار تحت وب با PHP و MySQL.'],
            'en' => ['degree' => 'BSc Computer Engineering — Software', 'field' => 'Software engineering', 'description' => 'Final project: web-based inventory management system built with PHP and MySQL.'],
        ],
        [
            'institution' => 'Technical College of Isfahan',
            'location' => 'Isfahan, Iran',
            'start' => '2010-09-01',
            'end' => '2012-06-30',
            'gpa' => null,
            'fa' => ['degree' => 'کاردانی فناوری اطلاعات', 'field' => 'فناوری اطلاعات', 'description' => 'پایه‌های برنامه‌نویسی، شبکه و پایگاه داده.'],
            'en' => ['degree' => 'Associate degree in IT', 'field' => 'Information technology', 'description' => 'Foundations of programming, networking and databases.'],
        ],
    ];

    foreach ($education as $index => $item) {
        $seeder->translated('education', 'education_translations', [
            'institution' => $item['institution'],
            'institution_url' => null,
            'logo_media_id' => null,
            'location' => $item['location'],
            'start_date' => $item['start'],
            'end_date' => $item['end'],
            'is_current' => 0,
            'gpa' => $item['gpa'],
            'is_active' => 1,
            'sort_order' => $index + 1,
        ], [
            'fa' => ['degree' => $item['fa']['degree'], 'field' => $item['fa']['field'], 'description' => $item['fa']['description']],
            'en' => ['degree' => $item['en']['degree'], 'field' => $item['en']['field'], 'description' => $item['en']['description']],
        ], 'education_id');
    }

    /* --------------------------------------------------------------------- */
    /* Certifications                                                        */
    /* --------------------------------------------------------------------- */
    $certifications = [
        [
            'issuer' => 'Zend / Perforce',
            'credential_id' => 'ZCP-PHP-2019-4471',
            'credential_url' => 'https://example.com/verify/ZCP-PHP-2019-4471',
            'issue' => '2019-11-20',
            'expiry' => null,
            'fa' => ['title' => 'گواهی‌نامه PHP Certified Engineer', 'description' => 'آزمون تخصصی زبان PHP شامل امنیت، مدیریت نشست، عملکرد و معماری اپلیکیشن.'],
            'en' => ['title' => 'PHP Certified Engineer', 'description' => 'Professional PHP exam covering security, session handling, performance and application architecture.'],
        ],
        [
            'issuer' => 'Meta',
            'credential_id' => 'META-REACT-2022-8890',
            'credential_url' => 'https://example.com/verify/META-REACT-2022-8890',
            'issue' => '2022-03-12',
            'expiry' => null,
            'fa' => ['title' => 'گواهی‌نامه React Developer', 'description' => 'توسعه رابط کاربری با React، مدیریت وضعیت، هوک‌ها و بهینه‌سازی رندر.'],
            'en' => ['title' => 'React Developer Certificate', 'description' => 'Front-end development with React: state management, hooks and rendering optimisation.'],
        ],
        [
            'issuer' => 'Oracle',
            'credential_id' => 'ORA-MYSQL-2021-1023',
            'credential_url' => 'https://example.com/verify/ORA-MYSQL-2021-1023',
            'issue' => '2021-06-18',
            'expiry' => null,
            'fa' => ['title' => 'گواهی‌نامه MySQL Database Administrator', 'description' => 'طراحی، بهینه‌سازی و نگهداری پایگاه داده MySQL؛ ایندکس‌گذاری، تراکنش و پشتیبان‌گیری.'],
            'en' => ['title' => 'MySQL Database Administrator', 'description' => 'Designing, tuning and maintaining MySQL databases: indexing, transactions and backups.'],
        ],
    ];

    foreach ($certifications as $index => $certification) {
        $seeder->translated('certifications', 'certification_translations', [
            'issuer' => $certification['issuer'],
            'issuer_url' => null,
            'credential_id' => $certification['credential_id'],
            'credential_url' => $certification['credential_url'],
            'image_media_id' => null,
            'issue_date' => $certification['issue'],
            'expiry_date' => $certification['expiry'],
            'is_active' => 1,
            'sort_order' => $index + 1,
        ], [
            'fa' => ['title' => $certification['fa']['title'], 'description' => $certification['fa']['description']],
            'en' => ['title' => $certification['en']['title'], 'description' => $certification['en']['description']],
        ], 'certification_id');
    }
};
