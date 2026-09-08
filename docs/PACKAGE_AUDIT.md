# SHIRIN — Package Audit (Phase 1.5)

Date: 2026-09-08 · Scope: `backend/composer.json` (no lock file yet — see §4).

## 1. Production dependencies

| Package | Version | Purpose | Verdict |
|---|---|---|---|
| `php` | `^8.2` | Runtime floor for Laravel 12 | ✅ matches shared-host reality |
| `laravel/framework` | `^12.0` | Core framework | ✅ pinned to approved major |
| `laravel/sanctum` | `^4.0` | API token guard (`auth:sanctum`); SPA-cookie ready | ✅ justified by API v1 contract; no custom token code |
| `laravel/tinker` | `^2.10.1` | REPL for ops/debugging | ✅ standard, zero runtime footprint |
| `spatie/laravel-permission` | `^6.0` | Roles + permissions + `HasRoles`/`HasPermissions` | ✅ per Phase 0.5 decision; replaces ~400 lines of hand-rolled RBAC |

No auth scaffolding kit (Breeze/Jetstream/Fortify) — deliberate: hand-written
session auth keeps the dependency surface minimal and the bilingual Blade layer
fully owned. No admin-panel kit (Filament/Nova) — deliberate: config-driven
custom shell per product direction.

## 2. Dev dependencies (all standard skeleton, none ship to prod)

`fakerphp/faker`, `laravel/pail`, `laravel/pint`, `laravel/sail`, `mockery/mockery`,
`nunomaduro/collision`, `phpunit/phpunit ^11.5.50`. All justified (factories, logs,
style, optional docker, testing). None required on shared hosting (`--no-dev`).

## 3. Compatibility

- All packages support PHP 8.2+ and Laravel 12 (Spatie v6 requires Laravel 11/12;
  Sanctum v4 requires Laravel 11/12; PHPUnit 11 matches Laravel 12 skeleton).
- No package requires Redis, daemons, ext-imagick, or Node — shared-host safe.
- No known CVE review possible offline; `composer audit` must run on first
  localhost install and in CI (already a Phase 4 checklist item — promoted to
  merge-gate: run once before merging PR #1).

## 4. Findings

| ID | Severity | Finding | Action |
|---|---|---|---|
| PKG-01 | Medium | **No `composer.lock` committed.** Reproducible installs impossible until one exists. Cannot be generated in this sandbox (no packagist egress). | Owner runs `composer update` on localhost, reviews, commits lock in a follow-up commit before/at merge. CI `composer install` behaves as update until then (comment added to workflow template). |
| PKG-02 | Low | `laravel/sail` pulls docker scaffolding unused by the project. | Keep (skeleton standard, dev-only, zero prod effect). Revisit if it ever complicates installs. |

## Verdict

**Dependencies: APPROVED** — minimal, compatible, no VPS-only requirements.
One follow-up (PKG-01) owned by the localhost environment, tracked in the final report.
