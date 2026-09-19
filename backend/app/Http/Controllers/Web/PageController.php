<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\SeoService;
use App\Services\SsrRenderer;

/**
 * Public pages.
 *
 * Each route produces a complete HTML document: metadata from SeoService,
 * navigation from the database, a server-rendered content block (real rows,
 * escaped) and the `window.__BOOTSTRAP__` payload the React app hydrates from.
 * The result is fast on first paint, crawlable and fully usable without JS.
 */
final class PageController extends Controller
{
    public function root(Request $request): Response
    {
        $locale = $this->locales()->defaultCode();

        return Response::redirect('/' . $locale . ($request->queryString() !== '' ? '?' . $request->queryString() : ''), 302);
    }

    public function home(Request $request): Response
    {
        $locale = $this->locales()->resolve((string) $request->routeParam('locale', $this->locales()->defaultCode()));

        if (!$this->locales()->isSupported($locale)) {
            return $this->unknownLocale($request);
        }

        $this->track($request, $locale);
        $renderer = new SsrRenderer($this->content());
        $rendered = $renderer->home($locale);
        $home = $this->content()->home($locale);

        return $this->shell($request, $locale, [
            'path' => '/' . $locale,
            'type' => 'page',
            'is_home' => true,
            'title' => $locale === 'fa' ? 'آرش مهدوی — توسعه‌دهنده فول‌استک' : 'Arash Mahdavi — Full-Stack Web Developer',
            'description' => (string) ($home['page']['subtitle'] ?? ''),
            'image' => (string) $this->settings()->get('profile_image_url', ''),
            'prerender' => $rendered['html'],
            'bootstrap_extra' => ['home' => $home],
        ]);
    }

    public function projects(Request $request): Response
    {
        $locale = $this->routeLocale($request);
        $filters = [
            'category' => (string) $request->query('category', ''),
            'technology' => (string) $request->query('technology', ''),
            'search' => (string) $request->query('q', ''),
            'order' => (string) $request->query('order', 'featured'),
        ];

        $renderer = new SsrRenderer($this->content());
        $rendered = $renderer->projects($locale, $filters, $this->page($request));
        $this->track($request, $locale, 'page');

        return $this->shell($request, $locale, [
            'path' => '/' . $locale . '/projects',
            'type' => 'page',
            'title' => $locale === 'fa' ? 'پروژه‌ها' : 'Projects',
            'description' => $locale === 'fa'
                ? 'نمونه‌کارهای آرش مهدوی: فروشگاه اینترنتی، داشبورد مدیریتی، سامانه سازمانی و API.'
                : 'Selected work by Arash Mahdavi: e-commerce, dashboards, business systems and APIs.',
            'prerender' => $rendered['html'],
            'breadcrumbs' => $rendered['breadcrumbs'],
        ]);
    }

    public function project(Request $request): Response
    {
        $locale = $this->routeLocale($request);
        $slug = (string) $request->routeParam('slug');
        $project = $this->content()->project($slug, $locale);

        if ($project === null) {
            return $this->missing($request, $locale, 'project');
        }

        $renderer = new SsrRenderer($this->content());
        $rendered = $renderer->project($locale, $project);
        $this->track($request, $locale, 'project', (int) $project['id']);

        return $this->shell($request, $locale, [
            'path' => '/' . $locale . '/projects/' . $slug,
            'type' => 'project',
            'entity_id' => (int) $project['id'],
            'title' => (string) $project['title'],
            'description' => (string) ($project['summary'] ?? ''),
            'image' => (string) ($project['cover_url'] ?? ''),
            'slug_map' => $this->seo()->slugMap('project_translations', 'project_id', (int) $project['id']),
            'prerender' => $rendered['html'],
            'breadcrumbs' => $rendered['breadcrumbs'],
            'work' => [
                'title' => $project['title'],
                'summary' => $project['summary'] ?? '',
                'image' => $project['cover_url'] ?? null,
                'started_at' => $project['started_at'] ?? null,
                'completed_at' => $project['completed_at'] ?? null,
                'project_url' => $project['project_url'] ?? null,
                'technologies' => array_map(
                    static fn (array $technology): string => (string) $technology['name'],
                    (array) ($project['technologies'] ?? [])
                ),
            ],
        ]);
    }

    public function blog(Request $request): Response
    {
        $locale = $this->routeLocale($request);
        $filters = [
            'category' => (string) $request->query('category', ''),
            'tag' => (string) $request->query('tag', ''),
            'search' => (string) ($request->query('q', '') ?: ''),
        ];

        $renderer = new SsrRenderer($this->content());
        $rendered = $renderer->blog($locale, $filters, $this->page($request));
        $this->track($request, $locale, 'page');

        return $this->shell($request, $locale, [
            'path' => '/' . $locale . '/blog',
            'type' => 'page',
            'title' => $locale === 'fa' ? 'نوشته‌ها' : 'Articles',
            'description' => $locale === 'fa'
                ? 'یادداشت‌های فنی درباره معماری، امنیت، پایگاه داده و کارایی.'
                : 'Engineering notes on architecture, security, databases and performance.',
            'prerender' => $rendered['html'],
            'breadcrumbs' => $rendered['breadcrumbs'],
        ]);
    }

    public function post(Request $request): Response
    {
        $locale = $this->routeLocale($request);
        $slug = (string) $request->routeParam('slug');
        $post = $this->content()->post($slug, $locale);

        if ($post === null) {
            return $this->missing($request, $locale, 'post');
        }

        $renderer = new SsrRenderer($this->content());
        $rendered = $renderer->post($locale, $post);
        $this->track($request, $locale, 'post', (int) $post['id']);

        return $this->shell($request, $locale, [
            'path' => '/' . $locale . '/blog/' . $slug,
            'type' => 'post',
            'entity_id' => (int) $post['id'],
            'title' => (string) $post['title'],
            'description' => (string) ($post['excerpt'] ?? ''),
            'image' => (string) ($post['cover_url'] ?? ''),
            'slug_map' => $this->seo()->slugMap('blog_post_translations', 'post_id', (int) $post['id']),
            'prerender' => $rendered['html'],
            'breadcrumbs' => $rendered['breadcrumbs'],
            'article' => [
                'title' => $post['title'],
                'excerpt' => $post['excerpt'] ?? '',
                'image' => $post['cover_url'] ?? null,
                'published_at' => $post['published_at'] ?? null,
                'updated_at' => $post['updated_at'] ?? null,
                'category' => $post['category_name'] ?? null,
                'tags' => array_map(
                    static fn (array $tag): string => (string) $tag['name'],
                    (array) ($post['tags'] ?? [])
                ),
            ],
        ]);
    }

    public function services(Request $request): Response
    {
        $locale = $this->routeLocale($request);
        $renderer = new SsrRenderer($this->content());
        $rendered = $renderer->simple(
            $locale,
            'services',
            $locale === 'fa' ? 'خدمات' : 'Services',
            $locale === 'fa'
                ? 'از طراحی رابط کاربری تا API و نگه‌داری: کاری که برای رشد کسب‌وکار شما لازم است.'
                : 'From interface design to APIs and maintenance — what your business actually needs.'
        );
        $this->track($request, $locale, 'page');

        return $this->shell($request, $locale, [
            'path' => '/' . $locale . '/services',
            'type' => 'page',
            'title' => $locale === 'fa' ? 'خدمات' : 'Services',
            'description' => $locale === 'fa'
                ? 'طراحی و توسعه وب‌سایت، اپلیکیشن وب، فروشگاه اینترنتی، داشبورد، API و نگه‌داری.'
                : 'Websites, web applications, e-commerce, dashboards, APIs and maintenance.',
            'prerender' => $rendered['html'],
            'breadcrumbs' => $rendered['breadcrumbs'],
        ]);
    }

    public function service(Request $request): Response
    {
        $locale = $this->routeLocale($request);
        $slug = (string) $request->routeParam('slug');
        $service = $this->content()->serviceBySlug($slug, $locale);

        if ($service === null) {
            return $this->missing($request, $locale, 'service');
        }

        $renderer = new SsrRenderer($this->content());
        $body = $renderer->markdown((string) ($service['description'] ?? ''));
        $html = '<article class="ssr" data-ssr="service"><header><h1>'
            . Security::escape((string) $service['title']) . '</h1><p class="ssr-lead">'
            . Security::escape((string) ($service['summary'] ?? '')) . '</p></header>'
            . '<section>' . $body . '</section>';

        if (($service['features'] ?? []) !== []) {
            $html .= '<section><h2>' . ($locale === 'fa' ? 'شامل' : 'What is included') . '</h2><ul class="ssr-chips">';

            foreach ($service['features'] as $feature) {
                $html .= '<li>' . Security::escape((string) $feature) . '</li>';
            }

            $html .= '</ul></section>';
        }

        $html .= '</article>';
        $this->track($request, $locale, 'service', (int) $service['id']);

        return $this->shell($request, $locale, [
            'path' => '/' . $locale . '/services/' . $slug,
            'type' => 'service',
            'entity_id' => (int) $service['id'],
            'title' => (string) $service['title'],
            'description' => (string) ($service['summary'] ?? ''),
            'prerender' => $html,
            'breadcrumbs' => [
                ['label' => $locale === 'fa' ? 'خانه' : 'Home', 'href' => '/' . $locale],
                ['label' => $locale === 'fa' ? 'خدمات' : 'Services', 'href' => '/' . $locale . '/services'],
                ['label' => (string) $service['title'], 'href' => '/' . $locale . '/services/' . $slug],
            ],
        ]);
    }

    public function about(Request $request): Response
    {
        $locale = $this->routeLocale($request);
        $renderer = new SsrRenderer($this->content());
        $rendered = $renderer->simple(
            $locale,
            'about',
            $locale === 'fa' ? 'درباره من' : 'About me',
            $locale === 'fa'
                ? 'توسعه‌دهنده فول‌استک با تمرکز بر PHP، MySQL و React؛ اهل معماری تمیز و کارِ تمام‌شده.'
                : 'Full-stack developer focused on PHP, MySQL and React — clean architecture, finished work.'
        );
        $this->track($request, $locale, 'page');

        return $this->shell($request, $locale, [
            'path' => '/' . $locale . '/about',
            'type' => 'page',
            'title' => $locale === 'fa' ? 'درباره من' : 'About',
            'description' => $locale === 'fa'
                ? 'درباره آرش مهدوی، رویکرد کاری و مهارت‌های فنی.'
                : 'About Arash Mahdavi: approach, experience and technical skills.',
            'prerender' => $rendered['html'],
            'breadcrumbs' => $rendered['breadcrumbs'],
        ]);
    }

    public function contact(Request $request): Response
    {
        $locale = $this->routeLocale($request);
        $contact = $this->content()->contactInfo($locale);
        $isFa = $locale === 'fa';
        $flash = \App\Core\Session::pullFlash('contact_status');

        $html = '<div class="ssr" data-ssr="contact"><header><h1>' . ($isFa ? 'تماس' : 'Contact') . '</h1><p class="ssr-lead">'
            . ($isFa
                ? 'پروژه‌ای در ذهن دارید؟ فرم زیر را پر کنید؛ معمولاً در کمتر از یک روز کاری پاسخ می‌دهم.'
                : 'Have a project in mind? Send a note below — I usually reply within one business day.')
            . '</p></header>';

        if (is_array($flash)) {
            $html .= '<p class="ssr-alert ' . Security::escape((string) ($flash['type'] ?? 'info')) . '">'
                . Security::escape((string) ($flash['message'] ?? '')) . '</p>';
        }

        $html .= '<ul class="ssr-contact">';

        foreach ([
            ['email', $contact['email'] ?? '', $isFa ? 'ایمیل' : 'Email'],
            ['phone', $contact['phone_display'] ?? ($contact['phone'] ?? ''), $isFa ? 'تلفن' : 'Phone'],
            ['location', $contact['location'] ?? '', $isFa ? 'موقعیت' : 'Location'],
            ['hours', $contact['working_hours'] ?? '', $isFa ? 'ساعات کاری' : 'Working hours'],
        ] as [$key, $value, $label]) {
            if ((string) $value !== '') {
                $html .= '<li><span>' . Security::escape($label) . '</span><strong dir="auto">' . Security::escape((string) $value) . '</strong></li>';
            }
        }

        $html .= '</ul>';

        // Server-rendered form: works without JavaScript and posts to the same
        // validated, throttled controller the API uses.
        $html .= '<form class="ssr-form" method="post" action="/' . $locale . '/contact" novalidate>'
            . Security::csrfField()
            . '<input type="hidden" name="locale" value="' . Security::escape($locale) . '">'
            . '<div class="ssr-field"><label for="contact-name">' . ($isFa ? 'نام' : 'Name') . '</label>'
            . '<input id="contact-name" name="name" type="text" required maxlength="160" autocomplete="name"></div>'
            . '<div class="ssr-field"><label for="contact-email">' . ($isFa ? 'ایمیل' : 'Email') . '</label>'
            . '<input id="contact-email" name="email" type="email" required maxlength="190" autocomplete="email" dir="ltr"></div>'
            . '<div class="ssr-field"><label for="contact-subject">' . ($isFa ? 'موضوع' : 'Subject') . '</label>'
            . '<input id="contact-subject" name="subject" type="text" maxlength="200"></div>'
            . '<div class="ssr-field"><label for="contact-message">' . ($isFa ? 'پیام' : 'Message') . '</label>'
            . '<textarea id="contact-message" name="message" rows="6" required minlength="10"></textarea></div>'
            // Honeypot: hidden from humans, irresistible to bots.
            . '<div class="ssr-honeypot" aria-hidden="true"><label for="contact-website">Website</label>'
            . '<input id="contact-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>'
            . '<button type="submit" class="ssr-cta">' . ($isFa ? 'ارسال پیام' : 'Send message') . '</button>'
            . '</form></div>';

        $this->track($request, $locale, 'page');

        return $this->shell($request, $locale, [
            'path' => '/' . $locale . '/contact',
            'type' => 'page',
            'title' => $isFa ? 'تماس' : 'Contact',
            'description' => $isFa
                ? 'راه‌های تماس با آرش مهدوی و فرم ارسال پیام.'
                : 'Ways to reach Arash Mahdavi, plus a direct message form.',
            'prerender' => $html,
            'breadcrumbs' => [['label' => $isFa ? 'خانه' : 'Home', 'href' => '/' . $locale], ['label' => $isFa ? 'تماس' : 'Contact', 'href' => '/' . $locale . '/contact']],
            'contact_page' => true,
        ]);
    }

    public function testimonials(Request $request): Response
    {
        $locale = $this->routeLocale($request);
        $isFa = $locale === 'fa';
        $renderer = new SsrRenderer($this->content());
        $html = '<div class="ssr" data-ssr="testimonials"><header><h1>' . ($isFa ? 'نظر مشتریان' : 'Testimonials') . '</h1></header><ul class="ssr-cards">';

        foreach ($this->content()->testimonials($locale) as $testimonial) {
            $html .= '<li><blockquote>' . Security::escape((string) $testimonial['quote']) . '</blockquote>'
                . '<p class="ssr-meta">' . Security::escape((string) $testimonial['client_name']) . ' — '
                . Security::escape((string) ($testimonial['client_position'] ?? '')) . '</p></li>';
        }

        $html .= '</ul></div>';

        return $this->shell($request, $locale, [
            'path' => '/' . $locale . '/testimonials',
            'type' => 'page',
            'title' => $isFa ? 'نظر مشتریان' : 'Testimonials',
            'description' => $isFa ? 'تجربه همکاری مشتریان با آرش مهدوی.' : 'What clients say about working with Arash Mahdavi.',
            'prerender' => $html,
            'breadcrumbs' => [['label' => $isFa ? 'خانه' : 'Home', 'href' => '/' . $locale], ['label' => $isFa ? 'نظر مشتریان' : 'Testimonials', 'href' => '/' . $locale . '/testimonials']],
        ]);
    }

    public function resume(Request $request): Response
    {
        $locale = $this->routeLocale($request);
        $isFa = $locale === 'fa';
        $resume = $this->content()->resume($locale);
        $sections = [];

        foreach ([
            'experiences' => $isFa ? 'تجربه کاری' : 'Experience',
            'education' => $isFa ? 'تحصیلات' : 'Education',
            'certifications' => $isFa ? 'گواهی‌نامه‌ها' : 'Certifications',
        ] as $key => $label) {
            $sections[$key] = ['label' => $label, 'items' => $this->content()->{$key}($locale)];
        }

        $html = '<div class="ssr" data-ssr="resume"><header><h1>' . ($isFa ? 'رزومه' : 'Résumé') . '</h1>';

        if ($resume !== null && ($resume['summary'] ?? '') !== '') {
            $html .= '<div class="ssr-lead">' . (new SsrRenderer($this->content()))->markdown((string) $resume['summary']) . '</div>';
        }

        if ($resume !== null && ($resume['download_url'] ?? '') !== '') {
            $html .= '<p class="ssr-actions"><a class="ssr-cta" href="' . Security::escape((string) $resume['download_url'])
                . '" download>' . ($isFa ? 'دانلود فایل PDF' : 'Download PDF') . '</a>'
                . '<a class="ssr-cta ssr-cta-ghost" href="/' . $locale . '/resume/print">'
                . ($isFa ? 'نسخه چاپی' : 'Print version') . '</a></p>';
        }

        $html .= '</header>';

        foreach ($sections as $section) {
            if (($section['items'] ?? []) === []) {
                continue;
            }

            $html .= '<section><h2>' . Security::escape($section['label']) . '</h2><ol class="ssr-timeline">';

            foreach ($section['items'] as $item) {
                $title = (string) ($item['position'] ?? $item['degree'] ?? $item['title'] ?? '');
                $org = (string) ($item['company'] ?? $item['institution'] ?? $item['issuer'] ?? '');
                $start = (string) ($item['start_date'] ?? $item['issued_at'] ?? '');
                $end = (string) ($item['end_date'] ?? '');
                $html .= '<li><h3>' . Security::escape($title) . '</h3><p class="ssr-meta">'
                    . Security::escape($org)
                    . ($start !== '' ? ' · ' . Security::escape($start) . ($end !== '' ? ' — ' . Security::escape($end) : '') : '')
                    . '</p></li>';
            }

            $html .= '</ol></section>';
        }

        $html .= '</div>';
        $this->track($request, $locale, 'page');

        return $this->shell($request, $locale, [
            'path' => '/' . $locale . '/resume',
            'type' => 'page',
            'title' => $isFa ? 'رزومه' : 'Résumé',
            'description' => $isFa ? 'رزومه کامل آرش مهدوی: تجربه، تحصیلات و گواهی‌نامه‌ها.' : 'Full résumé: experience, education and certifications.',
            'prerender' => $html,
            'breadcrumbs' => [['label' => $isFa ? 'خانه' : 'Home', 'href' => '/' . $locale], ['label' => $isFa ? 'رزومه' : 'Résumé', 'href' => '/' . $locale . '/resume']],
        ]);
    }

    /** Print-friendly résumé (no chrome, print stylesheet, auto-opens the dialog). */
    public function resumePrint(Request $request): Response
    {
        $locale = $this->routeLocale($request);
        $response = $this->resume($request);
        $html = str_replace('</body>', '<script>window.addEventListener("load",function(){window.print();});</script></body>', $response->content());

        return Response::html(str_replace('<body class="site"', '<body class="site print-view"', $html));
    }

    public function search(Request $request): Response
    {
        $locale = $this->routeLocale($request);
        $isFa = $locale === 'fa';
        $term = trim((string) $request->query('q', ''));
        $results = $term === '' ? ['projects' => [], 'posts' => [], 'services' => []] : $this->content()->search($term, $locale, 12);
        $total = count($results['projects'] ?? []) + count($results['posts'] ?? []) + count($results['services'] ?? []);

        $html = '<div class="ssr" data-ssr="search"><header><h1>' . ($isFa ? 'جست‌وجو' : 'Search') . '</h1>'
            . '<form class="ssr-search" method="get" action="/' . $locale . '/search" role="search">'
            . '<input type="search" name="q" value="' . Security::escape($term) . '" placeholder="'
            . ($isFa ? 'جست‌وجو در پروژه‌ها و مقالات…' : 'Search projects and articles…') . '" minlength="2">'
            . '<button type="submit">' . ($isFa ? 'جست‌وجو' : 'Search') . '</button></form>';

        if ($term !== '') {
            $html .= '<p class="ssr-meta">' . ($isFa
                ? $total . ' نتیجه برای «' . Security::escape($term) . '»'
                : $total . ' result' . ($total === 1 ? '' : 's') . ' for “' . Security::escape($term) . '”') . '</p>';
        }

        $html .= '</header>';

        foreach ([
            'projects' => $isFa ? 'پروژه‌ها' : 'Projects',
            'posts' => $isFa ? 'نوشته‌ها' : 'Articles',
            'services' => $isFa ? 'خدمات' : 'Services',
        ] as $key => $label) {
            if (($results[$key] ?? []) === []) {
                continue;
            }

            $html .= '<section><h2>' . Security::escape($label) . '</h2><ul class="ssr-cards">';

            foreach ($results[$key] as $item) {
                $html .= '<li><a href="' . Security::escape((string) ($item['url'] ?? '#')) . '"><h3>'
                    . Security::escape((string) ($item['title'] ?? '')) . '</h3><p>'
                    . Security::escape((string) ($item['summary'] ?? $item['excerpt'] ?? '')) . '</p></a></li>';
            }

            $html .= '</ul></section>';
        }

        $html .= '</div>';

        return $this->shell($request, $locale, [
            'path' => '/' . $locale . '/search',
            'type' => 'page',
            'title' => $isFa ? 'جست‌وجو' : 'Search',
            'description' => $isFa ? 'جست‌وجو در محتوای سایت.' : 'Search across the site.',
            'robots' => 'noindex,follow',
            'prerender' => $html,
        ]);
    }

    /** Editable pages created in Admin → Pages (privacy, terms, faq …). */
    public function contentPage(Request $request): Response
    {
        $locale = $this->routeLocale($request);
        $key = (string) $request->routeParam('page');
        $page = $this->content()->page($key, $locale);

        if ($page === null) {
            return $this->missing($request, $locale, 'page');
        }

        $renderer = new SsrRenderer($this->content());
        $rendered = $renderer->simple($locale, $key, (string) $page['title'], (string) ($page['subtitle'] ?? ''));

        return $this->shell($request, $locale, [
            'path' => '/' . $locale . '/' . $key,
            'type' => 'page',
            'title' => (string) $page['title'],
            'description' => (string) ($page['subtitle'] ?? ''),
            'prerender' => $rendered['html'],
            'breadcrumbs' => $rendered['breadcrumbs'],
        ]);
    }

    public function sitemap(Request $request): Response
    {
        $unused = $request;
        $path = rtrim((string) Config::get('app.public_path', ''), '/') . '/sitemap.xml';

        // Generated on first request and whenever the admin saves content;
        // robots.txt is written next to it at the same time.
        if (!is_file($path) || filesize($path) === 0) {
            $this->seo()->writeSitemap($this->content());
        }

        return Response::file($path, 'application/xml; charset=utf-8')->withCache(3600);
    }

    public function robots(Request $request): Response
    {
        $unused = $request;
        $path = rtrim((string) Config::get('app.public_path', ''), '/') . '/robots.txt';

        if (!is_file($path) || filesize($path) === 0) {
            $this->seo()->writeSitemap($this->content());
        }

        if (!is_file($path)) {
            return Response::text("User-agent: *\nAllow: /\nDisallow: /admin\n", 200)->withCache(3600);
        }

        return Response::file($path, 'text/plain; charset=utf-8')->withCache(3600);
    }

    public function health(Request $request): Response
    {
        $unused = $request;

        return Response::json([
            'status' => 'ok',
            'env' => (string) Config::get('app.env', 'production'),
            'database' => \App\Core\Database::driver(),
            'locale' => $this->locales()->defaultCode(),
            'time' => gmdate('c'),
        ]);
    }

    /* --------------------------------------------------------------------- */
    /* Shell                                                                 */
    /* --------------------------------------------------------------------- */

    /**
     * @param array<string,mixed> $options
     */
    private function shell(Request $request, string $locale, array $options): Response
    {
        $path = (string) $options['path'];
        $bootstrap = $this->bootstrapPayload($locale);
        $meta = $this->seo()->forPage([
            'locale' => $locale,
            'path' => $path,
            'type' => (string) ($options['type'] ?? 'page'),
            'entity_id' => $options['entity_id'] ?? null,
            'fallback' => [
                'title' => (string) ($options['title'] ?? ''),
                'description' => (string) ($options['description'] ?? ''),
                'image' => (string) ($options['image'] ?? ''),
                'is_home' => (bool) ($options['is_home'] ?? false),
            ],
            'slug_map' => $options['slug_map'] ?? [],
        ]);

        if (($options['robots'] ?? null) !== null) {
            $meta['robots'] = (string) $options['robots'];
        }

        $structured = $this->seo()->structuredData([
            'locale' => $locale,
            'path' => $path,
            'meta' => $meta,
            'breadcrumbs' => $options['breadcrumbs'] ?? [],
            'article' => $options['article'] ?? null,
            'work' => $options['work'] ?? null,
        ]);

        if (($options['bootstrap_extra'] ?? []) !== []) {
            $bootstrap = array_merge($bootstrap, (array) $options['bootstrap_extra']);
        }

        $bootstrap['seo'] = $meta;
        $bootstrap['breadcrumbs'] = $options['breadcrumbs'] ?? [];
        $bootstrap['route'] = ['path' => $path, 'type' => $options['type'] ?? 'page'];

        if (($options['contact_page'] ?? false) === true) {
            $bootstrap['contact'] = $this->content()->contactInfo($locale);
        }

        $html = View::spaShell([
            'lang' => $locale,
            'dir' => $this->locales()->direction($locale),
            'theme' => 'dark',
            'title' => (string) $meta['document_title'],
            'head' => $this->seo()->head($meta, $structured),
            'bootstrap' => $bootstrap,
            'prerender' => $this->chrome($locale, (string) ($options['prerender'] ?? '')),
            'kind' => 'public',
        ]);

        return Response::html($html)
            ->withHeader('Content-Language', $locale)
            ->withCache(180);
    }

    /** Site header + footer wrapped around the server-rendered content. */
    private function chrome(string $locale, string $content): string
    {
        $isFa = $locale === 'fa';
        $flat = $this->settings()->publicPayload($locale)['flat'] ?? [];
        $siteName = (string) ($flat['site_name'] ?? 'Arash Mahdavi');
        $initial = mb_substr($siteName, 0, 1, 'UTF-8');

        $header = '<header class="site-header"><div class="wrap">'
            . '<a class="brand" href="/' . $locale . '"><span class="brand-mark">' . Security::escape($initial) . '</span>'
            . '<span class="brand-text"><strong>' . Security::escape($siteName) . '</strong><small>'
            . Security::escape((string) ($flat['hero_title'] ?? '')) . '</small></span></a>'
            . '<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">'
            . '<span></span><span></span><span></span><span class="sr-only">' . ($isFa ? 'منو' : 'Menu') . '</span></button>'
            . '<nav id="site-nav" class="site-nav" aria-label="' . ($isFa ? 'منوی اصلی' : 'Main navigation') . '"><ul>';

        foreach ($this->navigation()->header($locale) as $item) {
            $header .= '<li><a href="' . Security::escape((string) $item['href']) . '"'
                . ((bool) ($item['external'] ?? false) ? ' target="_blank" rel="noopener"' : '') . '>'
                . Security::escape((string) $item['label']) . '</a>';

            if (($item['children'] ?? []) !== []) {
                $header .= '<ul class="submenu">';

                foreach ($item['children'] as $child) {
                    $header .= '<li><a href="' . Security::escape((string) $child['href']) . '">'
                        . Security::escape((string) $child['label']) . '</a></li>';
                }

                $header .= '</ul>';
            }

            $header .= '</li>';
        }

        $header .= '</ul></nav><div class="header-actions">';
        $header .= '<div class="lang-switch" data-locale="' . Security::escape($locale) . '">';

        foreach ($this->locales()->publicList() as $language) {
            $code = (string) $language['code'];
            $header .= '<a href="' . Security::escape($this->switchHref($locale, $code)) . '"'
                . ($code === $locale ? ' class="is-active" aria-current="true"' : '')
                . ' hreflang="' . Security::escape($code) . '">' . Security::escape((string) ($language['native_name'] ?? strtoupper($code))) . '</a>';
        }

        $header .= '</div>';

        if ((string) ($flat['availability_status'] ?? '') !== '') {
            $header .= '<a class="header-cta" href="/' . $locale . '/contact">' . Security::escape((string) $flat['availability_status']) . '</a>';
        }

        $header .= '</div></div></header>';

        $footer = '<footer class="site-footer"><div class="wrap">';
        $footerColumns = $this->navigation()->footer($locale);

        foreach ($footerColumns as $location => $items) {
            if ($items === []) {
                continue;
            }

            $titleKey = match ($location) {
                'footer_quick' => 'footer_title_quick',
                'footer_services' => 'footer_title_services',
                'footer_company' => 'footer_title_company',
                default => 'footer_title_legal',
            };
            $footer .= '<div class="footer-col"><h3>' . Security::escape((string) ($flat[$titleKey] ?? '')) . '</h3><ul>';

            foreach ($items as $item) {
                $footer .= '<li><a href="' . Security::escape((string) $item['href']) . '">'
                    . Security::escape((string) $item['label']) . '</a></li>';
            }

            $footer .= '</ul></div>';
        }

        $footer .= '<div class="footer-col"><h3>' . ($isFa ? 'ارتباط' : 'Get in touch') . '</h3><ul>';

        if ((string) ($flat['contact_email'] ?? '') !== '') {
            $footer .= '<li><a dir="ltr" href="mailto:' . Security::escape((string) $flat['contact_email']) . '">'
                . Security::escape((string) $flat['contact_email']) . '</a></li>';
        }

        if ((string) ($flat['contact_phone_display'] ?? '') !== '') {
            $footer .= '<li><a dir="ltr" href="tel:' . Security::escape((string) $flat['contact_phone']) . '">'
                . Security::escape((string) $flat['contact_phone_display']) . '</a></li>';
        }

        if ((string) ($flat['contact_location'] ?? '') !== '') {
            $footer .= '<li>' . Security::escape((string) $flat['contact_location']) . '</li>';
        }

        $footer .= '</ul><ul class="socials">';

        foreach ($this->navigation()->socials($locale) as $social) {
            $footer .= '<li><a href="' . Security::escape(Security::safeUrl((string) $social['url'])) . '"'
                . ' target="_blank" rel="noopener me" aria-label="' . Security::escape((string) $social['label']) . '">'
                . Security::escape((string) $social['label']) . '</a></li>';
        }

        $footer .= '</ul></div></div><div class="wrap footer-bottom"><p>'
            . Security::escape((string) ($flat['footer_copyright'] ?? ('© ' . gmdate('Y') . ' ' . $siteName)))
            . '</p><p class="built-with">' . Security::escape((string) ($flat['footer_note'] ?? '')) . '</p></div></footer>';

        // Tiny progressive enhancement (mobile nav + theme); React replaces it later.
        $script = '<script>(function(){var b=document.querySelector(".nav-toggle");if(!b)return;'
            . 'b.addEventListener("click",function(){var n=document.getElementById("site-nav");'
            . 'var open=n.classList.toggle("is-open");b.setAttribute("aria-expanded",open?"true":"false");});})();</script>';

        return $header . '<main id="content">' . $content . '</main>' . $footer . $script;
    }

    /** Locale switch that keeps the current path and asks the API for the translated slug. */
    private function switchHref(string $from, string $to): string
    {
        if ($to === $from) {
            return '/' . $to;
        }

        $path = '/' . trim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
        $segments = array_values(array_filter(explode('/', $path)));
        $segments[0] = $to;
        $suffix = count($segments) > 1 ? '/' . implode('/', array_slice($segments, 1)) : '';

        // Slugs are per language, so the switcher asks for the translated one;
        // the client-side app upgrades this link when it has the answer.
        return '/' . $to . $suffix;
    }

    /** @return array<string,mixed> */
    private function bootstrapPayload(string $locale): array
    {
        $navigation = $this->navigation()->payload($locale);
        $settings = $this->settings()->publicPayload($locale);

        return [
            'locale' => $locale,
            'direction' => $this->locales()->direction($locale),
            'locales' => $this->locales()->publicList(),
            'settings' => $settings,
            'strings' => \App\Services\TranslationService::forLocale($locale),
            'navigation' => $navigation,
            'contact' => $this->content()->contactInfo($locale),
            'stats' => $this->content()->statistics(),
            'csrf_token' => Security::csrfToken(),
            'paths' => [
                'home' => '/' . $locale,
                'projects' => '/' . $locale . '/projects',
                'blog' => '/' . $locale . '/blog',
                'services' => '/' . $locale . '/services',
                'about' => '/' . $locale . '/about',
                'contact' => '/' . $locale . '/contact',
                'resume' => '/' . $locale . '/resume',
                'api' => '/api/v1',
            ],
        ];
    }

    private function routeLocale(Request $request): string
    {
        $locale = $this->locales()->resolve((string) $request->routeParam('locale', ''));

        if (!$this->locales()->isSupported($locale)) {
            $locale = $this->locales()->defaultCode();
        }

        return $locale;
    }

    private function unknownLocale(Request $request): Response
    {
        $unused = $request;

        return Response::redirect('/' . $this->locales()->defaultCode(), 302);
    }

    private function missing(Request $request, string $locale, string $what): Response
    {
        $unused = $request;

        throw \App\Core\HttpException::notFound(
            $locale === 'fa'
                ? 'این ' . ($what === 'post' ? 'نوشته' : 'صفحه') . ' پیدا نشد.'
                : 'The requested ' . $what . ' could not be found.'
        );
    }

    private function track(Request $request, string $locale, ?string $entityType = null, ?int $entityId = null): void
    {
        // Skip bots and asset-ish requests; a failure here never breaks a page.
        if (preg_match('/bot|crawler|spider|preview/i', $request->userAgent()) === 1) {
            return;
        }

        try {
            (new DashboardService())->recordVisit($request->path(), $locale, $entityType, $entityId);
        } catch (\Throwable) {
            // analytics must never take the site down
        }
    }
}
