<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\SsrRenderer;

/**
 * Shell-level endpoints the React app calls on boot and on route changes:
 * settings, languages, UI strings, navigation, the pre-rendered content block,
 * site search and the language-switcher slug lookup.
 */
final class SiteController extends Controller
{
    /**
     * Everything the app needs before its first paint. The HTML shell already
     * embeds this payload in `window.__BOOTSTRAP__`; the endpoint exists for
     * client-side navigation, cached shells and third-party consumers.
     */
    public function bootstrap(Request $request): Response
    {
        $locale = $this->locale($request);

        return Response::json([
            'data' => $this->bootstrapPayload($locale),
            'locale' => $locale,
        ])->withCache(60);
    }

    /** @return array<string,mixed> */
    public function bootstrapPayload(string $locale): array
    {
        $navigation = $this->navigation()->payload($locale);

        return [
            'locale' => $locale,
            'direction' => $this->locales()->direction($locale),
            'locales' => $this->locales()->publicList(),
            'settings' => $this->settings()->publicPayload($locale),
            'strings' => $this->shellStrings($locale),
            'navigation' => $navigation,
            'contact' => $this->content()->contactInfo($locale),
            'stats' => $this->content()->statistics(),
            'csrf_token' => Security::csrfToken(),
            'routes' => [
                'home' => '/' . $locale,
                'projects' => '/' . $locale . '/projects',
                'blog' => '/' . $locale . '/blog',
                'services' => '/' . $locale . '/services',
                'about' => '/' . $locale . '/about',
                'contact' => '/' . $locale . '/contact',
                'resume' => '/' . $locale . '/resume',
            ],
        ];
    }

    public function home(Request $request): Response
    {
        $locale = $this->locale($request);
        $renderer = new SsrRenderer($this->content());

        return Response::json([
            'data' => [
                'content' => $this->content()->home($locale),
                'prerender' => $renderer->home($locale)['html'],
                'bootstrap' => $this->bootstrapPayload($locale),
            ],
            'locale' => $locale,
        ])->withCache(120);
    }

    /** A single content page with its sections (name differs from the base helper). */
    public function contentPage(Request $request): Response
    {
        $locale = $this->locale($request);
        $key = (string) $request->routeParam('key');
        $page = $this->content()->page($key, $locale);

        if ($page === null) {
            $this->notFound('Page');
        }

        return Response::json([
            'data' => [
                'page' => $page,
                'sections' => $this->content()->sections($key, $locale),
            ],
            'locale' => $locale,
        ]);
    }

    public function contactInfo(Request $request): Response
    {
        $locale = $this->locale($request);

        return $this->respond([
            'contact' => $this->content()->contactInfo($locale),
            'socials' => $this->navigation()->socials($locale),
            'services' => array_map(
                static fn (array $service): array => ['id' => $service['id'], 'title' => $service['title']],
                $this->content()->services($locale)
            ),
        ], $locale)->withCache(300);
    }

    /** Public settings + locale metadata (kept apart from the service accessor). */
    public function siteSettings(Request $request): Response
    {
        $locale = $this->locale($request);

        return $this->respond([
            'settings' => $this->settings()->publicPayload($locale),
            'locales' => $this->locales()->publicList(),
            'strings' => $this->shellStrings($locale),
        ], $locale)->withCache(300);
    }

    public function search(Request $request): Response
    {
        $locale = $this->locale($request);
        $term = trim((string) $request->query('q', $request->query('term', '')));

        if (mb_strlen($term) < 2) {
            return $this->respond(['query' => $term, 'results' => []], $locale);
        }

        return $this->respond([
            'query' => $term,
            'results' => $this->content()->search($term, $locale, $this->perPage($request, 8, 24)),
        ], $locale);
    }

    /**
     * Language switcher support: `/en/projects/erp` → `/fa/projects/<fa-slug>`.
     * Returns the translated slug, or null when the entity has no translation
     * (the switcher then falls back to the section root).
     */
    public function translateSlug(Request $request): Response
    {
        $from = $this->locales()->resolve((string) $request->query('from', ''));
        $to = $this->locales()->resolve((string) $request->query('to', ''));
        $type = (string) $request->query('type', 'project');
        $slug = trim((string) $request->query('slug', ''));

        if ($slug === '') {
            $this->notFound('Slug');
        }

        return $this->respond([
            'type' => $type,
            'slug' => $slug,
            'from' => $from,
            'to' => $to,
            'translated' => $this->content()->translateSlug($type, $slug, $from, $to),
        ], $to);
    }

    /** Lightweight visitor counter (used by the dashboard "views" card). */
    public function track(Request $request): Response
    {
        $locale = $this->locale($request);
        (new DashboardService())->recordVisit((string) $request->input('path', '/'), $locale);

        return Response::noContent();
    }
}
