<?php

declare(strict_types=1);

namespace Database;

use App\Core\Database;
use App\Core\Json;
use App\Core\Security;
use App\Models\Admin;

/**
 * Runs the ordered seed files in backend/database/seeds.
 *
 * Every entity is inserted in Persian *and* English so both locales render
 * fully populated pages on first run, and the admin panel has real content to
 * manage. Seeds are idempotent: with --fresh the demo content tables are
 * cleared first, otherwise seeding is skipped when content already exists.
 */
final class Seeder
{
    private array $log = [];

    /** Tables cleared for a fresh seed (admin accounts are preserved). */
    private const CONTENT_TABLES = [
        'project_technologies', 'project_images', 'project_translations', 'projects',
        'project_category_translations', 'project_categories',
        'skill_translations', 'skills', 'skill_category_translations', 'skill_categories',
        'service_translations', 'services',
        'experience_translations', 'experiences',
        'education_translations', 'education',
        'certification_translations', 'certifications',
        'testimonial_translations', 'testimonials',
        'blog_post_tags', 'blog_post_translations', 'blog_posts',
        'blog_tag_translations', 'blog_tags', 'blog_category_translations', 'blog_categories',
        'section_translations', 'sections', 'page_translations', 'pages',
        'navigation_translations', 'navigation',
        'social_link_translations', 'social_links',
        'setting_translations', 'settings',
        'translation_values', 'translations',
        'media_translations', 'media',
        'messages', 'resumes', 'seo_metadata', 'analytics_visits',
        'languages',
    ];

    public function run(array $options = []): array
    {
        $force = (bool) ($options['fresh'] ?? $options['force'] ?? false);
        $alreadySeeded = Database::table('languages')->count() > 0;

        if ($alreadySeeded && !$force) {
            $this->log[] = 'skip  seed (content already present — use --fresh to reload)';

            return $this->log;
        }

        if ($force) {
            $this->truncateContent();
        }

        // The default admin has to exist before the content seeds run: posts and
        // other rows reference admins.id as their author.
        $this->ensureDefaultAdmin($options);

        $files = glob(__DIR__ . '/seeds/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $seeder = require $file;

            if (!is_callable($seeder)) {
                continue;
            }

            $seeder($this);
            $this->log[] = 'seed  ' . $name;
        }

        $this->ensureDefaultAdmin($options);

        return $this->log;
    }

    private function truncateContent(): void
    {
        if (Database::isSqlite()) {
            Database::connect()->exec('PRAGMA foreign_keys = OFF');
        } else {
            Database::connect()->exec('SET FOREIGN_KEY_CHECKS = 0');
        }

        foreach (self::CONTENT_TABLES as $table) {
            try {
                Database::table($table)->delete();
            } catch (\Throwable) {
                // table may not exist in a partially migrated install
            }
        }

        if (Database::isSqlite()) {
            Database::connect()->exec('PRAGMA foreign_keys = ON');
        } else {
            Database::connect()->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->log[] = 'clear demo content tables';
    }

    private function ensureDefaultAdmin(array $options): void
    {
        $email = strtolower((string) ($options['email'] ?? \App\Core\Env::get('ADMIN_EMAIL', 'admin@example.com')));
        $password = (string) ($options['password'] ?? \App\Core\Env::get('ADMIN_PASSWORD', 'Admin@12345'));

        if (Admin::findByEmail($email) !== null) {
            return;
        }

        Admin::create([
            'name' => (string) \App\Core\Env::get('ADMIN_NAME', 'Arash Mahdavi'),
            'email' => $email,
            'password_hash' => Security::hashPassword($password),
            'role' => 'super_admin',
            'bio' => 'Full-stack developer and owner of this portfolio.',
            'is_active' => 1,
        ]);

        $this->log[] = 'create default admin ' . $email;
    }

    public function log(): array
    {
        return $this->log;
    }

    /** JSON-encode array values (e.g. *_json columns) before insert. */
    private static function encodeArrays(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_array($value)) {
                $row[$key] = Json::encode($value);
            }
        }

        return $row;
    }

    /* --------------------------------------------------------------------- */
    /* Helpers used by the seed files                                        */
    /* --------------------------------------------------------------------- */

    /**
     * Insert a base row plus one row per language in its translation table.
     *
     * @param array<string,mixed> $base base-table columns
     * @param array<string,array<string,mixed>> $translations lang => translated columns
     */
    public function translated(
        string $table,
        string $translationTable,
        array $base,
        array $translations,
        string $foreignKey
    ): int {
        $now = gmdate('Y-m-d H:i:s');
        $base['created_at'] ??= $now;
        $base['updated_at'] ??= $now;
        $base = self::encodeArrays($base);

        $id = Database::table($table)->insertGetId($base);

        foreach ($translations as $lang => $fields) {
            $fields[$foreignKey] = $id;
            $fields['lang'] = $lang;
            $fields['created_at'] = $now;
            $fields['updated_at'] = $now;
            $fields = self::encodeArrays($fields);

            Database::table($translationTable)->insert($fields);
        }

        return $id;
    }

    /** Insert a settings row (+ optional per-language values). */
    public function setting(
        string $key,
        string $group,
        string $type,
        mixed $value,
        bool $public = true,
        array $translations = []
    ): void {
        $now = gmdate('Y-m-d H:i:s');
        $encoded = is_array($value) ? Json::encode($value) : ($value === null ? null : (string) $value);

        $id = Database::table('settings')->insertGetId([
            'group_key' => $group,
            'key_name' => $key,
            'value_type' => $type,
            'value' => $encoded,
            'is_public' => $public ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($translations as $lang => $translated) {
            Database::table('setting_translations')->insert([
                'setting_id' => $id,
                'lang' => $lang,
                'value' => (string) $translated,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Register a file that already exists in public/uploads as a media row.
     *
     * Dimensions and size are read from the file itself so the media library
     * stays truthful (the admin panel shows the same numbers for new uploads).
     */
    public function media(string $relativePath, string $folder = 'general', ?array $altText = null, ?string $originalName = null): int
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $absolute = rtrim((string) \App\Core\Config::get('uploads.disk_path'), '/') . '/' . $relativePath;
        $now = gmdate('Y-m-d H:i:s');

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };

        $size = is_file($absolute) ? (int) filesize($absolute) : 0;
        $width = null;
        $height = null;

        if (is_file($absolute) && function_exists('getimagesize') && !in_array($extension, ['svg', 'pdf'], true)) {
            $dimensions = @getimagesize($absolute);
            if (is_array($dimensions)) {
                $width = (int) $dimensions[0];
                $height = (int) $dimensions[1];
            }
        }

        $id = Database::table('media')->insertGetId([
            'filename' => basename($relativePath),
            'original_name' => $originalName ?? basename($relativePath),
            'path' => $relativePath,
            'url' => rtrim((string) \App\Core\Config::get('uploads.url_prefix', '/uploads'), '/') . '/' . $relativePath,
            'mime_type' => $mime,
            'extension' => $extension,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'folder' => $folder,
            'alt_text' => $altText[(string) \App\Core\Config::get('app.default_locale', 'fa')] ?? reset($altText) ?: null,
            'caption' => null,
            'uploaded_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Per-language alt text / caption live in media_translations so the same
        // asset can be described differently in the Persian and English sites.
        foreach ($altText ?? [] as $lang => $text) {
            if ($text === null || $text === '') {
                continue;
            }

            Database::table('media_translations')->insert([
                'media_id' => $id,
                'lang' => (string) $lang,
                'alt_text' => (string) $text,
                'caption' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $id;
    }

    /** Insert a UI string used by the frontend (overridable in the admin). */
    public function uiString(string $key, string $group, array $values): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $id = Database::table('translations')->insertGetId([
            'key_name' => $key,
            'group_key' => $group,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($values as $lang => $value) {
            Database::table('translation_values')->insert([
                'translation_id' => $id,
                'lang' => $lang,
                'value' => (string) $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** Insert SEO metadata for an entity in every language. */
    public function seo(string $entityType, ?int $entityId, array $perLanguage): void
    {
        $now = gmdate('Y-m-d H:i:s');

        foreach ($perLanguage as $lang => $fields) {
            Database::table('seo_metadata')->insert([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'lang' => $lang,
                'seo_title' => $fields['title'] ?? null,
                'meta_description' => $fields['description'] ?? null,
                'keywords' => $fields['keywords'] ?? null,
                'canonical_url' => $fields['canonical'] ?? null,
                'robots' => $fields['robots'] ?? 'index,follow',
                'og_title' => $fields['og_title'] ?? ($fields['title'] ?? null),
                'og_description' => $fields['og_description'] ?? ($fields['description'] ?? null),
                'twitter_card' => 'summary_large_image',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** Resolve (and cache) the id of a language code. */
    public function languageId(string $code): int
    {
        static $cache = [];

        $cache[$code] ??= (int) Database::scalar('SELECT id FROM languages WHERE code = ? LIMIT 1', [$code]);

        return $cache[$code];
    }

    /** Find a skill id by its English name (used to link project technologies). */
    public function skillId(string $englishName): ?int
    {
        $row = Database::selectOne(
            'SELECT s.id FROM skills s
             JOIN skill_translations t ON t.skill_id = s.id
             WHERE t.lang = ? AND t.name = ?
             LIMIT 1',
            ['en', $englishName]
        );

        return $row === null ? null : (int) $row['id'];
    }
}
