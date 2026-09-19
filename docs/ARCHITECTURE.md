# System Architecture — Full-Stack Developer Portfolio & CMS

> **Project**: personal brand website + content management system for a professional full-stack web developer.
> **Stack**: React 18 (frontend) · PHP 8.3 (backend/API) · MySQL 8 (database, SQLite supported for local dev) · Vite (build).

---

## 1. Goals & constraints

| Goal | Architectural answer |
| --- | --- |
| Visitors understand who/what/how-to-contact in seconds | Server-injected SEO shell + React SPA, content served from DB |
| Nothing hard-coded in the UI | Every public string/entity lives in MySQL and is exposed through `/api/v1/*` |
| Administrator never edits code | Data-driven admin: REST CRUD + schema-driven forms + singleton editors |
| Two languages, RTL + LTR, clean URLs | Path prefix locale routing (`/fa/...`, `/en/...`) over a *translation-table* schema |
| Search-engine visibility | PHP renders route-specific `<head>` (title, description, canonical, hreflang, OG, Twitter) + JSON-LD + semantic pre-render block |
| Production-grade security | Prepared statements, output escaping, CSRF, hashed passwords, rate limiting, upload validation |
| Scales to more languages | `languages` table drives routing, admin editors, sitemap and hreflang automatically |

---

## 2. High-level architecture

```
                         ┌────────────────────────────────────────────┐
   Browser  ──HTTP──────▶ │  WEB ROOT  /public                         │
                         │  ├── index.php      → front controller     │
                         │  ├── app/           → built React bundles  │
                         │  └── uploads/       → media library files  │
                         └───────────┬────────────────────────────────┘
                                     │
                    ┌────────────────▼─────────────────┐
                    │  ROUTER (backend/app/Core/Router)│
                    └───┬───────────────┬──────────────┘
      /api/v1/*  ───────┘               └─────── /fa/*, /en/*, /admin, /sitemap.xml …
             │                                              │
   ┌─────────▼──────────┐                        ┌──────────▼────────────┐
   │  API LAYER         │                        │  SHELL / SEO RENDERER │
   │  Controllers\Api   │                        │  Services\SeoRenderer │
   │  Middleware:       │                        │  Services\SsrRenderer │
   │   Json, Throttle,  │                        │  + React SPA hydration│
   │   Cors             │                        └──────────┬────────────┘
   └─────────┬──────────┘                                   │
             │                                              │
   ┌─────────▼──────────┐                        ┌──────────▼────────────┐
   │  ADMIN API LAYER   │                        │  PUBLIC READ SERVICES │
   │  Controllers\Admin │                        │  Services\Content*    │
   │  Middleware:       │                        └──────────┬────────────┘
   │   AdminAuth, Csrf, │                                   │
   │   Throttle, Role   │                                   │
   └─────────┬──────────┘                                   │
             └───────────────┬──────────────────────────────┘
                             │
                  ┌──────────▼───────────┐
                  │  REPOSITORY / MODEL  │  → Query Builder → PDO (prepared stmts)
                  └──────────┬───────────┘
                             │
             ┌───────────────▼────────────────┐
             │  MySQL 8 (SQLite for local/dev)│
             │  30 relational tables          │
             └────────────────────────────────┘
```

### Layers

| Layer | Location | Responsibility | Must NOT |
| --- | --- | --- | --- |
| Core | `backend/app/Core` | Router, Request/Response, DB connection + query builder, Model, Validator, Session, Auth, Security, RateLimit, Media, Logger, Migration, JSON | contain business rules |
| Models | `backend/app/Models` | table mapping, relations, casts, scopes | contain HTTP or SQL strings inline |
| Repositories | `backend/app/Repositories` | reusable, translation-aware queries | render or format HTTP output |
| Services | `backend/app/Services` | business rules: slug handling, publishing, dashboard stats, SEO/SSR rendering, settings resolution | know about React or HTML layout |
| HTTP / API | `backend/app/Http/Controllers/Api` | request → validation → service → JSON resource | touch PDO directly |
| HTTP / Admin | `backend/app/Http/Controllers/Admin` | authentication-aware CRUD + media + dashboard | duplicate public logic |
| Frontend public | `frontend/src/public` | presentational components + data hooks | hard-code content |
| Frontend admin | `frontend/src/admin` | dashboard UI rendered from backend schema | hard-code form fields per entity |

Business rules live in **one** place; the public site and the admin panel consume the same services.

---

## 3. Directory structure

```
landding/
├── backend/
│   ├── app/
│   │   ├── Core/                 Config, Container, Router, Request, Response, Kernel,
│   │   │                         Database (Connection, QueryBuilder), Model, Validator,
│   │   │                         Session, Auth, Security, RateLimiter, MediaLibrary,
│   │   │                         Migrator, Schema, Str/Arr/Json helpers, View
│   │   ├── Http/
│   │   │   ├── Controllers/Api/  public REST controllers (v1)
│   │   │   ├── Controllers/Admin/ admin REST controllers (auth + CSRF)
│   │   │   ├── Middleware/       AdminAuth, Csrf, Throttle, Json, Role
│   │   │   └── Resources/        JSON transformers (entity → API shape)
│   │   ├── Models/               Admin, Project, Post, Media, Setting, Language, …
│   │   ├── Repositories/         ProjectRepository, PostRepository, …
│   │   └── Services/             ContentService, SettingsService, SeoService,
│   │                             SsrRenderer, DashboardService, MediaService,
│   │                             SlugService, TranslationService, NavigationService
│   ├── bootstrap/app.php         autoloader, config, error/exception handling, container
│   ├── config/                   app.php, database.php, uploads.php, security.php, mail.php
│   ├── database/
│   │   ├── schema.php            single portable schema definition (dialect-agnostic DSL)
│   │   ├── Migrator.php          emits MySQL or SQLite DDL from schema.php
│   │   ├── install.php           create schema + seed
│   │   └── seeds/                seed data (fa + en demo content)
│   ├── resources/views/          error pages, mail templates, admin shell
│   ├── routes/                   api.php, admin.php, web.php
│   └── storage/                  logs, cache, sessions, backups, sqlite database
├── frontend/
│   ├── index.html                public SPA entry
│   ├── admin.html                admin SPA entry (separate bundle)
│   ├── static/fonts/             self-hosted Inter, Vazirmatn, JetBrains Mono
│   └── src/
│       ├── public/               site: layout, sections, pages, ui primitives
│       ├── admin/                dashboard: shell, tables, forms, editors
│       └── shared/               api client, i18n provider, hooks, design tokens
├── public/                       web root (docroot)
│   ├── index.php                 front controller
│   ├── .htaccess                 Apache rewrite + upload hardening + caching
│   ├── app/                      built React bundles (Vite output)
│   └── uploads/                  media library (PHP execution disabled)
├── tools/
│   ├── server.mjs                PHP runtime HTTP server (dev/preview; see §9)
│   └── cli.mjs                   CLI bridge: migrate, seed, lint, user:create
├── docs/                         architecture, database, API, install, security, admin
└── scripts/                      build helpers
```

---

## 4. Request lifecycles

### 4.1 Public page (`GET /en/projects/business-erp`)

1. `.htaccess` (or the Node runtime host) routes the path to `public/index.php`.
2. `bootstrap/app.php` loads config, autoloader, error handlers.
3. `Router` matches `web` routes → `PageController@render` with `{locale, path}`.
4. `LanguageService` resolves `locale` (`fa` default), validates it against the `languages` table.
5. `SeoService` resolves metadata for the route from `seo_metadata` (+ entity fallbacks, OG image, canonical, alternates for every active language).
6. `SsrRenderer` renders a semantic, crawlable content block (titles, text, lists, images, breadcrumbs) for the requested entity — real DB data, escaped.
7. `View` renders `public/app/index.html`, injecting: `<html lang/dir>`, `<head>` metadata, JSON-LD graph, `window.__BOOTSTRAP__` (locale, languages, settings, route hints) and the pre-rendered block inside `#root`.
8. React boots, reads `__BOOTSTRAP__`, fetches `/api/v1/*` for interactive data, replaces the pre-render block.
9. If JS is disabled/unavailable the pre-rendered semantic HTML remains readable and indexable.

### 4.2 Public API (`GET /api/v1/projects?lang=en&category=ecommerce`)

`Router` → `Middleware\Json` + `Throttle(public)` → `Api\ProjectController@index` → `ProjectService::paginate()` → `ProjectRepository` (joins `projects ⋈ project_translations`, filters `is_active=1`, `lang=$lang`, ordered by `sort_order`) → `ProjectResource::collection()` → JSON envelope:

```json
{ "data": [ … ], "meta": { "page": 1, "per_page": 9, "total": 24, "total_pages": 3 }, "locale": "en" }
```

### 4.3 Admin write (`PUT /api/admin/projects/12`)

1. `AdminAuth` middleware: DB-backed session (`admin_sessions`) + idle timeout + IP/UA binding → `Admin` model.
2. `Csrf` middleware: `X-CSRF-Token` compared (timing-safe) with the session token.
3. `Role` middleware: `role.can(resource, action)`.
4. `ResourceController@update` → `ResourceRegistry` definition for `projects` → `Validator` (server-side rules per field group, incl. per-language required rules) → `Transaction`:
   validate & escape → update base row → upsert `*_translations` rows → sync pivot (`project_technologies`) → sync media (`project_images`) → write `seo_metadata`.
5. Response: the refreshed entity (so the admin table updates without a second fetch). Public pages immediately reflect the change.
6. `AuditLogger` records the change (admin, entity, action, IP, diff summary).

---

## 5. Internationalisation (multilingual) architecture

**Never duplicate entities per language.** Translation-table pattern:

```
projects                     project_translations
──────────────              ────────────────────────
id            PK             id            PK
category_id   FK             project_id    FK → projects.id  (ON DELETE CASCADE)
cover_media_id FK            lang          VARCHAR(8)  ─┐ UNIQUE(project_id, lang)
tech_stack    JSON           title         VARCHAR(200) │
project_url   VARCHAR(255)   slug          VARCHAR(200) │ indexed
is_featured   TINYINT(1)     summary       VARCHAR(500) │
sort_order    INT            description   MEDIUMTEXT   │
is_active     TINYINT(1)     seo_title …                │
created_at …                 UNIQUE(project_id, lang) ──┘
```

Rules

* **Shared/structural data** (ids, dates, order, status, media, URLs, ratings) → base table.
* **Human-readable data** (titles, slugs, descriptions, labels, SEO copy) → `*_translations`.
* Every translation table has `UNIQUE(entity_id, lang)` (kills duplicate content) and index `(lang, slug)` for slug lookups.
* Resolution strategy: `COALESCE(t.value, fallback.value)` — if a translation is missing the default locale is served, so a half-translated site never shows empty content.
* Persisted UI strings (`translations` + `translation_values`) cover static labels ("Read more", "All projects", …) and are editable in **Admin → Language Management**. The React bundle ships built-in defaults and merges DB overrides at runtime.
* Adding a language = insert a row in `languages` (+ optional translations). Routing, hreflang, sitemap and admin editors pick it up automatically.

Locale routing: `/` → default locale redirect (`/fa`), `/en/...`, `/fa/...`. The React router uses a `/:lang` prefix segment; `LanguageSwitcher` swaps the prefix and keeps the remaining path (e.g. `/en/projects/erp` → `/fa/projects/erp`) — but slugs are per-language, so the switcher asks the API for the translated slug (`GET /api/v1/translate-slug?type=project&slug=…&to=fa`) and falls back to the section root when unavailable.

Direction: `dir="rtl"` for `fa`, `ltr` for `en`, driven by the `languages.direction` column. CSS is written logically (`margin-inline`, `padding-inline`, `inset-inline`) so both directions share one stylesheet.

---

## 6. Admin panel architecture

### 6.1 Data-driven CRUD ("no code to change content")

`AdminResourceRegistry` (PHP) declares every admin resource:

```php
'projects' => [
    'model'        => Project::class,
    'label'        => ['fa' => 'پروژه‌ها', 'en' => 'Projects'],
    'translatable' => true,
    'soft_status'  => 'is_active',
    'orderable'    => true,
    'search'       => ['project_translations.title', 'project_translations.summary'],
    'filters'      => ['project_categories' => 'category_id', 'featured' => 'is_featured'],
    'columns'      => [ ['key' => 'title', 'type' => 'text'], … ],
    'fields'       => [
        ['key' => 'title',   'type' => 'text',     'required' => true, 'translatable' => true],
        ['key' => 'summary', 'type' => 'textarea', 'translatable' => true],
        ['key' => 'category_id', 'type' => 'select', 'source' => 'project_categories'],
        ['key' => 'gallery', 'type' => 'media-multiple'],
        …
    ],
],
```

`GET /api/admin/schema` exposes this to React; `frontend/src/admin` renders lists, filters, pagination and forms **from the schema** (text, textarea, rich-text, markdown, select, multi-select, media, media-multiple, number, toggle, date, datetime, repeatable, tags, color, slug). Adding a field to the registry instantly yields a working form field, validation rule and API key — no bespoke UI code.

Beside generic resources, purpose-built editors exist for: **Dashboard**, **Homepage (section builder)**, **About**, **Website settings**, **SEO**, **Navigation**, **Footer**, **Media library**, **Messages**, **Resume**, **Languages/UI strings**, **Admin users**, **Security**.

### 6.2 Admin information architecture

```
/admin                      login (unauthenticated)
/admin/dashboard            stats, charts, recent messages, quick actions
/admin/homepage             page + section builder (enable/disable, reorder, edit, CTA)
/admin/about                profile, photo, philosophy, strengths, stats
/admin/skills               skills (level, category, order, status)
/admin/technologies         technologies (icon, category, featured-on-hero badge)
/admin/services             services CRUD (+ features list per language)
/admin/projects             projects CRUD (gallery, tech, links, featured, SEO)
/admin/project-categories   categories CRUD
/admin/experience           employment timeline CRUD
/admin/education            education CRUD
/admin/certifications       certifications CRUD
/admin/testimonials         testimonials CRUD (rating, avatar, language, status)
/admin/blog                 posts CRUD (markdown, cover, tags, schedule, SEO, publish)
/admin/blog-categories      blog categories CRUD
/admin/messages             inbox (read/unread, star, delete, export)
/admin/resume               résumé files per language + download counters
/admin/navigation           header/footer menus (nested, translatable labels)
/admin/footer               footer columns, copyright, social
/admin/media                media library (upload, search, alt text, delete, usage)
/admin/seo                  per-page SEO, sitemap/robots preview, structured data
/admin/languages            languages + UI string translations
/admin/settings             identity, contact, socials, analytics, appearance, integrations
/admin/users                admin accounts + roles
/admin/security             active sessions, login attempts, password, 2FA-ready
```

### 6.3 Authentication & session security

* `password_hash(PASSWORD_DEFAULT)` / `password_verify()` + `password_needs_rehash()` upgrade-on-login.
* Sessions stored **in the database** (`admin_sessions`: id = random 64-char id, hashed in cookie; `admin_id`, `ip`, `user_agent`, `payload`, `last_activity`), cookie `HttpOnly; SameSite=Lax; Secure` (when HTTPS), regenerated on login, destroyed on logout.
* Idle timeout + absolute lifetime, per-session revocation from **Security**, IP/UA fingerprint check with re-authentication on mismatch.
* CSRF: per-session token, required on every non-GET admin request via `X-CSRF-Token`; public contact form uses a signed token + honeypot + throttle.
* Brute force: `login_attempts` table, exponential back-off per (email, IP) pair, hard lock + audit entry; generic error messages (no user enumeration).
* Password reset: single-use hashed tokens with expiry (`password_resets`); no plaintext token storage.
* Roles: `super_admin` (all), `admin` (content), `editor` (own content, no settings/users/security).

---

## 7. Security model

| Threat | Mitigation |
| --- | --- |
| SQL injection | 100 % prepared statements with bound parameters; identifiers from an allow-list; no string-built SQL with user input |
| XSS | Output escaping by default (`e()`), React escapes by default, Markdown rendered through a hardened renderer (HTML sanitised, no inline event handlers), CSP header |
| CSRF | Token per session, timing-safe compare, `SameSite=Lax` cookies, double-submit for public forms |
| Session hijacking | DB sessions, `HttpOnly`+`Secure`+`SameSite`, id regeneration, IP/UA binding, idle expiry, manual revocation |
| Brute force | Throttle middleware + `login_attempts` + lockout windows |
| Unsafe uploads | Extension **and** MIME sniff (`finfo`) **and** size **and** image dimension check, random filenames, `uploads/.htaccess` disables PHP execution, storage outside the app code, no user-controlled path |
| IDOR | Every admin route re-checks ownership/permission; public detail routes only expose `is_active`/published rows |
| Malicious input | Centralised `Validator`, type casting, JSON schema per resource, request size limits, UTF-8 normalisation |
| Unsafe redirects / SSRF | URL allow-list for outbound links, canonical URLs sanitised |
| Header hardening | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS (HTTPS) |

---

## 8. Database architecture (summary — see DATABASE.md)

30 tables in six groups:

1. **Identity & access**: `admins`, `admin_sessions`, `login_attempts`, `password_resets`
2. **Configuration**: `settings`, `setting_translations`, `languages`, `translations`, `translation_values`
3. **Structure**: `pages`, `page_translations`, `sections`, `section_translations`, `navigation`, `navigation_translations`, `social_links`
4. **Content**: `skills`, `skill_translations`, `skill_categories`, `services`, `service_translations`, `projects`, `project_translations`, `project_categories`, `project_category_translations`, `project_images`, `project_technologies`, `experiences`, `experience_translations`, `education`, `education_translations`, `certifications`, `certification_translations`, `testimonials`, `testimonial_translations`
5. **Editorial**: `blog_posts`, `blog_post_translations`, `blog_categories`, `blog_category_translations`, `blog_tags`, `blog_post_tags`
6. **Ops**: `media`, `media_translations`, `messages`, `resumes`, `seo_metadata`, `analytics_visits`

Conventions: `id BIGINT UNSIGNED AUTO_INCREMENT PK`; `created_at`/`updated_at` timestamps; status flags (`is_active`, `is_featured`, `status`); `sort_order INT` for manual ordering; FKs with `ON DELETE CASCADE` (children/translations) or `ON DELETE SET NULL` (optional media); unique slugs per language; indexes on every FK + filter/sort column.

Portability: the schema is expressed once in `database/schema.php` (dialect-agnostic DSL) and emitted as **MySQL** DDL by default, or **SQLite** DDL when `DB_DRIVER=sqlite` (used for the sandbox preview). Business code uses the same query builder and prepared statements for both.

---

## 9. Runtime & deployment

**Production (documented target)**: Apache/Nginx docroot → `public/`, PHP-FPM 8.3, MySQL 8, `public/app/*` pre-built with `npm run build`, uploads writable, `.env` per environment. Nginx sample + Apache `.htaccess` included.

**This sandbox / live preview**: the container has no native PHP or MySQL binaries, so `tools/server.mjs` runs a **PHP 8.3 WebAssembly runtime** (`@php-wasm/node`) inside Node and exposes the exact same application over HTTP: static files are streamed from disk, PHP requests go through the front controller with full superglobals/cookies/uploads support. The database defaults to SQLite (`DB_DRIVER=sqlite`) via PDO; switching `DB_DRIVER=mysql` + credentials moves to a real MySQL server without any code change. This keeps the deliverable runnable end-to-end while the codebase stays production-targeted.

Caching strategy: HTTP cache headers for hashed assets (immutable, 1 year), no-cache for HTML, ETag for API GETs, in-process memoisation of settings/navigation per request, indexed queries with pagination everywhere.

---

## 10. Performance budget

| Item | Target | How |
| --- | --- | --- |
| First contentful paint (4G mobile) | < 1.8 s | pre-rendered HTML shell, self-hosted variable fonts with `font-display: swap`, critical CSS inlined, no blocking JS in `<head>` |
| JS (public bundle) | < 220 KB gzip | React + router only; icons are inline SVG; markdown renderer is ~2 KB; no UI framework, no chart library (SVG charts hand-built) |
| Admin bundle | lazy-loaded | separate entry, route-level `React.lazy` |
| Images | lazy + sized | `loading="lazy"`, `decoding="async"`, width/height attributes, `srcset` for uploads, WebP serving when available |
| Queries per public page | ≤ 12 | batched repository queries, per-request memo cache, no N+1 (translations joined, not looped) |
| API latency | < 40 ms warm | prepared statements, indexes, small payloads, gzip |

---

## 11. Frontend architecture

* **One design system**: `frontend/src/shared/styles/tokens.css` (colour, spacing, radius, typography, motion, elevation) + `base.css` + component CSS. No CSS framework; utility *and* component classes coexist.
* **Component layers**: `ui/` primitives (Button, Card, Badge, Field, Modal, Toast, Skeleton, Tabs, MediaPicker) → `sections/` (Hero, About, Skills, Services, Projects, Experience, Testimonials, BlogTeaser, Contact, CTA) → `pages/` (route-level).
* **Data layer**: `shared/lib/api.js` (fetch wrapper, locale-aware, error normalisation) + hooks (`useApi`, `useLocale`, `useSettings`, `useSeo`) with a tiny in-memory cache; every section is data-driven and renders skeletons while loading.
* **Motion**: CSS transitions/keyframes + one `useReveal` IntersectionObserver hook; `prefers-reduced-motion` fully respected.
* **Accessibility**: semantic landmarks, skip link, focus-visible rings, `aria-*` on interactive widgets, keyboard-operable nav/menus/modals, contrast ≥ 4.5:1 in both themes.
* **Admin app**: schema-driven `ResourceTable` + `ResourceForm`, dashboard widgets, media library modal, toast system, optimistic feedback, mobile drawer navigation.

---

## 12. Testing & verification strategy

| Check | Command | Covers |
| --- | --- | --- |
| PHP syntax | `npm run lint:php` | every `.php` file |
| Build | `npm run build` | Vite compile of public + admin bundles |
| Schema + seed | `npm run db:install` | migrations, seed data, FK integrity |
| API smoke tests | `npm run test:api` | public endpoints, auth flow, CRUD round-trip, validation errors, 401/403/419 paths |
| SSR render check | `npm run test:render` | injected metadata, hreflang, JSON-LD, semantic block, RTL/LTR shells |
| Frontend render check | `npm run test:components` | `react-dom/server` render of every page component with mocked API data |
