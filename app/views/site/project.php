<article class="project" style="--c:<?= e($project['color']) ?>">
  <section class="page-head">
    <a class="link-arrow back" href="/work"><span class="arr">←</span> <?= t('work.back') ?></a>
    <span class="sec-index mono"><?= e(L($project, 'category')) ?></span>
    <h1 class="display"><?= e(L($project, 'title')) ?></h1>
    <p class="lead"><?= e(L($project, 'summary')) ?></p>
  </section>
  <div class="project-hero">
    <?php if ($project['image']): ?><img src="<?= e(upload_url($project['image'])) ?>" alt="<?= e(L($project, 'title')) ?>"><?php else: ?>
    <div class="pcard-abstract big"><b><?= e(mb_substr(L($project, 'title'), 0, 1)) ?></b></div><?php endif ?>
  </div>
  <section class="section project-body">
    <aside class="project-meta mono">
      <?php if ($project['client']): ?><div><small><?= t('work.client') ?></small><b><?= e($project['client']) ?></b></div><?php endif ?>
      <?php if ($project['year']): ?><div><small><?= t('work.year') ?></small><b><?= num($project['year']) ?></b></div><?php endif ?>
      <?php if ($project['stack']): ?><div><small><?= t('work.stack') ?></small><div class="tags"><?php foreach (explode(',', $project['stack']) as $tg): ?><span><?= e(trim($tg)) ?></span><?php endforeach ?></div></div><?php endif ?>
      <?php if ($project['url']): ?><a class="btn btn-ghost" href="<?= e($project['url']) ?>" target="_blank" rel="noopener"><?= t('work.visit') ?> ↗</a><?php endif ?>
    </aside>
    <div class="prose big"><?= nl2p(L($project, 'body')) ?></div>
  </section>
  <?php if ($next && $next['id'] != $project['id']): ?>
  <a class="next-project" href="/work/<?= e($next['slug']) ?>" style="--c:<?= e($next['color']) ?>">
    <span class="mono"><?= t('work.next') ?></span>
    <span class="display"><?= e(L($next, 'title')) ?> <span class="arr">→</span></span>
  </a>
  <?php endif ?>
</article>
