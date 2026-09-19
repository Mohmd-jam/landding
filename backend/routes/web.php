<?php

declare(strict_types=1);

/**
 * Web routes: the public site, the admin shell and crawler files.
 *
 * Each public URL returns a complete HTML document (SEO head + pre-rendered
 * content) that React then hydrates — see App\Core\View::spaShell().
 */

use App\Core\Router;
use App\Http\Controllers\Api\ContactController as PublicContactController;
use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\PageController;
use App\Http\Middleware\Csrf;
use App\Http\Middleware\Throttle;

/** @var Router $router */

// Crawler files and health endpoints (no locale prefix).
$router->get('/sitemap.xml', [PageController::class, 'sitemap']);
$router->get('/robots.txt', [PageController::class, 'robots']);
$router->get('/health', [PageController::class, 'health']);

// Admin shell (the SPA router takes over once the bundle loads).
$router->get('/admin', [AdminController::class, 'shell']);
$router->get('/admin/{path:.*}', [AdminController::class, 'shell']);

// Bare domain → default locale.
$router->get('/', [PageController::class, 'root']);
$router->get('/{locale}', [PageController::class, 'home']);

// Public pages.
$router->get('/{locale}/projects', [PageController::class, 'projects']);
$router->get('/{locale}/projects/{slug}', [PageController::class, 'project']);
$router->get('/{locale}/blog', [PageController::class, 'blog']);
$router->get('/{locale}/blog/{slug}', [PageController::class, 'post']);
$router->get('/{locale}/services', [PageController::class, 'services']);
$router->get('/{locale}/services/{slug}', [PageController::class, 'service']);
$router->get('/{locale}/about', [PageController::class, 'about']);
$router->get('/{locale}/contact', [PageController::class, 'contact']);
$router->get('/{locale}/resume', [PageController::class, 'resume']);
$router->get('/{locale}/resume/print', [PageController::class, 'resumePrint']);
$router->get('/{locale}/search', [PageController::class, 'search']);
$router->get('/{locale}/testimonials', [PageController::class, 'testimonials']);

// Editable content pages created in the admin (kept last so they never shadow
// the purpose-built routes above).
$router->get('/{locale}/{page:about|privacy|terms|faq|process}', [PageController::class, 'contentPage']);

// No-JavaScript fallback for the contact form (same controller as the API).
$router->post('/{locale}/contact', [PublicContactController::class, 'storeWeb'], [Csrf::class, Throttle::class . ':contact']);
