# SHIRIN — Security Review (Phase 0.5)

> Audit of SECURITY_PLAN.md + related designs. Verdict per area, then new findings.

## Area verdicts

| Area | Phase 0 plan | Review verdict |
|---|---|---|
| Authentication (bcrypt12, throttle, verify) | Solid | ✅ APPROVED |
| OWNER protection (role + protected flag + Gate::before) | Correct pattern | ✅ APPROVED — add break-glass (F-01) |
| CSRF/XSS/SQLi/mass-assignment | Standard Laravel posture | ✅ APPROVED |
| Upload pipeline (quarantine + sniff + re-encode) | Strong for shared hosting | ✅ APPROVED — tighten SVG + archives (F-02) |
| Signed stream/download URLs | Correct (short expiry, single scope) | ✅ APPROVED — add sharing-abuse note (F-03) |
| API tokens (Sanctum abilities) | Right choice | ✅ APPROVED — add SPA domain pitfall (F-04) |
| Admin hardening (gate + timeout + audit) | Good | ✅ APPROVED — add 2FA timeline (F-05) |
| Rate limiting | Present | ⚠️ CONDITIONAL — storage + shape fixes (F-06) |
| Secrets/config | Correct | ✅ APPROVED |
| CSP draft | Good start | ⚠️ CONDITIONAL — inline-style reality (F-07) |

## New findings (must address)

### F-01 — Break-glass OWNER recovery (missing)
- **Gap:** if the only OWNER loses credentials/2FA, the platform is ungovernable.
- **Fix:** offline sealed procedure: `php artisan shirin:owner-recover` (console-only,
  requires `APP_KEY` + one-time token printed at install and stored offline) that
  promotes a verified email to OWNER with full audit trail. Document; test once on staging.

### F-02 — Upload allow-list gaps: SVG, archives, embedded scripts
- **Fix:** covers/avatars: raster only (`jpg/png/webp`) — **SVG never accepted as image**
  (script vector). Audio: container allow-list as designed, plus: reject files >2× the
  probed-duration bitrate expectation (polyglot/junk padding heuristic → manual review),
  and never extract archives in v1 (no zip uploads).

### F-03 — Signed-URL sharing abuse (downloads/streams)
- **Fix:** downloads: bind signature to user id + single `file_id` + 10-min expiry +
  per-user daily quota + `downloads` row per issuance (already designed) + anomaly alert
  (>5× quota attempts/day → throttle + audit). Streams: 5-min expiry is enough (leakage
  window is tiny); no DRM claims — document honestly that URLs are deterrent-grade,
  like every standard streaming signed-URL scheme.

### F-04 — Sanctum SPA on shared hosting: domain pitfalls
- **Fix:** pin `SANCTUM_STATEFUL_DOMAINS` + `SESSION_DOMAIN` in `.env.example` with
  comments; v1 web player uses same-origin session cookie (no CORS); Bearer tokens only
  for future native apps. CORS `allowed_origins`: empty in v1 (same-origin only).

### F-05 — 2FA timeline explicit
- **Fix:** v1 (Phase 4): TOTP optional for `admin.access` holders, enforced for OWNER +
  SUPER ADMIN. Recovery codes (encrypted). No SMS (cost + SS7).

### F-06 — Rate limiter storage on file cache
- **Fix:** `throttle` works on file cache (fine for v1) but stampedes under attack on
  slow disks; set `CACHE_STORE=file` + documented upgrade to Redis on VPS. Add
  per-route shapes: `auth:5,1` · `search:30,1` · `api-global:120,1` · `uploads:10,60` ·
  `downloads:20,60` (plus quotas). Log `429` bursts to audit channel.

### F-07 — CSP vs existing frontend reality
- **Fix:** current JS sets inline `style="--progress:…"` and range `--range-progress`,
  so `style-src` MUST include `'unsafe-inline'` (styles, not scripts — acceptable).
  `script-src 'self'` stays strict (no inline scripts in Blade; JSONP to Deezer is a
  `<script src>` tag → needs `script-src 'self' https://api.deezer.com` only while the
  Deezer adapter exists; remove when owned catalogue stands alone).

### F-08 — Log redaction + admin alerts (missing)
- **Fix:** global log scrubber (tokens, passwords, emails partially masked); failed admin
  logins >5/10min → mail OWNER; `owner.protected_hit` → immediate mail + audit.

### F-09 — Dependency & header hygiene (missing)
- **Fix:** `composer audit` in CI from Phase 1; security-headers middleware
  (HSTS on HTTPS, nosniff, SAMEORIGIN, referrer-policy, permissions-policy);
  `Server` header minimization where host allows.

## Phase 1 security exit criteria (from this review)
- [ ] Spatie Permission installed; zero `user_id == 1` (CI grep gate)
- [ ] OWNER seed + break-glass procedure tested on localhost
- [ ] Upload validation test-suite with polyglot fixtures
- [ ] Signed URLs: expiry + tamper + cross-user reuse tests
- [ ] Rate-limit shapes configured + 429 JSON verified
- [ ] CSP + headers middleware active; `/.env` blocked on staging host
- [ ] `composer audit` clean in CI
