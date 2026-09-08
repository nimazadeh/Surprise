# SHIRIN — Phase 1 Database Report

## Migration inventory (fresh `migrate --seed`)

| # | Migration | Tables |
|---|---|---|
| 1 | Skeleton `create_users_table` | `users`, `password_reset_tokens`, `sessions` |
| 2 | Skeleton `create_cache_table` | `cache`, `cache_locks` |
| 3 | Skeleton `create_jobs_table` | `jobs`, `job_batches`, `failed_jobs` |
| 4 | Spatie (auto) | `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions` |
| 5 | Sanctum (auto) | `personal_access_tokens` |
| 6 | `add_profile_fields_to_users_table` | `users` += username (unique), avatar_path, locale `char(2)` default `fa`, status (indexed) default `active`, last_login_at |
| 7 | `create_settings_table` | `settings` (key PK, value, type, group indexed, timestamps) |

Total tables after migrate: **17**.

## Seeders

- `RolesAndPermissionsSeeder`: 5 roles, 11 permissions, documented matrix
  (owner/admin = all; editor = admin+music+uploads+analytics; premium_user =
  downloads+lab; user = none).
- `SettingsSeeder`: 9 rows (site ×2, features ×5, seo ×1, player ×1).
- OWNER account: interactive only (`shirin:install`), verified, `username=owner`
  unless the email already exists (then role is granted to the existing user).

## Alignment with DATABASE_DESIGN.md / corrections

- Applied: C-10 (framework tables listed), C-16 (`locale char(2)` default `fa`).
- Deferred to their phases: C-01 slug_redirects (Phase 2), C-02 playlists (Phase 3),
  C-06 analytics_daily (Phase 4), C-08 ad facts (Phase 5), C-11 premium_grants (Phase 3),
  C-15 JSON key allow-lists (Phase 2+).
- `username` unique non-nullable: safe on fresh installs; documented in setup guide.
