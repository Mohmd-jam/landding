<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);

$config = require APP . '/config.php';
date_default_timezone_set($config['app']['timezone']);
mb_internal_encoding('UTF-8');

if ($config['app']['debug']) { ini_set('display_errors', '1'); error_reporting(E_ALL); }
else { ini_set('display_errors', '0'); }

// Sessions
$sessDir = ROOT . '/storage/sessions';
if (is_dir($sessDir) && is_writable($sessDir)) session_save_path($sessDir);
session_name('jamsoft');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
session_start();

require APP . '/Database.php';
require APP . '/Installer.php';

// ---------- Helpers ----------
function cfg(string $path, $default = null) {
    global $config; $v = $config;
    foreach (explode('.', $path) as $k) { if (!is_array($v) || !array_key_exists($k, $v)) return $default; $v = $v[$k]; }
    return $v;
}
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function nl2p(?string $s): string {
    $parts = preg_split('/\R{2,}/u', trim((string)$s));
    return implode('', array_map(fn($p) => '<p>' . nl2br(e($p)) . '</p>', array_filter($parts, fn($p) => $p !== '')));
}
function base_url(): string {
    $u = cfg('app.url');
    if ($u) return rtrim($u, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
function url(string $path = ''): string { return '/' . ltrim($path, '/'); }
function redirect(string $to): never { header('Location: ' . $to); exit; }
function flash(string $key, ?string $msg = null) {
    if ($msg !== null) { $_SESSION['_flash'][$key] = $msg; return null; }
    $m = $_SESSION['_flash'][$key] ?? null; unset($_SESSION['_flash'][$key]); return $m;
}
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(20));
    return $_SESSION['_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_token" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if (!hash_equals(csrf_token(), $_POST['_token'] ?? '')) { http_response_code(419); exit('CSRF token mismatch'); }
}
function post(string $k, $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function slugify(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $s);
    return trim($s, '-') ?: bin2hex(random_bytes(4));
}
function view(string $name, array $data = []): string {
    extract($data, EXTR_SKIP);
    ob_start(); require APP . '/views/' . $name . '.php'; return ob_get_clean();
}
function render(string $layout, string $view, array $data = []): void {
    $data['content'] = view($view, $data);
    echo view('layouts/' . $layout, $data);
}
function db(): Database { static $db = null; return $db ??= new Database(cfg('db')); }

// ---------- i18n ----------
$langs = cfg('app.langs');
if (isset($_GET['lang']) && in_array($_GET['lang'], $langs, true)) {
    $_SESSION['lang'] = $_GET['lang'];
    setcookie('lang', $_GET['lang'], time() + 86400 * 365, '/');
    $back = strtok($_SERVER['REQUEST_URI'], '?');
    redirect($back ?: '/');
}
$lang = $_SESSION['lang'] ?? $_COOKIE['lang'] ?? cfg('app.default_lang');
if (!in_array($lang, $langs, true)) $lang = cfg('app.default_lang');
define('LANG', $lang);
define('RTL', LANG === 'fa');
define('DIR', RTL ? 'rtl' : 'ltr');
$GLOBALS['_t'] = require APP . '/lang/' . LANG . '.php';
function t(string $key, array $vars = []): string {
    $s = $GLOBALS['_t'][$key] ?? $key;
    foreach ($vars as $k => $v) $s = str_replace(':' . $k, (string)$v, $s);
    return $s;
}
/** Get localized column from a row: L($row,'title') -> title_fa / title_en */
function L(array $row, string $col): string {
    $v = $row[$col . '_' . LANG] ?? '';
    if ($v === '' || $v === null) $v = $row[$col . '_' . (LANG === 'fa' ? 'en' : 'fa')] ?? '';
    return (string)$v;
}
/** Persian digits when fa */
function num($n): string { $s = (string)$n; return RTL ? strtr($s, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']) : $s; }
function fdate(?string $dt): string {
    if (!$dt) return '';
    $ts = strtotime($dt);
    if (RTL) return num(jdate($ts));
    return date('M j, Y', $ts);
}
/** Minimal Gregorian -> Jalali (Y/m/d) */
function jdate(int $ts): string {
    [$gy, $gm, $gd] = explode('-', date('Y-n-j', $ts));
    $gy = (int)$gy; $gm = (int)$gm; $gd = (int)$gd;
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jy = ($gy <= 1600) ? 0 : 979; $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * intdiv($days, 12053); $days %= 12053;
    $jy += 4 * intdiv($days, 1461); $days %= 1461;
    if ($days > 365) { $jy += intdiv($days - 1, 365); $days = ($days - 1) % 365; }
    $jm = ($days < 186) ? 1 + intdiv($days, 31) : 7 + intdiv($days - 186, 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return sprintf('%d/%02d/%02d', $jy, $jm, $jd);
}

// ---------- Settings ----------
function settings(): array {
    static $s = null;
    if ($s !== null) return $s;
    $s = [];
    try {
        foreach (db()->all('SELECT `key`,`lang`,`value` FROM settings') as $r) {
            if ($r['lang'] === '' || $r['lang'] === LANG) $s[$r['key']] = $r['value'];
        }
    } catch (Throwable $e) {}
    return $s;
}
function s(string $key, string $default = ''): string { return settings()[$key] ?? $default; }

// ---------- Theme ----------
if (isset($_GET['theme']) && in_array($_GET['theme'], ['light', 'dark'], true)) {
    setcookie('theme', $_GET['theme'], time() + 86400 * 365, '/');
    redirect(strtok($_SERVER['REQUEST_URI'], '?') ?: '/');
}
define('THEME', in_array($_COOKIE['theme'] ?? '', ['light', 'dark'], true) ? $_COOKIE['theme'] : 'dark');

// ---------- Auth ----------
function auth(): ?array { return $_SESSION['admin'] ?? null; }
function require_auth(): void { if (!auth()) redirect(url('admin/login')); }

// ---------- Uploads ----------
function handle_upload(string $field, ?string $current = null): ?string {
    if (empty($_FILES[$field]['name'])) return $current;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) return $current;
    if ($f['size'] > cfg('upload.max_mb') * 1024 * 1024) return $current;
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, cfg('upload.allowed'), true)) return $current;
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dir = cfg('upload.dir');
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    if (move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        if ($current && is_file($dir . '/' . basename($current))) @unlink($dir . '/' . basename($current));
        return $name;
    }
    return $current;
}
function upload_url(?string $file): string { return $file ? cfg('upload.url') . '/' . rawurlencode($file) : ''; }
