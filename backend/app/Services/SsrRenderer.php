<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Security;

/**
 * Server-side pre-render of the public pages.
 *
 * The site is a React application, but every route still ships real HTML: a
 * crawler, a slow connection or a visitor without JavaScript gets the actual
 * content — headings, cards, article body, breadcrumbs — built from the same
 * database rows the API serves, escaped on output. React then hydrates and
 * takes over, so there is never a blank page while the bundle boots.
 */
final class SsrRenderer
{
    public function __construct(private ?ContentService $content = null)
    {
        $this->content ??= new ContentService();
    }

    /** @return array{html:string,breadcrumbs:array<int,array{label:string,href:string}>} */
    public function home(string $locale): array
    {
        $home = $this->content->home($locale);
        $isFa = $locale === 'fa';
        $hero = $this->sectionOfType($home['sections'] ?? [], 'hero');
        $page = $home['page'] ?? [];

        $html = '<div class="ssr" data-ssr="home">';
        $html .= '<header class="ssr-hero">';

        if (($hero['eyebrow'] ?? '') !== '') {
            $html .= '<p class="ssr-eyebrow">' . $this->e($hero['eyebrow']) . '</p>';
        }

        $html .= '<h1>' . $this->e($hero['title'] ?? ($page['title'] ?? '')) . '</h1>';

        $subtitle = $hero['subtitle'] ?? ($page['subtitle'] ?? '');

        if ($subtitle !== '') {
            $html .= '<p class="ssr-lead">' . $this->e($subtitle) . '</p>';
        }

        if (($hero['body'] ?? '') !== '') {
            $html .= '<div class="ssr-hero-body">' . $this->markdown((string) $hero['body']) . '</div>';
        }

        $actions = [];

        if (($hero['cta_label'] ?? '') !== '') {
            $actions[] = '<a class="ssr-cta" href="' . $this->e($hero['cta_url'] ?? '#contact') . '">'
                . $this->e($hero['cta_label']) . '</a>';
        }

        if (($hero['cta2_label'] ?? '') !== '') {
            $actions[] = '<a class="ssr-cta ssr-cta-ghost" href="' . $this->e($hero['cta2_url'] ?? '#projects') . '">'
                . $this->e($hero['cta2_label']) . '</a>';
        }

        if ($actions !== []) {
            $html .= '<p class="ssr-actions">' . implode(' ', $actions) . '</p>';
        }

        if (($home['technologies'] ?? []) !== []) {
            $html .= '<ul class="ssr-badges">';

            foreach (array_slice($home['technologies'], 0, 12) as $technology) {
                $html .= '<li>' . $this->e((string) $technology['name']) . '</li>';
            }

            $html .= '</ul>';
        }

        $html .= '</header>';

        // Stats (numbers come from settings, so they are edited in one place).
        if (($home['statistics'] ?? []) !== []) {
            $html .= '<section class="ssr-section" id="stats"><ul class="ssr-stats">';

            foreach ($home['statistics'] as $stat) {
                $html .= '<li><strong>' . $this->e((string) $stat['value'] . (string) ($stat['suffix'] ?? '')) . '</strong></li>';
            }

            $html .= '</ul></section>';
        }

        $html .= $this->sectionBlock($home['services'] ?? [], 'services', $isFa ? 'خدمات' : 'Services', 'title', 'description');
        $html .= $this->projectBlock($home['featured_projects'] ?? [], $isFa ? 'پروژه‌های منتخب' : 'Selected projects');
        $html .= $this->skillsBlock($home['skills'] ?? [], $isFa);
        $html .= $this->experienceBlock($home['experiences'] ?? [], $isFa);
        $html .= $this->testimonialBlock($home['testimonials'] ?? [], $isFa);
        $html .= $this->postBlock($home['posts'] ?? [], $isFa ? 'آخرین نوشته‌ها' : 'Latest articles');
        $html .= '</div>';

        return ['html' => $html, 'breadcrumbs' => []];
    }

    /** @return array{html:string,breadcrumbs:array<int,array{label:string,href:string}>} */
    public function projects(string $locale, array $filters = [], int $page = 1): array
    {
        $isFa = $locale === 'fa';
        $result = $this->content->projects($locale, $filters, (int) Config::get('app.per_page', 9), $page);
        $base = '/' . $locale . '/projects';

        $html = '<div class="ssr" data-ssr="projects">';
        $html .= '<header><h1>' . ($isFa ? 'پروژه‌ها' : 'Projects') . '</h1><p>' . ($isFa
            ? 'فروشگاه اینترنتی، داشبورد مدیریتی، سامانه سازمانی و API.'
            : 'E-commerce platforms, admin dashboards, business systems and APIs.') . '</p></header>';

        if (($result['items'] ?? []) === []) {
            $html .= '<p>' . ($isFa ? 'به‌زودی پروژه‌های بیشتری اضافه می‌شود.' : 'More projects are coming soon.') . '</p>';
        } else {
            $html .= $this->projectBlock($result['items']);
            $html .= $this->pagination($base, (int) $result['page'], (int) $result['total_pages'], $isFa);
        }

        $html .= '</div>';

        return ['html' => $html, 'breadcrumbs' => $this->crumbs($locale, $isFa ? 'پروژه‌ها' : 'Projects', $base)];
    }

    /** @return array{html:string,breadcrumbs:array<int,array{label:string,href:string}>} */
    public function project(string $locale, array $project): array
    {
        $isFa = $locale === 'fa';
        $base = '/' . $locale . '/projects';

        $html = '<article class="ssr" data-ssr="project">';
        $html .= '<header>';

        if (($project['category_name'] ?? '') !== '') {
            $html .= '<p class="ssr-eyebrow">' . $this->e($project['category_name']) . '</p>';
        }

        $html .= '<h1>' . $this->e((string) $project['title']) . '</h1>';
        $html .= '<p class="ssr-lead">' . $this->e((string) ($project['summary'] ?? '')) . '</p></header>';

        $facts = [];

        if (($project['client'] ?? '') !== '') {
            $facts[] = ($isFa ? 'کارفرما: ' : 'Client: ') . (string) $project['client'];
        }

        if (($project['completed_at'] ?? '') !== '') {
            $facts[] = ($isFa ? 'تحویل: ' : 'Delivered: ') . (string) $project['completed_at'];
        }

        if ($facts !== []) {
            $html .= '<p class="ssr-meta">' . $this->e(implode(' · ', $facts)) . '</p>';
        }

        if (($project['technologies'] ?? []) !== []) {
            $html .= '<ul class="ssr-chips">';

            foreach ($project['technologies'] as $technology) {
                $html .= '<li>' . $this->e(is_array($technology) ? (string) ($technology['name'] ?? '') : (string) $technology) . '</li>';
            }

            $html .= '</ul>';
        }

        foreach ([['description', null], ['challenge', $isFa ? 'چالش' : 'The challenge'], ['solution', $isFa ? 'راهکار' : 'The solution'], ['results', $isFa ? 'نتیجه' : 'The result']] as [$key, $heading]) {
            if (($project[$key] ?? '') === '') {
                continue;
            }

            $html .= '<section>';

            if ($heading !== null) {
                $html .= '<h2>' . $this->e($heading) . '</h2>';
            }

            $html .= $this->markdown((string) $project[$key]) . '</section>';
        }

        if (($project['gallery'] ?? []) !== []) {
            $html .= '<section><h2>' . ($isFa ? 'تصاویر پروژه' : 'Screenshots') . '</h2><ul class="ssr-gallery">';

            foreach ($project['gallery'] as $image) {
                $html .= '<li><img src="' . $this->e((string) $image['url']) . '" alt="'
                    . $this->e((string) ($image['alt'] ?? $project['title'])) . '" loading="lazy" decoding="async"></li>';
            }

            $html .= '</ul></section>';
        }

        $links = [];

        foreach ([['project_url', $isFa ? 'مشاهده وب‌سایت' : 'Visit website'], ['github_url', 'GitHub']] as [$key, $label]) {
            if (($project[$key] ?? '') !== '') {
                $links[] = '<a href="' . $this->e(Security::safeUrl((string) $project[$key])) . '" rel="noopener nofollow" target="_blank">'
                    . $this->e($label) . '</a>';
            }
        }

        if ($links !== []) {
            $html .= '<p class="ssr-actions">' . implode(' ', $links) . '</p>';
        }

        if (($project['related'] ?? []) !== []) {
            $html .= $this->projectBlock($project['related'], $isFa ? 'پروژه‌های مرتبط' : 'Related projects');
        }

        $html .= '</article>';

        return [
            'html' => $html,
            'breadcrumbs' => $this->crumbs($locale, $isFa ? 'پروژه‌ها' : 'Projects', $base, [
                'label' => (string) $project['title'],
                'href' => $base . '/' . $project['slug'],
            ]),
        ];
    }

    /** @return array{html:string,breadcrumbs:array<int,array{label:string,href:string}>} */
    public function blog(string $locale, array $filters = [], int $page = 1): array
    {
        $isFa = $locale === 'fa';
        $result = $this->content->posts($locale, $filters, 9, $page);
        $base = '/' . $locale . '/blog';

        $html = '<div class="ssr" data-ssr="blog"><header><h1>' . ($isFa ? 'نوشته‌ها' : 'Articles') . '</h1><p>'
            . ($isFa
                ? 'یادداشت‌های فنی درباره معماری نرم‌افزار، امنیت، پایگاه داده و کارایی.'
                : 'Engineering notes on architecture, security, databases and performance.')
            . '</p></header>';

        if (($result['items'] ?? []) === []) {
            $html .= '<p>' . ($isFa ? 'به‌زودی مقاله‌های تازه منتشر می‌شود.' : 'New articles are on the way.') . '</p>';
        } else {
            $html .= $this->postBlock($result['items']);
            $html .= $this->pagination($base, (int) $result['page'], (int) $result['total_pages'], $isFa);
        }

        $html .= '</div>';

        return ['html' => $html, 'breadcrumbs' => $this->crumbs($locale, $isFa ? 'نوشته‌ها' : 'Articles', $base)];
    }

    /** @return array{html:string,breadcrumbs:array<int,array{label:string,href:string}>} */
    public function post(string $locale, array $post): array
    {
        $isFa = $locale === 'fa';
        $base = '/' . $locale . '/blog';

        $html = '<article class="ssr" data-ssr="post"><header>';

        if (($post['category_name'] ?? '') !== '') {
            $html .= '<p class="ssr-eyebrow">' . $this->e($post['category_name']) . '</p>';
        }

        $html .= '<h1>' . $this->e((string) $post['title']) . '</h1>';
        $html .= '<p class="ssr-meta">' . $this->e((string) ($post['published_at'] ?? '')) . ' · '
            . $this->e($this->readingTime((string) ($post['content'] ?? ''), $isFa)) . '</p></header>';

        if (($post['cover_url'] ?? '') !== '') {
            $html .= '<figure><img src="' . $this->e((string) $post['cover_url']) . '" alt="'
                . $this->e((string) $post['title']) . '" loading="eager" decoding="async"></figure>';
        }

        $html .= '<section>' . $this->markdown((string) ($post['content'] ?? '')) . '</section>';

        if (($post['tags'] ?? []) !== []) {
            $html .= '<ul class="ssr-chips">';

            foreach ($post['tags'] as $tag) {
                $html .= '<li>' . $this->e(is_array($tag) ? (string) ($tag['name'] ?? '') : (string) $tag) . '</li>';
            }

            $html .= '</ul>';
        }

        if (($post['related'] ?? []) !== []) {
            $html .= $this->postBlock($post['related'], $isFa ? 'مطالب مرتبط' : 'Related articles');
        }

        $html .= '</article>';

        return [
            'html' => $html,
            'breadcrumbs' => $this->crumbs($locale, $isFa ? 'نوشته‌ها' : 'Articles', $base, [
                'label' => (string) $post['title'],
                'href' => $base . '/' . $post['slug'],
            ]),
        ];
    }

    /** Generic content page assembled from `pages` + `sections`. */
    public function simple(string $locale, string $key, string $title, string $intro = ''): array
    {
        $isFa = $locale === 'fa';
        $sections = $this->content->sections($key, $locale);

        $html = '<div class="ssr" data-ssr="' . $this->e($key) . '"><header><h1>' . $this->e($title) . '</h1>';

        if ($intro !== '') {
            $html .= '<p class="ssr-lead">' . $this->e($intro) . '</p>';
        }

        $html .= '</header>';

        foreach ($sections as $section) {
            $eyebrow = (string) ($section['eyebrow'] ?? '');

            if ($eyebrow !== '') {
                $html .= '<p class="ssr-eyebrow">' . $this->e($eyebrow) . '</p>';
            }

            if (($section['title'] ?? '') !== '') {
                $html .= '<h2>' . $this->e((string) $section['title']) . '</h2>';
            }

            if (($section['subtitle'] ?? '') !== '') {
                $html .= '<p class="ssr-lead">' . $this->e((string) $section['subtitle']) . '</p>';
            }

            if (($section['body'] ?? '') !== '') {
                $html .= '<div>' . $this->markdown((string) $section['body']) . '</div>';
            }

            if (($section['items'] ?? []) !== []) {
                $html .= '<ul class="ssr-chips">';

                foreach ($section['items'] as $item) {
                    $text = is_array($item) ? (string) ($item['label'] ?? $item['title'] ?? '') : (string) $item;
                    $html .= '<li>' . $this->e($text) . '</li>';
                }

                $html .= '</ul>';
            }
        }

        $html .= '</div>';

        return ['html' => $html, 'breadcrumbs' => $this->crumbs($locale, $title, '/' . $locale . '/' . $key)];
    }

    /** Skills grid (used by the About / Skills surfaces). */
    public function skills(string $locale): array
    {
        $isFa = $locale === 'fa';
        $skills = $this->content->skills($locale);

        return [
            'html' => $this->skillsBlock($skills, $isFa),
            'breadcrumbs' => [],
        ];
    }

    /**
     * Minimal, safe Markdown subset: headings, bold/italic, inline code, fenced
     * code, lists, quotes, links and paragraphs. Text is escaped first, so raw
     * HTML stored in the database can never become executable markup.
     */
    public function markdown(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        $blocks = [];
        $markdown = preg_replace_callback('/```([a-zA-Z0-9+#-]*)\n(.*?)```/s', static function (array $match) use (&$blocks): string {
            $blocks[] = '<pre class="ssr-code"><code>' . Security::escape(rtrim($match[2])) . '</code></pre>';

            return "\x01" . (count($blocks) - 1) . "\x01";
        }, $markdown) ?? $markdown;

        $lines = preg_split('/\n/', $markdown) ?: [];
        $html = '';
        $listType = null;

        $closeList = static function () use (&$listType, &$html): void {
            if ($listType !== null) {
                $html .= $listType === 'ul' ? '</ul>' : '</ol>';
                $listType = null;
            }
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $closeList();
                continue;
            }

            if (preg_match('/^\x01(\d+)\x01$/', $trimmed, $match) === 1) {
                $closeList();
                $html .= $blocks[(int) $match[1]] ?? '';
                continue;
            }

            if (preg_match('/^(#{1,4})\s+(.*)$/u', $trimmed, $match) === 1) {
                $closeList();
                $level = min(4, max(2, strlen($match[1])));
                $html .= '<h' . $level . '>' . $this->inline($match[2]) . '</h' . $level . '>';
                continue;
            }

            if (preg_match('/^[-*]\s+(.*)$/u', $trimmed, $match) === 1) {
                if ($listType !== 'ul') {
                    $closeList();
                    $html .= '<ul>';
                    $listType = 'ul';
                }

                $html .= '<li>' . $this->inline($match[1]) . '</li>';
                continue;
            }

            if (preg_match('/^\d+[.)]\s+(.*)$/u', $trimmed, $match) === 1) {
                if ($listType !== 'ol') {
                    $closeList();
                    $html .= '<ol>';
                    $listType = 'ol';
                }

                $html .= '<li>' . $this->inline($match[1]) . '</li>';
                continue;
            }

            if (preg_match('/^>\s?(.*)$/u', $trimmed, $match) === 1) {
                $closeList();
                $html .= '<blockquote>' . $this->inline($match[1]) . '</blockquote>';
                continue;
            }

            $closeList();
            $html .= '<p>' . $this->inline($trimmed) . '</p>';
        }

        $closeList();

        return $html;
    }

    /* --------------------------------------------------------------------- */
    /* Blocks                                                                */
    /* --------------------------------------------------------------------- */

    /** @param array<int,array<string,mixed>> $items */
    private function projectBlock(array $items, ?string $heading = null): string
    {
        if ($items === []) {
            return '';
        }

        $html = '<section>' . ($heading !== null ? '<h2>' . $this->e($heading) . '</h2>' : '') . '<ul class="ssr-cards ssr-cards-projects">';

        foreach ($items as $project) {
            $html .= '<li><a href="' . $this->e((string) ($project['url'] ?? '#')) . '">';

            if (($project['cover_url'] ?? '') !== '') {
                $html .= '<img src="' . $this->e((string) $project['cover_url']) . '" alt="'
                    . $this->e((string) ($project['cover']['alt'] ?? $project['title'] ?? '')) . '" loading="lazy" decoding="async">';
            }

            $html .= '<h3>' . $this->e((string) $project['title']) . '</h3>';
            $html .= '<p>' . $this->e((string) ($project['summary'] ?? '')) . '</p>';

            if (($project['technologies'] ?? []) !== []) {
                $html .= '<p class="ssr-meta">' . $this->e(implode(' · ', (array) $project['technologies'])) . '</p>';
            }

            $html .= '</a></li>';
        }

        return $html . '</ul></section>';
    }

    /** @param array<int,array<string,mixed>> $items */
    private function postBlock(array $items, ?string $heading = null): string
    {
        if ($items === []) {
            return '';
        }

        $html = '<section>' . ($heading !== null ? '<h2>' . $this->e($heading) . '</h2>' : '') . '<ul class="ssr-cards">';

        foreach ($items as $post) {
            $html .= '<li><a href="' . $this->e((string) ($post['url'] ?? '#')) . '">';
            $html .= '<h3>' . $this->e((string) $post['title']) . '</h3>';
            $html .= '<p>' . $this->e((string) ($post['excerpt'] ?? '')) . '</p>';

            if (($post['published_at'] ?? '') !== '') {
                $html .= '<p class="ssr-meta">' . $this->e(substr((string) $post['published_at'], 0, 10)) . '</p>';
            }

            $html .= '</a></li>';
        }

        return $html . '</ul></section>';
    }

    /** @param array<int,array<string,mixed>> $items */
    private function sectionBlock(array $items, string $id, string $heading, string $titleKey, string $bodyKey): string
    {
        if ($items === []) {
            return '';
        }

        $html = '<section class="ssr-section" id="' . $this->e($id) . '"><h2>' . $this->e($heading) . '</h2><ul class="ssr-cards">';

        foreach ($items as $item) {
            $html .= '<li><h3>' . $this->e((string) ($item[$titleKey] ?? '')) . '</h3>'
                . '<p>' . $this->e((string) ($item[$bodyKey] ?? '')) . '</p></li>';
        }

        return $html . '</ul></section>';
    }

    /** @param array{items?:array<int,array<string,mixed>>,groups?:array<int,array<string,mixed>>} $skills */
    private function skillsBlock(array $skills, bool $isFa): string
    {
        $groups = $skills['groups'] ?? [];
        $items = $skills['items'] ?? [];

        if ($groups === [] && $items === []) {
            return '';
        }

        $html = '<section class="ssr-section" id="skills"><h2>'
            . ($isFa ? 'مهارت‌ها و تکنولوژی‌ها' : 'Skills & technologies') . '</h2>';

        if ($groups !== []) {
            foreach ($groups as $group) {
                $html .= '<h3>' . $this->e((string) ($group['name'] ?? '')) . '</h3><ul class="ssr-chips">';

                foreach (($group['skills'] ?? []) as $skill) {
                    $html .= '<li>' . $this->e((string) $skill['name']);
                    $html .= ($skill['kind'] ?? 'skill') === 'skill' ? ' — ' . (int) $skill['level'] . '%' : '';
                    $html .= '</li>';
                }

                $html .= '</ul>';
            }
        } else {
            $html .= '<ul class="ssr-chips">';

            foreach ($items as $skill) {
                $html .= '<li>' . $this->e((string) $skill['name']) . '</li>';
            }

            $html .= '</ul>';
        }

        return $html . '</section>';
    }

    /** @param array<int,array<string,mixed>> $experiences */
    private function experienceBlock(array $experiences, bool $isFa): string
    {
        if ($experiences === []) {
            return '';
        }

        $html = '<section class="ssr-section" id="experience"><h2>' . ($isFa ? 'تجربه کاری' : 'Experience') . '</h2><ol class="ssr-timeline">';

        foreach ($experiences as $experience) {
            $html .= '<li><h3>' . $this->e((string) $experience['position']) . '</h3>';
            $html .= '<p class="ssr-meta">' . $this->e((string) $experience['company']) . ' · '
                . $this->e($this->range($experience['start_date'] ?? null, $experience['end_date'] ?? null, (bool) ($experience['is_current'] ?? false), $isFa))
                . (($experience['location'] ?? '') !== '' ? ' · ' . $this->e((string) $experience['location']) : '')
                . '</p>';

            if (($experience['description'] ?? '') !== '') {
                $html .= '<p>' . $this->e((string) $experience['description']) . '</p>';
            }

            if (($experience['responsibilities'] ?? []) !== []) {
                $html .= '<ul>';

                foreach ($experience['responsibilities'] as $responsibility) {
                    $html .= '<li>' . $this->e((string) $responsibility) . '</li>';
                }

                $html .= '</ul>';
            }

            $html .= '</li>';
        }

        return $html . '</ol></section>';
    }

    /** @param array<int,array<string,mixed>> $testimonials */
    private function testimonialBlock(array $testimonials, bool $isFa): string
    {
        if ($testimonials === []) {
            return '';
        }

        $html = '<section class="ssr-section" id="testimonials"><h2>' . ($isFa ? 'نظر مشتریان' : 'Testimonials') . '</h2><ul class="ssr-cards">';

        foreach ($testimonials as $testimonial) {
            $html .= '<li><blockquote>' . $this->e((string) $testimonial['quote']) . '</blockquote>'
                . '<p class="ssr-meta">' . $this->e((string) $testimonial['client_name']) . ' — '
                . $this->e((string) ($testimonial['client_position'] ?? ''))
                . (($testimonial['company'] ?? '') !== '' ? ' · ' . $this->e((string) $testimonial['company']) : '')
                . '</p></li>';
        }

        return $html . '</ul></section>';
    }

    /* --------------------------------------------------------------------- */
    /* Helpers                                                               */
    /* --------------------------------------------------------------------- */

    /** @param array<int,array<string,mixed>> $sections */
    private function sectionOfType(array $sections, string $type): array
    {
        foreach ($sections as $section) {
            if (($section['type_name'] ?? '') === $type) {
                return $section;
            }
        }

        return $sections[0] ?? [];
    }

    /**
     * @param array{label:string,href:string}|null $leaf
     * @return array<int,array{label:string,href:string}>
     */
    private function crumbs(string $locale, string $label, string $href, ?array $leaf = null): array
    {
        $crumbs = [
            ['label' => $locale === 'fa' ? 'خانه' : 'Home', 'href' => '/' . $locale],
            ['label' => $label, 'href' => $href],
        ];

        if ($leaf !== null) {
            $crumbs[] = $leaf;
        }

        return $crumbs;
    }

    private function inline(string $text): string
    {
        $escaped = Security::escape($text);
        $escaped = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u', '<em>$1</em>', $escaped) ?? $escaped;

        // Only http(s) links survive; anything else stays plain text.
        return preg_replace(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u',
            '<a href="$2" rel="noopener nofollow" target="_blank">$1</a>',
            $escaped
        ) ?? $escaped;
    }

    private function pagination(string $base, int $page, int $totalPages, bool $isFa): string
    {
        if ($totalPages <= 1) {
            return '';
        }

        $html = '<nav class="ssr-pagination" aria-label="' . ($isFa ? 'صفحه‌ها' : 'Pagination') . '"><ul>';

        for ($index = 1; $index <= $totalPages; $index++) {
            $href = $index === 1 ? $base : $base . '?page=' . $index;
            $html .= '<li>' . ($index === $page
                ? '<span aria-current="page">' . $index . '</span>'
                : '<a href="' . $this->e($href) . '">' . $index . '</a>') . '</li>';
        }

        return $html . '</ul></nav>';
    }

    private function readingTime(string $text, bool $isFa): string
    {
        $minutes = max(1, (int) ceil(mb_strlen(strip_tags($text)) / 900));

        return $isFa ? $minutes . ' دقیقه مطالعه' : $minutes . ' min read';
    }

    private function range(?string $start, ?string $end, bool $current, bool $isFa): string
    {
        $from = ($start !== null && $start !== '') ? substr($start, 0, 7) : '';

        if ($current) {
            return $from . ' — ' . ($isFa ? 'تاکنون' : 'present');
        }

        $to = ($end !== null && $end !== '') ? substr($end, 0, 7) : '';

        return ($from !== '' || $to !== '') ? $from . ' — ' . $to : '';
    }

    private function e(mixed $value): string
    {
        return Security::escape((string) $value);
    }
}
