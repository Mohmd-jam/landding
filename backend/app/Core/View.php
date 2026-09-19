<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal PHP template renderer plus the SPA shell pipeline.
 *
 * The public site is a React application, but every route is still served with
 * a fully-formed HTML document: real <title>/<meta> tags, canonical + hreflang
 * alternates, JSON-LD structured data and a server-rendered content block. That
 * keeps the site fast on first paint and crawlable/usable even without
 * JavaScript, while the React bundle takes over on load.
 */
final class View
{
    /** @param array<string,mixed> $data */
    public static function render(string $view, array $data = []): string
    {
        $path = dirname(__DIR__, 2) . '/resources/views/' . str_replace('.', '/', $view) . '.php';

        if (!is_file($path)) {
            throw HttpException::server('View not found: ' . $view);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $path;

        return (string) ob_get_clean();
    }

    public static function exists(string $view): bool
    {
        return is_file(dirname(__DIR__, 2) . '/resources/views/' . str_replace('.', '/', $view) . '.php');
    }

    /** HTML error document (no framework, no assets required). */
    public static function errorPage(int $status, string $message, bool $debug = false): string
    {
        $view = match (true) {
            $status === 404 => 'errors.404',
            $status >= 500 => 'errors.500',
            default => 'errors.generic',
        };

        try {
            return self::render($view, ['message' => $message, 'status' => $status, 'debug' => $debug]);
        } catch (\Throwable) {
            return self::fallbackErrorDocument($status, $message);
        }
    }

    /**
     * Render the built SPA shell with server-side metadata.
     *
     * @param array{
     *     lang?:string,dir?:string,theme?:string,title?:string,
     *     head?:string,bootstrap?:array,prerender?:string,kind?:string
     * } $payload
     */
    public static function spaShell(array $payload): string
    {
        $lang = (string) ($payload['lang'] ?? 'fa');
        $dir = (string) ($payload['dir'] ?? ($lang === 'fa' ? 'rtl' : 'ltr'));
        $theme = (string) ($payload['theme'] ?? 'dark');
        $title = (string) ($payload['title'] ?? Config::get('app.name', 'Portfolio'));
        $kind = (string) ($payload['kind'] ?? 'public');
        $shellPath = (string) Config::get($kind === 'admin' ? 'app.admin_shell' : 'app.spa_shell');

        if ($shellPath === '' || !is_file($shellPath)) {
            return self::fallbackShell($payload);
        }

        $html = (string) file_get_contents($shellPath);

        $replacements = [
            '__LANG__' => Security::escape($lang),
            '__DIR__' => Security::escape($dir),
            '__THEME__' => Security::escape($theme),
            '__TITLE__' => Security::escape($title),
            '<!--app:head-->' => (string) ($payload['head'] ?? ''),
            '<!--app:bootstrap-->' => '<script>window.__BOOTSTRAP__ = '
                . Json::encodeForScript($payload['bootstrap'] ?? [])
                . ';</script>',
            '<!--app:prerender-->' => (string) ($payload['prerender'] ?? ''),
            '<!--app:critical-css-->' => self::criticalCss(),
        ];

        $html = str_replace(array_keys($replacements), array_values($replacements), $html);

        return self::withoutMissingAssets($html);
    }

    /**
     * Drop <script>/<link> tags that point at a bundle which has not been built
     * yet (`npm run build`). The server-rendered page stays complete and styled
     * by the inline critical CSS instead of requesting a 404.
     */
    private static function withoutMissingAssets(string $html): string
    {
        $public = rtrim((string) Config::get('app.public_path', ''), '/');

        return preg_replace_callback(
            '#<(script|link)\b[^>]*?(?:src|href)="([^"]*/app/assets/[^"]+)"[^>]*>\s*(?:</script>)?#i',
            static function (array $match) use ($public): string {
                $path = parse_url($match[2], PHP_URL_PATH) ?? $match[2];

                return is_file($public . $path) ? $match[0] : '';
            },
            $html
        ) ?? $html;
    }

    /**
     * Inline the design tokens so the very first paint already has the theme
     * colours (no flash of unstyled content, one less blocking request).
     */
    public static function criticalCss(): string
    {
        $path = dirname(__DIR__, 3) . '/frontend/src/shared/styles/tokens.css';

        if (!is_file($path)) {
            return '';
        }

        $css = (string) file_get_contents($path);

        // Trim comments to keep the inline block small.
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;

        return '<style>' . trim($css) . '</style>';
    }

    /** @param array<string,mixed> $payload */
    private static function fallbackShell(array $payload): string
    {
        $lang = Security::escape((string) ($payload['lang'] ?? 'fa'));
        $dir = Security::escape((string) ($payload['dir'] ?? 'rtl'));
        $title = Security::escape((string) ($payload['title'] ?? 'Portfolio'));

        return '<!doctype html><html lang="' . $lang . '" dir="' . $dir . '"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $title . '</title>'
            . (string) ($payload['head'] ?? '')
            . self::criticalCss()
            . '</head><body class="' . ($kind === 'admin' ? 'admin' : 'site') . '">'
            . '<div id="root">' . (string) ($payload['prerender'] ?? '') . '</div>'
            . '<script>window.__BOOTSTRAP__ = ' . Json::encodeForScript($payload['bootstrap'] ?? []) . ';</script>'
            . '<link rel="stylesheet" href="/app/assets/site.css">'
            . '</body></html>';
    }

    /** Last-resort error page when even the template layer fails. */
    private static function fallbackErrorDocument(int $status, string $message): string
    {
        return '<!doctype html><html lang="en" dir="ltr"><head><meta charset="utf-8">'
            . '<title>' . $status . '</title></head><body style="font-family:system-ui;background:#0b0d12;color:#e8edf5;padding:48px">'
            . '<h1 style="font-size:20px">' . $status . '</h1><p>' . Security::escape($message) . '</p>'
            . '</body></html>';
    }
}
