<?php require_once APP . '/views/site/_partials.php'; ?>
<form method="post" enctype="multipart/form-data" class="card form">
  <?= csrf_field() ?>
  <div class="card-head"><h2><?= $id ? $A('ویرایش', 'Edit') : $A('افزودن', 'Add') ?> — <?= e($res['title']) ?></h2>
    <a class="btn sm" href="/admin/<?= $section ?>">← <?= $A('بازگشت', 'Back') ?></a></div>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif ?>
  <div class="fields">
  <?php foreach ($res['fields'] as $f): [$name, $type, $label] = $f; $req = $f[3] ?? false; $v = $row[$name] ?? ''; $isFa = str_ends_with($name, '_fa'); $isEn = str_ends_with($name, '_en'); $dir = $isFa ? 'rtl' : ($isEn ? 'ltr' : ''); ?>
    <?php if ($type === 'check'): ?>
      <label class="check"><input type="checkbox" name="<?= $name ?>" <?= ($row === [] && !$id && in_array($name, ['active','published'])) || $v ? 'checked' : '' ?>> <?= e($label) ?></label>
    <?php elseif ($type === 'textarea'): ?>
      <label class="full"><?= e($label) ?><?= $req ? ' *' : '' ?><textarea name="<?= $name ?>" rows="<?= str_starts_with($name, 'body') ? 10 : 3 ?>" <?= $dir ? "dir=$dir" : '' ?>><?= e($v) ?></textarea></label>
    <?php elseif ($type === 'image'): ?>
      <div class="full imgfield"><span><?= e($label) ?></span>
        <?php if ($v): ?><img src="<?= e(upload_url($v)) ?>" alt=""><label class="check"><input type="checkbox" name="<?= $name ?>__remove"> <?= $A('حذف تصویر', 'Remove image') ?></label><?php endif ?>
        <input type="file" name="<?= $name ?>" accept="image/*"></div>
    <?php elseif ($type === 'icon'): ?>
      <div class="full"><span class="lbl"><?= e($label) ?></span><div class="icons">
        <?php foreach (icon_list() as $ico): ?><label class="ico"><input type="radio" name="icon" value="<?= $ico ?>" <?= ($v ?: 'code') === $ico ? 'checked' : '' ?>><span><?= icon($ico, 20) ?></span></label><?php endforeach ?>
      </div></div>
    <?php elseif ($type === 'color'): ?>
      <label><?= e($label) ?><span class="colorwrap"><input type="color" name="<?= $name ?>" value="<?= e($v ?: '#e8ff47') ?>"><code dir="ltr"><?= e($v ?: '#e8ff47') ?></code></span></label>
    <?php else: ?>
      <label><?= e($label) ?><?= $req ? ' *' : '' ?><input type="<?= $type === 'number' ? 'number' : 'text' ?>" name="<?= $name ?>" value="<?= e((string)$v) ?>" <?= $dir ? "dir=$dir" : '' ?> <?= $req ? 'required' : '' ?>></label>
    <?php endif ?>
  <?php endforeach ?>
  </div>
  <div class="form-foot"><button class="btn primary"><?= $A('ذخیره', 'Save') ?></button></div>
</form>
