<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\AuditLogger;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use App\Services\SeoService;

/** Website settings (identity, contact, social, appearance, analytics, integrations). */
final class SettingController extends Controller
{
    public function index(Request $request): Response
    {
        $unused = $request;

        return Response::json([
            'data' => [
                'groups' => $this->settings()->adminPayload(),
                'types' => ['string', 'text', 'int', 'bool', 'json', 'url', 'email'],
            ],
        ])->withCache(30);
    }

    /**
     * Batch update. Each entry may carry a plain value and/or per-language
     * translations: { key: { value: 12, translations: { fa: '…', en: '…' } } }.
     */
    public function update(Request $request): Response
    {
        $payload = $request->body();
        $updates = (array) ($payload['settings'] ?? $payload);

        if ($updates === []) {
            return Response::json(['message' => 'Nothing to save.', 'data' => ['updated' => 0]]);
        }

        $clean = [];

        foreach ($updates as $key => $value) {
            $key = (string) $key;

            if (!preg_match('/^[a-z0-9_]{2,80}$/', $key)) {
                continue;
            }

            if (is_array($value) && (array_key_exists('value', $value) || array_key_exists('translations', $value))) {
                $clean[$key] = [
                    'value' => $value['value'] ?? null,
                    'translations' => isset($value['translations']) && is_array($value['translations'])
                        ? array_intersect_key($value['translations'], array_flip($this->locales()->codes()))
                        : [],
                    'type' => $value['type'] ?? null,
                ];

                continue;
            }

            // A bare array is treated as per-language values.
            $clean[$key] = is_array($value)
                ? ['value' => null, 'translations' => array_intersect_key($value, array_flip($this->locales()->codes()))]
                : ['value' => $value, 'translations' => []];
        }

        $count = $this->settings()->updateMany($clean);
        $this->settings()->flush();
        $this->locales()->reset();

        AuditLogger::log(Auth::id(), 'settings.update', 'settings', null, ['keys' => array_keys($clean)]);

        // The sitemap/canonical URLs depend on settings, so keep them in sync.
        if (array_intersect(array_keys($clean), ['site_url', 'site_name', 'seo_title_template', 'og_default_image_url']) !== []) {
            (new SeoService($this->settings(), $this->locales(), $this->navigation()))->writeSitemap($this->content());
        }

        return Response::json([
            'data' => ['updated' => $count, 'groups' => $this->settings()->adminPayload()],
            'message' => $count . ' setting(s) saved.',
        ]);
    }
}
