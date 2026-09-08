# SHIRIN — Phase 1 Test Report

## A. Automated suite (authored: 36 tests, 9 files)

| File | Tests | Covers |
|---|---|---|
| `AuthTest` | 8 | View/register/login/logout/banned/throttle(429)/validation |
| `AdminAccessTest` | 5 | Guest→login, user→403, editor→200, banned→403, owner bypass (id≠1) |
| `SettingsServiceTest` | 4 | Defaults, typed roundtrip, cache+flush, forget |
| `FeatureFlagTest` | 3 | Download modes matrix, owner premium, lab tiering |
| `ApiV1Test` | 5 | Ping envelope, flags, 404 envelope, me 401/200 |
| `LocaleTest` | 4 | fa/RTL default, en switch, user persist, invalid→404 |
| `PublicPagesTest` | 3 | Pages 200, security headers, friendly 404 |
| `PasswordResetTest` | 2 | Link notification, full reset + login |
| `EmailVerificationTest` | 2 | Notice, signed-URL verify |

Run: `cd backend && php artisan test` (CI: `.github/workflows/backend-tests.yml`).

## B. Execution status — honest disclosure ⚠️

**The suite could NOT be executed inside this build sandbox:**

- No PHP runtime exists in the sandbox and none is installable (no apt egress;
  package mirrors unreachable from this network).
- `composer install` is impossible here (repo.packagist.org unreachable).
- GitHub git-protocol egress works, so the Laravel 12.x skeleton was cloned
  verbatim and all Phase 1 code was authored against it.

**What was executed instead (all passing):**

1. Static verification script: composer.json validity + dependency pins; 69 PHP
   files brace balance; zero hardcoded-ID patterns; 21/21 route→controller@method
   targets resolve; 6/6 `view()` targets exist; 2/2 Blade components resolve;
   14/14 `route()` names defined; fa/en lang parity + 46/46 keys exist;
   middleware wiring; 5 migrations + 2 seeders; 8/8 storage dirs; 9 test files.
2. CI workflow added so the suite runs automatically on push/PR (GitHub-hosted
   runner with PHP 8.3 + SQLite).

**Required before merge:** CI must be green on the Phase 1 PR. If any test fails
there, fix on the branch and re-run — do not merge red.

## C. Manual checklist (localhost, post-merge)

- [ ] `composer setup` → migrate/seed clean on MySQL
- [ ] `shirin:install` creates OWNER; `/admin` reachable; plain user gets 403
- [ ] Register → verify (log mailer) → login → logout
- [ ] Locale switch fa↔en; legal pages render in both
- [ ] `/api/v1/ping` envelope; `/api/v1/auth/me` 401/200
- [ ] `/.env` unreachable on staging host; `/up` 200
