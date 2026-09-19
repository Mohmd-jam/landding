<?php require_once APP . '/views/site/_partials.php'; ?>
<section class="page-head">
  <span class="sec-index mono"><?= t('sec.work') ?></span>
  <h1 class="display"><?= t('sec.work.title') ?></h1>
  <?php if (count($cats) > 1): ?>
  <div class="filters mono" data-filters>
    <button class="active" data-f="*"><?= t('work.filter.all') ?></button>
    <?php foreach ($cats as $c): ?><button data-f="<?= e($c) ?>"><?= e($c) ?></button><?php endforeach ?>
  </div>
  <?php endif ?>
</section>
<section class="section pt0">
  <div class="pgrid" data-filter-grid>
    <?php foreach ($projects as $i => $p) echo project_card($p, $i); ?>
  </div>
</section>
