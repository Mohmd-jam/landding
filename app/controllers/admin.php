<?php
/** Admin panel routes: /admin/... */
$seg = explode('/', $path);          // admin, section, action, id
$section = $seg[1] ?? 'dashboard';
$action  = $seg[2] ?? 'index';
$id      = isset($seg[3]) ? (int)$seg[3] : (ctype_digit($action) ? (int)$action : 0);
if (ctype_digit($action)) $action = 'edit';
$fa = LANG === 'fa';
$A = fn(string $f, string $en) => $fa ? $f : $en;   // quick inline translation for admin

// ---------- Login / logout ----------
if ($section === 'login') {
    if (auth()) redirect(url('admin'));
    $error = null;
    if ($method === 'POST') {
        csrf_check();
        $u = db()->one('SELECT * FROM users WHERE email=?', [post('email')]);
        if ($u && password_verify(post('password'), $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin'] = ['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email']];
            redirect(url('admin'));
        }
        $error = $A('ایمیل یا رمز عبور اشتباه است.', 'Invalid email or password.');
    }
    echo view('admin/login', compact('error', 'fa'));
    exit;
}
if ($section === 'logout') { unset($_SESSION['admin']); session_regenerate_id(true); redirect(url('admin/login')); }

require_auth();

// Resource definitions — table => fields (drives forms & lists dynamically)
$resources = [
    'services' => [
        'title' => $A('خدمات', 'Services'), 'order' => 'sort, id', 'label' => fn($r) => L($r, 'title'),
        'fields' => [
            ['icon', 'icon', $A('آیکون', 'Icon')],
            ['title_fa', 'text', 'عنوان (فارسی)', true], ['title_en', 'text', 'Title (English)', true],
            ['desc_fa', 'textarea', 'توضیح (فارسی)'], ['desc_en', 'textarea', 'Description (English)'],
            ['tags_fa', 'text', 'برچسب‌ها (فارسی، با کاما)'], ['tags_en', 'text', 'Tags (English, comma separated)'],
            ['sort', 'number', $A('ترتیب', 'Order')], ['active', 'check', $A('فعال', 'Active')],
        ],
    ],
    'projects' => [
        'title' => $A('نمونه‌کارها', 'Projects'), 'order' => 'featured DESC, sort, id', 'label' => fn($r) => L($r, 'title'),
        'fields' => [
            ['title_fa', 'text', 'عنوان (فارسی)', true], ['title_en', 'text', 'Title (English)', true],
            ['slug', 'text', 'Slug (URL)'],
            ['category_fa', 'text', 'دسته (فارسی)'], ['category_en', 'text', 'Category (English)'],
            ['summary_fa', 'textarea', 'خلاصه (فارسی)'], ['summary_en', 'textarea', 'Summary (English)'],
            ['body_fa', 'textarea', 'متن کامل (فارسی)'], ['body_en', 'textarea', 'Full text (English)'],
            ['stack', 'text', $A('تکنولوژی‌ها (با کاما)', 'Stack (comma separated)')],
            ['client', 'text', $A('کارفرما', 'Client')], ['year', 'text', $A('سال', 'Year')],
            ['url', 'text', $A('لینک سایت', 'Website URL')],
            ['image', 'image', $A('تصویر', 'Image')], ['color', 'color', $A('رنگ', 'Color')],
            ['featured', 'check', $A('ویژه', 'Featured')], ['sort', 'number', $A('ترتیب', 'Order')], ['active', 'check', $A('فعال', 'Active')],
        ],
    ],
    'skills' => [
        'title' => $A('مهارت‌ها', 'Skills'), 'order' => 'sort, id', 'label' => fn($r) => $r['name'],
        'fields' => [
            ['name', 'text', $A('نام', 'Name'), true],
            ['group_fa', 'text', 'گروه (فارسی)'], ['group_en', 'text', 'Group (English)'],
            ['level', 'number', $A('سطح (۰ تا ۱۰۰)', 'Level (0-100)')], ['sort', 'number', $A('ترتیب', 'Order')],
        ],
    ],
    'testimonials' => [
        'title' => $A('نظرات مشتریان', 'Testimonials'), 'order' => 'sort, id', 'label' => fn($r) => L($r, 'name'),
        'fields' => [
            ['name_fa', 'text', 'نام (فارسی)', true], ['name_en', 'text', 'Name (English)', true],
            ['role_fa', 'text', 'سمت (فارسی)'], ['role_en', 'text', 'Role (English)'],
            ['text_fa', 'textarea', 'متن (فارسی)'], ['text_en', 'textarea', 'Text (English)'],
            ['avatar', 'image', $A('آواتار', 'Avatar')], ['sort', 'number', $A('ترتیب', 'Order')], ['active', 'check', $A('فعال', 'Active')],
        ],
    ],
    'posts' => [
        'title' => $A('بلاگ', 'Blog posts'), 'order' => 'created_at DESC, id DESC', 'label' => fn($r) => L($r, 'title'),
        'fields' => [
            ['title_fa', 'text', 'عنوان (فارسی)', true], ['title_en', 'text', 'Title (English)', true],
            ['slug', 'text', 'Slug (URL)'],
            ['excerpt_fa', 'textarea', 'خلاصه (فارسی)'], ['excerpt_en', 'textarea', 'Excerpt (English)'],
            ['body_fa', 'textarea', 'متن (فارسی)'], ['body_en', 'textarea', 'Body (English)'],
            ['image', 'image', $A('تصویر', 'Image')], ['published', 'check', $A('منتشر شده', 'Published')],
        ],
    ],
];

$nav = [
    'dashboard' => [$A('داشبورد', 'Dashboard'), 'grid'],
    'settings'  => [$A('تنظیمات سایت', 'Site settings'), 'sliders'],
    'services'  => [$resources['services']['title'], 'layout'],
    'projects'  => [$resources['projects']['title'], 'folder'],
    'skills'    => [$resources['skills']['title'], 'bar'],
    'testimonials' => [$resources['testimonials']['title'], 'quote'],
    'posts'     => [$resources['posts']['title'], 'edit'],
    'messages'  => [$A('پیام‌ها', 'Messages'), 'mail'],
    'profile'   => [$A('حساب کاربری', 'Account'), 'user'],
];
$unread = (int)db()->val('SELECT COUNT(*) FROM messages WHERE is_read=0');
$shared = compact('nav', 'section', 'unread', 'fa', 'A');
$layout = function (string $view, array $data = []) use ($shared) { render('admin', 'admin/' . $view, $data + $shared); };

// ---------- Dashboard ----------
if ($section === 'dashboard') {
    $stats = [
        'projects' => db()->val('SELECT COUNT(*) FROM projects'), 'services' => db()->val('SELECT COUNT(*) FROM services'),
        'posts' => db()->val('SELECT COUNT(*) FROM posts'), 'messages' => db()->val('SELECT COUNT(*) FROM messages'),
    ];
    $latest = db()->all('SELECT * FROM messages ORDER BY id DESC LIMIT 6');
    $layout('dashboard', compact('stats', 'latest'));
    exit;
}

// ---------- Settings ----------
if ($section === 'settings') {
    $groups = [
        $A('عمومی', 'General') => [
            ['brand', '', $A('نام برند', 'Brand name')], ['accent', 'color', $A('رنگ اصلی', 'Accent color')],
            ['email', '', $A('ایمیل', 'Email')], ['phone', '', $A('تلفن', 'Phone')],
            ['address', 'fa', 'آدرس (فارسی)'], ['address', 'en', 'Address (English)'],
            ['telegram', '', 'Telegram'], ['github', '', 'GitHub'], ['linkedin', '', 'LinkedIn'], ['instagram', '', 'Instagram'],
            ['show_blog', 'check', $A('نمایش بلاگ', 'Show blog')], ['show_testimonials', 'check', $A('نمایش نظرات', 'Show testimonials')],
        ],
        $A('سئو', 'SEO') => [
            ['site_title', 'fa', 'عنوان سایت (فارسی)'], ['site_title', 'en', 'Site title (English)'],
            ['meta_desc', 'fa:textarea', 'توضیحات متا (فارسی)'], ['meta_desc', 'en:textarea', 'Meta description (English)'],
        ],
        $A('بخش هیرو', 'Hero') => [
            ['hero_kicker', 'fa', 'برچسب بالا (فارسی)'], ['hero_kicker', 'en', 'Kicker (English)'],
            ['hero_title', 'fa:textarea', 'تیتر (فارسی)'], ['hero_title', 'en:textarea', 'Headline (English)'],
            ['hero_sub', 'fa:textarea', 'زیرتیتر (فارسی)'], ['hero_sub', 'en:textarea', 'Sub‑headline (English)'],
            ['hero_cta', 'fa', 'دکمه اصلی (فارسی)'], ['hero_cta', 'en', 'Primary button (English)'],
            ['hero_cta2', 'fa', 'دکمه دوم (فارسی)'], ['hero_cta2', 'en', 'Secondary button (English)'],
        ],
        $A('آمار', 'Stats') => [
            ['stat_years', '', $A('سال تجربه', 'Years')], ['stat_projects', '', $A('پروژه', 'Projects')],
            ['stat_clients', '', $A('مشتری', 'Clients')], ['stat_uptime', '', $A('آپ‌تایم', 'Uptime')],
        ],
        $A('درباره', 'About') => [
            ['about_title', 'fa', 'عنوان (فارسی)'], ['about_title', 'en', 'Title (English)'],
            ['about_text', 'fa:textarea', 'متن (فارسی)'], ['about_text', 'en:textarea', 'Text (English)'],
            ['process_title', 'fa', 'عنوان فرآیند (فارسی)'], ['process_title', 'en', 'Process title (English)'],
        ],
        $A('تماس و فوتر', 'Contact & footer') => [
            ['cta_title', 'fa', 'عنوان تماس (فارسی)'], ['cta_title', 'en', 'Contact title (English)'],
            ['cta_text', 'fa:textarea', 'متن تماس (فارسی)'], ['cta_text', 'en:textarea', 'Contact text (English)'],
            ['footer_text', 'fa:textarea', 'متن فوتر (فارسی)'], ['footer_text', 'en:textarea', 'Footer text (English)'],
        ],
    ];
    if ($method === 'POST') {
        csrf_check();
        foreach ($groups as $g) foreach ($g as [$key, $type]) {
            $lang = in_array(explode(':', $type)[0], ['fa', 'en'], true) ? explode(':', $type)[0] : '';
            $field = $key . ($lang ? "__$lang" : '');
            $val = $type === 'check' ? (isset($_POST[$field]) ? '1' : '0') : post($field);
            $ex = db()->one('SELECT id FROM settings WHERE `key`=? AND `lang`=?', [$key, $lang]);
            if ($ex) db()->update('settings', ['value' => $val], (int)$ex['id']);
            else db()->insert('settings', ['key' => $key, 'lang' => $lang, 'value' => $val]);
        }
        flash('ok', $A('تنظیمات ذخیره شد.', 'Settings saved.'));
        redirect(url('admin/settings'));
    }
    $all = [];
    foreach (db()->all('SELECT * FROM settings') as $r) $all[$r['key'] . ($r['lang'] ? '__' . $r['lang'] : '')] = $r['value'];
    $layout('settings', compact('groups', 'all'));
    exit;
}

// ---------- Messages ----------
if ($section === 'messages') {
    if ($action === 'read' && $id) { db()->update('messages', ['is_read' => 1], $id); redirect(url('admin/messages')); }
    if ($action === 'delete' && $id && $method === 'POST') { csrf_check(); db()->delete('messages', $id); flash('ok', $A('حذف شد.', 'Deleted.')); redirect(url('admin/messages')); }
    if ($action === 'view' && $id) {
        $m = db()->one('SELECT * FROM messages WHERE id=?', [$id]);
        if (!$m) redirect(url('admin/messages'));
        if (!$m['is_read']) db()->update('messages', ['is_read' => 1], $id);
        $layout('message', compact('m'));
        exit;
    }
    $rows = db()->all('SELECT * FROM messages ORDER BY id DESC');
    $layout('messages', compact('rows'));
    exit;
}

// ---------- Profile ----------
if ($section === 'profile') {
    $u = db()->one('SELECT * FROM users WHERE id=?', [auth()['id']]);
    $error = null;
    if ($method === 'POST') {
        csrf_check();
        $data = ['name' => post('name'), 'email' => post('email')];
        if (post('password') !== '') {
            if (!password_verify(post('current'), $u['password'])) $error = $A('رمز فعلی اشتباه است.', 'Current password is wrong.');
            elseif (strlen(post('password')) < 6) $error = $A('رمز جدید حداقل ۶ کاراکتر.', 'New password must be at least 6 characters.');
            else $data['password'] = password_hash(post('password'), PASSWORD_DEFAULT);
        }
        if (!$error) {
            db()->update('users', $data, (int)$u['id']);
            $_SESSION['admin']['name'] = $data['name']; $_SESSION['admin']['email'] = $data['email'];
            flash('ok', $A('ذخیره شد.', 'Saved.')); redirect(url('admin/profile'));
        }
    }
    $layout('profile', compact('u', 'error'));
    exit;
}

// ---------- Generic CRUD ----------
if (isset($resources[$section])) {
    $res = $resources[$section];
    $table = $section;

    if ($action === 'delete' && $id && $method === 'POST') {
        csrf_check();
        $row = db()->one("SELECT * FROM $table WHERE id=?", [$id]);
        foreach ($res['fields'] as $f) if ($f[1] === 'image' && !empty($row[$f[0]])) @unlink(cfg('upload.dir') . '/' . basename($row[$f[0]]));
        db()->delete($table, $id);
        flash('ok', $A('حذف شد.', 'Deleted.'));
        redirect(url("admin/$section"));
    }

    if ($action === 'create' || $action === 'edit') {
        $row = $id ? db()->one("SELECT * FROM $table WHERE id=?", [$id]) : [];
        if ($id && !$row) redirect(url("admin/$section"));
        $error = null;
        if ($method === 'POST') {
            csrf_check();
            $data = [];
            foreach ($res['fields'] as [$name, $type, $label]) {
                $required = $res['fields'][array_search($name, array_column($res['fields'], 0))][3] ?? false;
                switch ($type) {
                    case 'check':  $data[$name] = isset($_POST[$name]) ? 1 : 0; break;
                    case 'number': $data[$name] = (int)post($name); break;
                    case 'image':  $data[$name] = handle_upload($name, $row[$name] ?? null);
                                   if (isset($_POST["{$name}__remove"])) { if (!empty($data[$name])) @unlink(cfg('upload.dir') . '/' . basename($data[$name])); $data[$name] = null; }
                                   break;
                    default:       $data[$name] = post($name);
                        if ($required && $data[$name] === '') $error = $A("فیلد «{$label}» الزامی است.", "Field \"{$label}\" is required.");
                }
            }
            if (array_key_exists('slug', $data)) {
                $data['slug'] = slugify($data['slug'] !== '' ? $data['slug'] : ($data['title_en'] ?? $data['title_fa']));
                $dup = db()->one("SELECT id FROM $table WHERE slug=? AND id<>?", [$data['slug'], $id]);
                if ($dup) $data['slug'] .= '-' . substr(bin2hex(random_bytes(2)), 0, 3);
            }
            if (!$error) {
                if ($id) db()->update($table, $data, $id); else $id = db()->insert($table, $data);
                flash('ok', $A('ذخیره شد.', 'Saved.'));
                redirect(url("admin/$section"));
            }
            $row = array_merge($row, $data);
        }
        $layout('form', compact('res', 'row', 'id', 'error'));
        exit;
    }

    $rows = db()->all("SELECT * FROM $table ORDER BY {$res['order']}");
    $layout('list', compact('res', 'rows'));
    exit;
}

http_response_code(404);
$layout('404');
