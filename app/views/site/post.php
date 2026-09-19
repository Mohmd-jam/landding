<article class="article">
  <section class="page-head narrow">
    <a class="link-arrow back" href="/blog"><span class="arr">←</span> <?= t('blog.back') ?></a>
    <span class="sec-index mono"><?= fdate($post['created_at']) ?> · <?= num(max(1, (int)ceil(mb_strlen(L($post, 'body')) / 900))) ?> <?= t('blog.min') ?></span>
    <h1 class="h1 serif"><?= e(L($post, 'title')) ?></h1>
    <p class="lead"><?= e(L($post, 'excerpt')) ?></p>
  </section>
  <?php if ($post['image']): ?><div class="article-img"><img src="<?= e(upload_url($post['image'])) ?>" alt=""></div><?php endif ?>
  <section class="section narrow pt0"><div class="prose big"><?= nl2p(L($post, 'body')) ?></div></section>
</article>
