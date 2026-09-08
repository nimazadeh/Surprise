# SHIRIN — Session Handoff (after Phase 2)

> Read this + `git log` + `docs/ROADMAP.md` +
> `docs/DEVELOPMENT_WORKFLOW.md` before any new session. No chat
> history is assumed.

## Current project state

Phase 1 + 1.5 merged to `main` (`8c03f13`, PR #1 squash-merged 2026-09-08).
Phase 2 implemented on branch `arena/01a08237-surprise` (stands in for
`phase-2-music-core`), pushed, PR open (`Phase 2: Music Core Domain
Foundation`, base `main`) — it also carries the two pre-Phase 2 workflow-doc
commits (separate docs PR impossible on the locked single branch).
Merge after CI green + approval.

## Completed

- Static SHIRIN frontend (pre-existing, untouched): premium Vanilla music UX.
- Phase 0 docs (9 files) + Phase 0.5 review docs (5 files).
- Phase 1: `backend/` Laravel 12 (auth, RBAC, admin shell, settings/flags,
  API v1, fa/en i18n, legal pages, storage foundation, CI workflow).
- Phase 1 reports: SETUP, IMPLEMENTATION, SECURITY_CHECK, TEST_REPORT,
  DATABASE_REPORT.
- Pre-Phase 2: `docs/DEVELOPMENT_WORKFLOW.md` (binding GitHub workflow).
- Phase 2: Music Core Domain Foundation — catalogue DB (genres/artists/
  albums/tracks/slug_redirects), Eloquent domain + `HasSlug`, admin CMS
  (CRUD + toggles + covers), 8 Form Requests, API v1 reads, SEO detail
  pages + 301s + JSON-LD, `CoverArtService` + cover proxy, factories +
  `GenreSeeder`, 43 new tests.
- Phase 2 reports: `PHASE_2_DATABASE_DESIGN.md`, `PHASE_2_MUSIC_CORE_REPORT.md`
  (incl. test + security sections per workflow §10).

## Current architecture (decisions that bind future work)

- Repo = static frontend (root, GitHub Pages) + Laravel backend (`backend/`, PHP host).
- No build step: Vite removed; plain `public/css/shirin.css` (additive Phase 2 styles).
- RBAC = Spatie Permission; roles owner/admin/editor/premium_user/user (5 in P1;
  content/ads split in P4). NEVER `user_id == 1` (CI grep gate).
- Owner bypass = `Gate::before` + `hasRole('owner')`.
- API envelope `{success,data,message[,code,errors]}`; versioned `/api/v1`.
- Flags single source = `settings` table via `FeatureFlagService`.
- Default locale `fa` (RTL), fallback `en`.
- Music: status draft/published/archived; slug route keys, Latin-only,
  immutable after publish, never reused (renames 301); single `language`
  fa/en (translations later); `genre_id` primary FK (pivot later);
  nullable `album_id` (loose singles); `source`/`provider_id` dual-source
  ready; music writes behind `music.manage` (middleware + Form Requests;
  model policies in P4); covers on `media` disk via `CoverArtService`,
  served by `/media/covers/*` proxy.

## Database

22 tables post-migrate (17 Phase 1 + 5 Phase 2; see
PHASE_2_DATABASE_DESIGN.md). Seeders: roles, settings, genres (8 rows).
OWNER via `php artisan shirin:install` (interactive, no defaults).

## Files added (Phase 2)

- `backend/database/migrations/2026_09_08_00000{3..7}_*` (genres, artists,
  albums, tracks, slug_redirects)
- `backend/app/Models/{Artist,Album,Track,Genre,SlugRedirect}.php`,
  `Models/Concerns/HasSlug.php`
- `backend/app/Contracts/CoverArtService.php`,
  `backend/app/Services/LocalCoverArtService.php`
- `backend/app/Http/Requests/{Store,Update}{Artist,Album,Track,Genre}Request.php`
- `backend/app/Http/Controllers/Admin/{Artist,Album,Track,Genre}Controller.php`
- `backend/app/Http/Controllers/Api/V1/{Artist,Album,Track}Controller.php`,
  `backend/app/Http/Resources/{Artist,Album,Track}Resource.php`
- `backend/app/Http/Controllers/Web/Music/{Artist,Album,Track}Controller.php`,
  `Web/MediaController.php`
- `backend/resources/views/admin/{artists,albums,tracks,genres}/*` (17 files),
  `web/music/*/*` (3 files)
- `backend/lang/{en,fa}/music.php`, `database/factories/*` (4),
  `database/seeders/GenreSeeder.php`, `tests/Feature/Music/*` (4)
- `docs/PHASE_2_{DATABASE_DESIGN,MUSIC_CORE_REPORT}.md`

## Files modified (Phase 2)

- `backend/routes/{web,api}.php` (music routes; incl. `MetaController`
  import fix), `config/shirin{,_nav}.php`, `Providers/AppServiceProvider.php`
  (cover binding), `DashboardController` + dashboard view (music stats),
  `layouts/app.blade.php` (`@stack('head')`), `lang/*/admin.php`,
  `public/css/shirin.css` (additive), `DatabaseSeeder`
- `docs/{ROADMAP,PROJECT_STATUS,SESSION_HANDOFF}.md` — frontend files: NONE.

## Packages

Phase 2 added NONE. See PROJECT_STATUS.md. `vendor/` intentionally NOT
committed (`.gitignore`); `composer.lock` still pending (PKG-01, owner action).

## Tests

79 tests total (36 P1 + 43 P2: 15 model / 14 admin / 8 API / 6 web).
Static verification passed (58/58 route targets, 30/30 views, 45/45 route
names, lang parity, zero ID-checks). PHPUnit execution happens in CI
(sandbox has no PHP runtime / packagist egress). Do not merge red.

## Next phase

Phase 2.5 — Catalogue delivery (planned, see ROADMAP.md): provider adapter,
player-on-API, search API, stream signing, nested endpoints, catalogue index
pages, hash-link redirects, PWA re-register. Then Phase 3 — User libraries.
Requires: CI green + Phase 2 PR merge first. Requires: R-01 licensing
decision before owned audio.

## Warnings

- Branch lock: this environment permits work ONLY on `arena/01a08237-surprise`.
  It plays the role of `phase-2-music-core`; do not create other branches here.
- `username` on users is unique non-nullable (fresh-install assumption).
- Spatie/Sanctum migrations auto-load from vendor — never duplicate them.
- Same-file parallel edits are unsafe in this environment (last-write-wins):
  apply multiple edits to one file sequentially (see Phase 2 fix commit).
