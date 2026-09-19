<div class="cards">
  <?php foreach (['projects' => [$A('پروژه', 'Projects'), 'projects'], 'services' => [$A('خدمت', 'Services'), 'services'], 'posts' => [$A('نوشته', 'Posts'), 'posts'], 'messages' => [$A('پیام', 'Messages'), 'messages']] as $k => [$l, $link]): ?>
  <a class="card stat" href="/admin/<?= $link ?>"><b><?= num($stats[$k]) ?></b><span><?= e($l) ?></span></a>
  <?php endforeach ?>
</div>
<div class="card">
  <div class="card-head"><h2><?= $A('آخرین پیام‌ها', 'Latest messages') ?></h2><a href="/admin/messages" class="btn sm"><?= $A('همه', 'All') ?></a></div>
  <?php if (!$latest): ?><p class="muted"><?= $A('پیامی نیست.', 'No messages yet.') ?></p><?php else: ?>
  <table><thead><tr><th><?= $A('نام', 'Name') ?></th><th><?= $A('موضوع', 'Subject') ?></th><th><?= $A('تاریخ', 'Date') ?></th><th></th></tr></thead><tbody>
  <?php foreach ($latest as $m): ?>
  <tr class="<?= $m['is_read'] ? '' : 'unread' ?>"><td><?= e($m['name']) ?><br><small class="muted" dir="ltr"><?= e($m['email']) ?></small></td><td><?= e($m['subject']) ?></td><td class="mono"><?= fdate($m['created_at']) ?></td><td><a class="btn sm" href="/admin/messages/view/<?= $m['id'] ?>"><?= $A('مشاهده', 'View') ?></a></td></tr>
  <?php endforeach ?></tbody></table><?php endif ?>
</div>
<div class="card quick">
  <h2><?= $A('دسترسی سریع', 'Quick actions') ?></h2>
  <div class="row">
    <a class="btn" href="/admin/projects/create">+ <?= $A('پروژه جدید', 'New project') ?></a>
    <a class="btn" href="/admin/posts/create">+ <?= $A('نوشته جدید', 'New post') ?></a>
    <a class="btn" href="/admin/services/create">+ <?= $A('خدمت جدید', 'New service') ?></a>
    <a class="btn" href="/admin/settings"><?= $A('ویرایش متن‌های سایت', 'Edit site texts') ?></a>
  </div>
</div>
