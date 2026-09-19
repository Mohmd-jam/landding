<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Http\Controllers\Controller;

/** Public blog API: published articles only, with category/tag/search filters. */
final class BlogController extends Controller
{
    public function index(Request $request): Response
    {
        $locale = $this->locale($request);

        $filters = [
            'category' => trim((string) $request->query('category', '')),
            'tag' => trim((string) $request->query('tag', '')),
            'search' => trim((string) ($request->query('q', '') ?: '')),
            'featured' => $request->boolean('featured'),
            'order' => (string) $request->query('order', 'latest'),
        ];

        $result = $this->content()->posts($locale, $filters, $this->perPage($request, 6, 36), $this->page($request));

        return $this->respondList($request, $result, $locale)->withCache(120);
    }

    public function show(Request $request): Response
    {
        $locale = $this->locale($request);
        $post = $this->content()->post((string) $request->routeParam('slug'), $locale);

        if ($post === null) {
            $this->notFound('Article');
        }

        // The single-post payload feeds the article page: body, tags, related.
        $post['content_html'] = (new \App\Services\SsrRenderer($this->content()))->markdown((string) ($post['content'] ?? ''));

        return $this->respond($post, $locale);
    }

    public function categories(Request $request): Response
    {
        $locale = $this->locale($request);

        return Response::json(['data' => $this->content()->postCategories($locale), 'locale' => $locale])->withCache(300);
    }

    public function tags(Request $request): Response
    {
        $locale = $this->locale($request);

        return Response::json(['data' => $this->content()->postTags($locale), 'locale' => $locale])->withCache(300);
    }

    /** Related-article helper used by the reader (same category, newest first). */
    public function related(Request $request): Response
    {
        $locale = $this->locale($request);
        $slug = (string) $request->routeParam('slug');
        $post = $this->content()->post($slug, $locale);

        if ($post === null) {
            $this->notFound('Article');
        }

        $related = array_values(array_filter(
            $this->content()->posts($locale, ['category' => (string) ($post['category_slug'] ?? '')], 4, 1)['items'],
            static fn (array $item): bool => (string) ($item['slug'] ?? '') !== $slug
        ));

        return $this->respond(array_slice($related, 0, 3), $locale);
    }

    /** Reading time helper shared with the shell (kept here for the API shape). */
    public function readingTime(string $content): int
    {
        return Str::readingTime($content);
    }
}
