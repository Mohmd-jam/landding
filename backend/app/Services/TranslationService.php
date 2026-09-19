<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;

/**
 * Editable UI strings.
 *
 * Static labels ("Read more", "All projects", "Send message" …) live in the
 * database so the whole site — including its microcopy — is editable from
 * Admin → Language Management. The React bundle ships English/Persian defaults
 * and merges these rows on top, which also means a brand-new language starts
 * from defaults instead of an empty screen.
 */
final class TranslationService
{
    private const CACHE_KEY = 'translations.all';
    private const CACHE_TTL = 600;

    /** @return array<string,array<string,string>> key => lang => value */
    public static function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, static function (): array {
            $keys = Database::table('translations')->get();
            $values = Database::table('translation_values')->get();

            $byId = [];

            foreach ($values as $value) {
                $byId[(int) $value['translation_id']][(string) $value['lang']] = (string) ($value['value'] ?? '');
            }

            $out = [];

            foreach ($keys as $key) {
                $out[(string) $key['key_name']] = $byId[(int) $key['id']] ?? [];
            }

            return $out;
        });
    }

    /**
     * Flat dictionary for one language, merged over the fallback locale so a
     * partially translated string never renders empty.
     *
     * @return array<string,string>
     */
    public static function forLocale(string $locale, ?string $fallback = null): array
    {
        $strings = self::all();
        $fallback ??= (string) \App\Core\Config::get('app.fallback_locale', 'en');

        $out = [];

        foreach ($strings as $key => $values) {
            $value = $values[$locale] ?? $values[$fallback] ?? '';

            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Admin view: every key with all language values (missing ones included).
     *
     * @return array<int,array{id:int,key:string,group:string,values:array<string,string>}>
     */
    public static function grouped(?string $filter = null, ?string $search = null): array
    {
        $query = Database::table('translations')->orderBy('group_key')->orderBy('key_name');

        if ($filter !== null && $filter !== '') {
            $query->where('group_key', $filter);
        }

        if ($search !== null && $search !== '') {
            $query->where('key_name', 'LIKE', '%' . $search . '%');
        }

        $keys = $query->get();
        $values = Database::table('translation_values')->get();

        $byId = [];

        foreach ($values as $value) {
            $byId[(int) $value['translation_id']][(string) $value['lang']] = (string) ($value['value'] ?? '');
        }

        $out = [];

        foreach ($keys as $key) {
            $out[] = [
                'id' => (int) $key['id'],
                'key' => (string) $key['key_name'],
                'group' => (string) $key['group_key'],
                'values' => $byId[(int) $key['id']] ?? [],
            ];
        }

        return $out;
    }

    /** @return array<int,string> */
    public static function groups(): array
    {
        $groups = [];

        foreach (Database::table('translations')->select(['group_key'])->get() as $row) {
            $groups[] = (string) $row['group_key'];
        }

        $groups = array_values(array_unique($groups));
        sort($groups);

        return $groups;
    }

    /**
     * Create or update a string in every language.
     *
     * @param array<string,string> $values lang => value
     */
    public static function save(string $key, string $group, array $values): int
    {
        $existing = Database::table('translations')->where('key_name', $key)->first();
        $now = gmdate('Y-m-d H:i:s');

        if ($existing === null) {
            $id = Database::table('translations')->insertGetId([
                'key_name' => $key,
                'group_key' => $group,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $id = (int) $existing['id'];
            Database::table('translations')->where('id', $id)->update([
                'group_key' => $group,
                'updated_at' => $now,
            ]);
        }

        foreach ($values as $lang => $value) {
            $row = Database::table('translation_values')
                ->where('translation_id', $id)
                ->where('lang', (string) $lang)
                ->first();

            if ($row === null) {
                Database::table('translation_values')->insert([
                    'translation_id' => $id,
                    'lang' => (string) $lang,
                    'value' => (string) $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                Database::table('translation_values')->where('id', (int) $row['id'])->update([
                    'value' => (string) $value,
                    'updated_at' => $now,
                ]);
            }
        }

        self::flush();

        return $id;
    }

    public static function delete(string $key): void
    {
        Database::table('translations')->where('key_name', $key)->delete();
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
