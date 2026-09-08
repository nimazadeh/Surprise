# SHIRIN — Database Review (Phase 1.5)

Date: 2026-09-08 · Scope: all migrations + seeders in `backend/database`.

## 1. Migration inventory (verified on disk)

| Order | Migration | Tables / change | Rollback |
|---|---|---|---|
| 1 | `0001_01_01_000000_create_users_table` (skeleton) | `users`, `password_reset_tokens`, `sessions` | ✅ drops all three |
| 2 | `0001_01_01_000001_create_cache_table` (skeleton) | `cache`, `cache_locks` | ✅ |
| 3 | `0001_01_01_000002_create_jobs_table` (skeleton) | `jobs`, `job_batches`, `failed_jobs` | ✅ |
| 4 | Spatie permission (package auto-load) | 5 RBAC tables | ✅ via package |
| 5 | Sanctum (package auto-load) | `personal_access_tokens` | ✅ via package |
| 6 | `2026_09_08_000001_add_profile_fields_to_users_table` | `users` += username (unique), avatar_path, locale `char(2)` default `fa`, status (indexed) default `active`, last_login_at | ✅ drops columns (+implicit index drop) |
| 7 | `2026_09_08_000002_create_settings_table` | `settings` (string PK `key`, text value, type, group indexed, timestamps) | ✅ |

## 2. Checks

- **Naming:** Laravel conventions (`snake_plural`, FK-ready `*_id` where applicable). ✅
- **Indexes:** username unique, status indexed, settings.group indexed, settings PK lookup.
  No missing-index hot path at Phase 1 scale. ✅
- **Nullability:** only genuinely-optional columns nullable (`avatar_path`, `last_login_at`,
  `settings.value`). `username` non-nullable is correct (registration requires it). ✅
- **FK/cascades:** none needed yet (no relations beyond package morphs). Future catalogue
  migrations must add FKs with explicit `cascadeOnDelete`/`restrictOnDelete` per
  DATABASE_DESIGN.md. ✅ (nothing to fix now)
- **Migration order:** profile extension runs after skeleton users table; settings
  independent; package tables load before seeders. ✅
- **Fresh-install assumption:** `username` unique non-nullable is safe because Phase 1
  ships no prior user rows; documented in SESSION_HANDOFF. ✅

## 3. Phase 2 readiness (artists/albums/tracks/… NOT created — by design)

| Requirement | Ready? |
|---|---|
| `users.id` stable PK for FKs (playlists, favorites, uploads…) | ✅ bigint unsigned |
| `settings` + flags to gate new modules | ✅ |
| Roles/permissions to guard music admin | ✅ |
| Slug strategy (`slug_redirects` per C-01) | ⏳ designed, migration lands with catalogue tables in Phase 2 |
| Counter-cache + rollup jobs | ⏳ Phase 2/4 |
| No blocking decision (engine is MySQL 8/MariaDB 10.6+, utf8mb4) | ✅ |

## Verdict

**Database: PASS.** Schema is clean, minimal, correctly ordered, and fully
rollback-capable. No fix required. Catalogue tables remain correctly deferred.
