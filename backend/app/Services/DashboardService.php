<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * Aggregates the admin overview: content counters, message health, traffic
 * sparkline and the security panel (sessions, failed logins).
 *
 * All numbers come from the database; nothing is mocked in the UI.
 */
final class DashboardService
{
    /** @return array<string,int|float> */
    public function stats(): array
    {
        $projects = Database::table('projects');
        $posts = Database::table('blog_posts');
        $messages = Database::table('messages');

        return [
            'projects' => (int) $projects->count(),
            'projects_published' => count(Database::select(
                'SELECT p.id FROM projects p
                 JOIN project_translations t ON t.project_id = p.id
                 WHERE p.is_active = 1 GROUP BY p.id'
            )),
            'projects_featured' => (int) Database::table('projects')->where('is_featured', 1)->count(),
            'posts' => (int) $posts->count(),
            'posts_published' => (int) Database::table('blog_posts')->where('status', 'published')->count(),
            'posts_scheduled' => (int) Database::table('blog_posts')->where('status', 'scheduled')->count(),
            'posts_draft' => (int) Database::table('blog_posts')->where('status', 'draft')->count(),
            'messages' => (int) $messages->count(),
            'messages_unread' => (int) Database::table('messages')->where('is_read', 0)->count(),
            'messages_starred' => (int) Database::table('messages')->where('is_starred', 1)->count(),
            'messages_archived' => (int) Database::table('messages')->where('is_archived', 1)->count(),
            'services' => (int) Database::table('services')->count(),
            'skills' => (int) Database::table('skills')->where('kind', 'skill')->count(),
            'technologies' => (int) Database::table('skills')->where('kind', 'technology')->count(),
            'experiences' => (int) Database::table('experiences')->count(),
            'certifications' => (int) Database::table('certifications')->count(),
            'testimonials' => (int) Database::table('testimonials')->count(),
            'media' => (int) Database::table('media')->count(),
            'media_bytes' => (int) Database::table('media')->sum('size'),
            'views' => (int) Database::table('analytics_visits')->count(),
            'views_today' => (int) Database::selectOne(
                'SELECT COUNT(*) AS aggregate FROM analytics_visits WHERE created_at >= ?',
                [gmdate('Y-m-d 00:00:00')]
            )['aggregate'],
            'messages_today' => (int) Database::table('messages')->where('created_at', '>=', gmdate('Y-m-d 00:00:00'))->count(),
        ];
    }

    /** Everything the admin overview page renders, in one payload. */
    public function overview(): array
    {
        return [
            'stats' => $this->stats(),
            'visits' => $this->visits(14),
            'traffic' => $this->trafficBreakdown(30),
            'mix' => $this->contentMix(),
            'messages' => $this->recentMessages(6),
            'projects' => $this->recentProjects(5),
            'posts' => $this->recentPosts(5),
            'most_viewed' => $this->mostViewed(5),
            'security' => $this->security(5),
        ];
    }

    /** Inbox counters only (sidebar badge + messages screen header). */
    public function messageStats(): array
    {
        return [
            'total' => (int) Database::table('messages')->count(),
            'unread' => (int) Database::table('messages')->where('is_read', 0)->count(),
            'starred' => (int) Database::table('messages')->where('is_starred', 1)->count(),
            'archived' => (int) Database::table('messages')->where('is_archived', 1)->count(),
            'today' => (int) Database::table('messages')->where('created_at', '>=', gmdate('Y-m-d 00:00:00'))->count(),
        ];
    }

    /** Daily visits for the last N days (gap-filled so the chart never breaks). */
    public function visits(int $days = 14): array
    {
        $days = max(7, min(90, $days));
        $rows = Database::select(
            'SELECT substr(created_at, 1, 10) AS day, COUNT(*) AS visits
             FROM analytics_visits
             WHERE created_at >= ?
             GROUP BY day',
            [gmdate('Y-m-d 00:00:00', time() - ($days * 86400))]
        );

        $byDay = [];

        foreach ($rows as $row) {
            $byDay[(string) $row['day']] = (int) $row['visits'];
        }

        $series = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $day = gmdate('Y-m-d', time() - ($offset * 86400));
            $series[] = ['date' => $day, 'visits' => $byDay[$day] ?? 0];
        }

        return $series;
    }

    /** Traffic by language + top pages (used by the overview charts). */
    public function trafficBreakdown(int $days = 30): array
    {
        $since = gmdate('Y-m-d 00:00:00', time() - ($days * 86400));

        $byLanguage = Database::select(
            'SELECT COALESCE(lang, ?) AS lang, COUNT(*) AS visits
             FROM analytics_visits WHERE created_at >= ? GROUP BY lang ORDER BY visits DESC',
            [Config::get('app.default_locale', 'fa'), $since]
        );

        $topPaths = Database::select(
            'SELECT path, COUNT(*) AS visits FROM analytics_visits
             WHERE created_at >= ? GROUP BY path ORDER BY visits DESC LIMIT 8',
            [$since]
        );

        return [
            'languages' => array_map(static fn (array $row): array => [
                'lang' => (string) $row['lang'],
                'visits' => (int) $row['visits'],
            ], $byLanguage),
            'paths' => array_map(static fn (array $row): array => [
                'path' => (string) $row['path'],
                'visits' => (int) $row['visits'],
            ], $topPaths),
        ];
    }

    /** Content distribution for the donut chart. */
    public function contentMix(): array
    {
        return [
            ['key' => 'projects', 'value' => (int) Database::table('projects')->count()],
            ['key' => 'posts', 'value' => (int) Database::table('blog_posts')->count()],
            ['key' => 'services', 'value' => (int) Database::table('services')->count()],
            ['key' => 'testimonials', 'value' => (int) Database::table('testimonials')->count()],
            ['key' => 'media', 'value' => (int) Database::table('media')->count()],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function recentMessages(int $limit = 6): array
    {
        return Database::select(
            'SELECT id, name, email, subject, message, locale, is_read, is_starred, created_at
             FROM messages ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(50, $limit))
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function recentProjects(int $limit = 5): array
    {
        return Database::select(
            'SELECT p.id, p.is_active, p.is_featured, p.completed_at, p.updated_at,
                    COALESCE(t.title, f.title) AS title, COALESCE(t.slug, f.slug) AS slug,
                    m.url AS cover_url
             FROM projects p
             LEFT JOIN project_translations t ON t.project_id = p.id AND t.lang = ?
             LEFT JOIN project_translations f ON f.project_id = p.id AND f.lang = ?
             LEFT JOIN media m ON m.id = p.cover_media_id
             ORDER BY p.updated_at DESC, p.id DESC LIMIT ' . max(1, min(20, $limit)),
            [Config::get('app.default_locale', 'fa'), Config::get('app.fallback_locale', 'en')]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function recentPosts(int $limit = 5): array
    {
        return Database::select(
            'SELECT p.id, p.status, p.published_at, p.views, p.updated_at,
                    COALESCE(t.title, f.title) AS title, COALESCE(t.slug, f.slug) AS slug
             FROM blog_posts p
             LEFT JOIN blog_post_translations t ON t.post_id = p.id AND t.lang = ?
             LEFT JOIN blog_post_translations f ON f.post_id = p.id AND f.lang = ?
             ORDER BY p.updated_at DESC, p.id DESC LIMIT ' . max(1, min(20, $limit)),
            [Config::get('app.default_locale', 'fa'), Config::get('app.fallback_locale', 'en')]
        );
    }

    /** Most viewed published articles and projects. */
    public function mostViewed(int $limit = 5): array
    {
        $posts = Database::select(
            'SELECT p.views, COALESCE(t.title, f.title) AS title, COALESCE(t.slug, f.slug) AS slug
             FROM blog_posts p
             LEFT JOIN blog_post_translations t ON t.post_id = p.id AND t.lang = ?
             LEFT JOIN blog_post_translations f ON f.post_id = p.id AND f.lang = ?
             WHERE p.status = ? ORDER BY p.views DESC LIMIT ' . max(1, min(20, $limit)),
            [Config::get('app.default_locale', 'fa'), Config::get('app.fallback_locale', 'en'), 'published']
        );

        return array_map(static fn (array $row): array => [
            'title' => (string) $row['title'],
            'slug' => (string) $row['slug'],
            'views' => (int) $row['views'],
        ], $posts);
    }

    /** Security panel: active sessions + recent login attempts (success split in PHP). */
    public function security(int $limit = 10): array
    {
        $attempts = \App\Models\Admin::recentAttempts(30);
        $successful = [];
        $failed = [];

        foreach ($attempts as $attempt) {
            if ((int) ($attempt['successful'] ?? 0) === 1) {
                $successful[] = $attempt;
            } else {
                $failed[] = $attempt;
            }
        }

        return [
            'sessions' => \App\Models\Admin::activeSessions(),
            'attempts' => [
                'failed' => array_slice($failed, 0, $limit),
                'successful' => array_slice($successful, 0, $limit),
                'failed_total_24h' => count(array_filter($failed, static fn (array $row): bool => ($row['created_at'] ?? '') >= gmdate('Y-m-d H:i:s', time() - 86400))),
            ],
        ];
    }

    /** Record one visit (called from the public page controller). */
    public function recordVisit(string $path, ?string $lang, ?string $entityType = null, ?int $entityId = null): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $salt = (string) Config::get('app.key', 'portfolio');

        Database::table('analytics_visits')->insert([
            'path' => mb_substr($path, 0, 255),
            'lang' => $lang,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_hash' => $ip === '' ? null : hash_hmac('sha256', $ip, $salt),
            'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'referrer' => mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 255),
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
