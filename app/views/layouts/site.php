<?php $brand = s('brand', 'JamSoft'); $accent = s('accent', '#e8ff47'); ?><!doctype html>
<html lang="<?= LANG ?>" dir="<?= DIR ?>" data-theme="<?= THEME ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page['title'] ?: $brand) ?></title>
<meta name="description" content="<?= e($page['desc'] ?? '') ?>">
<meta property="og:title" content="<?= e($page['title'] ?: $brand) ?>">
<meta property="og:description" content="<?= e($page['desc'] ?? '') ?>">
<meta property="og:type" content="website">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Space+Grotesk:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/site.css?v=1">
<style>:root{--accent:<?= e($accent) ?>;}</style>
<script>(function(){var t=document.cookie.match(/(?:^|; )theme=(light|dark)/);if(t)document.documentElement.dataset.theme=t[1];})();</script>
</head>
<body class="<?= e($page['body_class'] ?? '') ?>">
<a class="skip" href="#main">skip</a>
<div class="cursor" aria-hidden="true"></div>

<header class="nav" id="top">
  <a class="nav-brand" href="/"><span class="nav-mark"><?= mb_strtoupper(mb_substr($brand, 0, 1)) ?></span><span><?= e($brand) ?><sup>®</sup></span></a>
  <nav class="nav-links" id="navLinks">
    <a href="/#services"><?= t('nav.services') ?></a>
    <a href="/work"><?= t('nav.work') ?></a>
    <a href="/#about"><?= t('nav.about') ?></a>
    <?php if (s('show_blog', '1') === '1'): ?><a href="/blog"><?= t('nav.blog') ?></a><?php endif ?>
    <a href="/#contact"><?= t('nav.contact') ?></a>
  </nav>
  <div class="nav-tools">
    <a class="pill" href="?lang=<?= LANG === 'fa' ? 'en' : 'fa' ?>" title="Language"><?= t('lang.switch') ?></a>
    <button class="pill theme-toggle" type="button" data-theme-toggle aria-label="<?= t('theme.toggle') ?>">
      <svg class="i-sun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4 1.4-1.4"/></svg>
      <svg class="i-moon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
    </button>
    <a class="pill solid hide-sm" href="/#contact"><?= t('nav.start') ?> <span class="arr">→</span></a>
    <button class="pill burger" type="button" data-nav-toggle aria-label="menu"><span></span><span></span></button>
  </div>
</header>

<main id="main">
<?= $content ?>
</main>

<footer class="footer">
  <div class="footer-grid">
    <div class="footer-about">
      <a class="nav-brand" href="/"><span class="nav-mark"><?= mb_strtoupper(mb_substr($brand, 0, 1)) ?></span><span><?= e($brand) ?></span></a>
      <p><?= e(s('footer_text')) ?></p>
      <p class="mono muted"><?= t('footer.made') ?></p>
    </div>
    <div>
      <h4 class="mono"><?= t('footer.links') ?></h4>
      <a href="/#services"><?= t('nav.services') ?></a>
      <a href="/work"><?= t('nav.work') ?></a>
      <a href="/#about"><?= t('nav.about') ?></a>
      <?php if (s('show_blog', '1') === '1'): ?><a href="/blog"><?= t('nav.blog') ?></a><?php endif ?>
    </div>
    <div>
      <h4 class="mono"><?= t('footer.contact') ?></h4>
      <a href="mailto:<?= e(s('email')) ?>" dir="ltr"><?= e(s('email')) ?></a>
      <a href="tel:<?= e(preg_replace('/\s+/', '', s('phone'))) ?>" dir="ltr"><?= e(s('phone')) ?></a>
      <span><?= e(s('address')) ?></span>
    </div>
    <div>
      <h4 class="mono"><?= t('footer.social') ?></h4>
      <?php foreach (['github' => 'GitHub', 'linkedin' => 'LinkedIn', 'telegram' => 'Telegram', 'instagram' => 'Instagram'] as $k => $l): if (s($k)): ?>
        <a href="<?= e(s($k)) ?>" target="_blank" rel="noopener"><?= $l ?> ↗</a>
      <?php endif; endforeach ?>
    </div>
  </div>
  <div class="footer-big" aria-hidden="true"><?= e(mb_strtoupper($brand)) ?></div>
  <div class="footer-bottom mono">
    <span>© <?= num(date('Y')) ?> <?= e($brand) ?>. <?= t('footer.rights') ?></span>
    <a href="#top">↑ top</a>
  </div>
</footer>

<script src="/assets/js/site.js?v=1" defer></script>
</body>
</html>
