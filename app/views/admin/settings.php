<form method="post" class="settings">
  <?= csrf_field() ?>
  <div class="tabs" data-tabs>
    <?php $i = 0; foreach ($groups as $g => $_): ?><button type="button" class="<?= $i++ ? '' : 'active' ?>" data-tab="t<?= $i ?>"><?= e($g) ?></button><?php endforeach ?>
  </div>
  <?php $i = 0; foreach ($groups as $g => $fields): $i++; ?>
  <div class="card tab <?= $i === 1 ? 'active' : '' ?>" id="t<?= $i ?>">
    <h2><?= e($g) ?></h2>
    <div class="fields">
    <?php foreach ($fields as [$key, $type, $label]): $lang = in_array(explode(':', $type)[0], ['fa','en']) ? explode(':', $type)[0] : ''; $name = $key . ($lang ? "__$lang" : ''); $v = $all[$name] ?? ''; $ta = str_contains($type, 'textarea'); ?>
      <?php if ($type === 'check'): ?><label class="check"><input type="checkbox" name="<?= $name ?>" <?= $v === '1' ? 'checked' : '' ?>> <?= e($label) ?></label>
      <?php elseif ($type === 'color'): ?><label><?= e($label) ?><span class="colorwrap"><input type="color" name="<?= $name ?>" value="<?= e($v ?: '#e8ff47') ?>"><code dir="ltr"><?= e($v) ?></code></span></label>
      <?php elseif ($ta): ?><label class="full"><?= e($label) ?><textarea name="<?= $name ?>" rows="3" dir="<?= $lang === 'en' ? 'ltr' : 'rtl' ?>"><?= e($v) ?></textarea></label>
      <?php else: ?><label><?= e($label) ?><input name="<?= $name ?>" value="<?= e($v) ?>" dir="<?= $lang === 'fa' ? 'rtl' : 'ltr' ?>"></label><?php endif ?>
    <?php endforeach ?>
    </div>
  </div>
  <?php endforeach ?>
  <div class="form-foot sticky"><button class="btn primary"><?= $A('ذخیره تنظیمات', 'Save settings') ?></button></div>
</form>
