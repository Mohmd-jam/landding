# JamSoft — Personal / Studio Website

Bilingual (فارسی / English) personal website for **JamSoft** with a full admin panel.
Pure **PHP 8 + MySQL**, no framework, no build step.

## Features
- 🌗 Dark / light theme (remembered in cookie, no flash)
- 🌐 FA (RTL) + EN (LTR) — every text is editable per language from the admin
- 🧩 Fully dynamic: services, projects (portfolio), skills, testimonials, blog, contact messages, site texts, SEO, colors
- 🔐 Admin panel `/admin` — login, CRUD, image uploads, settings tabs, message inbox, profile/password
- 🛡 CSRF protection, password hashing, honeypot on contact form, prepared statements
- 🐬 MySQL in production — SQLite fallback for local preview
- 📱 Responsive, custom cursor, magnetic buttons, scroll reveal, counters, portfolio filters

## Structure
```
public/          ← document root (index.php, assets, uploads)
app/
  bootstrap.php  ← helpers, i18n, sessions, auth
  config.php     ← defaults  (override with app/config.local.php — git‑ignored)
  Database.php   ← PDO wrapper (mysql | sqlite)
  Installer.php  ← creates tables + demo content
  controllers/   ← site.php, admin.php, install.php
  views/         ← layouts, site/, admin/
  lang/          ← fa.php, en.php (UI strings)
database/schema.mysql.sql
storage/         ← sessions, sqlite file
```

## Install (shared host / VPS)
1. Upload the project; point the web server document root to `public/`
   (if you can't, the root `.htaccess` forwards requests to `public/`).
2. Create a MySQL database and put credentials in `app/config.local.php`:
   ```php
   <?php
   return [
     'app' => ['url' => 'https://yourdomain.com'],
     'db'  => ['host' => 'localhost', 'name' => 'jamsoft', 'user' => 'db_user', 'pass' => 'secret'],
   ];
   ```
3. Make `public/uploads/` and `storage/` writable.
4. Open `https://yourdomain.com/install` → create the admin user (optionally with demo content).
5. Log in at `/admin` and edit everything.

Alternatively import `database/schema.mysql.sql` manually — the installer will still seed data.

## Local development
```bash
php -S 0.0.0.0:8080 -t public public/router.php
```
For a quick local run without MySQL, set `'db' => ['driver' => 'sqlite']` in `app/config.local.php`.

## Nginx snippet
```nginx
root /var/www/jamsoft/public;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php8.2-fpm.sock; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
```
