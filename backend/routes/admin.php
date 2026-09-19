<?php

declare(strict_types=1);

/**
 * Admin API.
 *
 * Two layers guard every route: `AdminAuth` (database-backed session) and
 * `Role` (capability). State-changing requests additionally require a valid
 * CSRF token, so a compromised page cannot drive the panel from the outside.
 *
 * The generic `/{resource}` routes are the data-driven CRUD engine described by
 * `AdminResourceRegistry` — 21 resources share one controller, one validator and
 * one audit path.
 */

use App\Core\Router;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\NavigationController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\AdminAuth;
use App\Http\Middleware\Csrf;
use App\Http\Middleware\ForceJson;
use App\Http\Middleware\Role;
use App\Http\Middleware\Throttle;

/** @var Router $router */
$router->group('/api/admin', [ForceJson::class], static function (Router $router): void {
    /* --------------------------------------------------------------------- */
    /* Sessions                                                              */
    /* --------------------------------------------------------------------- */

    // Starts the session and hands the SPA its CSRF token (login must send it back).
    $router->get('/session', [AuthController::class, 'session']);
    $router->post('/login', [AuthController::class, 'login'], [Csrf::class, Throttle::class . ':login']);

    /* --------------------------------------------------------------------- */
    /* Authenticated area                                                    */
    /* --------------------------------------------------------------------- */

    $router->group('', [AdminAuth::class], static function (Router $router): void {
        $router->get('/me', [AuthController::class, 'me']);
        $router->post('/logout', [AuthController::class, 'logout'], [Csrf::class]);
        $router->put('/password', [AuthController::class, 'password'], [Csrf::class]);

        $router->get('/dashboard', [DashboardController::class, 'index']);
        $router->get('/schema', [ResourceController::class, 'schema']);

        /* Media library -------------------------------------------------- */
        $router->get('/media', [MediaController::class, 'index']);
        $router->post('/media', [MediaController::class, 'store'], [Csrf::class, Throttle::class . ':upload', Role::class . ':media']);
        $router->put('/media/{id}', [MediaController::class, 'update'], [Csrf::class, Role::class . ':media']);
        $router->delete('/media/{id}', [MediaController::class, 'destroy'], [Csrf::class, Role::class . ':media']);
        $router->post('/media/bulk', [MediaController::class, 'bulk'], [Csrf::class, Role::class . ':media']);

        /* Inbox ---------------------------------------------------------- */
        $router->get('/messages', [MessageController::class, 'index']);
        $router->post('/messages/bulk', [MessageController::class, 'bulk'], [Csrf::class, Role::class . ':messages']);
        $router->get('/messages/{id}', [MessageController::class, 'show']);
        $router->put('/messages/{id}', [MessageController::class, 'update'], [Csrf::class, Role::class . ':messages']);
        $router->delete('/messages/{id}', [MessageController::class, 'destroy'], [Csrf::class, Role::class . ':messages']);

        /* Settings, languages, UI strings -------------------------------- */
        $router->get('/settings', [SettingController::class, 'index']);
        $router->put('/settings', [SettingController::class, 'update'], [Csrf::class, Role::class . ':seo']);
        $router->get('/languages', [LanguageController::class, 'index']);
        $router->post('/languages', [LanguageController::class, 'store'], [Csrf::class, Role::class . ':seo']);
        $router->put('/languages/{id}', [LanguageController::class, 'update'], [Csrf::class, Role::class . ':seo']);
        $router->delete('/languages/{id}', [LanguageController::class, 'destroy'], [Csrf::class, Role::class . ':seo']);
        $router->get('/translations', [TranslationController::class, 'index']);
        $router->post('/translations', [TranslationController::class, 'store'], [Csrf::class, Role::class . ':seo']);
        $router->put('/translations/{id}', [TranslationController::class, 'update'], [Csrf::class, Role::class . ':seo']);
        $router->delete('/translations/{id}', [TranslationController::class, 'destroy'], [Csrf::class, Role::class . ':seo']);

        /* Navigation (tree + reorder) ------------------------------------ */
        $router->get('/navigation', [NavigationController::class, 'index']);
        $router->post('/navigation', [NavigationController::class, 'store'], [Csrf::class, Role::class . ':seo']);
        $router->put('/navigation/{id}', [NavigationController::class, 'update'], [Csrf::class, Role::class . ':seo']);
        $router->delete('/navigation/{id}', [NavigationController::class, 'destroy'], [Csrf::class, Role::class . ':seo']);
        $router->post('/navigation/reorder', [NavigationController::class, 'reorder'], [Csrf::class, Role::class . ':seo']);

        /* SEO ------------------------------------------------------------ */
        $router->get('/seo', [SeoController::class, 'index']);
        $router->put('/seo', [SeoController::class, 'update'], [Csrf::class, Role::class . ':seo']);
        $router->post('/seo/sitemap', [SeoController::class, 'sitemap'], [Csrf::class, Role::class . ':seo']);

        /* Resume files --------------------------------------------------- */
        $router->post('/resumes/{id}/file', [ResourceController::class, 'uploadResumeFile'], [Csrf::class, Role::class . ':content']);
        $router->delete('/resumes/{id}/file', [ResourceController::class, 'deleteResumeFile'], [Csrf::class, Role::class . ':content']);

        /* Admin users & security ----------------------------------------- */
        $router->get('/users', [UserController::class, 'index']);
        $router->post('/users', [UserController::class, 'store'], [Csrf::class, Role::class . ':seo']);
        $router->put('/users/{id}', [UserController::class, 'update'], [Csrf::class, Role::class . ':seo']);
        $router->delete('/users/{id}', [UserController::class, 'destroy'], [Csrf::class, Role::class . ':seo']);
        $router->get('/security', [SecurityController::class, 'index']);
        $router->delete('/security/sessions/{id}', [SecurityController::class, 'revoke'], [Csrf::class, Role::class . ':seo']);
        $router->post('/security/attempts/clear', [SecurityController::class, 'clearAttempts'], [Csrf::class, Role::class . ':seo']);
        $router->post('/security/cache/clear', [SecurityController::class, 'clearCache'], [Csrf::class, Role::class . ':seo']);

        /* Generic, registry-driven CRUD ---------------------------------- */
        $router->get('/{resource}', [ResourceController::class, 'index']);
        $router->post('/{resource}', [ResourceController::class, 'store'], [Csrf::class]);
        $router->post('/{resource}/reorder', [ResourceController::class, 'reorder'], [Csrf::class]);
        $router->get('/{resource}/{id}', [ResourceController::class, 'show']);
        $router->put('/{resource}/{id}', [ResourceController::class, 'update'], [Csrf::class]);
        $router->delete('/{resource}/{id}', [ResourceController::class, 'destroy'], [Csrf::class]);
        $router->post('/{resource}/{id}/toggle', [ResourceController::class, 'toggle'], [Csrf::class]);
    });
});
