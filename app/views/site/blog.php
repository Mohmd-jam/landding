<section class="page-head">
  <span class="sec-index mono"><?= t('sec.blog') ?></span>
  <h1 class="display"><?= t('sec.blog.title') ?></h1>
</section>
<section class="section pt0">
  <div class="posts list">
    <?php foreach ($posts as $po): ?>
    <a class="post reveal" href="/blog/<?= e($po['slug']) ?>">
      <span class="mono muted"><?= fdate($po['created_at']) ?></span>
      <h3><?= e(L($po, 'title')) ?></h3>
      <p><?= e(L($po, 'excerpt')) ?></p>
      <span class="link-arrow"><?= t('blog.read') ?> <span class="arr">→</span></span>
    </a>
    <?php endforeach ?>
    <?php if (!$posts): ?><p class="muted">—</p><?php endif ?>
  </div>
</section>
