<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Language;

/**
 * Locale resolution and the persisted UI-string dictionary.
 *
 * Languages live in the database, so a new language added in
 * Admin → Languages becomes routable, translatable and indexable immediately.
 */
final class LocaleService
{
    /** @var array<int,array<string,mixed>>|null */
    private static ?array $languages = null;

    /** @var array<string,string>|null */
    private static ?array $strings = null;

    /** @return array<int,array<string,mixed>> */
    public function languages(): array
    {
        if (self::$languages === null) {
            self::$languages = array_map(static function (array $language): array {
                $language['direction'] = $language['direction'] ?: ($language['code'] === 'fa' ? 'rtl' : 'ltr');

                return $language;
            }, Language::active());
        }

        return self::$languages;
    }

    /** @return array<string,array<string,mixed>> */
    public function languagesByCode(): array
    {
        $map = [];

        foreach ($this->languages() as $language) {
            $map[(string) $language['code']] = $language;
        }

        return $map;
    }

    /** @return array<int,string> Active language codes, in display order. */
    public function codes(): array
    {
        return array_map(static fn (array $language): string => (string) $language['code'], $this->languages());
    }

    public function defaultCode(): string
    {
        $configured = (string) Config::get('app.default_locale', 'fa');

        foreach ($this->languages() as $language) {
            if ((bool) $language['is_default']) {
                return (string) $language['code'];
            }
        }

        return $this->isSupported($configured) ? $configured : 'fa';
    }

    public function fallbackCode(): string
    {
        $fallback = (string) Config::get('app.fallback_locale', 'en');

        return $this->isSupported($fallback) ? $fallback : $this->defaultCode();
    }

    public function isSupported(string $code): bool
    {
        foreach ($this->languages() as $language) {
            if ($language['code'] === $code && (bool) $language['is_active']) {
                return true;
            }
        }

        return false;
    }

    /** Normalise any input (path segment, query param, header) into a supported locale. */
    public function resolve(?string $candidate): string
    {
        if ($candidate === null || $candidate === '') {
            return $this->defaultCode();
        }

        $candidate = strtolower(substr(trim($candidate), 0, 8));

        // Accept "en-US" style values
        if (str_contains($candidate, '-')) {
            $candidate = explode('-', $candidate)[0];
        }

        return $this->isSupported($candidate) ? $candidate : $this->defaultCode();
    }

    public function direction(string $code): string
    {
        foreach ($this->languages() as $language) {
            if ($language['code'] === $code) {
                return (string) $language['direction'];
            }
        }

        return $code === 'fa' || $code === 'ar' ? 'rtl' : 'ltr';
    }

    /**
     * UI strings for the requested locale, merged over the fallback locale so a
     * partially translated interface never renders empty labels.
     *
     * @return array<string,string>
     */
    public function strings(string $locale): array
    {
        $fallback = $this->fallbackCode();

        if (self::$strings === null) {
            self::$strings = [];
            $rows = \App\Core\Database::select(
                'SELECT t.key_name, v.lang, v.value
                 FROM translations t
                 JOIN translation_values v ON v.translation_id = t.id'
            );

            foreach ($rows as $row) {
                self::$strings[(string) $row['key_name']][(string) $row['lang']] = (string) $row['value'];
            }
        }

        $result = [];

        foreach (self::$strings as $key => $values) {
            $result[$key] = $values[$locale] ?? $values[$fallback] ?? ($values === [] ? '' : reset($values));
        }

        return $result;
    }

    /** Public language list for the switcher / hreflang output. */
    public function publicList(): array
    {
        return array_map(static fn (array $language): array => [
            'code' => $language['code'],
            'name' => $language['name'],
            'native_name' => $language['native_name'],
            'direction' => $language['direction'],
            'flag' => $language['flag'],
            'is_default' => (bool) $language['is_default'],
        ], $this->languages());
    }

    public function reset(): void
    {
        self::$languages = null;
        self::$strings = null;
    }
}
