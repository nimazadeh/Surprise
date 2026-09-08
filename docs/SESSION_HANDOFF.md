# SHIRIN — Session Handoff (after Phase 1)

> Read this + `git log` + `docs/ROADMAP.md` before any new session. No chat
> history is assumed.

## Current project state

Phase 1 implemented on branch `arena/01a0812b-surprise`, pushed, PR open
(`Phase 1: Laravel Foundation`, base `main`). Merge after CI green + approval.

## Completed

- Static SHIRIN frontend (pre-existing, untouched): premium Vanilla music UX.
- Phase 0 docs (9 files) + Phase 0.5 review docs (5 files).
- Phase 1: `backend/` Laravel 12 (auth, RBAC, admin shell, settings/flags,
  API v1, fa/en i18n, legal pages, storage foundation, CI workflow).
- Phase 1 reports: SETUP, IMPLEMENTATION, SECURITY_CHECK, TEST_REPORT,
  DATABASE_REPORT.

## Current architecture (decisions that bind future work)

- Repo = static frontend (root, GitHub Pages) + Laravel backend (`backend/`, PHP host).
- No build step: Vite removed; plain `public/css/shirin.css`.
- RBAC = Spatie Permission; roles owner/admin/editor/premium_user/user (5 in P1;
  content/ads split in P4). NEVER `user_id == 1` (CI grep gate).
- Owner bypass = `Gate::before` + `hasRole('owner')`.
- API envelope `{success,data,message[,code,errors]}`; versioned `/api/v1`.
- Flags single source = `settings` table via `FeatureFlagService`.
- Default locale `fa` (RTL), fallback `en`.

## Database

17 tables post-migrate (see PHASE_1_DATABASE_REPORT.md). Seeders: roles,
settings. OWNER via `php artisan shirin:install` (interactive, no defaults).

## Files added

- `backend/` (~90 files: app, config, database, lang, resources, routes, tests…)
- `.github/workflows/backend-tests.yml`
- `docs/PHASE_1_{SETUP,IMPLEMENTATION,SECURITY_CHECK,TEST_REPORT,DATABASE_REPORT}.md`
- `docs/PROJECT_STATUS.md`, `docs/SESSION_HANDOFF.md` (this file)

## Files modified

- `docs/ROADMAP.md` (Phase 1 status) — frontend files: NONE.

## Packages

See PROJECT_STATUS.md. `vendor/` intentionally NOT committed (`.gitignore`).

## Tests

36 tests / 9 files authored; static verification passed (21/21 route targets,
lang parity, zero ID-checks). PHPUnit execution happens in CI (sandbox has no
PHP runtime / packagist egress). Do not merge red.

## Next phase

Phase 2 — Music core (see IMPLEMENTATION.md recommendation + ROADMAP.md).
Requires: R-01 licensing decision before owned audio. Requires: CI green + PR
merge first.

## Warnings

- Branch lock: this environment permits work ONLY on `arena/01a0812b-surprise`.
  It plays the role of `phase-1-foundation`; do not create other branches here.
- `username` on users is unique non-nullable (fresh-install assumption).
- Spatie/Sanctum migrations auto-load from vendor — never duplicate them.
