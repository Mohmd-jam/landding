<div class="card msg">
  <div class="card-head"><h2><?= e($m['subject'] ?: $A('پیام', 'Message')) ?></h2><a class="btn sm" href="/admin/messages">← <?= $A('بازگشت', 'Back') ?></a></div>
  <dl class="meta">
    <dt><?= $A('نام', 'Name') ?></dt><dd><?= e($m['name']) ?></dd>
    <dt><?= $A('ایمیل', 'Email') ?></dt><dd dir="ltr"><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a></dd>
    <?php if ($m['phone']): ?><dt><?= $A('تلفن', 'Phone') ?></dt><dd dir="ltr"><a href="tel:<?= e($m['phone']) ?>"><?= e($m['phone']) ?></a></dd><?php endif ?>
    <?php if ($m['budget']): ?><dt><?= $A('بودجه', 'Budget') ?></dt><dd><?= e($m['budget']) ?></dd><?php endif ?>
    <dt><?= $A('تاریخ', 'Date') ?></dt><dd class="mono"><?= fdate($m['created_at']) ?> · <span dir="ltr"><?= e($m['created_at']) ?></span></dd>
    <dt>IP</dt><dd class="mono" dir="ltr"><?= e($m['ip']) ?></dd>
  </dl>
  <div class="msg-body"><?= nl2br(e($m['message'])) ?></div>
  <div class="row">
    <a class="btn primary" href="mailto:<?= e($m['email']) ?>?subject=Re: <?= rawurlencode($m['subject'] ?? '') ?>"><?= $A('پاسخ با ایمیل', 'Reply by email') ?></a>
    <form method="post" action="/admin/messages/delete/<?= $m['id'] ?>" data-confirm="<?= $A('حذف شود؟', 'Delete?') ?>"><?= csrf_field() ?><button class="btn danger"><?= $A('حذف', 'Delete') ?></button></form>
  </div>
</div>
