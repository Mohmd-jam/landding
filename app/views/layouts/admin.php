<?php
$icons = [
 'grid'=>'<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
 'sliders'=>'<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
 'layout'=>'<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>',
 'folder'=>'<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
 'bar'=>'<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
 'quote'=>'<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/>',
 'edit'=>'<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
 'mail'=>'<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
 'user'=>'<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
];
$ic = fn($n) => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$n] ?? '') . '</svg>';
?><!doctype html>
<html lang="<?= LANG ?>" dir="<?= DIR ?>" data-theme="<?= THEME ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($nav[$section][0] ?? 'Admin') ?> — JamSoft</title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css?v=1">
<script>(function(){var t=document.cookie.match(/(?:^|; )theme=(light|dark)/);if(t)document.documentElement.dataset.theme=t[1];})();</script>
</head>
<body class="admin">
<aside class="side">
  <a class="brand" href="/admin"><span class="logo">J</span> JamSoft <small>admin</small></a>
  <nav>
    <?php foreach ($nav as $k => [$label, $icon]): ?>
    <a href="/admin<?= $k === 'dashboard' ? '' : "/$k" ?>" class="<?= $section === $k ? 'active' : '' ?>"><?= $ic($icon) ?> <span><?= e($label) ?></span>
      <?php if ($k === 'messages' && $unread): ?><b class="badge"><?= $unread ?></b><?php endif ?></a>
    <?php endforeach ?>
  </nav>
  <div class="side-foot">
    <a href="/" target="_blank">↗ <?= $A('مشاهده سایت', 'View site') ?></a>
    <a href="/admin/logout"><?= $A('خروج', 'Log out') ?></a>
  </div>
</aside>
<div class="main">
  <header class="top">
    <button class="icon-btn menu" data-side-toggle aria-label="menu">☰</button>
    <h1><?= e($nav[$section][0] ?? '') ?></h1>
    <div class="top-tools">
      <a class="icon-btn" href="?lang=<?= $fa ? 'en' : 'fa' ?>"><?= $fa ? 'EN' : 'فا' ?></a>
      <button class="icon-btn" data-theme-toggle title="theme"><span class="i-sun">☀</span><span class="i-moon">☾</span></button>
      <span class="who"><?= e(auth()['name']) ?></span>
    </div>
  </header>
  <div class="content">
    <?php if ($m = flash('ok')): ?><div class="alert ok"><?= e($m) ?></div><?php endif ?>
    <?= $content ?>
  </div>
</div>
<script src="/assets/js/admin.js?v=1" defer></script>
</body></html>
