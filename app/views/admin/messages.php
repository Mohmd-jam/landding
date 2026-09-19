<div class="card">
  <div class="card-head"><h2><?= $A('پیام‌ها', 'Messages') ?> <small class="muted">(<?= num(count($rows)) ?>)</small></h2></div>
  <?php if (!$rows): ?><p class="muted"><?= $A('پیامی نیست.', 'No messages yet.') ?></p><?php else: ?>
  <table><thead><tr><th><?= $A('نام', 'Name') ?></th><th class="hide-sm"><?= $A('موضوع', 'Subject') ?></th><th class="hide-sm"><?= $A('بودجه', 'Budget') ?></th><th><?= $A('تاریخ', 'Date') ?></th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $m): ?>
  <tr class="<?= $m['is_read'] ? '' : 'unread' ?>">
    <td><a href="/admin/messages/view/<?= $m['id'] ?>"><?= e($m['name']) ?></a><br><small class="muted" dir="ltr"><?= e($m['email']) ?></small></td>
    <td class="hide-sm"><?= e($m['subject']) ?></td><td class="hide-sm mono"><?= e($m['budget']) ?></td>
    <td class="mono"><?= fdate($m['created_at']) ?></td>
    <td class="actions"><a class="btn sm" href="/admin/messages/view/<?= $m['id'] ?>"><?= $A('مشاهده', 'View') ?></a>
      <form method="post" action="/admin/messages/delete/<?= $m['id'] ?>" data-confirm="<?= $A('حذف شود؟', 'Delete?') ?>"><?= csrf_field() ?><button class="btn sm danger"><?= $A('حذف', 'Delete') ?></button></form></td>
  </tr>
  <?php endforeach ?></tbody></table><?php endif ?>
</div>
