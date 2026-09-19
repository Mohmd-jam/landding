<?php require_once APP . '/views/site/_partials.php'; ?>

<!-- ============ HERO ============ -->
<section class="hero">
  <div class="hero-grid">
    <div class="hero-left">
      <div class="hero-kicker mono"><span class="dot"></span> <?= e(s('hero_kicker')) ?></div>
      <h1 class="display"><?= e(s('hero_title')) ?></h1>
      <p class="lead"><?= e(s('hero_sub')) ?></p>
      <div class="hero-actions">
        <a class="btn btn-primary magnetic" href="#contact"><?= e(s('hero_cta')) ?> <span class="arr">→</span></a>
        <a class="btn btn-ghost magnetic" href="/work"><?= e(s('hero_cta2')) ?></a>
      </div>
    </div>
    <aside class="hero-right">
      <div class="term">
        <div class="term-bar"><i></i><i></i><i></i><span class="mono">jamsoft — deploy.sh</span></div>
        <pre class="mono" id="term" dir="ltr"><span class="c">$</span> composer install --no-dev
<span class="g">✓</span> 42 packages installed
<span class="c">$</span> php artisan migrate --force
<span class="g">✓</span> 18 migrations · MySQL 8
<span class="c">$</span> npm run build
<span class="g">✓</span> built in 1.2s
<span class="c">$</span> ./deploy production
<span class="g">✓</span> live — <span class="y">200 OK</span> · 187ms
<span class="c">$</span> <span class="cursor-blink">▍</span></pre>
      </div>
      <div class="hero-badge mono"><span class="dot live"></span> <?= t('hero.available') ?></div>
    </aside>
  </div>

  <div class="marquee" aria-hidden="true">
    <div class="marquee-track mono">
      <?php $items = ['PHP 8', 'Laravel', 'MySQL', 'REST API', 'SaaS', 'E‑commerce', 'Automation', 'PWA', 'Redis', 'Docker', 'Vue', 'Tailwind'];
      for ($k = 0; $k < 2; $k++) foreach ($items as $it): ?><span><?= $it ?></span><i>✦</i><?php endforeach ?>
    </div>
  </div>
</section>

<!-- ============ STATS ============ -->
<section class="stats">
  <?php foreach ([['stat_years', 'stat.years', '+'], ['stat_projects', 'stat.projects', '+'], ['stat_clients', 'stat.clients', '+'], ['stat_uptime', 'stat.uptime', '']] as [$k, $l, $suf]): ?>
  <div class="stat reveal">
    <div class="stat-n"><span data-count="<?= e(s($k, '0')) ?>"><?= num(s($k, '0')) ?></span><?= $suf ?></div>
    <div class="stat-l mono"><?= t($l) ?></div>
  </div>
  <?php endforeach ?>
</section>

<!-- ============ SERVICES ============ -->
<section class="section" id="services">
  <header class="sec-head">
    <span class="sec-index mono">01 / <?= t('sec.services') ?></span>
    <h2 class="h2"><?= t('sec.services.title') ?></h2>
  </header>
  <div class="services">
    <?php foreach ($services as $i => $sv): ?>
    <article class="service reveal">
      <div class="service-top"><span class="service-icon"><?= icon($sv['icon']) ?></span><span class="mono muted"><?= sprintf('%02d', $i + 1) ?></span></div>
      <h3><?= e(L($sv, 'title')) ?></h3>
      <p><?= e(L($sv, 'desc')) ?></p>
      <?php if ($tags = L($sv, 'tags')): ?><div class="tags"><?php foreach (explode(',', $tags) as $tg): ?><span><?= e(trim($tg)) ?></span><?php endforeach ?></div><?php endif ?>
    </article>
    <?php endforeach ?>
  </div>
</section>

<!-- ============ WORK ============ -->
<section class="section" id="work">
  <header class="sec-head">
    <span class="sec-index mono">02 / <?= t('sec.work') ?></span>
    <h2 class="h2"><?= t('sec.work.title') ?></h2>
    <a class="link-arrow" href="/work"><?= t('sec.work.all') ?> <span class="arr">→</span></a>
  </header>
  <div class="pgrid">
    <?php foreach ($projects as $i => $p) echo project_card($p, $i); ?>
  </div>
</section>

<!-- ============ ABOUT ============ -->
<section class="section about" id="about">
  <div class="about-grid">
    <div>
      <span class="sec-index mono">03 / <?= t('sec.about') ?></span>
      <h2 class="h2 serif"><?= e(s('about_title')) ?></h2>
      <div class="prose"><?= nl2p(s('about_text')) ?></div>
    </div>
    <div class="skills reveal">
      <h4 class="mono muted"><?= t('sec.skills') ?></h4>
      <?php $groups = []; foreach ($skills as $sk) $groups[L($sk, 'group')][] = $sk; ?>
      <?php foreach ($groups as $g => $list): ?>
      <div class="skill-group">
        <div class="skill-g mono"><?= e($g) ?></div>
        <?php foreach ($list as $sk): ?>
        <div class="skill"><span><?= e($sk['name']) ?></span><i style="--w:<?= (int)$sk['level'] ?>%"></i><b class="mono"><?= num($sk['level']) ?>%</b></div>
        <?php endforeach ?>
      </div>
      <?php endforeach ?>
    </div>
  </div>
</section>

<!-- ============ PROCESS ============ -->
<section class="section process">
  <header class="sec-head">
    <span class="sec-index mono">04 / <?= t('sec.process') ?></span>
    <h2 class="h2"><?= e(s('process_title')) ?></h2>
  </header>
  <ol class="steps">
    <?php for ($i = 1; $i <= 4; $i++): ?>
    <li class="step reveal"><span class="step-n serif"><?= num($i) ?></span><h3><?= t("process.$i.t") ?></h3><p><?= t("process.$i.d") ?></p></li>
    <?php endfor ?>
  </ol>
</section>

<?php if ($testimonials): ?>
<!-- ============ TESTIMONIALS ============ -->
<section class="section" id="testimonials">
  <header class="sec-head">
    <span class="sec-index mono">05 / <?= t('sec.testimonials') ?></span>
    <h2 class="h2"><?= t('sec.testimonials.title') ?></h2>
  </header>
  <div class="quotes">
    <?php foreach ($testimonials as $tm): ?>
    <blockquote class="quote reveal">
      <p class="serif">“<?= e(L($tm, 'text')) ?>”</p>
      <footer>
        <?php if ($tm['avatar']): ?><img src="<?= e(upload_url($tm['avatar'])) ?>" alt=""><?php else: ?><span class="avatar"><?= e(mb_substr(L($tm, 'name'), 0, 1)) ?></span><?php endif ?>
        <div><b><?= e(L($tm, 'name')) ?></b><small class="mono muted"><?= e(L($tm, 'role')) ?></small></div>
      </footer>
    </blockquote>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>

<?php if ($posts): ?>
<!-- ============ BLOG ============ -->
<section class="section" id="blog">
  <header class="sec-head">
    <span class="sec-index mono">06 / <?= t('sec.blog') ?></span>
    <h2 class="h2"><?= t('sec.blog.title') ?></h2>
    <a class="link-arrow" href="/blog"><?= t('sec.blog.all') ?> <span class="arr">→</span></a>
  </header>
  <div class="posts">
    <?php foreach ($posts as $po): ?>
    <a class="post reveal" href="/blog/<?= e($po['slug']) ?>">
      <span class="mono muted"><?= fdate($po['created_at']) ?></span>
      <h3><?= e(L($po, 'title')) ?></h3>
      <p><?= e(L($po, 'excerpt')) ?></p>
      <span class="link-arrow"><?= t('blog.read') ?> <span class="arr">→</span></span>
    </a>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>

<!-- ============ CONTACT ============ -->
<section class="section contact" id="contact">
  <div class="contact-grid">
    <div class="contact-intro">
      <span class="sec-index mono">07 / <?= t('sec.contact') ?></span>
      <h2 class="display serif"><?= e(s('cta_title')) ?></h2>
      <p class="lead"><?= e(s('cta_text')) ?></p>
      <div class="contact-lines mono">
        <a href="mailto:<?= e(s('email')) ?>" dir="ltr"><?= e(s('email')) ?></a>
        <a href="tel:<?= e(preg_replace('/\s+/', '', s('phone'))) ?>" dir="ltr"><?= e(s('phone')) ?></a>
        <?php if (s('telegram')): ?><a href="<?= e(s('telegram')) ?>" target="_blank" rel="noopener">Telegram ↗</a><?php endif ?>
      </div>
    </div>
    <form class="cform" method="post" action="/contact" id="contactForm">
      <?= csrf_field() ?>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
      <?php if ($m = flash('contact_ok')): ?><div class="alert ok"><?= e($m) ?></div><?php endif ?>
      <?php if ($m = flash('contact_err')): ?><div class="alert err"><?= e($m) ?></div><?php endif ?>
      <?php $old = $_SESSION['contact_old'] ?? []; unset($_SESSION['contact_old']); ?>
      <div class="row2">
        <label><span><?= t('form.name') ?> *</span><input name="name" required value="<?= e($old['name'] ?? '') ?>"></label>
        <label><span><?= t('form.email') ?> *</span><input name="email" type="email" required dir="ltr" value="<?= e($old['email'] ?? '') ?>"></label>
      </div>
      <div class="row2">
        <label><span><?= t('form.phone') ?></span><input name="phone" dir="ltr" value="<?= e($old['phone'] ?? '') ?>"></label>
        <label><span><?= t('form.subject') ?></span>
          <select name="subject">
            <?php foreach (['web', 'shop', 'auto', 'saas', 'other'] as $sj): ?><option <?= ($old['subject'] ?? '') === t("subj.$sj") ? 'selected' : '' ?>><?= t("subj.$sj") ?></option><?php endforeach ?>
          </select></label>
      </div>
      <label><span><?= t('form.budget') ?></span>
        <div class="chips">
          <?php foreach (['< 50M', '50–150M', '150–500M', '500M+', t('form.budget.na')] as $b): ?>
          <label class="chip"><input type="radio" name="budget" value="<?= e($b) ?>" <?= ($old['budget'] ?? '') === $b ? 'checked' : '' ?>><span><?= e($b) ?></span></label>
          <?php endforeach ?>
        </div></label>
      <label><span><?= t('form.message') ?> *</span><textarea name="message" rows="5" required><?= e($old['message'] ?? '') ?></textarea></label>
      <button class="btn btn-primary" type="submit"><?= t('form.send') ?> <span class="arr">→</span></button>
    </form>
  </div>
</section>
