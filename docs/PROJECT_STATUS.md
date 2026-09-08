# SHIRIN — Project Status

Last updated: 2026-09-08 (Phase 2.5 implemented, PR pending).

## Completed

- [x] Phase 0: architecture blueprint (`docs/ARCHITECTURE.md` + 8 files)
- [x] Phase 0.5: review + readiness (`docs/*_REVIEW.md`, `DATABASE_CORRECTIONS.md`, `FEATURE_PRIORITY_MATRIX.md`, `PHASE_1_READINESS_REPORT.md`)
- [x] Phase 1: Laravel 12 foundation in `backend/` (auth, RBAC, admin shell, settings/flags, API v1 contract, i18n, storage, CI template) — merged to `main` (`8c03f13`, PR #1)
- [x] Pre-Phase 2: global workflow (`docs/DEVELOPMENT_WORKFLOW.md`, binding from Phase 2)
- [x] Phase 2: Music Core Domain Foundation (catalogue DB + models, admin CMS, API v1 reads, SEO pages, cover-art service, 43 tests) — merged to `main` (`2551e85`, PR #2)
- [x] Phase 2.5: Catalogue delivery (owner-approved conservative scope: provider adapter, player-on-API, search, nested endpoints, featured + resolve, public index pages, CORS, PWA v8, CI workflow staged for owner activation, 57 tests) — PR pending

## Current architecture

- Static Vanilla frontend at repo root (GitHub Pages) — **first intentional
  frontend changes in Phase 2.5** (9 files: `ShirinApiProvider` on
  `/api/v1` with Deezer JSONP fallback, hash-link redirects, SW v8);
  `apiBaseUrl: ''` default keeps Pages behavior unchanged until the owner
  points it at the backend.
- Laravel 12 backend in `backend/` — session auth + Sanctum-ready API v1.
- Roles: owner/admin/editor/premium_user/user (Spatie). Owner bypass via `Gate::before`.
- Flags: `features.{download_center,music_lab,music_lab_premium_only,ads,user_uploads,catalogue_provider}`.
- Music domain: Genre/Artist/Album/Track (slug route keys, status
  draft/published/archived, dual-source ready); admin CMS behind
  `music.manage`; SEO detail pages + index pages with 301s + JSON-LD;
  covers via `CoverArtService` + `/media/covers/*` proxy.
- Dual-source catalogue (Phase 2.5): `MusicProvider` contract →
  `DeezerMusicProvider` (metadata-only HTTP adapter, cached, timeout-guarded,
  never throws); `CatalogueService` merges owned-first with provider
  enrichment (twin-suppressed, never persisted, flag-killable). Merged
  search + featured + resolve endpoints; nested catalogue endpoints; CORS
  allowlist from `FRONTEND_ORIGINS`. **No owned audio exists (R-01).**

## Database (after `migrate --seed`)

20 data tables (21 with `migrations` bookkeeping) — **unchanged in Phase
2.5 (zero migrations)**. Seeders: roles+permissions, settings (now incl.
`features.catalogue_provider`), genres (8 base rows).

## Packages

`laravel/framework ^12`, `laravel/sanctum ^4`, `laravel/tinker`,
`spatie/laravel-permission ^6`, **`guzzlehttp/guzzle ^7.8` (Phase 2.5,
PKG-03)** + require-dev phpunit/pint/faker/pail/sail/collision/mockery.
`vendor/` intentionally NOT committed; `composer.lock` still pending owner
generation on localhost (PKG-01).

## Tests

**136 authored** (36 P1 + 43 P2 + 57 P2.5: 5 CORS, 10 provider, 6 service,
17 API search/nested/featured+resolve, 9 web catalogue, rest in-suite).
Sandbox cannot execute PHP (same constraint as P1/P2); static verification
passed (route/view/lang/import/ID-check sweeps + node smoke runs).
**CI was activated in Phase 2.5 and runs the suite on push/PR — the
first-ever executions; merge only when green.**

## Next phase

Phase 3 — User libraries: playlists, favorites, reactions, history,
follows; `localStorage→API` first-login merge (slug-as-id client ids are
already stable); profiles + settings; notifications skeleton. Stream
signing remains gated on R-01.

## Warnings

- R-01 (music licensing) unresolved: no owned audio may publish. Phase 2.5
  deliberately ships no audio upload/signing/processing.
- `composer.lock` still pending (owner, localhost — now includes guzzle).
- Production API switch requires: backend deployed + `FRONTEND_ORIGINS`
  (Pages URL) + `apiBaseUrl` in `js/config.js` (see `PHASE_2_5_SETUP.md`).
- Session is locked to branch `arena/01a0826b-surprise`; it stands in for
  `phase-2-5-catalogue-delivery` (see SESSION_HANDOFF.md).
- Slugs are Latin-only, immutable after publish, never reused; renames 301
  via `slug_redirects` (C-01).
