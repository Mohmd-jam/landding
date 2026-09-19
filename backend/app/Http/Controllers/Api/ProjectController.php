<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Http\Controllers\Controller;

/** Public projects API: filtering, search, pagination and single-project detail. */
final class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $locale = $this->locale($request);

        $filters = [
            'category' => trim((string) $request->query('category', '')),
            'technology' => trim((string) $request->query('technology', '')),
            'search' => trim((string) $request->query('q', '')),
            'featured' => $request->boolean('featured'),
            'order' => (string) $request->query('order', 'featured'),
        ];

        $result = $this->content()->projects(
            $locale,
            $filters,
            $this->perPage($request, (int) \App\Core\Config::get('app.per_page', 9), 48),
            $this->page($request)
        );

        return $this->respondList($request, [
            'items' => $result['items'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total' => $result['total'],
            'total_pages' => $result['total_pages'],
        ], $locale)->withCache(120);
    }

    public function show(Request $request): Response
    {
        $locale = $this->locale($request);
        $project = $this->content()->project((string) $request->routeParam('slug'), $locale);

        if ($project === null) {
            $this->notFound('Project');
        }

        return $this->respond($project, $locale)->withCache(60);
    }

    public function categories(Request $request): Response
    {
        $locale = $this->locale($request);

        return Response::json([
            'data' => $this->content()->projectCategories($locale),
            'meta' => ['total' => count($this->content()->projectCategories($locale))],
            'locale' => $locale,
        ])->withCache(300);
    }

    /** Technologies are `skills` rows with kind = 'technology'. */
    public function technologies(Request $request): Response
    {
        $locale = $this->locale($request);

        return Response::json([
            'data' => $this->content()->featuredTechnologies($locale, 60),
            'locale' => $locale,
        ])->withCache(300);
    }
}
