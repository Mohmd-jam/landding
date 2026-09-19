<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;

/** Overview screen: counters, charts, recent activity and the security panel. */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $unused = $request;
        $dashboard = new DashboardService();
        $overview = $dashboard->overview();

        $overview['media'] = [
            'total' => (int) Database::table('media')->count(),
            'human_size' => (new \App\Services\MediaService())->humanSize((int) Database::table('media')->sum('size')),
            'recent' => Database::select(
                'SELECT id, filename, url, folder, size, width, height, created_at FROM media ORDER BY id DESC LIMIT 8'
            ),
        ];

        $overview['unread_messages'] = $dashboard->messageStats();
        $overview['activity'] = Database::select(
            'SELECT al.id, al.action, al.entity_type, al.entity_id, al.created_at, a.name AS admin_name
             FROM audit_logs al LEFT JOIN admins a ON a.id = al.admin_id
             ORDER BY al.id DESC LIMIT 12'
        );

        $overview['content'] = [
            'draft_posts' => (int) Database::table('blog_posts')->where('status', 'draft')->count(),
            'scheduled_posts' => (int) Database::table('blog_posts')->where('status', 'scheduled')->count(),
            'inactive_projects' => (int) Database::table('projects')->where('is_active', 0)->count(),
            'partial_translations' => $this->partialTranslations(),
            'missing_seo' => $this->missingSeo(),
        ];

        $overview['system'] = [
            'php' => PHP_VERSION,
            'driver' => Database::driver(),
            'env' => (string) Config::get('app.env', 'production'),
            'debug' => (bool) Config::get('app.debug', false),
            'admin' => (string) (Auth::user()['name'] ?? ''),
            'timezone' => (string) Config::get('app.timezone', 'UTC'),
            'server_time' => gmdate('c'),
        ];

        return Response::json(['data' => $overview]);
    }

    /** Entities that exist in the default locale but miss a fallback translation. */
    private function partialTranslations(): array
    {
        $out = [];

        foreach ([
            'project_translations' => 'project_id',
            'blog_post_translations' => 'post_id',
            'service_translations' => 'service_id',
            'skill_translations' => 'skill_id',
        ] as $table => $foreignKey) {
            $rows = Database::select(
                'SELECT ' . $foreignKey . ' AS entity_id, GROUP_CONCAT(lang) AS langs FROM ' . $table . ' GROUP BY ' . $foreignKey
            );

            $missing = 0;
            $codes = $this->locales()->languages();

            foreach ($rows as $row) {
                $langs = array_filter(explode(',', (string) $row['langs']));

                if (count($langs) < count($codes)) {
                    $missing++;
                }
            }

            if ($missing > 0) {
                $out[] = ['table' => $table, 'missing' => $missing];
            }
        }

        return $out;
    }

    /** Published content without a seo_metadata row. */
    private function missingSeo(): int
    {
        $projects = (int) Database::scalar(
            'SELECT COUNT(*) AS aggregate FROM projects p
             WHERE p.is_active = 1 AND NOT EXISTS (
                 SELECT 1 FROM seo_metadata s WHERE s.entity_type = ? AND s.entity_id = p.id
             )',
            ['project']
        );

        $posts = (int) Database::scalar(
            'SELECT COUNT(*) AS aggregate FROM blog_posts b
             WHERE b.status = ? AND NOT EXISTS (
                 SELECT 1 FROM seo_metadata s WHERE s.entity_type = ? AND s.entity_id = b.id
             )',
            ['published', 'post']
        );

        return $projects + $posts;
    }
}
