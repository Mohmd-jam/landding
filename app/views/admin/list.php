<div class="card">
  <div class="card-head"><h2><?= e($res['title']) ?> <small class="muted">(<?= num(count($rows)) ?>)</small></h2><a class="btn primary" href="/admin/<?= $section ?>/create">+ <?= $A('افزودن', 'Add new') ?></a></div>
  <?php if (!$rows): ?><p class="muted"><?= $A('موردی وجود ندارد.', 'Nothing here yet.') ?></p><?php else: ?>
  <table><thead><tr><th style="width:50px">#</th><th><?= $A('عنوان', 'Title') ?></th>
    <?php foreach ($res['fields'] as [$n, $t]): if (in_array($t, ['check', 'number'])): ?><th class="hide-sm"><?= e($res['fields'][array_search($n, array_column($res['fields'], 0))][2]) ?></th><?php endif; endforeach ?>
    <th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td class="mono muted"><?= $r['id'] ?></td>
    <td><a href="/admin/<?= $section ?>/<?= $r['id'] ?>"><?= e($res['label']($r)) ?></a>
      <?php if (!empty($r['slug'])): ?><br><small class="muted mono">/<?= e($r['slug']) ?></small><?php endif ?></td>
    <?php foreach ($res['fields'] as [$n, $t]): if ($t === 'check'): ?><td class="hide-sm"><span class="dot <?= $r[$n] ? 'on' : '' ?>"></span></td>
      <?php elseif ($t === 'number'): ?><td class="hide-sm mono"><?= num($r[$n]) ?></td><?php endif; endforeach ?>
    <td class="actions">
      <a class="btn sm" href="/admin/<?= $section ?>/<?= $r['id'] ?>"><?= $A('ویرایش', 'Edit') ?></a>
      <form method="post" action="/admin/<?= $section ?>/delete/<?= $r['id'] ?>" data-confirm="<?= $A('حذف شود؟', 'Delete this item?') ?>"><?= csrf_field() ?><button class="btn sm danger"><?= $A('حذف', 'Delete') ?></button></form>
    </td>
  </tr>
  <?php endforeach ?></tbody></table><?php endif ?>
</div>
