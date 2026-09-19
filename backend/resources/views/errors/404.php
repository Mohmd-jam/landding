<?php
/**
 * 404 - styled in the site's visual language so a wrong URL still feels designed.
 *
 * @var string $message
 */
$lang = (string) config('app.default_locale', 'fa');
$isFa = $lang === 'fa';
$title = $isFa ? 'صفحه پیدا نشد' : 'Page not found';
$text = trim((string) $message) !== '' ? $message : ($isFa ? 'آدرسی که وارد کردید وجود ندارد یا جابه‌جا شده است.' : 'That address does not exist or has moved.');
?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e(locale_dir($lang)) ?>" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, follow">
    <title>404 - <?= e($title) ?></title>
    <link rel="stylesheet" href="/app/error.css">
</head>
<body class="error-page">
    <main class="error-card" role="main">
        <p class="error-code">404</p>
        <h1><?= e($title) ?></h1>
        <p class="error-text"><?= e($text) ?></p>
        <div class="error-actions">
            <a class="btn btn-primary" href="/<?= e($lang) ?>"><?= e($isFa ? 'بازگشت به صفحه اصلی' : 'Back to home') ?></a>
            <a class="btn" href="/<?= e($lang) ?>/projects"><?= e($isFa ? 'مشاهده پروژه‌ها' : 'Browse projects') ?></a>
        </div>
    </main>
</body>
</html>
