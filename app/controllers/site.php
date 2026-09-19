<?php
/** Public site routes */
$seg = $path === '' ? [] : explode('/', $path);
$page = ['title' => s('site_title'), 'desc' => s('meta_desc'), 'body_class' => ''];

// POST /contact
if ($path === 'contact' && $method === 'POST') {
    csrf_check();
    $name = post('name'); $email = post('email'); $msg = post('message');
    $honeypot = post('website');
    if ($honeypot !== '' ) redirect(url('#contact'));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($msg) < 5) {
        flash('contact_err', t('form.error'));
        $_SESSION['contact_old'] = $_POST;
    } else {
        db()->insert('messages', [
            'name' => mb_substr($name, 0, 190), 'email' => mb_substr($email, 0, 190),
            'phone' => mb_substr(post('phone'), 0, 50), 'subject' => mb_substr(post('subject'), 0, 190),
            'budget' => mb_substr(post('budget'), 0, 50), 'message' => mb_substr($msg, 0, 5000),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        flash('contact_ok', t('form.sent'));
        unset($_SESSION['contact_old']);
    }
    redirect(url('#contact'));
}

// Home
if ($path === '') {
    $data = [
        'services'     => db()->all('SELECT * FROM services WHERE active=1 ORDER BY sort, id'),
        'projects'     => db()->all('SELECT * FROM projects WHERE active=1 ORDER BY featured DESC, sort, id LIMIT 6'),
        'skills'       => db()->all('SELECT * FROM skills ORDER BY sort, id'),
        'testimonials' => s('show_testimonials', '1') === '1' ? db()->all('SELECT * FROM testimonials WHERE active=1 ORDER BY sort, id') : [],
        'posts'        => s('show_blog', '1') === '1' ? db()->all('SELECT * FROM posts WHERE published=1 ORDER BY created_at DESC, id DESC LIMIT 3') : [],
    ];
    $page['body_class'] = 'home';
    render('site', 'site/home', $data + ['page' => $page]);
    exit;
}

// Work list
if ($path === 'work') {
    $projects = db()->all('SELECT * FROM projects WHERE active=1 ORDER BY featured DESC, sort, id');
    $cats = array_values(array_unique(array_filter(array_map(fn($p) => L($p, 'category'), $projects))));
    $page['title'] = t('nav.work') . ' — ' . s('brand', 'JamSoft');
    render('site', 'site/work', compact('projects', 'cats', 'page'));
    exit;
}
// Work single
if (($seg[0] ?? '') === 'work' && isset($seg[1])) {
    $project = db()->one('SELECT * FROM projects WHERE slug=? AND active=1', [$seg[1]]);
    if (!$project) { http_response_code(404); render('site', 'site/404', ['page' => $page]); exit; }
    $next = db()->one('SELECT * FROM projects WHERE active=1 AND (sort>? OR (sort=? AND id>?)) ORDER BY sort, id LIMIT 1', [$project['sort'], $project['sort'], $project['id']])
         ?? db()->one('SELECT * FROM projects WHERE active=1 ORDER BY sort, id LIMIT 1');
    $page['title'] = L($project, 'title') . ' — ' . s('brand', 'JamSoft');
    $page['desc'] = L($project, 'summary');
    render('site', 'site/project', compact('project', 'next', 'page'));
    exit;
}
// Blog
if ($path === 'blog') {
    $posts = db()->all('SELECT * FROM posts WHERE published=1 ORDER BY created_at DESC, id DESC');
    $page['title'] = t('nav.blog') . ' — ' . s('brand', 'JamSoft');
    render('site', 'site/blog', compact('posts', 'page'));
    exit;
}
if (($seg[0] ?? '') === 'blog' && isset($seg[1])) {
    $post = db()->one('SELECT * FROM posts WHERE slug=? AND published=1', [$seg[1]]);
    if (!$post) { http_response_code(404); render('site', 'site/404', ['page' => $page]); exit; }
    $page['title'] = L($post, 'title') . ' — ' . s('brand', 'JamSoft');
    $page['desc'] = L($post, 'excerpt');
    render('site', 'site/post', compact('post', 'page'));
    exit;
}

http_response_code(404);
$page['title'] = t('404.title');
render('site', 'site/404', ['page' => $page]);
