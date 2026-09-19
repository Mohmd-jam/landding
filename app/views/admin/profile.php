<form method="post" class="card form narrow">
  <?= csrf_field() ?>
  <h2><?= $A('حساب کاربری', 'Account') ?></h2>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif ?>
  <div class="fields">
    <label><?= $A('نام', 'Name') ?><input name="name" value="<?= e($u['name']) ?>" required></label>
    <label><?= $A('ایمیل', 'Email') ?><input name="email" type="email" value="<?= e($u['email']) ?>" required dir="ltr"></label>
    <label class="full"><?= $A('رمز فعلی (برای تغییر رمز)', 'Current password (to change password)') ?><input name="current" type="password" dir="ltr"></label>
    <label class="full"><?= $A('رمز جدید', 'New password') ?><input name="password" type="password" dir="ltr" minlength="6"></label>
  </div>
  <div class="form-foot"><button class="btn primary"><?= $A('ذخیره', 'Save') ?></button></div>
</form>
