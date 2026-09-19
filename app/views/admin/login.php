<!doctype html>
<html lang="<?= LANG ?>" dir="<?= DIR ?>" data-theme="<?= THEME ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — JamSoft</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css"></head>
<body class="auth">
<form method="post" class="auth-card">
  <?= csrf_field() ?>
  <div class="brand"><span class="logo">J</span> JamSoft <small>admin</small></div>
  <h1><?= $fa ? 'ورود به پنل' : 'Sign in' ?></h1>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif ?>
  <label><?= $fa ? 'ایمیل' : 'Email' ?><input name="email" type="email" required autofocus dir="ltr"></label>
  <label><?= $fa ? 'رمز عبور' : 'Password' ?><input name="password" type="password" required dir="ltr"></label>
  <button class="btn primary"><?= $fa ? 'ورود' : 'Sign in' ?></button>
  <p class="muted center"><a href="?lang=<?= $fa ? 'en' : 'fa' ?>"><?= $fa ? 'English' : 'فارسی' ?></a> · <a href="/">← <?= $fa ? 'سایت' : 'site' ?></a></p>
</form>
</body></html>
