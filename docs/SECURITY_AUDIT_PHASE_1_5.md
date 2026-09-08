# SHIRIN — Security Audit (Phase 1.5)

Date: 2026-09-08 · Method: code review + repo-wide greps (results below).

## 1. Authentication ✅

- Hashing: `bcrypt(12)` via `hashed` cast; explicit `Hash::make` call sites are
  double-hash safe (`isHashed` guard). Factory + reset + installer verified.
- Sessions: DB driver, regenerate on login/logout, `http_only`, `same_site=lax`,
  `secure` flag env-ready (prod note added to PHASE_1_SETUP.md in this phase).
- Remember-me: token hidden from serialization; rotated on password reset. ✅
- Email verification: `MustVerifyEmail` + signed routes + throttled resend. ✅
- Throttling: `5,1` on login/register/reset, `3,1` on resend; 429 covered by test. ✅

## 2. Authorization — ONE CRITICAL FINDING, FIXED ✅

- **SEC-01 (critical, fixed in this phase):** `AppServiceProvider` defined
  `Gate::define('admin.access', fn ($u) => $u->can('admin.access'))` — a
  self-referential gate. For any user resolving to `null` in all `Gate::before`
  callbacks, authorization recursed until stack exhaustion (HTTP 500 instead of
  403 on the exact path meant to forbid). Fix: the gate now calls Spatie's
  `checkPermissionTo('admin.access')` directly — no re-entry, fail-closed
  (`false`) when the permission row is missing. Owner bypass (`Gate::before` +
  `hasRole('owner')`) unchanged.
- Repo-wide grep for `user_id == 1`-style checks: **zero hits** (re-verified).
- No hidden bypasses: single `Gate::before` (owner), single `Gate::define`
  (admin.access), `AdminMiddleware` enforces `auth` + permission + ban check. ✅
- `@can` in admin nav fails closed for unknown permissions. ✅

## 3. Web security ✅

- CSRF: `@csrf` on every form; web group intact. ✅
- XSS: zero `{!! !!}` in Blade/app (grep verified); all output escaped. ✅
- SQLi: Eloquent + query builder only; zero raw SQL (grep verified). ✅
- Mass assignment: explicit `$fillable`; `status` removed from the register path
  in this phase (FIX-2) — admin-only by construction. ✅
- Headers: global middleware sets nosniff, SAMEORIGIN, strict referrer,
  permissions-policy, baseline CSP (`script-src 'self'`), HSTS-when-secure. ✅
- Error exposure: friendly 403/404/419/500 pages; API envelope hides internals;
  `APP_DEBUG=false` required in prod setup guide. ✅

## 4. File/storage foundation ✅

- No upload endpoint exists (attack surface: none yet). ✅
- `media` + `quarantine` disks rooted under `storage/app` (outside webroot);
  quarantine has no URL mapping by design. ✅
- Allow-list + size caps live in `config/shirin.php` as server truth for later phases. ✅
- `storage/*.key`, `.env`, `vendor/` all gitignored; verified absent from repo. ✅

## 5. Residual (tracked, out of Phase 1 scope)

`is_protected` owner hardening, 2FA, break-glass recovery, `composer audit` in CI,
failed-admin-login alerts — all land in Phase 4 per SECURITY_PLAN.md.

## Verdict

**Security: PASS (after SEC-01 fix).** One critical gating bug found and fixed;
all other areas clean with evidence above.
