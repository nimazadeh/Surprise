# SHIRIN — Deployment Compatibility Review (Phase 1.5)

Date: 2026-09-08 · Targets: localhost → shared PHP hosting → VPS.

## 1. Forbidden-dependency scan ✅

| Capability | Required? | Evidence |
|---|---|---|
| Redis | ❌ No | `CACHE_STORE=file`, `QUEUE_CONNECTION=database`, sessions in DB |
| Supervisor / daemons | ❌ No | Scheduler drains queue (`queue:work --stop-when-empty --max-time=50`) via 1 cron |
| Docker | ❌ No | Sail is dev-only, never referenced by app code |
| Long-running workers | ❌ No | Bounded `--max-jobs=50 --max-time=50` runs |
| FFmpeg / binaries | ❌ No | No media processing in Phase 1 |
| Node / build step | ❌ No | Vite removed; plain CSS; `composer.json` scripts contain zero npm calls (verified) |

## 2. Shared-hosting checklist ✅

- PHP 8.2+ with common extensions only (mbstring/xml/curl/zip/gd/fileinfo/pdo_mysql). ✅
- MySQL/MariaDB, single database, utf8mb4. ✅
- Writable: `storage/`, `bootstrap/cache/` (standard). ✅
- Cron: single `schedule:run` entry; 15-minute-granularity hosts degrade gracefully
  (jobs delayed, never lost; UX copy requirement already documented). ✅
- `.env` outside webroot; `public/` docroot; stock Laravel `.htaccess`. ✅
- `config:cache` / `route:cache` / `view:cache` compatible (no closures in config/routes
  except the scheduler definition, which is cache-safe). ✅

## 3. VPS migration path ✅

Unchanged from DEPLOYMENT_PLAN.md and unblocked: cache/queue/disk remain
env-swappable drivers; no code assumes file/DB drivers. Sanctum + signed-URL
designs carry over untouched.

## Verdict

**Deployment compatibility: PASS.** Phase 1 runs on localhost and typical shared
PHP hosting with zero VPS-only requirements.
