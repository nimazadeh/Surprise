# SHIRIN — Phase 1 Setup Guide

> Backend lives in `backend/` (Laravel 12). The static frontend at repo root is
> untouched and still deploys to GitHub Pages independently.

## 1. Requirements

| Need | Version |
|---|---|
| PHP | 8.2+ (8.3 recommended) |
| Extensions | mbstring, xml, curl, zip, gd, fileinfo, pdo_mysql (prod) / pdo_sqlite (tests) |
| Database | MySQL 8 / MariaDB 10.6+ (or SQLite for a quick look) |
| Composer | 2.x |
| Cron (production) | 1 job: `schedule:run` every minute |

No Node.js, no build step, no daemon.

## 2. Local install (XAMPP or plain PHP)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
# create empty database `shirin` (utf8mb4) via phpMyAdmin or:
#   mysql -u root -e "CREATE DATABASE shirin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed
php artisan shirin:install   # interactive: creates the OWNER account
php artisan serve            # http://localhost:8000
```

Open: `/` (home) · `/register` · `/login` · `/admin` (owner/admin only).

## 3. Environment variables (key ones)

| Key | Default | Notes |
|---|---|---|
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `fa` / `en` | Persian RTL default |
| `DB_*` | MySQL `shirin` @ 127.0.0.1 | XAMPP-friendly |
| `CACHE_STORE` | `file` | Shared-host safe |
| `QUEUE_CONNECTION` | `database` | Drained by scheduler, no daemon |
| `SESSION_DRIVER` | `database` | |
| `MAIL_MAILER` | `log` | Set SMTP on production |
| `SANCTUM_STATEFUL_DOMAINS` | localhost entries | Extend with prod domain |
| `UPLOAD_MAX_AUDIO_MB` / `UPLOAD_MAX_IMAGE_MB` | 100 / 5 | Server truth (uploader UI lands later) |

## 4. Shared-hosting deploy (summary)

1. Upload `backend/` above `public_html`; point domain docroot to `backend/public`.
2. `composer install --no-dev --optimize-autoloader` (or upload local `vendor/`).
3. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, DB creds,
   `SESSION_SECURE_COOKIE=true`, and production `SANCTUM_STATEFUL_DOMAINS`.
4. `php artisan key:generate && php artisan migrate --seed --force && php artisan shirin:install`.
5. `php artisan config:cache route:cache view:cache`.
6. Cron: `* * * * * php /path/backend/artisan schedule:run >> /dev/null 2>&1`.
7. Verify: `/up` → 200, `/.env` → 404, `/admin` requires login.

Full runbook: `docs/DEPLOYMENT_PLAN.md`.
