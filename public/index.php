<?php
require dirname(__DIR__) . '/app/bootstrap.php';

$uri = strtok($_SERVER['REQUEST_URI'], '?');
$path = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

// ---- Installation guard ----
try {
    $installer = new Installer(db());
    $installed = $installer->isInstalled();
} catch (Throwable $e) {
    $installed = false;
    $dbError = $e->getMessage();
}
if (!$installed && $path !== 'install') redirect(url('install'));
if ($path === 'install') { require APP . '/controllers/install.php'; exit; }

// ---- Admin ----
if ($path === 'admin' || str_starts_with($path, 'admin/')) {
    require APP . '/controllers/admin.php';
    exit;
}

// ---- Public site ----
require APP . '/controllers/site.php';
