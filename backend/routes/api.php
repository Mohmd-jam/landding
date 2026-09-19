<?php

declare(strict_types=1);

/**
 * Public REST API (v1).
 *
 * Read endpoints are throttled and cacheable; the contact form is the only
 * write endpoint and it is protected by CSRF, a honeypot and a tighter bucket.
 *
 * Every response is JSON: `{ data: …, meta?: …, locale: … }`.
 */

use App\Core\Router;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Middleware\Csrf;
use App\Http\Middleware\ForceJson;
use App\Http\Middleware\Throttle;

/** @var Router $router */
$router->group('/api/v1', [ForceJson::class, Throttle::class . ':public'], static function (Router $router): void {
    // Shell bootstrap: settings, languages, UI strings, navigation, socials.
    $router->get('/bootstrap', [SiteController::class, 'bootstrap']);
    $router->get('/home', [SiteController::class, 'home']);
    $router->get('/pages/{key}', [SiteController::class, 'contentPage']);
    $router->get('/contact-info', [SiteController::class, 'contactInfo']);
    $router->get('/search', [SiteController::class, 'search']);
    $router->get('/translate-slug', [SiteController::class, 'translateSlug']);
    $router->get('/settings', [SiteController::class, 'siteSettings']);

    // Projects
    $router->get('/projects', [ProjectController::class, 'index']);
    $router->get('/projects/{slug}', [ProjectController::class, 'show']);
    $router->get('/project-categories', [ProjectController::class, 'categories']);
    $router->get('/technologies', [ProjectController::class, 'technologies']);

    // Blog
    $router->get('/posts', [BlogController::class, 'index']);
    $router->get('/posts/{slug}', [BlogController::class, 'show']);
    $router->get('/blog-categories', [BlogController::class, 'categories']);
    $router->get('/blog-tags', [BlogController::class, 'tags']);

    // Profile content
    $router->get('/services', [ContentController::class, 'services']);
    $router->get('/services/{slug}', [ContentController::class, 'service']);
    $router->get('/skills', [ContentController::class, 'skills']);
    $router->get('/timeline', [ContentController::class, 'timeline']);
    $router->get('/experiences', [ContentController::class, 'experiences']);
    $router->get('/education', [ContentController::class, 'education']);
    $router->get('/certifications', [ContentController::class, 'certifications']);
    $router->get('/testimonials', [ContentController::class, 'testimonials']);
    $router->get('/resume', [ContentController::class, 'resume']);
    $router->get('/resume/download', [ContentController::class, 'download']);

    // Public form: CSRF + honeypot + a dedicated throttle bucket.
    $router->post('/contact', [ContactController::class, 'store'], [Csrf::class, Throttle::class . ':contact']);
});

// Uploaded-image derivatives (cached on disk, immutable from the browser's view).
$router->get('/media/thumb/{width}/{path:.*}', [MediaController::class, 'thumb']);
