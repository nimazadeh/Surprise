# SHIRIN — Project Status

Last updated: 2026-09-08 (Phase 2 implemented, PR pending).

## Completed

- [x] Phase 0: architecture blueprint (`docs/ARCHITECTURE.md` + 8 files)
- [x] Phase 0.5: review + readiness (`docs/*_REVIEW.md`, `DATABASE_CORRECTIONS.md`, `FEATURE_PRIORITY_MATRIX.md`, `PHASE_1_READINESS_REPORT.md`)
- [x] Phase 1: Laravel 12 foundation in `backend/` (auth, RBAC, admin shell, settings/flags, API v1 contract, i18n, storage, CI) — merged to `main` (`8c03f13`, PR #1)
- [x] Pre-Phase 2: global workflow (`docs/DEVELOPMENT_WORKFLOW.md`, binding from Phase 2)
- [x] Phase 2: Music Core Domain Foundation (catalogue DB + models, admin CMS, API v1 reads, SEO pages, cover-art service, 43 tests) — PR pending

## Current architecture

- Static Vanilla frontend at repo root (GitHub Pages) — untouched.
- Laravel 12 backend in `backend/` — session auth + Sanctum-ready API v1.
- Roles: owner/admin/editor/premium_user/user (Spatie). Owner bypass via `Gate::before`.
- Flags: `features.{download_center,music_lab,music_lab_premium_only,ads,user_uploads}`.
- Music domain: Genre/Artist/Album/Track models (slug route keys, status
  draft/published/archived, `source`/`provider_id` dual-source ready);
  admin CMS behind `music.manage`; API v1 reads shaped for the player;
  SEO detail pages `/artists|/albums|/tracks/{slug}` with 301s + JSON-LD;
  covers via `CoverArtService` + `/media/covers/*` proxy.

## Database (after `migrate --seed`)

22 tables: Phase 1 set (17) + `genres`, `artists`, `albums`, `tracks`,
`slug_redirects`. Seeders: roles+permissions, settings, genres (8 base rows).

## Packages

`laravel/framework ^12`, `laravel/sanctum ^4`, `laravel/tinker`, `spatie/laravel-permission ^6`
+ require-dev phpunit/pint/faker/pail/sail/collision/mockery. Phase 2 added NONE.
`vendor/` intentionally NOT committed (`.gitignore`); `composer.lock` still
pending owner generation on localhost (PKG-01).

## Tests

79 tests authored (36 Phase 1 + 43 Phase 2: 15 model, 14 admin, 8 API, 6 web).
Sandbox cannot execute (no PHP/packagist egress); static verification passed
(58/58 route targets, 30/30 views, 45/45 route names, lang parity, zero
ID-checks). CI runs them on push/PR. Merge only when green.

## Next phase

Phase 2.5 — Catalogue delivery: provider adapter, player-on-API, search API,
stream signing, nested endpoints, catalogue index pages, hash-link redirects,
PWA re-register. Then Phase 3 — User libraries.

## Warnings

- R-01 (music licensing) must be resolved before ANY owned audio publishes.
  Phase 2 stores metadata + covers only; no audio exists yet.
- Session is locked to branch `arena/01a08237-surprise`; it stands in for
  `phase-2-music-core` (see SESSION_HANDOFF.md).
- Slugs are Latin-only (`alpha_dash`), immutable after publish, never reused;
  renames 301 via `slug_redirects`.
