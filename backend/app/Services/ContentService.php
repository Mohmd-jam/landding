<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Json;

/**
 * Read model for every public content type.
 *
 * Each method joins the entity with its translation table for the requested
 * locale **and** the fallback locale, so a partially translated site still
 * renders (COALESCE keeps the primary locale, then the fallback).
 * Admin writes go through the same tables, which is why a change in the panel
 * appears on the public site immediately.
 */
final class ContentService
{
    public function __construct(private ?LocaleService $locales = null)
    {
        $this->locales ??= new LocaleService();
    }

    private function fallback(string $locale): string
    {
        return $this->locales->fallbackCode() === $locale
            ? $this->locales->defaultCode()
            : $this->locales->fallbackCode();
    }

    /* --------------------------------------------------------------------- */
    /* Pages & sections                                                      */
    /* --------------------------------------------------------------------- */

    public function page(string $key, string $locale): ?array
    {
        $fallback = $this->fallback($locale);

        $row = Database::selectOne(
            "SELECT p.id, p.key_name, p.template, p.icon,
                    COALESCE(t.title, f.title) AS title,
                    COALESCE(t.subtitle, f.subtitle) AS subtitle,
                    COALESCE(t.content, f.content) AS content,
                    COALESCE(t.seo_title, f.seo_title) AS seo_title,
                    COALESCE(t.seo_description, f.seo_description) AS seo_description
             FROM pages p
             LEFT JOIN page_translations t ON t.page_id = p.id AND t.lang = ?
             LEFT JOIN page_translations f ON f.page_id = p.id AND f.lang = ?
             WHERE p.key_name = ? AND p.is_active = 1
             LIMIT 1",
            [$locale, $fallback, $key]
        );

        return $row;
    }

    /** @return array<int,array<string,mixed>> */
    public function sections(string $pageKey, string $locale): array
    {
        $fallback = $this->fallback($locale);

        $rows = Database::select(
            "SELECT s.id, s.key_name, s.type_name, s.sort_order, s.settings_json,
                    COALESCE(t.eyebrow, f.eyebrow) AS eyebrow,
                    COALESCE(t.title, f.title) AS title,
                    COALESCE(t.subtitle, f.subtitle) AS subtitle,
                    COALESCE(t.body, f.body) AS body,
                    COALESCE(t.cta_label, f.cta_label) AS cta_label,
                    COALESCE(t.cta_url, f.cta_url) AS cta_url,
                    COALESCE(t.cta2_label, f.cta2_label) AS cta2_label,
                    COALESCE(t.cta2_url, f.cta2_url) AS cta2_url,
                    COALESCE(t.items_json, f.items_json) AS items_json
             FROM sections s
             JOIN pages p ON p.id = s.page_id
             LEFT JOIN section_translations t ON t.section_id = s.id AND t.lang = ?
             LEFT JOIN section_translations f ON f.section_id = s.id AND f.lang = ?
             WHERE p.key_name = ? AND s.is_active = 1
             ORDER BY s.sort_order, s.id",
            [$locale, $fallback, $pageKey]
        );

        $settings = new SettingsService();

        return array_map(function (array $row) use ($settings, $locale): array {
            $row['sort_order'] = (int) $row['sort_order'];
            $row['settings'] = Json::decode($row['settings_json'], []);
            $row['items'] = Json::list($row['items_json']);

            // Statistics sections read their numbers from settings so a value
            // is edited in exactly one place (Admin → Website settings).
            if ($row['type_name'] === 'stats' || ($row['settings']['source'] ?? null) === 'statistics') {
                foreach ($row['items'] as $index => $item) {
                    $key = $item['key'] ?? null;
                    if (is_string($key)) {
                        $row['items'][$index]['value'] = $settings->get($key, 0);
                    }
                }
            }

            $row['stats'] = $row['type_name'] === 'stats'
                ? $this->statistics()
                : [];

            return $row;
        }, $rows);
    }

    /** @return array<int,array{key:string,value:int,label:string,suffix:?string}> */
    public function statistics(): array
    {
        $settings = new SettingsService();

        return [
            ['key' => 'stat_years', 'value' => $settings->int('stat_years', 9), 'label' => '', 'suffix' => '+'],
            ['key' => 'stat_projects', 'value' => $settings->int('stat_projects', 60), 'label' => '', 'suffix' => '+'],
            ['key' => 'stat_clients', 'value' => $settings->int('stat_clients', 40), 'label' => '', 'suffix' => '+'],
            ['key' => 'stat_satisfaction', 'value' => $settings->int('stat_satisfaction', 98), 'label' => '', 'suffix' => '%'],
        ];
    }

    /* --------------------------------------------------------------------- */
    /* Navigation & social                                                   */
    /* --------------------------------------------------------------------- */

    /** @return array<string,array<int,array<string,mixed>>> */
    public function navigation(string $locale): array
    {
        $fallback = $this->fallback($locale);

        $rows = Database::select(
            "SELECT n.id, n.location, n.parent_id, n.type_name, n.target_value, n.icon, n.open_in_new_tab, n.sort_order,
                    COALESCE(t.label, f.label) AS label
             FROM navigation n
             LEFT JOIN navigation_translations t ON t.navigation_id = n.id AND t.lang = ?
             LEFT JOIN navigation_translations f ON f.navigation_id = n.id AND f.lang = ?
             WHERE n.is_active = 1
             ORDER BY n.location, n.sort_order, n.id",
            [$locale, $fallback]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $row['open_in_new_tab'] = (bool) $row['open_in_new_tab'];
            $row['sort_order'] = (int) $row['sort_order'];
            $grouped[(string) $row['location']][] = $row;
        }

        return $grouped;
    }

    /** @return array<int,array<string,mixed>> */
    public function socialLinks(string $locale): array
    {
        $fallback = $this->fallback($locale);

        $rows = Database::select(
            "SELECT s.id, s.platform, s.url, s.icon, s.sort_order,
                    COALESCE(t.label, f.label) AS label,
                    COALESCE(t.handle, f.handle) AS handle
             FROM social_links s
             LEFT JOIN social_link_translations t ON t.social_link_id = s.id AND t.lang = ?
             LEFT JOIN social_link_translations f ON f.social_link_id = s.id AND f.lang = ?
             WHERE s.is_active = 1
             ORDER BY s.sort_order, s.id",
            [$locale, $fallback]
        );

        return $rows;
    }

    /* --------------------------------------------------------------------- */
    /* Skills & services                                                     */
    /* --------------------------------------------------------------------- */

    /** @return array<int,array<string,mixed>> */
    public function skillCategories(string $locale): array
    {
        $fallback = $this->fallback($locale);

        return Database::select(
            "SELECT c.id, c.key_name, c.icon, c.sort_order,
                    COALESCE(t.name, f.name) AS name,
                    COALESCE(t.description, f.description) AS description
             FROM skill_categories c
             LEFT JOIN skill_category_translations t ON t.category_id = c.id AND t.lang = ?
             LEFT JOIN skill_category_translations f ON f.category_id = c.id AND f.lang = ?
             WHERE c.is_active = 1
             ORDER BY c.sort_order, c.id",
            [$locale, $fallback]
        );
    }

    /**
     * @return array{items: array<int,array<string,mixed>>, groups: array<int,array<string,mixed>>}
     */
    public function skills(string $locale, string $kind = 'skill'): array
    {
        $fallback = $this->fallback($locale);

        $rows = Database::select(
            "SELECT s.id, s.category_id, s.kind, s.icon, s.level, s.proficiency, s.years, s.color,
                    s.is_featured, s.sort_order,
                    COALESCE(t.name, f.name) AS name,
                    COALESCE(t.description, f.description) AS description,
                    COALESCE(ct.name, cf.name) AS category_name,
                    c.key_name AS category_key, c.icon AS category_icon, c.sort_order AS category_order
             FROM skills s
             LEFT JOIN skill_translations t ON t.skill_id = s.id AND t.lang = ?
             LEFT JOIN skill_translations f ON f.skill_id = s.id AND f.lang = ?
             LEFT JOIN skill_categories c ON c.id = s.category_id
             LEFT JOIN skill_category_translations ct ON ct.category_id = c.id AND ct.lang = ?
             LEFT JOIN skill_category_translations cf ON cf.category_id = c.id AND cf.lang = ?
             WHERE s.is_active = 1 AND s.kind = ?
             ORDER BY COALESCE(c.sort_order, 99), s.sort_order, s.id",
            [$locale, $fallback, $locale, $fallback, $kind]
        );

        foreach ($rows as $index => $row) {
            $rows[$index]['level'] = (int) $row['level'];
            $rows[$index]['is_featured'] = (bool) $row['is_featured'];
        }

        $groups = [];
        foreach ($rows as $row) {
            $key = (string) ($row['category_key'] ?? 'other');
            $groups[$key]['key'] = $key;
            $groups[$key]['name'] = $row['category_name'] ?? '';
            $groups[$key]['icon'] = $row['category_icon'] ?? null;
            $groups[$key]['skills'][] = $row;
        }

        return ['items' => $rows, 'groups' => array_values($groups)];
    }

    /** @return array<int,array<string,mixed>> */
    public function services(string $locale, bool $onlyFeatured = false): array
    {
        $fallback = $this->fallback($locale);
        $featured = $onlyFeatured ? ' AND s.is_featured = 1' : '';

        $rows = Database::select(
            "SELECT s.id, s.icon, s.accent, s.is_featured, s.sort_order,
                    COALESCE(t.title, f.title) AS title,
                    COALESCE(t.slug, f.slug) AS slug,
                    COALESCE(t.summary, f.summary) AS summary,
                    COALESCE(t.description, f.description) AS description,
                    COALESCE(t.features_json, f.features_json) AS features_json
             FROM services s
             LEFT JOIN service_translations t ON t.service_id = s.id AND t.lang = ?
             LEFT JOIN service_translations f ON f.service_id = s.id AND f.lang = ?
             WHERE s.is_active = 1{$featured}
             ORDER BY s.sort_order, s.id",
            [$locale, $fallback]
        );

        foreach ($rows as $index => $row) {
            $rows[$index]['features'] = Json::list($row['features_json']);
            unset($rows[$index]['features_json']);
        }

        return $rows;
    }

    public function serviceBySlug(string $slug, string $locale): ?array
    {
        $fallback = $this->fallback($locale);

        return Database::selectOne(
            "SELECT s.id, s.icon, s.accent,
                    COALESCE(t.title, f.title) AS title,
                    COALESCE(t.slug, f.slug) AS slug,
                    COALESCE(t.summary, f.summary) AS summary,
                    COALESCE(t.description, f.description) AS description,
                    COALESCE(t.features_json, f.features_json) AS features_json
             FROM services s
             LEFT JOIN service_translations t ON t.service_id = s.id AND t.lang = ?
             LEFT JOIN service_translations f ON f.service_id = s.id AND f.lang = ?
             WHERE s.is_active = 1 AND (t.slug = ? OR f.slug = ?)
             LIMIT 1",
            [$locale, $fallback, $slug, $slug]
        );
    }

    /* --------------------------------------------------------------------- */
    /* Projects                                                              */
    /* --------------------------------------------------------------------- */

    /** @return array<int,array<string,mixed>> */
    public function projectCategories(string $locale): array
    {
        $fallback = $this->fallback($locale);

        $rows = Database::select(
            "SELECT c.id, c.icon, c.color, c.sort_order,
                    COALESCE(t.name, f.name) AS name,
                    COALESCE(t.slug, f.slug) AS slug,
                    (SELECT COUNT(*) FROM projects p WHERE p.category_id = c.id AND p.is_active = 1) AS projects_count
             FROM project_categories c
             LEFT JOIN project_category_translations t ON t.category_id = c.id AND t.lang = ?
             LEFT JOIN project_category_translations f ON f.category_id = c.id AND f.lang = ?
             WHERE c.is_active = 1
             ORDER BY c.sort_order, c.id",
            [$locale, $fallback]
        );

        foreach ($rows as $index => $row) {
            $rows[$index]['projects_count'] = (int) $row['projects_count'];
        }

        return $rows;
    }

    /** @return array{items:array,total:int,page:int,per_page:int,total_pages:int} */
    public function projects(string $locale, array $filters = [], int $perPage = 9, int $page = 1): array
    {
        $fallback = $this->fallback($locale);

        $sql = "SELECT p.id, p.client, p.project_url, p.github_url, p.tech_stack, p.started_at, p.completed_at,
                       p.is_featured, p.sort_order, p.views, p.category_id,
                       COALESCE(t.title, f.title) AS title,
                       COALESCE(t.slug, f.slug) AS slug,
                       COALESCE(t.summary, f.summary) AS summary,
                       COALESCE(ct.name, cf.name) AS category_name,
                       COALESCE(ct.slug, cf.slug) AS category_slug,
                       c.icon AS category_icon, c.color AS category_color,
                       m.url AS cover_url, m.path AS cover_path, m.width AS cover_width, m.height AS cover_height,
                       m.alt_text AS cover_alt";
        $countSql = 'SELECT COUNT(*) AS aggregate';
        $from = " FROM projects p
                  LEFT JOIN project_translations t ON t.project_id = p.id AND t.lang = ?
                  LEFT JOIN project_translations f ON f.project_id = p.id AND f.lang = ?
                  LEFT JOIN project_categories c ON c.id = p.category_id
                  LEFT JOIN project_category_translations ct ON ct.category_id = c.id AND ct.lang = ?
                  LEFT JOIN project_category_translations cf ON cf.category_id = c.id AND cf.lang = ?
                  LEFT JOIN media m ON m.id = p.cover_media_id";
        $where = ' WHERE p.is_active = 1';
        $bindings = [$locale, $fallback, $locale, $fallback];

        if (!empty($filters['category'])) {
            $where .= ' AND (ct.slug = ? OR cf.slug = ?)';
            $bindings[] = (string) $filters['category'];
            $bindings[] = (string) $filters['category'];
        }

        if (!empty($filters['featured'])) {
            $where .= ' AND p.is_featured = 1';
        }

        if (!empty($filters['technology'])) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM project_technologies pt
                JOIN skills sk ON sk.id = pt.skill_id
                JOIN skill_translations st ON st.skill_id = sk.id AND st.lang = ?
                WHERE pt.project_id = p.id AND st.name = ?
            )';
            $bindings[] = $locale;
            $bindings[] = (string) $filters['technology'];
        }

        if (!empty($filters['search'])) {
            $where .= ' AND (COALESCE(t.title, f.title) LIKE ? OR COALESCE(t.summary, f.summary) LIKE ? OR p.tech_stack LIKE ?)';
            $term = '%' . $filters['search'] . '%';
            $bindings = array_merge($bindings, [$term, $term, $term]);
        }

        $total = (int) Database::scalar($countSql . $from . $where, $bindings);

        $order = match ($filters['order'] ?? 'featured') {
            'latest' => 'p.completed_at DESC, p.id DESC',
            'oldest' => 'p.completed_at ASC, p.id ASC',
            'title' => 'COALESCE(t.title, f.title) ASC',
            default => 'p.is_featured DESC, p.sort_order ASC, p.completed_at DESC',
        };

        $perPage = max(1, min($perPage, 48));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $items = Database::select(
            $sql . $from . $where . " ORDER BY {$order} LIMIT {$perPage} OFFSET {$offset}",
            $bindings
        );

        foreach ($items as $index => $item) {
            $items[$index] = $this->decorateProjectCard($item, $locale);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) max(1, ceil($total / $perPage)),
        ];
    }

    public function project(string $slug, string $locale): ?array
    {
        $fallback = $this->fallback($locale);

        $row = Database::selectOne(
            "SELECT p.id, p.client, p.project_url, p.github_url, p.tech_stack, p.started_at, p.completed_at,
                    p.is_featured, p.views, p.category_id, p.created_at, p.updated_at,
                    COALESCE(t.title, f.title) AS title,
                    COALESCE(t.slug, f.slug) AS slug,
                    COALESCE(t.summary, f.summary) AS summary,
                    COALESCE(t.description, f.description) AS description,
                    COALESCE(t.challenge, f.challenge) AS challenge,
                    COALESCE(t.solution, f.solution) AS solution,
                    COALESCE(t.results, f.results) AS results,
                    COALESCE(t.seo_title, f.seo_title) AS seo_title,
                    COALESCE(t.seo_description, f.seo_description) AS seo_description,
                    COALESCE(ct.name, cf.name) AS category_name,
                    COALESCE(ct.slug, cf.slug) AS category_slug,
                    c.icon AS category_icon, c.color AS category_color,
                    m.url AS cover_url, m.path AS cover_path, m.width AS cover_width, m.height AS cover_height,
                    m.alt_text AS cover_alt
             FROM projects p
             LEFT JOIN project_translations t ON t.project_id = p.id AND t.lang = ?
             LEFT JOIN project_translations f ON f.project_id = p.id AND f.lang = ?
             LEFT JOIN project_categories c ON c.id = p.category_id
             LEFT JOIN project_category_translations ct ON ct.category_id = c.id AND ct.lang = ?
             LEFT JOIN project_category_translations cf ON cf.category_id = c.id AND cf.lang = ?
             LEFT JOIN media m ON m.id = p.cover_media_id
             WHERE p.is_active = 1 AND (t.slug = ? OR f.slug = ?)
             LIMIT 1",
            [$locale, $fallback, $locale, $fallback, $slug, $slug]
        );

        if ($row === null) {
            return null;
        }

        $projectId = (int) $row['id'];
        $row = $this->decorateProjectCard($row, $locale);

        $row['gallery'] = array_map(static fn (array $image): array => [
            'id' => (int) $image['id'],
            'url' => $image['url'],
            'caption' => $image['caption'],
            'width' => $image['width'] !== null ? (int) $image['width'] : null,
            'height' => $image['height'] !== null ? (int) $image['height'] : null,
            'alt' => $image['alt_text'],
        ], Database::select(
            'SELECT pi.id, pi.caption, pi.sort_order, m.url, m.width, m.height, m.alt_text
             FROM project_images pi
             JOIN media m ON m.id = pi.media_id
             WHERE pi.project_id = ?
             ORDER BY pi.sort_order, pi.id',
            [$projectId]
        ));

        $row['technologies'] = Database::select(
            "SELECT sk.id, sk.icon, COALESCE(st.name, sf.name) AS name
             FROM project_technologies pt
             JOIN skills sk ON sk.id = pt.skill_id
             LEFT JOIN skill_translations st ON st.skill_id = sk.id AND st.lang = ?
             LEFT JOIN skill_translations sf ON sf.skill_id = sk.id AND sf.lang = ?
             WHERE pt.project_id = ?
             ORDER BY pt.sort_order, sk.sort_order",
            [$locale, $fallback, $projectId]
        );

        $related = Database::select(
            "SELECT p.id, COALESCE(t.title, f.title) AS title, COALESCE(t.slug, f.slug) AS slug,
                    COALESCE(t.summary, f.summary) AS summary, m.url AS cover_url, m.path AS cover_path
             FROM projects p
             LEFT JOIN project_translations t ON t.project_id = p.id AND t.lang = ?
             LEFT JOIN project_translations f ON f.project_id = p.id AND f.lang = ?
             LEFT JOIN media m ON m.id = p.cover_media_id
             WHERE p.is_active = 1 AND p.id != ? AND (p.category_id = ? OR p.category_id IS NULL)
             ORDER BY p.is_featured DESC, p.sort_order
             LIMIT 3",
            [$locale, $fallback, $projectId, $row['category_id']]
        );

        $row['related'] = array_map(fn (array $item): array => $this->decorateProjectCard($item, $locale), $related);

        return $row;
    }

    private function decorateProjectCard(array $item, string $locale): array
    {
        $item['id'] = (int) $item['id'];
        $item['is_featured'] = (bool) ($item['is_featured'] ?? false);
        $item['cover_url'] = $item['cover_url'] ?? null;
        $item['cover'] = $item['cover_url'] !== null ? [
            'url' => $item['cover_url'],
            'path' => $item['cover_path'] ?? null,
            'width' => isset($item['cover_width']) && $item['cover_width'] !== null ? (int) $item['cover_width'] : null,
            'height' => isset($item['cover_height']) && $item['cover_height'] !== null ? (int) $item['cover_height'] : null,
            'alt' => $item['cover_alt'] ?? null,
        ] : null;

        $item['technologies'] = isset($item['tech_stack']) && $item['tech_stack'] !== null
            ? array_values(array_filter(array_map('trim', explode('·', (string) $item['tech_stack']))))
            : [];

        $url = '/' . $locale . '/projects/' . ($item['slug'] ?? '');
        $item['url'] = $url;

        unset($item['cover_path'], $item['cover_width'], $item['cover_height'], $item['cover_alt']);

        return $item;
    }

    /* --------------------------------------------------------------------- */
    /* Timeline                                                              */
    /* --------------------------------------------------------------------- */

    /** @return array<int,array<string,mixed>> */
    public function experiences(string $locale): array
    {
        $fallback = $this->fallback($locale);

        $rows = Database::select(
            "SELECT e.id, e.company, e.company_url, e.location, e.employment_type, e.start_date, e.end_date,
                    e.is_current, e.sort_order,
                    COALESCE(t.position, f.position) AS position,
                    COALESCE(t.description, f.description) AS description,
                    COALESCE(t.responsibilities_json, f.responsibilities_json) AS responsibilities_json,
                    COALESCE(t.achievements_json, f.achievements_json) AS achievements_json
             FROM experiences e
             LEFT JOIN experience_translations t ON t.experience_id = e.id AND t.lang = ?
             LEFT JOIN experience_translations f ON f.experience_id = e.id AND f.lang = ?
             WHERE e.is_active = 1
             ORDER BY e.start_date DESC, e.sort_order",
            [$locale, $fallback]
        );

        foreach ($rows as $index => $row) {
            $rows[$index]['is_current'] = (bool) $row['is_current'];
            $rows[$index]['responsibilities'] = Json::list($row['responsibilities_json']);
            $rows[$index]['achievements'] = Json::list($row['achievements_json']);
            unset($rows[$index]['responsibilities_json'], $rows[$index]['achievements_json']);
        }

        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function education(string $locale): array
    {
        $fallback = $this->fallback($locale);

        return Database::select(
            "SELECT e.id, e.institution, e.institution_url, e.location, e.start_date, e.end_date, e.is_current, e.gpa,
                    COALESCE(t.degree, f.degree) AS degree,
                    COALESCE(t.field, f.field) AS field,
                    COALESCE(t.description, f.description) AS description
             FROM education e
             LEFT JOIN education_translations t ON t.education_id = e.id AND t.lang = ?
             LEFT JOIN education_translations f ON f.education_id = e.id AND f.lang = ?
             WHERE e.is_active = 1
             ORDER BY e.start_date DESC, e.sort_order",
            [$locale, $fallback]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function certifications(string $locale): array
    {
        $fallback = $this->fallback($locale);

        return Database::select(
            "SELECT c.id, c.issuer, c.issuer_url, c.credential_id, c.credential_url, c.issue_date, c.expiry_date,
                    COALESCE(t.title, f.title) AS title,
                    COALESCE(t.description, f.description) AS description,
                    m.url AS image_url
             FROM certifications c
             LEFT JOIN certification_translations t ON t.certification_id = c.id AND t.lang = ?
             LEFT JOIN certification_translations f ON f.certification_id = c.id AND f.lang = ?
             LEFT JOIN media m ON m.id = c.image_media_id
             WHERE c.is_active = 1
             ORDER BY c.issue_date DESC, c.sort_order",
            [$locale, $fallback]
        );
    }

    /* --------------------------------------------------------------------- */
    /* Testimonials                                                          */
    /* --------------------------------------------------------------------- */

    /** @return array<int,array<string,mixed>> */
    public function testimonials(string $locale): array
    {
        $fallback = $this->fallback($locale);

        $rows = Database::select(
            "SELECT ts.id, ts.rating, ts.lang, ts.is_featured, ts.sort_order,
                    COALESCE(t.client_name, f.client_name) AS client_name,
                    COALESCE(t.client_position, f.client_position) AS client_position,
                    COALESCE(t.company, f.company) AS company,
                    COALESCE(t.quote, f.quote) AS quote,
                    m.url AS avatar_url, m.path AS avatar_path, m.alt_text AS avatar_alt
             FROM testimonials ts
             LEFT JOIN testimonial_translations t ON t.testimonial_id = ts.id AND t.lang = ?
             LEFT JOIN testimonial_translations f ON f.testimonial_id = ts.id AND f.lang = ?
             LEFT JOIN media m ON m.id = ts.avatar_media_id
             WHERE ts.is_active = 1
             ORDER BY ts.sort_order, ts.id",
            [$locale, $fallback]
        );

        foreach ($rows as $index => $row) {
            $rows[$index]['rating'] = (int) $row['rating'];
            $rows[$index]['is_featured'] = (bool) $row['is_featured'];
            $rows[$index]['initials'] = $this->initials((string) $row['client_name']);
        }

        return $rows;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_substr($part, 0, 1, 'UTF-8');
        }

        return mb_strtoupper($initials, 'UTF-8');
    }

    /* --------------------------------------------------------------------- */
    /* Blog                                                                  */
    /* --------------------------------------------------------------------- */

    /** @return array<int,array<string,mixed>> */
    public function postCategories(string $locale): array
    {
        $fallback = $this->fallback($locale);

        $rows = Database::select(
            "SELECT c.id, c.icon, c.color, c.sort_order,
                    COALESCE(t.name, f.name) AS name,
                    COALESCE(t.slug, f.slug) AS slug,
                    (SELECT COUNT(*) FROM blog_posts p WHERE p.category_id = c.id AND p.status = 'published') AS posts_count
             FROM blog_categories c
             LEFT JOIN blog_category_translations t ON t.category_id = c.id AND t.lang = ?
             LEFT JOIN blog_category_translations f ON f.category_id = c.id AND f.lang = ?
             WHERE c.is_active = 1
             ORDER BY c.sort_order, c.id",
            [$locale, $fallback]
        );

        foreach ($rows as $index => $row) {
            $rows[$index]['posts_count'] = (int) $row['posts_count'];
        }

        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function postTags(string $locale): array
    {
        $fallback = $this->fallback($locale);

        return Database::select(
            "SELECT tg.id, COALESCE(t.name, f.name) AS name, COALESCE(t.slug, f.slug) AS slug,
                    (SELECT COUNT(*) FROM blog_post_tags bt JOIN blog_posts p ON p.id = bt.post_id
                      WHERE bt.tag_id = tg.id AND p.status = 'published') AS posts_count
             FROM blog_tags tg
             LEFT JOIN blog_tag_translations t ON t.tag_id = tg.id AND t.lang = ?
             LEFT JOIN blog_tag_translations f ON f.tag_id = tg.id AND f.lang = ?
             WHERE tg.is_active = 1
             ORDER BY posts_count DESC, tg.id",
            [$locale, $fallback]
        );
    }

    /** @return array{items:array,total:int,page:int,per_page:int,total_pages:int} */
    public function posts(string $locale, array $filters = [], int $perPage = 6, int $page = 1): array
    {
        $fallback = $this->fallback($locale);

        $from = " FROM blog_posts p
                  LEFT JOIN blog_post_translations t ON t.post_id = p.id AND t.lang = ?
                  LEFT JOIN blog_post_translations f ON f.post_id = p.id AND f.lang = ?
                  LEFT JOIN blog_categories c ON c.id = p.category_id
                  LEFT JOIN blog_category_translations ct ON ct.category_id = c.id AND ct.lang = ?
                  LEFT JOIN blog_category_translations cf ON cf.category_id = c.id AND cf.lang = ?
                  LEFT JOIN media m ON m.id = p.cover_media_id";
        $bindings = [$locale, $fallback, $locale, $fallback];
        $where = " WHERE p.status = 'published' AND (p.published_at IS NULL OR p.published_at <= ?)";
        $bindings[] = now_utc();

        if (!empty($filters['category'])) {
            $where .= ' AND (ct.slug = ? OR cf.slug = ?)';
            $bindings[] = (string) $filters['category'];
            $bindings[] = (string) $filters['category'];
        }

        if (!empty($filters['tag'])) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM blog_post_tags bt
                JOIN blog_tag_translations tt ON tt.tag_id = bt.tag_id AND tt.lang = ?
                WHERE bt.post_id = p.id AND tt.slug = ?
            )';
            $bindings[] = $locale;
            $bindings[] = (string) $filters['tag'];
        }

        if (!empty($filters['featured'])) {
            $where .= ' AND p.is_featured = 1';
        }

        if (!empty($filters['search'])) {
            $where .= ' AND (COALESCE(t.title, f.title) LIKE ? OR COALESCE(t.excerpt, f.excerpt) LIKE ?)';
            $term = '%' . $filters['search'] . '%';
            $bindings[] = $term;
            $bindings[] = $term;
        }

        $total = (int) Database::scalar('SELECT COUNT(*)' . $from . $where, $bindings);
        $perPage = max(1, min($perPage, 24));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $items = Database::select(
            "SELECT p.id, p.status, p.published_at, p.is_featured, p.views, p.reading_time, p.cover_media_id,
                    COALESCE(t.title, f.title) AS title,
                    COALESCE(t.slug, f.slug) AS slug,
                    COALESCE(t.excerpt, f.excerpt) AS excerpt,
                    COALESCE(ct.name, cf.name) AS category_name,
                    COALESCE(ct.slug, cf.slug) AS category_slug,
                    c.color AS category_color, c.icon AS category_icon,
                    m.url AS cover_url, m.path AS cover_path, m.width AS cover_width, m.height AS cover_height,
                    (SELECT GROUP_CONCAT(COALESCE(tt.name, tf.name))
                     FROM blog_post_tags bt
                     JOIN blog_tag_translations tt ON tt.tag_id = bt.tag_id AND tt.lang = ?
                     LEFT JOIN blog_tag_translations tf ON tf.tag_id = bt.tag_id AND tf.lang = ?
                     WHERE bt.post_id = p.id) AS tags"
            . $from . $where
            . ' ORDER BY p.is_featured DESC, p.published_at DESC, p.id DESC'
            . " LIMIT {$perPage} OFFSET {$offset}",
            array_merge([$locale, $fallback], $bindings)
        );

        foreach ($items as $index => $item) {
            $items[$index] = $this->decoratePostCard($item, $locale);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) max(1, ceil($total / $perPage)),
        ];
    }

    public function post(string $slug, string $locale, bool $countView = true): ?array
    {
        $fallback = $this->fallback($locale);

        $row = Database::selectOne(
            "SELECT p.id, p.status, p.published_at, p.is_featured, p.views, p.reading_time, p.allow_comments,
                    p.canonical_url, p.created_at, p.updated_at, p.category_id, p.author_id,
                    COALESCE(t.title, f.title) AS title,
                    COALESCE(t.slug, f.slug) AS slug,
                    COALESCE(t.excerpt, f.excerpt) AS excerpt,
                    COALESCE(t.content, f.content) AS content,
                    COALESCE(t.seo_title, f.seo_title) AS seo_title,
                    COALESCE(t.seo_description, f.seo_description) AS seo_description,
                    COALESCE(t.keywords, f.keywords) AS keywords,
                    COALESCE(ct.name, cf.name) AS category_name,
                    COALESCE(ct.slug, cf.slug) AS category_slug,
                    c.color AS category_color, c.icon AS category_icon,
                    m.url AS cover_url, m.path AS cover_path, m.width AS cover_width, m.height AS cover_height,
                    a.name AS author_name
             FROM blog_posts p
             LEFT JOIN blog_post_translations t ON t.post_id = p.id AND t.lang = ?
             LEFT JOIN blog_post_translations f ON f.post_id = p.id AND f.lang = ?
             LEFT JOIN blog_categories c ON c.id = p.category_id
             LEFT JOIN blog_category_translations ct ON ct.category_id = c.id AND ct.lang = ?
             LEFT JOIN blog_category_translations cf ON cf.category_id = c.id AND cf.lang = ?
             LEFT JOIN media m ON m.id = p.cover_media_id
             LEFT JOIN admins a ON a.id = p.author_id
             WHERE p.status = 'published' AND (t.slug = ? OR f.slug = ?)
             LIMIT 1",
            [$locale, $fallback, $locale, $fallback, $slug, $slug]
        );

        if ($row === null) {
            return null;
        }

        $postId = (int) $row['id'];
        $row = $this->decoratePostCard($row, $locale);

        $row['tags'] = Database::select(
            "SELECT tg.id, COALESCE(t.name, f.name) AS name, COALESCE(t.slug, f.slug) AS slug
             FROM blog_post_tags bt
             JOIN blog_tags tg ON tg.id = bt.tag_id
             LEFT JOIN blog_tag_translations t ON t.tag_id = tg.id AND t.lang = ?
             LEFT JOIN blog_tag_translations f ON f.tag_id = tg.id AND f.lang = ?
             WHERE bt.post_id = ?",
            [$locale, $fallback, $postId]
        );

        $row['related'] = array_map(
            fn (array $item): array => $this->decoratePostCard($item, $locale),
            Database::select(
                "SELECT p.id, p.published_at, p.reading_time, COALESCE(t.title, f.title) AS title,
                        COALESCE(t.slug, f.slug) AS slug, COALESCE(t.excerpt, f.excerpt) AS excerpt,
                        m.url AS cover_url, m.path AS cover_path
                 FROM blog_posts p
                 LEFT JOIN blog_post_translations t ON t.post_id = p.id AND t.lang = ?
                 LEFT JOIN blog_post_translations f ON f.post_id = p.id AND f.lang = ?
                 LEFT JOIN media m ON m.id = p.cover_media_id
                 WHERE p.status = 'published' AND p.id != ? AND (p.category_id = ? OR p.category_id IS NULL)
                 ORDER BY p.published_at DESC LIMIT 3",
                [$locale, $fallback, $postId, $row['category_id']]
            )
        );

        if ($countView) {
            Database::table('blog_posts')->where('id', $postId)->increment('views');
        }

        return $row;
    }

    private function decoratePostCard(array $item, string $locale): array
    {
        $item['id'] = (int) $item['id'];
        $item['views'] = (int) ($item['views'] ?? 0);
        $item['reading_time'] = (int) ($item['reading_time'] ?? 1);
        $item['is_featured'] = (bool) ($item['is_featured'] ?? false);
        $item['cover'] = !empty($item['cover_url']) ? [
            'url' => $item['cover_url'],
            'path' => $item['cover_path'] ?? null,
            'width' => isset($item['cover_width']) && $item['cover_width'] !== null ? (int) $item['cover_width'] : null,
            'height' => isset($item['cover_height']) && $item['cover_height'] !== null ? (int) $item['cover_height'] : null,
        ] : null;
        $item['tag_list'] = isset($item['tags']) && is_string($item['tags']) && $item['tags'] !== ''
            ? explode(',', $item['tags'])
            : ($item['tag_list'] ?? []);
        $item['url'] = '/' . $locale . '/blog/' . ($item['slug'] ?? '');

        unset($item['cover_path'], $item['cover_width'], $item['cover_height'], $item['cover_media_id']);

        return $item;
    }

    /* --------------------------------------------------------------------- */
    /* Résumé, contact, search                                               */
    /* --------------------------------------------------------------------- */

    public function resume(string $locale): ?array
    {
        $row = Database::selectOne(
            "SELECT r.id, r.lang, r.title, r.file_path, r.version, r.is_primary,
                    m.url AS file_url, m.mime_type AS file_mime
             FROM resumes r
             LEFT JOIN media m ON m.id = r.file_media_id
             WHERE r.lang = ? AND r.is_active = 1
             ORDER BY r.is_primary DESC, r.id
             LIMIT 1",
            [$locale]
        );

        if ($row === null) {
            return null;
        }

        return $row + [
            'download_url' => $row['file_url'] !== null
                ? '/api/v1/resume/download?lang=' . $locale
                : null,
            'print_url' => '/' . $locale . '/resume?print=1',
        ];
    }

    public function contactInfo(string $locale): array
    {
        $settings = new SettingsService();

        return [
            'email' => $settings->get('contact_email'),
            'phone' => $settings->get('contact_phone'),
            'phone_display' => $settings->translated('contact_phone_display', $locale),
            'location' => $settings->translated('contact_location', $locale),
            'address' => $settings->translated('contact_address', $locale),
            'working_hours' => $settings->translated('working_hours', $locale),
            'response_time' => $settings->translated('response_time', $locale),
            'availability' => $settings->bool('availability_enabled')
                ? $settings->translated('availability_status', $locale)
                : null,
            'socials' => $this->socialLinks($locale),
        ];
    }

    /** Cross-content search used by the site search box. */
    public function search(string $term, string $locale, int $limit = 8): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return ['projects' => [], 'posts' => [], 'services' => []];
        }

        $projects = $this->projects($locale, ['search' => $term], $limit, 1)['items'];
        $posts = $this->posts($locale, ['search' => $term], $limit, 1)['items'];
        $services = array_values(array_filter(
            $this->services($locale),
            static fn (array $service): bool => mb_stripos((string) $service['title'], $term) !== false
                || mb_stripos((string) $service['summary'], $term) !== false
        ));

        return ['projects' => $projects, 'posts' => $posts, 'services' => array_slice($services, 0, $limit)];
    }

    /**
     * Find the translated slug of an entity so the language switcher can keep
     * the visitor on the same page when they change language.
     */
    public function translateSlug(string $type, string $slug, string $from, string $to): ?string
    {
        $config = match ($type) {
            'project' => ['project_translations', 'project_id'],
            'post' => ['blog_post_translations', 'post_id'],
            default => null,
        };

        if ($config === null) {
            return null;
        }

        [$table, $foreignKey] = $config;

        $row = Database::selectOne(
            "SELECT target.slug
             FROM {$table} source
             JOIN {$table} target ON target.{$foreignKey} = source.{$foreignKey} AND target.lang = ?
             WHERE source.lang = ? AND source.slug = ?
             LIMIT 1",
            [$to, $from, $slug]
        );

        return $row === null ? null : (string) $row['slug'];
    }

    public function featuredTechnologies(string $locale, int $limit = 12): array
    {
        return array_slice($this->skills($locale, 'technology')['items'], 0, $limit);
    }

    /** Home page payload assembled from the section builder. */
    public function home(string $locale): array
    {
        $sections = $this->sections('home', $locale);
        $settings = new SettingsService();

        return [
            'page' => $this->page('home', $locale),
            'sections' => $sections,
            'statistics' => $this->statistics(),
            'featured_projects' => $this->projects($locale, ['featured' => true], 6, 1)['items'],
            'services' => $this->services($locale, true),
            'skills' => $this->skills($locale, 'skill'),
            'technologies' => $this->featuredTechnologies($locale),
            'experiences' => array_slice($this->experiences($locale), 0, 4),
            'testimonials' => $this->testimonials($locale),
            'posts' => $this->posts($locale, [], 3, 1)['items'],
            'contact' => $this->contactInfo($locale),
            'hero_code' => $settings->get('hero_code_snippet', ''),
        ];
    }
}
