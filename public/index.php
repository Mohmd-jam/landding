<?php

declare(strict_types=1);

/**
 * Front controller.
 *
 * Apache/Nginx rewrite every non-static request here (see .htaccess). The same
 * file is executed by tools/server.mjs in the sandbox through the PHP-WASM
 * runtime, so development and production share one code path.
 */

use App\Core\Kernel;

$bootstrap = require dirname(__DIR__) . '/backend/bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $bootstrap['kernel'];

$kernel->handle(App\Core\Request::capture())->send();
