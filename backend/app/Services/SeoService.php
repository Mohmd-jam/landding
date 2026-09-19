<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Json;
use App\Core\Security;

/**
 * Everything a crawler reads: per-page metadata, hreflang alternates, JSON-LD
 * structured data, sitemap.xml and robots.txt.
 *
 * Resolution order for a page: explicit `seo_metadata` row (requested locale,
 * then the fallback locale) → entity fallback supplied by the controller
 * (title/summary/cover from the real content) → site-wide defaults from
 * `settings`. Every value stays editable in Admin → SEO.
 */
final class SeoService
{
    public function __construct(
        private ?SettingsService $settings = null,
        private ?LocaleService $locales = null,
        private ?NavigationService $navigation = null,
    ) {
        $this->settings ??= new SettingsService();
        $this->locales ??= new LocaleService();
        $this->navigation ??= new NavigationService();
    }

    /**
     * @param array{
     *   locale:string,path:string,type?:string,entity_id?:int|null,
     *   fallback?:array<string,mixed>,slug_map?:array<string,string>
     * } $context
     * @return array<string,mixed>
     */
    public function forPage(array $context): array
    {
        $locale = (string) $context['locale'];
        $fallbackLocale = $this->locales->fallbackCode();
        $type = (string) ($context['type'] ?? 'page');
        $entityId = $context['entity_id'] ?? null;
        $fallback = $context['fallback'] ?? [];

        $row = [];

        if ($entityId !== null) {
            $found = Database::table('seo_metadata')
                ->where('entity_type', $type)
                ->where('entity_id', (int) $entityId)
                ->where('lang', $locale)
                ->first();

            if ($found === null) {
                $found = Database::table('seo_metadata')
                    ->where('entity_type', $type)
                    ->where('entity_id', (int) $entityId)
                    ->where('lang', $fallbackLocale)
                    ->first();
            }

            $row = $found === null ? [] : (array) $found;
        }

        $siteName = (string) $this->settings->get('site_name', 'Arash Mahdavi');
        $template = (string) $this->settings->get('seo_title_template', '%s | ' . $siteName);
        $isHome = (bool) ($fallback['is_home'] ?? false);

        $title = $this->pick(
            $row['seo_title'] ?? null,
            $fallback['title'] ?? null,
            $siteName
        );

        $description = $this->pick(
            $row['meta_description'] ?? null,
            $fallback['description'] ?? null,
            (string) $this->settings->translated('site_tagline', $locale, '')
        );

        return [
            'title' => $title,
            'document_title' => ($isHome || $entityId === null) ? $title : sprintf($template, $title),
            'description' => $description,
            'keywords' => $this->pick(
                $row['keywords'] ?? null,
                $fallback['keywords'] ?? null,
                (string) $this->settings->translated('seo_keywords', $locale, '')
            ),
            'canonical' => $this->canonical((string) ($row['canonical_url'] ?? ''), (string) $context['path']),
            'robots' => (string) ($row['robots'] ?? 'index,follow'),
            'og' => [
                'title' => (string) ($row['og_title'] ?? $title),
                'description' => (string) ($row['og_description'] ?? $description),
                'image' => $this->ogImage($row, $fallback),
                'type' => in_array($type, ['post'], true) ? 'article' : 'website',
                'site_name' => $siteName,
                'locale' => $locale,
            ],
            'twitter' => [
                'card' => (string) ($row['twitter_card'] ?? 'summary_large_image'),
                'site' => (string) $this->settings->get('twitter_handle', ''),
            ],
            'alternates' => $this->alternates((string) $context['path'], $locale, $context['slug_map'] ?? []),
        ];
    }

    /**
     * hreflang alternates for every active language.
     *
     * @param array<string,string> $slugMap lang => translated slug
     * @return array<int,array{lang:string,href:string}>
     */
    public function alternates(string $path, string $locale, array $slugMap = []): array
    {
        $base = $this->baseUrl();
        $suffix = $this->suffixFor($path, $locale);
        $out = [];

        foreach ($this->locales->languages() as $language) {
            $code = (string) $language['code'];
            $translated = $suffix;

            if ($slugMap !== [] && isset($slugMap[$code]) && $suffix !== '') {
                $segments = explode('/', trim($suffix, '/'));
                $segments[count($segments) - 1] = $slugMap[$code];
                $translated = '/' . implode('/', $segments);
            }

            $out[] = ['lang' => $code, 'href' => $base . '/' . $code . $translated];
        }

        return $out;
    }

    /**
     * JSON-LD graph: WebSite + Person + WebPage, plus BreadcrumbList and the
     * entity node (BlogPosting / CreativeWork / SoftwareApplication).
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function structuredData(array $context): array
    {
        $locale = (string) $context['locale'];
        $path = (string) $context['path'];
        $base = $this->baseUrl();
        $url = $base . $path;
        $meta = $context['meta'] ?? [];
        $siteName = (string) $this->settings->get('site_name', 'Arash Mahdavi');

        $graph = [
            [
                '@type' => 'WebSite',
                '@id' => $base . '/#website',
                'url' => $base . '/',
                'name' => $siteName,
                'inLanguage' => $locale,
                'publisher' => ['@id' => $base . '/#person'],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $base . '/' . $locale . '/blog?q={search_term_string}'],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
            array_filter([
                '@type' => 'Person',
                '@id' => $base . '/#person',
                'name' => $siteName,
                'jobTitle' => (string) $this->settings->translated('hero_title', $locale, 'Full-Stack Web Developer'),
                'url' => $base . '/',
                'image' => (string) $this->settings->get('profile_image_url', ''),
                'email' => (string) $this->settings->get('contact_email', ''),
                'telephone' => (string) $this->settings->get('contact_phone', ''),
                'address' => (string) $this->settings->translated('contact_location', $locale, ''),
                'sameAs' => array_values(array_filter(array_map(
                    static fn (array $social): string => (string) $social['url'],
                    $this->navigation->socials($locale)
                ))),
            ]),
            array_filter([
                '@type' => 'WebPage',
                '@id' => $url . '#webpage',
                'url' => $url,
                'name' => $meta['title'] ?? $siteName,
                'description' => $meta['description'] ?? '',
                'inLanguage' => $locale,
                'isPartOf' => ['@id' => $base . '/#website'],
                'about' => ['@id' => $base . '/#person'],
                'breadcrumb' => ['@id' => $url . '#breadcrumb'],
            ]),
        ];

        $items = [];

        foreach (($context['breadcrumbs'] ?? []) as $index => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => (string) ($crumb['label'] ?? ''),
                'item' => $base . (string) ($crumb['href'] ?? '/'),
            ];
        }

        if ($items !== []) {
            $graph[] = [
                '@type' => 'BreadcrumbList',
                '@id' => $url . '#breadcrumb',
                'itemListElement' => $items,
            ];
        }

        if (isset($context['article']) && is_array($context['article'])) {
            $article = $context['article'];

            $graph[] = array_filter([
                '@type' => 'BlogPosting',
                '@id' => $url . '#article',
                'headline' => (string) ($article['title'] ?? ''),
                'description' => (string) ($article['excerpt'] ?? ''),
                'image' => $article['image'] ?? null,
                'datePublished' => $article['published_at'] ?? null,
                'dateModified' => $article['updated_at'] ?? ($article['published_at'] ?? null),
                'author' => ['@id' => $base . '/#person'],
                'publisher' => ['@id' => $base . '/#person'],
                'mainEntityOfPage' => ['@id' => $url . '#webpage'],
                'inLanguage' => $locale,
                'articleSection' => $article['category'] ?? null,
                'keywords' => implode(', ', (array) ($article['tags'] ?? [])),
            ]);
        }

        if (isset($context['work']) && is_array($context['work'])) {
            $work = $context['work'];

            $graph[] = array_filter([
                '@type' => ($work['kind'] ?? 'website') === 'software' ? 'SoftwareApplication' : 'CreativeWork',
                '@id' => $url . '#project',
                'name' => (string) ($work['title'] ?? ''),
                'description' => (string) ($work['summary'] ?? ''),
                'image' => $work['image'] ?? null,
                'dateCreated' => $work['started_at'] ?? null,
                'datePublished' => $work['completed_at'] ?? null,
                'creator' => ['@id' => $base . '/#person'],
                'url' => (string) ($work['project_url'] ?? $url),
                'keywords' => implode(', ', (array) ($work['technologies'] ?? [])),
                'inLanguage' => $locale,
            ]);
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    /** JSON-LD script tag (hex-escaped so content can never break out of it). */
    public function jsonLd(array $data): string
    {
        return '<script type="application/ld+json">'
            . Json::encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)
            . '</script>';
    }

    /**
     * Render a full <head> block: title, description, canonical, robots,
     * Open Graph, Twitter card, hreflang alternates and the JSON-LD graph.
     *
     * @param array<string,mixed> $meta
     */
    public function head(array $meta, array $structuredData): string
    {
        $lines = [
            '<title>' . Security::escape((string) $meta['document_title']) . '</title>',
            '<meta name="description" content="' . Security::escape((string) $meta['description']) . '">',
        ];

        if (($meta['keywords'] ?? '') !== '') {
            $lines[] = '<meta name="keywords" content="' . Security::escape((string) $meta['keywords']) . '">';
        }

        $lines[] = '<meta name="robots" content="' . Security::escape((string) $meta['robots']) . '">';
        $lines[] = '<link rel="canonical" href="' . Security::escape((string) $meta['canonical']) . '">';

        foreach ($meta['alternates'] ?? [] as $alternate) {
            $lines[] = '<link rel="alternate" hreflang="' . Security::escape((string) $alternate['lang'])
                . '" href="' . Security::escape((string) $alternate['href']) . '">';
        }

        $lines[] = '<meta property="og:type" content="' . Security::escape((string) $meta['og']['type']) . '">';
        $lines[] = '<meta property="og:title" content="' . Security::escape((string) $meta['og']['title']) . '">';
        $lines[] = '<meta property="og:description" content="' . Security::escape((string) $meta['og']['description']) . '">';
        $lines[] = '<meta property="og:url" content="' . Security::escape((string) $meta['canonical']) . '">';
        $lines[] = '<meta property="og:site_name" content="' . Security::escape((string) $meta['og']['site_name']) . '">';
        $lines[] = '<meta property="og:locale" content="' . Security::escape((string) $meta['og']['locale']) . '">';

        if (($meta['og']['image'] ?? '') !== '') {
            $lines[] = '<meta property="og:image" content="' . Security::escape((string) $meta['og']['image']) . '">';
            $lines[] = '<meta name="twitter:image" content="' . Security::escape((string) $meta['og']['image']) . '">';
        }

        $lines[] = '<meta name="twitter:card" content="' . Security::escape((string) $meta['twitter']['card']) . '">';
        $lines[] = '<meta name="twitter:title" content="' . Security::escape((string) $meta['og']['title']) . '">';
        $lines[] = '<meta name="twitter:description" content="' . Security::escape((string) $meta['og']['description']) . '">';

        if (($meta['twitter']['site'] ?? '') !== '') {
            $lines[] = '<meta name="twitter:site" content="' . Security::escape((string) $meta['twitter']['site']) . '">';
        }

        $lines[] = $this->jsonLd($structuredData);

        return implode("\n    ", $lines);
    }

    /** Regenerate public/sitemap.xml + public/robots.txt from the database. */
    public function writeSitemap(ContentService $content): array
    {
        $base = $this->baseUrl();
        $urls = [];
        $static = ['', '/projects', '/blog', '/services', '/about', '/contact', '/resume'];

        foreach ($this->locales->languages() as $language) {
            $code = (string) $language['code'];

            foreach ($static as $path) {
                $urls[] = [
                    'loc' => $base . '/' . $code . $path,
                    'changefreq' => $path === '' ? 'weekly' : 'monthly',
                    'priority' => $path === '' ? '1.0' : '0.8',
                    'lastmod' => gmdate('Y-m-d'),
                ];
            }

            foreach ($content->projects($code, [], 200, 1)['items'] as $project) {
                $urls[] = [
                    'loc' => $base . (string) $project['url'],
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                    'lastmod' => $this->dateOnly($project['updated_at'] ?? $project['completed_at'] ?? null),
                ];
            }

            foreach ($content->posts($code, [], 500, 1)['items'] as $post) {
                $urls[] = [
                    'loc' => $base . (string) $post['url'],
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                    'lastmod' => $this->dateOnly($post['updated_at'] ?? $post['published_at'] ?? null),
                ];
            }
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n"
                . '    <loc>' . Security::escape($url['loc']) . "</loc>\n"
                . '    <lastmod>' . Security::escape((string) $url['lastmod']) . "</lastmod>\n"
                . '    <changefreq>' . Security::escape($url['changefreq']) . "</changefreq>\n"
                . '    <priority>' . Security::escape($url['priority']) . "</priority>\n"
                . "  </url>\n";
        }

        $xml .= "</urlset>\n";

        $robots = "User-agent: *\nAllow: /\n"
            . "Disallow: /admin\nDisallow: /api/\nDisallow: " . Config::get('app.admin_path', 'admin') . "\n\n"
            . 'Sitemap: ' . $base . "/sitemap.xml\n";

        $public = rtrim((string) Config::get('app.public_path', ''), '/');

        @file_put_contents($public . '/sitemap.xml', $xml);
        @file_put_contents($public . '/robots.txt', $robots);

        return ['urls' => count($urls), 'files' => [$public . '/sitemap.xml', $public . '/robots.txt']];
    }

    /** @return array<string,string> lang => slug for one entity */
    public function slugMap(string $translationTable, string $foreignKey, int $entityId): array
    {
        $out = [];

        foreach (Database::table($translationTable)->where($foreignKey, $entityId)->get() as $row) {
            $out[(string) $row['lang']] = (string) $row['slug'];
        }

        return $out;
    }

    private function baseUrl(): string
    {
        $configured = (string) $this->settings->get('site_url', '');

        return rtrim($configured !== '' ? $configured : (string) Config::get('app.url', ''), '/');
    }

    /** Path without the leading /<locale> segment (used to build alternates). */
    private function suffixFor(string $path, string $locale): string
    {
        $prefix = '/' . trim($locale, '/');

        if ($path === $prefix || $path === $prefix . '/') {
            return '';
        }

        if (str_starts_with($path, $prefix . '/')) {
            return substr($path, strlen($prefix));
        }

        return $path;
    }

    private function canonical(string $configured, string $path): string
    {
        return $configured !== '' ? $configured : $this->baseUrl() . $path;
    }

    /** @param array<string,mixed> $row */
    private function ogImage(array $row, array $fallback): string
    {
        $mediaId = $row['og_image_media_id'] ?? null;

        if ($mediaId !== null) {
            $media = Database::table('media')->where('id', (int) $mediaId)->first();

            if ($media !== null) {
                $url = (string) $media['url'];

                return str_starts_with($url, 'http') ? $url : $this->baseUrl() . $url;
            }
        }

        $image = (string) ($fallback['image'] ?? $this->settings->get('og_default_image_url', ''));

        return ($image !== '' && !str_starts_with($image, 'http')) ? $this->baseUrl() . $image : $image;
    }

    private function pick(?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private function dateOnly(?string $value): string
    {
        return ($value === null || $value === '') ? gmdate('Y-m-d') : substr($value, 0, 10);
    }
}
