# SHIRIN — Phase 1 Implementation Report

Date: 2026-09-08 · Branch: `arena/01a0812b-surprise` (session-locked; stands in for `phase-1-foundation`)

## What was built

Laravel 12 foundation in `backend/`, additive only — zero changes to the static
frontend at repo root.

| Area | Delivered |
|---|---|
| Project | Laravel 12 skeleton, `shirin/backend`, no build step (Vite removed) |
| Config | `.env.example` (fa default, file cache, DB queue/session), `config/shirin.php`, `config/shirin_nav.php`, `media` + `quarantine` disks |
| Database | 2 new migrations (user profile fields, settings); package tables via auto-migrations (permissions, Sanctum, jobs, cache) |
| Auth | Register/login/logout, password reset, email verification; throttle `5,1`; banned-user block; `last_login_at` |
| RBAC | Spatie Permission; roles `owner/admin/editor/premium_user/user`; 11 permissions; `Gate::before` owner bypass (role-based, zero ID checks) |
| Admin | `/admin` + `admin` middleware; config-driven nav with Phase badges; dashboard stats + live flags |
| Settings/flags | `settings` table + cached `SettingsService` + `FeatureFlagService` (download_center modes, music_lab, ads, uploads) |
| Storage | `storage/app/{media/…,uploads/{quarantine,tmp/chunks},lab}` + disks; no uploader yet (by design) |
| Security | Global security-headers middleware (CSP/HSTS/nosniff/…), session hardening, friendly error pages (403/404/419/500) |
| API v1 | `/api/v1/{ping,meta/flags,auth/me}` with `{success,data,message}` envelope; Sanctum-ready |
| i18n/SEO | `fa` (RTL) + `en` (LTR) lang files, locale middleware + switcher, legal pages (terms/privacy/takedown), per-page titles/descriptions |
| Console | `shirin:install` (interactive OWNER, no default passwords); scheduler drains DB queue every minute |
| CI | `docs/ci/backend-tests.yml` → owner moves to `.github/workflows/` (PHP 8.3, Pint, hardcoded-ID grep gate, PHPUnit) |

## Files changed

- Added: `backend/` (~90 files), `.github/workflows/backend-tests.yml`, `docs/PHASE_1_*`
- Modified: none outside `backend/` and `docs/` (frontend untouched)
- Removed from skeleton: Vite/npm scaffold, welcome view, example tests, skeleton README/CHANGELOG

## Migrations created

1. `2026_09_08_000001_add_profile_fields_to_users_table` (username/avatar/locale/status/last_login_at)
2. `2026_09_08_000002_create_settings_table`
3. Package-provided (auto-run): Spatie permission tables, Sanctum tokens, jobs, cache (+ skeleton users/sessions)

## Packages installed

`laravel/framework ^12`, `laravel/sanctum ^4`, `laravel/tinker`, `spatie/laravel-permission ^6`
(+ skeleton require-dev: phpunit, pint, faker, pail, sail, collision, mockery).

## Decisions

1. Backend in `backend/` subdirectory (preserves GitHub Pages root deploy).
2. Spatie Permission over hand-rolled RBAC (per Phase 0.5 review).
3. No repository/DTO layers; services + FormRequests + Resources only.
4. Sanctum included now for the API contract; tokens issued from Phase 2.
5. Owner protection columns (`is_protected`) deferred to Phase 4 user management.

## Known limitations (intentional)

- No music catalogue, uploader, player migration, ads UI, downloads, billing — later phases.
- `is_protected` owner hardening + 2FA arrive in Phase 4.
- PHPUnit suite authored (36 tests) but executed only in CI/localhost — the build
  sandbox has no PHP runtime and no packagist egress (see PHASE_1_TEST_REPORT.md).

## Recommendation for Phase 2

Catalogue + player-on-API: artists/albums/tracks migrations (dual-source), seeders,
`/api/v1` read endpoints mirroring the current JS record shape, SEO Blade pages,
and the Vanilla player pointed at the API with hash-link redirects.
