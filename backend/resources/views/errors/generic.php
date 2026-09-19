<?php
/**
 * Generic HTTP error page (400 / 401 / 403 / 405 / 419 / 429 ...).
 *
 * @var string $message
 * @var int    $status
 */
$lang = (string) config('app.default_locale', 'fa');
$isFa = $lang === 'fa';
$labels = [
    400 => $isFa ? 'درخواست نامعتبر' : 'Bad request',
    401 => $isFa ? 'نیاز به ورود' : 'Authentication required',
    403 => $isFa ? 'دسترسی مجاز نیست' : 'Access denied',
    405 => $isFa ? 'متد مجاز نیست' : 'Method not allowed',
    419 => $isFa ? 'نشست منقضی شده' : 'Session expired',
    429 => $isFa ? 'درخواست‌های بیش از حد' : 'Too many requests',
];
$title = $labels[$status] ?? ($isFa ? 'خطا' : 'Error');
?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e(locale_dir($lang)) ?>" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e((string) $status) ?> - <?= e($title) ?></title>
    <link rel="stylesheet" href="/app/error.css">
</head>
<body class="error-page">
    <main class="error-card" role="main">
        <p class="error-code"><?= e((string) $status) ?></p>
        <h1><?= e($title) ?></h1>
        <p class="error-text"><?= e($message) ?></p>
        <div class="error-actions">
            <a class="btn btn-primary" href="/"><?= e($isFa ? 'صفحه اصلی' : 'Home') ?></a>
        </div>
    </main>
</body>
</html>
