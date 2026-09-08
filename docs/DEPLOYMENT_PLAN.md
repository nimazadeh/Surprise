# SHIRIN — Deployment Plan (Phase 0)

> Design only. Environments: **localhost → shared PHP hosting → VPS**.
> Every step must be executable by a solo developer with FTP/SSH-panel access.

## 1. Local development

**Option A — XAMPP (simplest, matches target):** PHP 8.2+, MySQL/MariaDB, Apache.
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve   # or Apache vhost → public/
```

**Option B — Laravel Herd / Valet / Sail:** allowed; must keep `composer.json`
PHP requirement (`^8.2`) and MySQL compatibility (no Postgres-only SQL).

- Mail in dev: `MAIL_MAILER=log`. Queue in dev: `QUEUE_CONNECTION=database`.
- `.env.example` documents every variable (app, db, media disk, mail, flags, Deezer adapter key if any).

## 2. Shared hosting production (first target)

**Requirements checklist for the host:** PHP 8.2+, MySQL/MariaDB, `mbstring/xml/curl/zip/gd/fileinfo`,
cron jobs, `.htaccess` support, free SSL (Let's Encrypt), ≥ 5 GB disk to start.

**Deploy steps:**

1. Point domain to `public/` (or upload Laravel one level above `public_html` and symlink/rewrite).
2. Upload code (zip + extract, or git pull if available). Never upload `node_modules` (none needed).
3. `cp .env.example .env` → set `APP_ENV=production`, `APP_DEBUG=false`, DB creds, `APP_URL=https://…`.
4. `composer install --no-dev --optimize-autoloader` (or upload vendor from local build).
5. `php artisan key:generate && php artisan migrate --force && php artisan storage:link`.
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
7. Verify: `/.env` → 403/404, `/storage/*` blocked except proxy routes, cron installed:
   `* * * * * php /path/artisan schedule:run` (drives DB queue worker via `schedule:work`-style
   command + analytics rollups + tmp purge).
8. Seed roles/permissions + OWNER account via `php artisan shirin:install` (interactive, never default passwords).

**Shared-hosting constraints honored:** no daemons (DB queue + cron), no Redis (file cache),
no FFmpeg (getID3 probing; cutter/converter show honest "VPS required" state),
`upload_max_filesize` respected with friendly errors + chunked fallback design.

## 3. VPS migration path (when revenue justifies it)

Trigger metrics: sustained CPU/queue backlog, storage > 80%, or Music Lab FFmpeg demand.

```text
shared ──► VPS (Ubuntu + PHP-FPM + Nginx + MySQL + Redis + Supervisor + FFmpeg)
  │            │ .env-only switches: CACHE_STORE=redis QUEUE_CONNECTION=redis
  │            │ MEDIA_DISK=s3 (or local + CDN) HORIZON on, Reverb optional
  │            ▼
  └────► Migration runbook: snapshot DB+media → import → dual-run 48h →
       DNS cutover → 7-day rollback window → decommission shared cron
```

- Zero code changes for queue/cache/disk swaps (drivers behind interfaces — verified in Phase 1 review).
- Backups on VPS: nightly DB dump (encrypted, off-site) + weekly media snapshot + tested restore quarterly.

## 4. CI/CD (lightweight)

- `main` is deployable: push → (future GitHub Action: `composer install`, `php artisan test`,
  `php -l`) → manual deploy to shared host in early phases; scripted rsync/Deployer on VPS.
- Release tags `v1.x` + `CHANGELOG.md`; `APP_VERSION` surfaced in admin footer.

## 5. Cache strategy (shared-first)

| Layer | Shared | VPS |
|---|---|---|
| Config/routes/views | `*:cache` artisans | same |
| Public content (artists/albums/tracks) | file cache, tag-flushed on publish | Redis tags |
| API responses (search-then-read heavy) | `Cache::remember` 60–300s | same, Redis |
| Covers/avatars | HTTP `Cache-Control` + ETag proxy | + CDN |
| Counters | queued increments, 5-min flush | same |

## 6. SEO architecture (served by deployment)

- SSR Blade routes `/artists/{slug}`, `/albums/{slug}`, `/tracks/{slug}`, `/playlists/{slug}`,
  `/genres/{slug}` with per-page `<title>`, meta description, canonical, OG/Twitter, JSON-LD
  (`MusicGroup`, `MusicAlbum`, `MusicRecording`), `sitemap.xml` (cached daily), `robots.txt`.
- Legacy hash links redirect: `#/album/{id}` → `/albums/{slug}` (JS + server id→slug resolver).
- LTR/RTL: `<html lang dir>` per locale; `hreflang` alternates `fa/en`.

## 7. Rollback & health

- Rollback: previous release dir + `migrate:rollback --step` plan per release note; flags allow
  instant module kill-switch without redeploy.
- Health: `/up` (Laravel) + admin dashboard queue/storage checks; uptime monitor from Phase 4.
