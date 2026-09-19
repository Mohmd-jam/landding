<?php

declare(strict_types=1);

namespace App\Http\Resources;

/**
 * Single source of truth for the admin panel's CRUD resources.
 *
 * Every entry describes: the base table, its translation table, the validated
 * fields (per language where relevant), relations (media, pivots, children) and
 * how lists are searched/ordered. The generic AdminResourceController uses this
 * map, and `GET /api/admin/schema` exposes it to React so forms are generated
 * from the real server-side rules instead of being hand-copied on the client —
 * no drift, and no client-side validation is ever trusted.
 */
final class AdminResourceRegistry
{
    /** Field type hints consumed by the React form renderer. */
    public const FORM_TYPES = [
        'title', 'slug', 'text', 'textarea', 'richtext', 'number', 'range', 'boolean',
        'select', 'multiselect', 'date', 'datetime', 'email', 'url', 'color', 'icon',
        'media', 'media_gallery', 'json', 'tags', 'password', 'hidden',
    ];

    /** @return array<int,string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /** @return array<string,mixed>|null */
    public static function get(string $key): ?array
    {
        $resource = self::all()[$key] ?? null;

        if ($resource === null) {
            return null;
        }

        $resource['key'] = $key;
        $resource['label'] = $resource['label'] ?? ucfirst(str_replace('_', ' ', $key));

        return $resource;
    }

    /** Compact definition used by `GET /api/admin/schema`. */
    public static function schema(): array
    {
        $out = [];

        foreach (self::all() as $key => $resource) {
            $out[$key] = [
                'key' => $key,
                'label' => $resource['label'],
                'group' => $resource['group'] ?? 'content',
                'translatable' => (bool) ($resource['translatable'] ?? false),
                'fields' => $resource['fields'],
                'list' => $resource['list'] ?? ['columns' => ['id']],
                'filters' => $resource['filters'] ?? [],
                'singular' => $resource['singular'] ?? rtrim($key, 's'),
            ];
        }

        return $out;
    }

    /**
     * Field definition shortcuts.
     *
     * @return array<string,mixed>
     */
    private static function field(string $type, array $rules = [], array $extra = []): array
    {
        return ['type' => $type, 'rules' => $rules] + $extra;
    }

    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        static $resources = null;

        if ($resources !== null) {
            return $resources;
        }

        $resources = [
            /* ------------------------------------------------------------- */
            /* Site structure                                                */
            /* ------------------------------------------------------------- */
            'pages' => [
                'label' => 'Pages',
                'group' => 'structure',
                'singular' => 'page',
                'table' => 'pages',
                'translation_table' => 'page_translations',
                'foreign_key' => 'page_id',
                'translatable' => true,
                'fields' => [
                    'key_name' => self::field('text', ['required', 'slug', 'max' => 60], ['label' => 'Key', 'help' => 'Used by the router; changing it changes the URL.']),
                    'template' => self::field('select', ['nullable', 'in' => ['default', 'home', 'projects', 'blog', 'contact', 'resume', 'legal']], ['label' => 'Template']),
                    'icon' => self::field('icon', ['nullable', 'max' => 60]),
                    'is_active' => self::field('boolean', [], ['label' => 'Active', 'default' => true]),
                    'sort_order' => self::field('number', ['int', 'min' => 0], ['label' => 'Order', 'default' => 0]),
                    'title' => self::field('title', ['required', 'max' => 200], ['translated' => true]),
                    'subtitle' => self::field('text', ['nullable', 'max' => 300], ['translated' => true]),
                    'content' => self::field('richtext', ['nullable'], ['translated' => true]),
                    'seo_title' => self::field('text', ['nullable', 'max' => 200], ['translated' => true]),
                    'seo_description' => self::field('textarea', ['nullable', 'max' => 320], ['translated' => true]),
                ],
                'list' => ['columns' => ['id', 'key_name', 'title', 'is_active', 'sort_order'], 'order' => ['sort_order', 'id']],
                'filters' => ['is_active'],
            ],
            'sections' => [
                'label' => 'Homepage sections',
                'group' => 'structure',
                'singular' => 'section',
                'table' => 'sections',
                'translation_table' => 'section_translations',
                'foreign_key' => 'section_id',
                'translatable' => true,
                'fields' => [
                    'page_id' => self::field('select', ['required', 'int', 'exists:pages,id'], ['label' => 'Page', 'options' => 'pages']),
                    'key_name' => self::field('text', ['required', 'slug', 'max' => 60], ['label' => 'Key']),
                    'type_name' => self::field('select', ['required', 'in' => ['hero', 'about', 'skills', 'services', 'projects', 'experience', 'testimonials', 'blog', 'contact', 'stats', 'cta', 'logos', 'process', 'faq']], ['label' => 'Section type']),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'sort_order' => self::field('number', ['int', 'min' => 0], ['default' => 0]),
                    'settings_json' => self::field('json', ['nullable', 'json'], ['label' => 'Settings (JSON)']),
                    'eyebrow' => self::field('text', ['nullable', 'max' => 120], ['translated' => true]),
                    'title' => self::field('title', ['nullable', 'max' => 220], ['translated' => true]),
                    'subtitle' => self::field('textarea', ['nullable', 'max' => 320], ['translated' => true]),
                    'body' => self::field('richtext', ['nullable'], ['translated' => true]),
                    'cta_label' => self::field('text', ['nullable', 'max' => 80], ['translated' => true]),
                    'cta_url' => self::field('text', ['nullable', 'max' => 255], ['translated' => true]),
                    'items_json' => self::field('json', ['nullable', 'json'], ['translated' => true, 'label' => 'Items (JSON)']),
                ],
                'list' => ['columns' => ['id', 'key_name', 'type_name', 'title', 'is_active', 'sort_order'], 'order' => ['sort_order', 'id']],
                'filters' => ['page_id', 'is_active'],
            ],
            'navigation' => [
                'label' => 'Navigation',
                'group' => 'structure',
                'singular' => 'menu item',
                'table' => 'navigation',
                'translation_table' => 'navigation_translations',
                'foreign_key' => 'navigation_id',
                'translatable' => true,
                'fields' => [
                    'location' => self::field('select', ['required', 'in' => ['header', 'footer_quick', 'footer_services', 'footer_company', 'footer_legal']], ['label' => 'Menu']),
                    'parent_id' => self::field('select', ['nullable', 'int'], ['label' => 'Parent', 'options' => 'self']),
                    'type_name' => self::field('select', ['required', 'in' => ['route', 'url', 'page', 'project_category', 'blog_category', 'anchor']], ['label' => 'Link type']),
                    'target_value' => self::field('text', ['required', 'max' => 255], ['label' => 'Target', 'help' => 'Path, absolute URL or anchor (#contact).']),
                    'icon' => self::field('icon', ['nullable', 'max' => 60]),
                    'open_in_new_tab' => self::field('boolean', [], ['label' => 'New tab']),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'sort_order' => self::field('number', ['int', 'min' => 0], ['default' => 0]),
                    'label' => self::field('text', ['required', 'max' => 120], ['translated' => true]),
                ],
                'list' => ['columns' => ['id', 'location', 'label', 'target_value', 'is_active', 'sort_order'], 'order' => ['location', 'sort_order']],
                'filters' => ['location', 'is_active'],
            ],
            'social_links' => [
                'label' => 'Social links',
                'group' => 'structure',
                'singular' => 'social link',
                'table' => 'social_links',
                'translation_table' => 'social_link_translations',
                'foreign_key' => 'social_link_id',
                'translatable' => true,
                'fields' => [
                    'platform' => self::field('select', ['required', 'in' => ['github', 'linkedin', 'instagram', 'telegram', 'x', 'youtube', 'dribbble', 'behance', 'stackoverflow', 'whatsapp', 'email', 'website', 'other']]),
                    'url' => self::field('url', ['required', 'url', 'max' => 255]),
                    'icon' => self::field('icon', ['nullable', 'max' => 60]),
                    'color' => self::field('color', ['nullable', 'max' => 20]),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'sort_order' => self::field('number', ['int', 'min' => 0], ['default' => 0]),
                    'label' => self::field('text', ['required', 'max' => 80], ['translated' => true]),
                    'handle' => self::field('text', ['nullable', 'max' => 80], ['translated' => true]),
                ],
                'list' => ['columns' => ['id', 'platform', 'label', 'url', 'is_active', 'sort_order'], 'order' => ['sort_order', 'id']],
                'filters' => ['is_active'],
            ],
            'languages' => [
                'label' => 'Languages',
                'group' => 'system',
                'singular' => 'language',
                'table' => 'languages',
                'translatable' => false,
                'fields' => [
                    'code' => self::field('text', ['required', 'max' => 8, 'regex:/^[a-z]{2}(-[A-Z]{2})?$/'], ['help' => 'ISO code, e.g. fa or en.']),
                    'name' => self::field('text', ['required', 'max' => 64]),
                    'native_name' => self::field('text', ['required', 'max' => 64]),
                    'direction' => self::field('select', ['required', 'in' => ['ltr', 'rtl']]),
                    'flag' => self::field('text', ['nullable', 'max' => 16]),
                    'is_default' => self::field('boolean'),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'sort_order' => self::field('number', ['int', 'min' => 0]),
                ],
                'list' => ['columns' => ['id', 'code', 'name', 'native_name', 'direction', 'is_default', 'is_active'], 'order' => ['sort_order', 'id']],
                'filters' => ['is_active'],
            ],
            'translations' => [
                'label' => 'Interface strings',
                'group' => 'system',
                'singular' => 'string',
                'table' => 'translations',
                'translation_table' => 'translation_values',
                'foreign_key' => 'translation_id',
                'translatable' => true,
                'fields' => [
                    'key_name' => self::field('text', ['required', 'max' => 150], ['label' => 'Key']),
                    'group_key' => self::field('text', ['required', 'max' => 40], ['default' => 'general']),
                    'value' => self::field('textarea', ['required'], ['translated' => true, 'label' => 'Value']),
                ],
                'list' => ['columns' => ['id', 'key_name', 'group_key', 'value'], 'order' => ['group_key', 'key_name']],
                'filters' => ['group_key'],
            ],
            'resumes' => [
                'label' => 'Résumés',
                'group' => 'content',
                'singular' => 'résumé',
                'table' => 'resumes',
                'translatable' => false,
                'fields' => [
                    'lang' => self::field('select', ['required', 'max' => 8], ['options' => 'languages']),
                    'title' => self::field('text', ['required', 'max' => 200]),
                    'file_media_id' => self::field('media', ['nullable', 'int', 'exists:media,id']),
                    'version' => self::field('text', ['nullable', 'max' => 40]),
                    'is_primary' => self::field('boolean', [], ['default' => true]),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                ],
                'list' => ['columns' => ['id', 'lang', 'title', 'version', 'downloads', 'is_primary', 'is_active'], 'order' => ['lang', 'id']],
                'filters' => ['lang'],
            ],

            /* ------------------------------------------------------------- */
            /* Skills & services                                             */
            /* ------------------------------------------------------------- */
            'skill_categories' => [
                'label' => 'Skill categories',
                'group' => 'skills',
                'singular' => 'category',
                'table' => 'skill_categories',
                'translation_table' => 'skill_category_translations',
                'foreign_key' => 'category_id',
                'translatable' => true,
                'fields' => [
                    'key_name' => self::field('text', ['required', 'slug', 'max' => 60]),
                    'icon' => self::field('icon', ['nullable', 'max' => 60]),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'sort_order' => self::field('number', ['int', 'min' => 0]),
                    'name' => self::field('text', ['required', 'max' => 120], ['translated' => true]),
                    'description' => self::field('textarea', ['nullable', 'max' => 300], ['translated' => true]),
                ],
                'list' => ['columns' => ['id', 'key_name', 'name', 'is_active', 'sort_order'], 'order' => ['sort_order', 'id']],
                'filters' => ['is_active'],
            ],
            'skills' => [
                'label' => 'Skills & technologies',
                'group' => 'skills',
                'singular' => 'skill',
                'table' => 'skills',
                'translation_table' => 'skill_translations',
                'foreign_key' => 'skill_id',
                'translatable' => true,
                'fields' => [
                    'category_id' => self::field('select', ['nullable', 'int', 'exists:skill_categories,id'], ['options' => 'skill_categories']),
                    'kind' => self::field('select', ['required', 'in' => ['skill', 'technology']]),
                    'icon' => self::field('icon', ['nullable', 'max' => 60]),
                    'level' => self::field('range', ['int', 'between:0,100'], ['default' => 80]),
                    'proficiency' => self::field('text', ['nullable', 'max' => 40]),
                    'years' => self::field('text', ['nullable', 'max' => 20]),
                    'color' => self::field('color', ['nullable', 'max' => 20]),
                    'is_featured' => self::field('boolean'),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'sort_order' => self::field('number', ['int', 'min' => 0]),
                    'name' => self::field('text', ['required', 'max' => 120], ['translated' => true]),
                    'description' => self::field('textarea', ['nullable', 'max' => 400], ['translated' => true]),
                ],
                'list' => ['columns' => ['id', 'name', 'kind', 'category_id', 'level', 'is_featured', 'is_active', 'sort_order'], 'order' => ['sort_order', 'id']],
                'filters' => ['kind', 'category_id', 'is_active'],
            ],
            'services' => [
                'label' => 'Services',
                'group' => 'content',
                'singular' => 'service',
                'table' => 'services',
                'translation_table' => 'service_translations',
                'foreign_key' => 'service_id',
                'translatable' => true,
                'slug_source' => 'title',
                'slug_type' => 'service',
                'fields' => [
                    'icon' => self::field('icon', ['nullable', 'max' => 60]),
                    'accent' => self::field('color', ['nullable', 'max' => 20]),
                    'is_featured' => self::field('boolean'),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'sort_order' => self::field('number', ['int', 'min' => 0]),
                    'title' => self::field('title', ['required', 'max' => 160], ['translated' => true]),
                    'slug' => self::field('slug', ['nullable', 'max' => 180], ['translated' => true, 'auto_from' => 'title']),
                    'summary' => self::field('textarea', ['nullable', 'max' => 400], ['translated' => true]),
                    'description' => self::field('richtext', ['nullable'], ['translated' => true]),
                    'features_json' => self::field('tags', ['nullable'], ['translated' => true, 'label' => 'Feature bullets']),
                ],
                'list' => ['columns' => ['id', 'title', 'is_featured', 'is_active', 'sort_order'], 'order' => ['sort_order', 'id']],
                'filters' => ['is_active'],
            ],

            /* ------------------------------------------------------------- */
            /* Portfolio                                                     */
            /* ------------------------------------------------------------- */
            'project_categories' => [
                'label' => 'Project categories',
                'group' => 'portfolio',
                'singular' => 'category',
                'table' => 'project_categories',
                'translation_table' => 'project_category_translations',
                'foreign_key' => 'category_id',
                'translatable' => true,
                'slug_source' => 'name',
                'slug_type' => 'project_category',
                'fields' => [
                    'icon' => self::field('icon', ['nullable', 'max' => 60]),
                    'color' => self::field('color', ['nullable', 'max' => 20]),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'sort_order' => self::field('number', ['int', 'min' => 0]),
                    'name' => self::field('text', ['required', 'max' => 120], ['translated' => true]),
                    'slug' => self::field('slug', ['nullable', 'max' => 140], ['translated' => true, 'auto_from' => 'name']),
                    'description' => self::field('textarea', ['nullable', 'max' => 300], ['translated' => true]),
                ],
                'list' => ['columns' => ['id', 'name', 'is_active', 'sort_order'], 'order' => ['sort_order', 'id']],
                'filters' => ['is_active'],
            ],
            'projects' => [
                'label' => 'Projects',
                'group' => 'portfolio',
                'singular' => 'project',
                'table' => 'projects',
                'translation_table' => 'project_translations',
                'foreign_key' => 'project_id',
                'translatable' => true,
                'slug_source' => 'title',
                'slug_type' => 'project',
                'seo' => true,
                'fields' => [
                    'category_id' => self::field('select', ['nullable', 'int', 'exists:project_categories,id'], ['options' => 'project_categories']),
                    'client' => self::field('text', ['nullable', 'max' => 160]),
                    'project_url' => self::field('url', ['nullable', 'url', 'max' => 255]),
                    'github_url' => self::field('url', ['nullable', 'url', 'max' => 255]),
                    'cover_media_id' => self::field('media', ['nullable', 'int', 'exists:media,id'], ['label' => 'Cover image']),
                    'tech_stack' => self::field('text', ['nullable', 'max' => 400], ['help' => 'Display pills, separated by ·']),
                    'started_at' => self::field('date', ['nullable', 'date']),
                    'completed_at' => self::field('date', ['nullable', 'date']),
                    'is_featured' => self::field('boolean'),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'sort_order' => self::field('number', ['int', 'min' => 0]),
                    'title' => self::field('title', ['required', 'max' => 200], ['translated' => true]),
                    'slug' => self::field('slug', ['nullable', 'max' => 200], ['translated' => true, 'auto_from' => 'title']),
                    'summary' => self::field('textarea', ['required', 'max' => 500], ['translated' => true]),
                    'description' => self::field('richtext', ['required'], ['translated' => true]),
                    'challenge' => self::field('richtext', ['nullable'], ['translated' => true]),
                    'solution' => self::field('richtext', ['nullable'], ['translated' => true]),
                    'results' => self::field('richtext', ['nullable'], ['translated' => true]),
                    'seo_title' => self::field('text', ['nullable', 'max' => 200], ['translated' => true]),
                    'seo_description' => self::field('textarea', ['nullable', 'max' => 320], ['translated' => true]),
                ],
                'relations' => [
                    'technology_ids' => [
                        'type' => 'pivot',
                        'table' => 'project_technologies',
                        'foreign_key' => 'project_id',
                        'related_key' => 'skill_id',
                        'order' => 'sort_order',
                        'rules' => ['nullable', 'array'],
                        'item_rules' => ['int', 'exists:skills,id'],
                    ],
                    'gallery_ids' => [
                        'type' => 'gallery',
                        'table' => 'project_images',
                        'foreign_key' => 'project_id',
                        'related_key' => 'media_id',
                        'order' => 'sort_order',
                        'rules' => ['nullable', 'array'],
                        'item_rules' => ['int', 'exists:media,id'],
                    ],
                ],
                'list' => ['columns' => ['id', 'title', 'category_id', 'is_featured', 'is_active', 'views', 'completed_at', 'sort_order'], 'order' => ['sort_order', 'id']],
                'filters' => ['category_id', 'is_featured', 'is_active'],
            ],

            /* ------------------------------------------------------------- */
            /* Timeline                                                      */
            /* ------------------------------------------------------------- */
            'experiences' => [
                'label' => 'Experience',
                'group' => 'timeline',
                'singular' => 'experience',
                'table' => 'experiences',
                'translation_table' => 'experience_translations',
                'foreign_key' => 'experience_id',
                'translatable' => true,
                'fields' => [
                    'company' => self::field('text', ['required', 'max' => 160]),
                    'company_url' => self::field('url', ['nullable', 'url', 'max' => 255]),
                    'company_logo_media_id' => self::field('media', ['nullable', 'int', 'exists:media,id']),
                    'location' => self::field('text', ['nullable', 'max' => 120]),
                    'employment_type' => self::field('select', ['required', 'in' => ['full_time', 'part_time', 'contract', 'freelance', 'internship']]),
                    'start_date' => self::field('date', ['required', 'date']),
                    'end_date' => self::field('date', ['nullable', 'date']),
                    'is_current' => self::field('boolean'),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'sort_order' => self::field('number', ['int', 'min' => 0]),
                    'position' => self::field('text', ['required', 'max' => 160], ['translated' => true]),
                    'description' => self::field('richtext', ['nullable'], ['translated' => true]),
                    'responsibilities_json' => self::field('tags', ['nullable'], ['translated' => true, 'label' => 'Responsibilities']),
                    'achievements_json' => self::field('tags', ['nullable'], ['translated' => true, 'label' => 'Achievements']),
                ],
                'list' => ['columns' => ['id', 'company', 'position', 'start_date', 'end_date', 'is_current', 'sort_order'], 'order' => ['start_date', 'sort_order']],
                'filters' => ['is_active'],
            ],
            'education' => [
                'label' => 'Education',
                'group' => 'timeline',
                'singular' => 'education entry',
                'table' => 'education',
                'translation_table' => 'education_translations',
                'foreign_key' => 'education_id',
                'translatable' => true,
                'fields' => [
                    'institution' => self::field('text', ['required', 'max' => 180]),
                    'institution_url' => self::field('url', ['nullable', 'url', 'max' => 255]),
                    'logo_media_id' => self::field('media', ['nullable', 'int', 'exists:media,id']),
                    'location' => self::field('text', ['nullable', 'max' => 120]),
                    'start_date' => self::field('date', ['required', 'date']),
                    'end_date' => self::field('date', ['nullable', 'date']),
                    'is_current' => self::field('boolean'),
                    'gpa' => self::field('text', ['nullable', 'max' => 20]),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'sort_order' => self::field('number', ['int', 'min' => 0]),
                    'degree' => self::field('text', ['required', 'max' => 160], ['translated' => true]),
                    'field' => self::field('text', ['nullable', 'max' => 160], ['translated' => true]),
                    'description' => self::field('richtext', ['nullable'], ['translated' => true]),
                ],
                'list' => ['columns' => ['id', 'institution', 'degree', 'start_date', 'end_date', 'sort_order'], 'order' => ['start_date', 'sort_order']],
                'filters' => ['is_active'],
            ],
            'certifications' => [
                'label' => 'Certifications',
                'group' => 'timeline',
                'singular' => 'certification',
                'table' => 'certifications',
                'translation_table' => 'certification_translations',
                'foreign_key' => 'certification_id',
                'translatable' => true,
                'fields' => [
                    'issuer' => self::field('text', ['required', 'max' => 180]),
                    'issuer_url' => self::field('url', ['nullable', 'url', 'max' => 255]),
                    'credential_id' => self::field('text', ['nullable', 'max' => 120]),
                    'credential_url' => self::field('url', ['nullable', 'url', 'max' => 255]),
                    'image_media_id' => self::field('media', ['nullable', 'int', 'exists:media,id']),
                    'issue_date' => self::field('date', ['nullable', 'date']),
                    'expiry_date' => self::field('date', ['nullable', 'date']),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'sort_order' => self::field('number', ['int', 'min' => 0]),
                    'title' => self::field('title', ['required', 'max' => 200], ['translated' => true]),
                    'description' => self::field('richtext', ['nullable'], ['translated' => true]),
                ],
                'list' => ['columns' => ['id', 'title', 'issuer', 'issue_date', 'is_active', 'sort_order'], 'order' => ['issue_date', 'sort_order']],
                'filters' => ['is_active'],
            ],
            'testimonials' => [
                'label' => 'Testimonials',
                'group' => 'content',
                'singular' => 'testimonial',
                'table' => 'testimonials',
                'translation_table' => 'testimonial_translations',
                'foreign_key' => 'testimonial_id',
                'translatable' => true,
                'fields' => [
                    'avatar_media_id' => self::field('media', ['nullable', 'int', 'exists:media,id']),
                    'rating' => self::field('number', ['int', 'between:1,5'], ['default' => 5]),
                    'lang' => self::field('select', ['required', 'max' => 8], ['options' => 'languages', 'label' => 'Language of the quote']),
                    'is_featured' => self::field('boolean'),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'sort_order' => self::field('number', ['int', 'min' => 0]),
                    'client_name' => self::field('text', ['required', 'max' => 160], ['translated' => true]),
                    'client_position' => self::field('text', ['nullable', 'max' => 160], ['translated' => true]),
                    'company' => self::field('text', ['nullable', 'max' => 160], ['translated' => true]),
                    'quote' => self::field('textarea', ['required'], ['translated' => true]),
                ],
                'list' => ['columns' => ['id', 'client_name', 'company', 'rating', 'lang', 'is_featured', 'is_active', 'sort_order'], 'order' => ['sort_order', 'id']],
                'filters' => ['is_active', 'lang'],
            ],

            /* ------------------------------------------------------------- */
            /* Blog                                                          */
            /* ------------------------------------------------------------- */
            'blog_categories' => [
                'label' => 'Blog categories',
                'group' => 'blog',
                'singular' => 'category',
                'table' => 'blog_categories',
                'translation_table' => 'blog_category_translations',
                'foreign_key' => 'category_id',
                'translatable' => true,
                'slug_source' => 'name',
                'slug_type' => 'blog_category',
                'fields' => [
                    'icon' => self::field('icon', ['nullable', 'max' => 60]),
                    'color' => self::field('color', ['nullable', 'max' => 20]),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'sort_order' => self::field('number', ['int', 'min' => 0]),
                    'name' => self::field('text', ['required', 'max' => 120], ['translated' => true]),
                    'slug' => self::field('slug', ['nullable', 'max' => 140], ['translated' => true, 'auto_from' => 'name']),
                    'description' => self::field('textarea', ['nullable', 'max' => 300], ['translated' => true]),
                ],
                'list' => ['columns' => ['id', 'name', 'is_active', 'sort_order'], 'order' => ['sort_order', 'id']],
                'filters' => ['is_active'],
            ],
            'blog_tags' => [
                'label' => 'Blog tags',
                'group' => 'blog',
                'singular' => 'tag',
                'table' => 'blog_tags',
                'translation_table' => 'blog_tag_translations',
                'foreign_key' => 'tag_id',
                'translatable' => true,
                'slug_source' => 'name',
                'slug_type' => 'blog_tag',
                'fields' => [
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'name' => self::field('text', ['required', 'max' => 80], ['translated' => true]),
                    'slug' => self::field('slug', ['nullable', 'max' => 100], ['translated' => true, 'auto_from' => 'name']),
                ],
                'list' => ['columns' => ['id', 'name', 'is_active'], 'order' => ['id']],
                'filters' => ['is_active'],
            ],
            'blog_posts' => [
                'label' => 'Articles',
                'group' => 'blog',
                'singular' => 'article',
                'table' => 'blog_posts',
                'translation_table' => 'blog_post_translations',
                'foreign_key' => 'post_id',
                'translatable' => true,
                'slug_source' => 'title',
                'slug_type' => 'post',
                'seo' => true,
                'fields' => [
                    'category_id' => self::field('select', ['nullable', 'int', 'exists:blog_categories,id'], ['options' => 'blog_categories']),
                    'cover_media_id' => self::field('media', ['nullable', 'int', 'exists:media,id']),
                    'status' => self::field('select', ['required', 'in' => ['draft', 'published', 'scheduled']]),
                    'published_at' => self::field('datetime', ['nullable', 'date'], ['help' => 'A future date publishes the article automatically.']),
                    'is_featured' => self::field('boolean'),
                    'allow_comments' => self::field('boolean'),
                    'reading_time' => self::field('number', ['nullable', 'int', 'min' => 1, 'max' => 120]),
                    'canonical_url' => self::field('url', ['nullable', 'url', 'max' => 255]),
                    'og_image_media_id' => self::field('media', ['nullable', 'int', 'exists:media,id']),
                    'sort_order' => self::field('number', ['int', 'min' => 0]),
                    'title' => self::field('title', ['required', 'max' => 220], ['translated' => true]),
                    'slug' => self::field('slug', ['nullable', 'max' => 220], ['translated' => true, 'auto_from' => 'title']),
                    'excerpt' => self::field('textarea', ['nullable', 'max' => 500], ['translated' => true]),
                    'content' => self::field('richtext', ['required'], ['translated' => true]),
                    'seo_title' => self::field('text', ['nullable', 'max' => 200], ['translated' => true]),
                    'seo_description' => self::field('textarea', ['nullable', 'max' => 320], ['translated' => true]),
                    'keywords' => self::field('text', ['nullable', 'max' => 320], ['translated' => true, 'help' => 'Comma separated keywords']),
                ],
                'relations' => [
                    'tag_ids' => [
                        'type' => 'pivot',
                        'table' => 'blog_post_tags',
                        'foreign_key' => 'post_id',
                        'related_key' => 'tag_id',
                        'order' => null,
                        'rules' => ['nullable', 'array'],
                        'item_rules' => ['int', 'exists:blog_tags,id'],
                    ],
                ],
                'list' => ['columns' => ['id', 'title', 'category_id', 'status', 'published_at', 'views', 'is_featured'], 'order' => ['published_at', 'id']],
                'filters' => ['status', 'category_id', 'is_featured'],
            ],

            /* ------------------------------------------------------------- */
            /* Inbox & access                                                */
            /* ------------------------------------------------------------- */
            'messages' => [
                'label' => 'Messages',
                'group' => 'inbox',
                'singular' => 'message',
                'table' => 'messages',
                'translatable' => false,
                'readonly' => true,
                'fields' => [
                    'name' => self::field('text', ['required', 'max' => 160]),
                    'email' => self::field('email', ['required', 'email', 'max' => 190]),
                    'phone' => self::field('text', ['nullable', 'max' => 40]),
                    'company' => self::field('text', ['nullable', 'max' => 160]),
                    'subject' => self::field('text', ['nullable', 'max' => 200]),
                    'budget' => self::field('text', ['nullable', 'max' => 60]),
                    'service_interest' => self::field('text', ['nullable', 'max' => 120]),
                    'message' => self::field('textarea', ['required']),
                    'notes' => self::field('textarea', ['nullable']),
                    'is_read' => self::field('boolean'),
                    'is_starred' => self::field('boolean'),
                    'is_archived' => self::field('boolean'),
                ],
                'list' => ['columns' => ['id', 'name', 'email', 'subject', 'is_read', 'is_starred', 'created_at'], 'order' => ['created_at', 'id']],
                'filters' => ['is_read', 'is_starred', 'is_archived'],
            ],
            'admins' => [
                'label' => 'Admin users',
                'group' => 'system',
                'singular' => 'admin',
                'table' => 'admins',
                'translatable' => false,
                'fields' => [
                    'name' => self::field('text', ['required', 'max' => 120]),
                    'email' => self::field('email', ['required', 'email', 'max' => 190]),
                    'password' => self::field('password', ['nullable', 'password'], ['virtual' => true, 'help' => 'Leave empty to keep the current password.']),
                    'role' => self::field('select', ['required', 'in' => ['super_admin', 'admin', 'editor']]),
                    'avatar_media_id' => self::field('media', ['nullable', 'int', 'exists:media,id']),
                    'bio' => self::field('textarea', ['nullable', 'max' => 500]),
                    'is_active' => self::field('boolean', [], ['default' => true]),
                    'must_change_password' => self::field('boolean'),
                ],
                'list' => ['columns' => ['id', 'name', 'email', 'role', 'is_active', 'last_login_at'], 'order' => ['id']],
                'filters' => ['role', 'is_active'],
            ],
        ];

        return $resources;
    }

    /**
     * Translatable field names of a resource (used by the controller to split
     * incoming payloads into base + *_translations rows).
     *
     * @return array<int,string>
     */
    public static function translatableFields(array $resource): array
    {
        $fields = [];

        foreach ($resource['fields'] as $name => $field) {
            if (!empty($field['translated'])) {
                $fields[] = $name;
            }
        }

        return $fields;
    }

    /** @return array<int,string> */
    public static function baseFields(array $resource): array
    {
        $fields = [];

        foreach ($resource['fields'] as $name => $field) {
            if (empty($field['translated']) && empty($field['virtual'])) {
                $fields[] = $name;
            }
        }

        return $fields;
    }
}
