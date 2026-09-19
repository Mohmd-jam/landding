<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Turns navigation rows from the database into the shapes the site shell needs:
 * a nested header menu, footer columns, social links and localised hrefs.
 *
 * Rows store a *target* ("projects", "projects/erp", "blog/react-php", or a full
 * external URL) and the localisation lives in `navigation_translations`, so a
 * language switch keeps the same menu structure.
 */
final class NavigationService
{
    /** @var array<string,array<int,array<string,mixed>>> per-request memo per location */
    private array $trees = [];

    /** @var array<string,array<int,array<string,mixed>>> */
    private array $socialCache = [];

    public function __construct(private ?ContentService $content = null)
    {
        $this->content ??= new ContentService();
    }

    /** @return array<int,array<string,mixed>> */
    public function header(string $locale): array
    {
        $tree = $this->tree('header', $locale);

        // The homepage link is always present so the logo/menu works even
        // before the admin has touched the navigation table.
        if ($tree === []) {
            $tree = [[
                'id' => 0,
                'label' => $locale === 'fa' ? 'خانه' : 'Home',
                'href' => '/' . $locale,
                'icon' => null,
                'external' => false,
                'children' => [],
            ]];
        }

        return $tree;
    }

    /** @return array<int,array<string,mixed>> */
    public function tree(string $location, string $locale): array
    {
        if (isset($this->trees[$location . '|' . $locale])) {
            return $this->trees[$location . '|' . $locale];
        }

        $rows = $this->content->navigation($locale)[$location] ?? [];
        $byId = [];
        $roots = [];

        foreach ($rows as $row) {
            $row['href'] = $this->resolveHref($row, $locale);
            $row['label'] = (string) ($row['label'] ?? '');
            $row['external'] = (bool) ($row['open_in_new_tab'] ?? false);
            $row['children'] = [];
            $byId[(int) $row['id']] = $row;
        }

        foreach ($byId as $id => $row) {
            $parent = (int) ($row['parent_id'] ?? 0);

            if ($parent !== 0 && isset($byId[$parent])) {
                $byId[$parent]['children'][] = $this->publicShape($row);
                continue;
            }

            $roots[] = $this->publicShape($row);
        }

        return $this->trees[$location . '|' . $locale] = $roots;
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    public function footer(string $locale): array
    {
        $columns = [];

        foreach (['footer_quick', 'footer_services', 'footer_company', 'footer_legal'] as $location) {
            $columns[$location] = $this->tree($location, $locale);
        }

        return $columns;
    }

    /** @return array<int,array<string,mixed>> */
    public function socials(string $locale): array
    {
        if (isset($this->socialCache[$locale])) {
            return $this->socialCache[$locale];
        }

        $out = [];

        foreach ($this->content->socialLinks($locale) as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'platform' => (string) $row['platform'],
                'label' => (string) ($row['label'] ?? ucfirst((string) $row['platform'])),
                'url' => (string) $row['url'],
                'icon' => (string) ($row['icon'] ?? $row['platform']),
                'handle' => $row['handle'] ?? null,
            ];
        }

        return $this->socialCache[$locale] = $out;
    }

    /** Everything the shell needs in one call (used by PageController + bootstrap). */
    public function payload(string $locale): array
    {
        return [
            'header' => $this->header($locale),
            'footer' => $this->footer($locale),
            'socials' => $this->socials($locale),
        ];
    }

    /** @param array<string,mixed> $row */
    public function resolveHref(array $row, string $locale): string
    {
        $target = trim((string) ($row['target_value'] ?? ''));
        $type = (string) ($row['type_name'] ?? 'route');
        $prefix = '/' . trim($locale, '/');

        if ($type === 'url') {
            return $target === '' ? $prefix : $target;
        }

        if ($type === 'anchor') {
            return $prefix . '/#' . ltrim($target, '#/');
        }

        return $target === '' ? $prefix : $prefix . '/' . ltrim($target, '/');
    }

    /** @param array<string,mixed> $row */
    private function publicShape(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'label' => (string) ($row['label'] ?? ''),
            'href' => (string) ($row['href'] ?? '/'),
            'icon' => $row['icon'] ?? null,
            'external' => (bool) ($row['external'] ?? false),
            'children' => array_values($row['children'] ?? []),
        ];
    }
}
