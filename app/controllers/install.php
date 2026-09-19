<?php
/** /install — one-time setup */
if (!empty($installed)) redirect('/');

$error = $dbError ?? null;
if ($method === 'POST' && !$error) {
    csrf_check();
    $name = post('name', 'Admin'); $email = post('email'); $pass = post('password');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
        $error = LANG === 'fa' ? 'ایمیل معتبر و رمز عبور حداقل ۶ کاراکتر وارد کنید.' : 'Enter a valid email and a password of at least 6 characters.';
    } else {
        try {
            $installer->install($name, $email, $pass, isset($_POST['seed']));
            $_SESSION['admin'] = ['id' => 1, 'name' => $name, 'email' => $email];
            flash('ok', LANG === 'fa' ? 'نصب با موفقیت انجام شد.' : 'Installation completed.');
            redirect(url('admin'));
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }
}
$fa = LANG === 'fa';
?><!doctype html>
<html lang="<?= LANG ?>" dir="<?= DIR ?>" data-theme="dark">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>JamSoft — Install</title>
<link rel="stylesheet" href="/assets/css/admin.css"></head>
<body class="auth">
<form method="post" class="auth-card">
  <?= csrf_field() ?>
  <div class="brand"><span class="logo">J</span> JamSoft <small>setup</small></div>
  <h1><?= $fa ? 'نصب اولیه' : 'Initial setup' ?></h1>
  <p class="muted"><?= $fa ? 'پایگاه‌داده: ' : 'Database: ' ?><code><?= e(cfg('db.driver')) ?><?= cfg('db.driver') === 'mysql' ? ' / ' . e(cfg('db.name')) : '' ?></code></p>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?>
    <?php if (!empty($dbError)): ?><br><small><?= $fa ? 'تنظیمات اتصال را در app/config.local.php اصلاح کنید.' : 'Fix connection settings in app/config.local.php.' ?></small><?php endif ?>
  </div><?php endif ?>
  <label><?= $fa ? 'نام مدیر' : 'Admin name' ?><input name="name" value="<?= e(post('name', 'Admin')) ?>" required></label>
  <label><?= $fa ? 'ایمیل' : 'Email' ?><input name="email" type="email" value="<?= e(post('email')) ?>" required></label>
  <label><?= $fa ? 'رمز عبور' : 'Password' ?><input name="password" type="password" required minlength="6"></label>
  <label class="check"><input type="checkbox" name="seed" checked> <?= $fa ? 'محتوای نمونه (خدمات، پروژه‌ها، …) اضافه شود' : 'Add demo content (services, projects, …)' ?></label>
  <button class="btn primary" <?= !empty($dbError) ? 'disabled' : '' ?>><?= $fa ? 'نصب' : 'Install' ?></button>
  <p class="muted center"><a href="?lang=<?= $fa ? 'en' : 'fa' ?>"><?= $fa ? 'English' : 'فارسی' ?></a></p>
</form>
</body></html>
