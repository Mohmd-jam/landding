<?php
/**
 * 500 - exposes details only when APP_DEBUG is enabled.
 *
 * @var string $message
 * @var bool   $debug
 */
$lang = (string) config('app.default_locale', 'fa');
$isFa = $lang === 'fa';
?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e(locale_dir($lang)) ?>" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>500 - <?= e($isFa ? 'خطای سرور' : 'Server error') ?></title>
    <link rel="stylesheet" href="/app/error.css">
</head>
<body class="error-page">
    <main class="error-card" role="main">
        <p class="error-code">500</p>
        <h1><?= e($isFa ? 'مشکلی در سرور رخ داد' : 'Something broke on our side') ?></h1>
        <p class="error-text"><?= e($message) ?></p>
        <div class="error-actions">
            <a class="btn btn-primary" href="/"><?= e($isFa ? 'تلاش دوباره' : 'Try again') ?></a>
        </div>
        <?php if (!empty($debug)): ?>
            <pre class="error-debug"><?= e($message) ?></pre>
        <?php endif; ?>
    </main>
</body>
</html>
