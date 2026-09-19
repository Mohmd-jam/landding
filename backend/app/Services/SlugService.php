<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Str;

/**
 * Slug generation for every translatable entity.
 *
 * Slugs are stored per language inside the *_translations tables (unique per
 * language, never globally), which keeps `/fa/projects/…` and `/en/projects/…`
 * independent while both point at the same row.
 */
final class SlugService
{
    private const TABLES = [
        'project' => ['project_translations', 'project_id', 'slug'],
        'post' => ['blog_post_translations', 'post_id', 'slug'],
        'service' => ['service_translations', 'service_id', 'slug'],
        'project_category' => ['project_category_translations', 'category_id', 'slug'],
        'blog_category' => ['blog_category_translations', 'category_id', 'slug'],
        'blog_tag' => ['blog_tag_translations', 'tag_id', 'slug'],
    ];

    /**
     * Build a unique slug for a source title in one language.
     * Persian/Arabic titles become readable-transliterated ASCII slugs.
     */
    public function make(string $title, string $lang, ?string $ignoreId = null, string $type = 'project'): string
    {
        $base = $this->slugify($title, $lang);

        return $this->unique($base, $lang, $type, $ignoreId);
    }

    public function unique(string $base, string $lang, string $type, ?string $ignoreId = null): string
    {
        $config = self::TABLES[$type] ?? null;

        if ($config === null) {
            return $base;
        }

        [$table, $foreignKey, $column] = $config;
        $candidate = $base;
        $suffix = 1;

        while (true) {
            $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ? AND lang = ?";
            $bindings = [$candidate, $lang];

            if ($ignoreId !== null) {
                $sql .= " AND {$foreignKey} != ?";
                $bindings[] = $ignoreId;
            }

            if ((int) Database::scalar($sql, $bindings) === 0) {
                return $candidate;
            }

            $candidate = $base . '-' . (++$suffix);

            if ($suffix > 200) {
                return $base . '-' . Str::random(6);
            }
        }
    }

    public function slugify(string $text, string $lang = 'en'): string
    {
        $text = trim($text);

        if ($lang === 'fa' || $this->hasPersian($text)) {
            $transliterated = $this->transliteratePersian($text);

            if ($transliterated !== '') {
                $text = $transliterated . '-' . substr(sha1($text), 0, 4);
            }
        }

        $slug = $this->ascii($text);

        return $slug === '' ? 'item-' . substr(sha1($text === '' ? 'empty' : $text), 0, 8) : $slug;
    }

    /** Keep Persian slugs readable if transliteration is not possible. */
    public function slugifyUnicode(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? '';

        return trim($text, '-');
    }

    private function ascii(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'å' => 'a', 'ã' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ß' => 'ss', 'ș' => 's', 'ț' => 't',
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'zh',
            'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f',
            'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y',
            'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];

        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

        return trim($text, '-');
    }

    private function hasPersian(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $text);
    }

    /**
     * Map Persian letters to their Latin equivalents (rough but stable and
     * readable — "طراحی فروشگاه" → "thrahy-frwshgah").
     */
    private function transliteratePersian(string $text): string
    {
        $map = [
            'آ' => 'a', 'ا' => 'a', 'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's',
            'ج' => 'j', 'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'z',
            'ر' => 'r', 'ز' => 'z', 'ژ' => 'zh', 'س' => 's', 'ش' => 'sh', 'ص' => 's',
            'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f',
            'ق' => 'gh', 'ک' => 'k', 'ك' => 'k', 'گ' => 'g', 'ل' => 'l', 'م' => 'm',
            'ن' => 'n', 'و' => 'v', 'ه' => 'h', 'ی' => 'y', 'ي' => 'y', 'ء' => '', 'ئ' => 'y',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ];

        $text = preg_replace('/[\x{064B}-\x{0652}\x{200C}]/u', '', $text) ?? $text;
        $text = strtr($text, $map);

        return $this->ascii($text);
    }

    /**
     * Build slugs for all languages of an entity in one call.
     *
     * @param array<string,string> $titles lang => title
     * @return array<string,string> lang => slug
     */
    public function forAllLanguages(array $titles, string $type, ?string $ignoreId = null): array
    {
        $slugs = [];

        foreach ($titles as $lang => $title) {
            $slugs[$lang] = $this->make((string) $title, (string) $lang, $ignoreId, $type);
        }

        return $slugs;
    }
}
