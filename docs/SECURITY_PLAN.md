# SHIRIN — Security Plan (Phase 0)

> Design only. Threat model: public music platform on shared hosting → VPS.
> Highest-value targets: OWNER account, upload pipeline, admin panel, user sessions.

## 1. Authentication

- Laravel session auth (web) + Sanctum tokens (API/mobile future); passwords via
  `bcrypt(12)`; email verification required for uploads/comments-equivalent actions.
- Login throttling: `throttle:5,1` on login/register/password endpoints + Cloudflare-style
  `429` JSON for API.
- Credential stuffing: generic error messages, login-attempt audit, optional 2FA (TOTP)
  for `admin.access` holders from Phase 4.
- Session: `http_only`, `same_site=lax`, `secure` in prod; regenerate on login;
  idle timeout for `/admin` (30 min) vs main site (2 weeks remember-me).
- Password rules: min 10 chars; `Password::defaults()` + breached-password check
  (`uncompromised`) when network allows.

## 2. Authorization (roles, never IDs)

```text
OWNER (is_protected) · SUPER ADMIN · ADMIN · CONTENT MANAGER · ADS MANAGER ·
PREMIUM USER · NORMAL USER
```

> Phase 1 status: the 5-role subset `owner/admin/editor/premium_user/user` is
> implemented (Spatie). `super_admin` + content/ads-manager split lands with the
> admin modules in Phase 4; `is_protected` hardening lands with user management.

- Tables: `roles / permissions / model_has_roles / role_has_permissions`
  (Spatie Permission — decided and implemented in Phase 1).
- **Hard rules:**
  - `if ($user->id === 1)` is a review-blocking violation. Use `$user->hasRole('owner')`
    and `Gate::before(fn($u) => $u->hasRole('owner') ? true : null)`.
  - OWNER role: `is_protected = true`; policies refuse demote/delete/ban/lock on
    protected holders; any such attempt is audit-logged as a security event.
  - Premium bypass: OWNER and valid `premium_grants` rows only; checked in one
    `PremiumAccessService`, not scattered conditionals.
- Policies per model (`TrackPolicy`, `PlaylistPolicy`, `AdPolicy`, `UserPolicy`…),
  Gates for cross-cutting checks (`downloads.access`, `music_lab.access`, `admin.access`).
- Every admin write route: `auth + permission middleware + policy check + audit log`.
- Tests must cover: horizontal escalation (user A edits user B's playlist),
  vertical escalation (content manager touches ads/settings), flag bypass
  (premium route with flags off).

## 3. Web attack surface

| Threat | Mitigation |
|---|---|
| CSRF | `@csrf` on all Blade forms; `VerifyCsrfToken` on web group; Sanctum CSRF cookie for SPA calls; `SameSite=Lax` |
| XSS (stored) | Blade `{{ }}` escaping by default; `{!! !!}` forbidden except allow-listed HTML purifier (none in v1); JS keeps `escapeHTML` discipline; CSP header (see below) |
| XSS (reflected) | Search `q` echoed only escaped; validation + max lengths |
| SQL injection | Eloquent/query builder only; raw SQL requires review + bindings |
| Mass assignment | `$fillable` allow-lists on every model; `$guarded = ['*']` default mindset |
| Open redirect | Redirect allow-list (`intended` validated to same-host paths) |
| Clickjacking | `X-Frame-Options: SAMEORIGIN` (player embeds excepted deliberately if needed) |
| MIME sniffing | `X-Content-Type-Options: nosniff`; uploads served with correct `Content-Type` |
| CSP (baseline) | `default-src 'self'; img-src 'self' data: https:; media-src 'self' https:; script-src 'self'; style-src 'self' 'unsafe-inline'; connect-src 'self' https://api.deezer.com` — tighten after audit |

## 4. File upload security (critical)

1. Validate twice: client hints (UX) + server truth (`mimes`, `max`, MIME sniff via
   `Fileinfo`, never trust extension).
2. Audio allow-list: `mp3, ogg, opus, wav, flac, m4a` (+ covers `jpg/png/webp`, ≤ 5 MB).
3. Store **outside webroot** (`storage/app`), serve via signed routes — uploads are
   never directly URL-addressable.
4. Rename all files (`uuid.ext`); strip executable bits; reject double extensions,
   SVG-with-script, and polyglots (re-encode covers through GD/Imagick).
5. Quarantine flow: `pending` → (automated checks + human review) → `approved`; only
   approved files attach to published tracks.
6. Size caps: web upload ≤ 100 MB (shared host), chunked protocol for larger on VPS;
   per-user daily quotas; global disk alarms.
7. AV scan hook: `MediaScanService` interface (no-op locally, ClamAV on VPS).

## 5. API security

- Sanctum tokens with abilities (`player`, `library`, `uploads`); least-privilege per client.
- Rate limits: global `120/min` API; `search 30/min`; `auth 5/min`; `downloads` quota-based.
- Signed stream URLs: `URL::temporarySignedRoute`, 5-minute expiry, single-track scope.
- Error shape never leaks internals (`APP_DEBUG=false` in prod; generic `message` + `code`).
- CORS: same-origin + configured mobile origins only.

## 6. Admin protection

- Separate `/admin` prefix (configurable), `admin.access` gate, idle timeout, optional IP
  allow-list (VPS), 2FA-ready, all actions audit-logged.
- Settings/flags changes: OWNER + SUPER ADMIN only; double-confirm destructive actions.
- Failed admin logins alert OWNER (mail) after 5 attempts.

## 7. Secrets & config

- No secrets in repo: `.env` only, `.env.example` documented; `config/` reads env with
  safe defaults; `php artisan config:cache` in prod.
- File perms on shared host: `storage/` + `bootstrap/cache/` writable, `640/750` mindset;
  block `/.env` and `/storage` via `.htaccess` rules (verified post-deploy).

## 8. Logging & incident basics

- Security events (`auth.failed_admin`, `upload.rejected_malicious`, `owner.protected_hit`)
  → `audit_logs` + `laravel.log` channel; nightly owner digest (mail) on VPS phase.
- Incident runbook (Phase 4): rotate `APP_KEY`/tokens, ban actor, purge malicious file,
  review audit trail, post-mortem note in `docs/RISKS.md`.
