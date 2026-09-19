<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Json;

/**
 * Typed access to the `settings` table (with per-language overrides) and the
 * write path used by Admin → Website settings / Homepage / Footer / SEO.
 */
final class SettingsService
{
    private const CACHE_KEY = 'settings.all';
    private const TTL = 300;

    /** @var array<string,array<string,mixed>>|null */
    private static ?array $raw = null;

    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        if (self::$raw !== null) {
            return self::$raw;
        }

        self::$raw = Cache::remember(self::CACHE_KEY, self::TTL, function (): array {
            $rows = Database::select(
                'SELECT id, group_key, key_name, value_type, value, is_public FROM settings ORDER BY group_key, key_name'
            );
            $translations = Database::select('SELECT setting_id, lang, value FROM setting_translations');

            $byId = [];
            foreach ($translations as $translation) {
                $byId[(int) $translation['setting_id']][(string) $translation['lang']] = (string) $translation['value'];
            }

            $result = [];
            foreach ($rows as $row) {
                $result[(string) $row['key_name']] = $row + ['translations' => $byId[(int) $row['id']] ?? []];
            }

            return $result;
        });

        return self::$raw;
    }

    /** Typed value for a key (bool/int/json cast according to value_type). */
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->all()[$key] ?? null;

        if ($setting === null || $setting['value'] === null) {
            return $default;
        }

        return $this->cast($setting['value_type'] ?? 'string', (string) $setting['value'], $default);
    }

    /** Translated value (falls back to the base value, then the fallback locale). */
    public function translated(string $key, string $locale, ?string $default = null): ?string
    {
        $setting = $this->all()[$key] ?? null;

        if ($setting === null) {
            return $default;
        }

        $translations = $setting['translations'] ?? [];

        if (isset($translations[$locale]) && $translations[$locale] !== '') {
            return $translations[$locale];
        }

        $fallback = (string) \App\Core\Config::get('app.fallback_locale', 'en');

        if (isset($translations[$fallback]) && $translations[$fallback] !== '') {
            return $translations[$fallback];
        }

        $value = (string) ($setting['value'] ?? '');

        return $value === '' ? $default : $value;
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    /** Public (is_public = 1) settings shaped for the frontend bootstrap payload. */
    public function publicPayload(string $locale): array
    {
        $payload = ['groups' => []];

        foreach ($this->all() as $key => $setting) {
            if (!(bool) $setting['is_public']) {
                continue;
            }

            $group = (string) $setting['group_key'];
            $value = $this->translated($key, $locale);

            if ($value === null || $value === '') {
                $value = $this->cast((string) $setting['value_type'], (string) ($setting['value'] ?? ''), null);
            }

            $payload['groups'][$group][$key] = $value;
            $payload['flat'][$key] = $value;
        }

        return $payload;
    }

    /** All settings for the admin editor (including private ones). */
    public function adminPayload(): array
    {
        $groups = [];

        foreach ($this->all() as $key => $setting) {
            $group = (string) $setting['group_key'];
            $groups[$group][$key] = [
                'key' => $key,
                'type' => (string) $setting['value_type'],
                'value' => $this->cast((string) $setting['value_type'], (string) ($setting['value'] ?? ''), null),
                'is_public' => (bool) $setting['is_public'],
                'translations' => $setting['translations'] ?? [],
            ];
        }

        return $groups;
    }

    /**
     * Persist a batch of settings updates coming from the admin panel.
     *
     * @param array<string,array{value?:mixed,translations?:array<string,string>,type?:string}> $updates
     */
    public function updateMany(array $updates): int
    {
        $now = now_utc();
        $changed = 0;

        foreach ($updates as $key => $payload) {
            $setting = $this->all()[$key] ?? null;

            if ($setting === null) {
                continue;
            }

            $type = (string) ($payload['type'] ?? $setting['value_type']);

            if (array_key_exists('value', $payload)) {
                $raw = $payload['value'];
                $encoded = is_array($raw) ? Json::encode($raw) : ($raw === null ? null : (string) $raw);

                Database::table('settings')->where('id', (int) $setting['id'])->update([
                    'value' => $encoded,
                    'value_type' => $type,
                    'updated_at' => $now,
                ]);
                $changed++;
            }

            foreach ((array) ($payload['translations'] ?? []) as $lang => $value) {
                $existing = Database::table('setting_translations')
                    ->where('setting_id', (int) $setting['id'])
                    ->where('lang', (string) $lang)
                    ->first();

                if ($existing === null) {
                    Database::table('setting_translations')->insert([
                        'setting_id' => (int) $setting['id'],
                        'lang' => (string) $lang,
                        'value' => (string) $value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    Database::table('setting_translations')->where('id', (int) $existing['id'])->update([
                        'value' => (string) $value,
                        'updated_at' => $now,
                    ]);
                }
                $changed++;
            }
        }

        $this->flush();

        return $changed;
    }

    public function flush(): void
    {
        self::$raw = null;
        Cache::forget(self::CACHE_KEY);
    }

    private function cast(string $type, string $value, mixed $default): mixed
    {
        return match ($type) {
            'bool' => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'int' => (int) $value,
            'json' => Json::decode($value, $default ?? []),
            default => $value,
        };
    }
}
