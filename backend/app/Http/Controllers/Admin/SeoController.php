<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\AuditLogger;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Http\Controllers\Controller;
use App\Services\SeoService;

/** SEO editor: per-page overrides, sitemap/robots regeneration, preview. */
final class SeoController extends Controller
{
    public function index(Request $request): Response
    {
        $locale = (string) $request->query('lang', $this->locales()->defaultCode());
        $entityType = (string) $request->query('entity_type', 'page');

        $rows = Database::table('seo_metadata')
            ->where('entity_type', $entityType)
            ->orderBy('entity_id')
            ->get();

        $byEntity = [];

        foreach ($rows as $row) {
            $byEntity[(int) ($row['entity_id'] ?? 0)][(string) $row['lang']] = $row;
        }

        $entities = $this->entitiesFor($entityType, $locale);
        $items = [];

        foreach ($entities as $entity) {
            $id = (int) $entity['id'];
            $items[] = [
                'entity_type' => $entityType,
                'entity_id' => $id,
                'title' => (string) $entity['title'],
                'url' => $entity['url'] ?? null,
                'metadata' => $byEntity[$id] ?? [],
                'has_metadata' => isset($byEntity[$id]),
            ];
        }

        $publicPath = rtrim((string) Config::get('app.public_path', ''), '/');

        return Response::json([
            'data' => [
                'entity_type' => $entityType,
                'entity_types' => ['page', 'project', 'post', 'service', 'home'],
                'items' => $items,
                'locales' => $this->locales()->publicList(),
                'defaults' => [
                    'site_url' => (string) $this->settings()->get('site_url', (string) Config::get('app.url', '')),
                    'title_template' => (string) $this->settings()->get('seo_title_template', ''),
                    'og_image' => (string) $this->settings()->get('og_default_image_url', ''),
                    'twitter_handle' => (string) $this->settings()->get('twitter_handle', ''),
                    'robots' => (string) $this->settings()->get('seo_default_robots', 'index,follow'),
                ],
                'sitemap' => [
                    'exists' => is_file($publicPath . '/sitemap.xml'),
                    'updated_at' => is_file($publicPath . '/sitemap.xml') ? gmdate('c', (int) filemtime($publicPath . '/sitemap.xml')) : null,
                    'size' => is_file($publicPath . '/sitemap.xml') ? (int) filesize($publicPath . '/sitemap.xml') : 0,
                    'robots_exists' => is_file($publicPath . '/robots.txt'),
                ],
            ],
        ]);
    }

    public function update(Request $request): Response
    {
        $input = Validator::make($request->body(), [
            'entity_type' => ['required', 'in:global,page,section,project,post,service,experience,home'],
            'entity_id' => ['nullable', 'int'],
            'lang' => ['required', 'string', 'max:8'],
            'seo_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'keywords' => ['nullable', 'string', 'max:320'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'robots' => ['nullable', 'in:index,follow,index,nofollow,noindex,follow,noindex,nofollow'],
            'og_title' => ['nullable', 'string', 'max:200'],
            'og_description' => ['nullable', 'string', 'max:320'],
            'og_image_media_id' => ['nullable', 'int', 'exists:media,id'],
            'twitter_card' => ['nullable', 'in:summary,summary_large_image'],
        ])->validate();

        if (!$this->locales()->isSupported((string) $input['lang'])) {
            throw HttpException::badRequest('Unknown language for this site.');
        }

        $now = now_utc();
        $entityType = (string) $input['entity_type'];
        $entityId = $input['entity_id'] ?? null;
        $lang = (string) $input['lang'];

        $existing = Database::table('seo_metadata')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('lang', $lang)
            ->first();

        $payload = [
            'seo_title' => $input['seo_title'] ?? null,
            'meta_description' => $input['meta_description'] ?? null,
            'keywords' => $input['keywords'] ?? null,
            'canonical_url' => $input['canonical_url'] ?? null,
            'robots' => $input['robots'] ?? 'index,follow',
            'og_title' => $input['og_title'] ?? null,
            'og_description' => $input['og_description'] ?? null,
            'og_image_media_id' => $input['og_image_media_id'] ?? null,
            'twitter_card' => $input['twitter_card'] ?? 'summary_large_image',
            'updated_at' => $now,
        ];

        if ($existing === null) {
            Database::table('seo_metadata')->insert($payload + [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'lang' => $lang,
                'created_at' => $now,
            ]);
        } else {
            Database::table('seo_metadata')->where('id', (int) $existing['id'])->update($payload);
        }

        AuditLogger::log(Auth::id(), 'seo.update', 'seo_metadata', (int) ($existing['id'] ?? 0), [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'lang' => $lang,
        ]);

        return Response::json(['message' => 'SEO metadata saved.']);
    }

    /** Regenerate public/sitemap.xml and public/robots.txt from the database. */
    public function sitemap(Request $request): Response
    {
        $unused = $request;

        $result = (new SeoService($this->settings(), $this->locales(), $this->navigation()))
            ->writeSitemap($this->content());

        AuditLogger::log(Auth::id(), 'seo.sitemap', 'seo_metadata', null, $result);

        return Response::json([
            'data' => $result,
            'message' => $result['urls'] . ' URLs written to sitemap.xml and robots.txt.',
        ]);
    }

    /**
     * Editor choices for the entity picker (pages, projects, posts…).
     *
     * @return array<int,array<string,mixed>>
     */
    private function entitiesFor(string $entityType, string $locale): array
    {
        $fallback = $this->locales()->fallbackCode();

        return match ($entityType) {
            'project' => Database::select(
                'SELECT p.id, COALESCE(t.title, f.title) AS title, COALESCE(t.slug, f.slug) AS slug
                 FROM projects p
                 LEFT JOIN project_translations t ON t.project_id = p.id AND t.lang = ?
                 LEFT JOIN project_translations f ON f.project_id = p.id AND f.lang = ?
                 ORDER BY p.sort_order, p.id',
                [$locale, $fallback]
            ),
            'post' => Database::select(
                'SELECT b.id, COALESCE(t.title, f.title) AS title, COALESCE(t.slug, f.slug) AS slug
                 FROM blog_posts b
                 LEFT JOIN blog_post_translations t ON t.post_id = b.id AND t.lang = ?
                 LEFT JOIN blog_post_translations f ON f.post_id = b.id AND f.lang = ?
                 ORDER BY b.published_at DESC, b.id DESC',
                [$locale, $fallback]
            ),
            'service' => Database::select(
                'SELECT s.id, COALESCE(t.title, f.title) AS title, COALESCE(t.slug, f.slug) AS slug
                 FROM services s
                 LEFT JOIN service_translations t ON t.service_id = s.id AND t.lang = ?
                 LEFT JOIN service_translations f ON f.service_id = s.id AND f.lang = ?
                 ORDER BY s.sort_order, s.id',
                [$locale, $fallback]
            ),
            default => Database::select(
                'SELECT p.id, COALESCE(t.title, f.title) AS title, p.key_name AS slug
                 FROM pages p
                 LEFT JOIN page_translations t ON t.page_id = p.id AND t.lang = ?
                 LEFT JOIN page_translations f ON f.page_id = p.id AND f.lang = ?
                 ORDER BY p.sort_order, p.id',
                [$locale, $fallback]
            ),
        };
    }
}
