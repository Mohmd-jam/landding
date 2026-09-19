<?php
/** Shared partial renderers for the public site */

function icon(string $name, int $s = 22): string {
    $paths = [
        'code'   => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
        'layout' => '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>',
        'cart'   => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
        'zap'    => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'cloud'  => '<path d="M18 10h-1.3A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/>',
        'smartphone' => '<rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.7-4 3-9 3s-9-1.3-9-3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/>',
        'globe'  => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'cpu'    => '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v3m6-3v3M9 20v3m6-3v3M20 9h3m-3 6h3M1 9h3m-3 6h3"/>',
        'chart'  => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'users'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
    ];
    $p = $paths[$name] ?? $paths['code'];
    return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
}
function icon_list(): array { return ['code','layout','cart','zap','cloud','smartphone','shield','database','globe','cpu','chart','users']; }

function project_card(array $p, int $i = 0): string {
    $img = $p['image'] ? upload_url($p['image']) : '';
    $style = 'style="--c:' . e($p['color'] ?: '#e8ff47') . '"';
    ob_start(); ?>
    <a class="pcard reveal" href="/work/<?= e($p['slug']) ?>" <?= $style ?> data-cat="<?= e(L($p, 'category')) ?>">
      <div class="pcard-media">
        <?php if ($img): ?><img src="<?= e($img) ?>" alt="<?= e(L($p, 'title')) ?>" loading="lazy"><?php else: ?>
        <div class="pcard-abstract"><span class="mono"><?= sprintf('%02d', $i + 1) ?></span><b><?= e(mb_substr(L($p, 'title'), 0, 1)) ?></b></div><?php endif ?>
      </div>
      <div class="pcard-body">
        <div class="pcard-meta mono"><span><?= e(L($p, 'category')) ?></span><span><?= num($p['year']) ?></span></div>
        <h3><?= e(L($p, 'title')) ?></h3>
        <p><?= e(L($p, 'summary')) ?></p>
        <span class="pcard-arrow">→</span>
      </div>
    </a>
    <?php return ob_get_clean();
}
