<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\HttpException;
use App\Core\Locale;
use App\Core\Request;
use App\Core\Response;
use App\Services\ContentService;
use App\Services\LocaleService;
use App\Services\NavigationService;
use App\Services\SeoService;
use App\Services\SettingsService;
use App\Services\TranslationService;

/**
 * Shared base for every controller.
 *
 * Services are instantiated lazily so a controller only pays for what it uses,
 * and the locale resolution lives in exactly one place (query string → session →
 * default locale).
 */
abstract class Controller
{
    private ?LocaleService $locales = null;
    private ?SettingsService $settings = null;
    private ?ContentService $content = null;
    private ?SeoService $seo = null;
    private ?NavigationService $navigation = null;

    protected function locales(): LocaleService
    {
        return $this->locales ??= new LocaleService();
    }

    protected function settings(): SettingsService
    {
        return $this->settings ??= new SettingsService();
    }

    protected function content(): ContentService
    {
        return $this->content ??= new ContentService();
    }

    protected function seo(): SeoService
    {
        return $this->seo ??= new SeoService($this->settings(), $this->locales(), $this->navigation());
    }

    protected function navigation(): NavigationService
    {
        return $this->navigation ??= new NavigationService();
    }

    /** Locale for this request: ?lang= → X-Locale header → default locale. */
    protected function locale(Request $request): string
    {
        $candidate = $request->query('lang');

        if (!is_string($candidate) || $candidate === '') {
            $candidate = $request->header('X-Locale');
        }

        return $this->locales()->resolve(is_string($candidate) ? $candidate : null);
    }

    /** @return array{items:array<int,mixed>,page:int,per_page:int,total:int,total_pages:int} */
    protected function respondList(Request $request, array $result, string $locale): Response
    {
        return Response::json([
            'data' => $result['items'] ?? [],
            'meta' => [
                'page' => (int) ($result['page'] ?? 1),
                'per_page' => (int) ($result['per_page'] ?? 0),
                'total' => (int) ($result['total'] ?? 0),
                'total_pages' => (int) ($result['total_pages'] ?? 1),
            ],
            'locale' => $locale,
        ]);
    }

    protected function respond(mixed $data, string $locale, int $status = 200): Response
    {
        return Response::json(['data' => $data, 'locale' => $locale], $status);
    }

    protected function notFound(string $what = 'Resource'): never
    {
        throw HttpException::notFound($what . ' not found.');
    }

    /** Normalised pagination inputs (never trust the client). */
    protected function page(Request $request, int $default = 1): int
    {
        return max(1, min(500, (int) $request->query('page', $default)));
    }

    protected function perPage(Request $request, int $default = 9, int $max = 50): int
    {
        return max(1, min($max, (int) $request->query('per_page', $default)));
    }

    /** @return array<string,string> */
    protected function shellStrings(string $locale): array
    {
        return TranslationService::forLocale($locale);
    }
}
