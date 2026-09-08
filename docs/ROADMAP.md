# SHIRIN — Implementation Roadmap (Phase 0)

> Design only. Each phase has **entry criteria → deliverables → exit criteria**.
> No phase starts until the previous exit criteria pass.

## Phase 0 — Foundation blueprint ✅ (complete)
- Deliverables: `docs/` (9 files). Exit: owner approves architecture + risks accepted,
  especially music-licensing risk (see RISKS.md R-01).

## Phase 0.5 — Architecture review ✅ (complete)
- Deliverables: `ARCHITECTURE_REVIEW.md`, `DATABASE_CORRECTIONS.md`,
  `SECURITY_REVIEW.md`, `FEATURE_PRIORITY_MATRIX.md`, `PHASE_1_READINESS_REPORT.md`.
- Verdict: **Phase 1 Ready: YES** (conditional items scheduled inside Phase 1).

## Phase 1.5 — Quality gate ✅ (audited 2026-09-08, on same PR)
- Deliverables: `*_AUDIT*`, `*_REVIEW*`, `TEST_REPORT_PHASE_1_5.md`,
  `PHASE_1_5_FINAL_REPORT.md`; 3 foundation fixes (gate recursion, register
  fillable, installer username); docs synchronized.
- Verdict: **READY for Phase 2** (see PHASE_1_5_FINAL_REPORT.md).

## Phase 1 — Laravel foundation ✅ (implemented 2026-09-08, PR pending)
- Deliverables: `backend/` (Laravel 12: auth, RBAC, admin shell, settings/flags,
  API v1, i18n, storage, CI) + `docs/PHASE_1_{SETUP,IMPLEMENTATION,SECURITY_CHECK,TEST_REPORT,DATABASE_REPORT}.md`.
- Exit: CI green on PR → merge. 36 tests authored; sandbox execution impossible
  (no PHP/packagist egress) — see PHASE_1_TEST_REPORT.md §B.

## Phase 1 — Laravel shell + auth + i18n + SEO skeleton
- Fresh Laravel 12 app reusing `css/*`, icons, design tokens; Blade layouts (RTL/LTR);
  `fa` default + `en`; auth (register/login/verify/reset); RBAC tables + OWNER seed;
  settings/flags system; SEO routes with placeholder data; `/up` + deploy to shared host.
- Exit: login→logout works on production URL in both languages; flags toggle a demo module.

## Phase 2 — Music Core Domain Foundation ✅ (implemented 2026-09-08, PR pending)
- Delivered: catalogue migrations (genres/artists/albums/tracks +
  slug_redirects), Eloquent domain, admin CMS (CRUD + toggles + covers), 8
  Form Requests, `/api/v1` read endpoints (player-shaped resources), SEO
  detail pages (`/artists|/albums|/tracks/{slug}` + 301s + JSON-LD),
  cover-art service + media proxy, factories + genre seeds, 43 tests,
  `docs/PHASE_2_{DATABASE_DESIGN,MUSIC_CORE_REPORT}.md`.
- Exit: CI green on PR → merge. Static verification passed (58/58 routes,
  lang parity, zero ID-checks); PHPUnit runs in CI (sandbox has no PHP).

## Phase 2.5 — Catalogue delivery ✅ (implemented 2026-09-08, PR pending)
- Owner-approved scope (R-01 conservative: **no audio upload, no stream
  signing, no audio processing**): dual-source provider adapter
  (`MusicProvider` contract + Deezer HTTP adapter + `CatalogueService`
  merge rules, flag-gated), Vanilla player pointed at `/api/v1`
  (`ShirinApiProvider` + automatic JSONP fallback, `apiBaseUrl` deploy
  switch), merged search API (30/min, LIKE-hardened), nested endpoints
  (`/artists/{slug}/albums|tracks`, `/albums/{slug}/tracks`),
  featured bootstrap + `resolve` (hash-link → SEO page), public catalogue
  index pages (`/artists`, `/albums`, `/tracks`, `/genres/{slug}`),
  CORS for the Pages origin, PWA cache v8, **CI workflow staged**
  (`docs/ci/backend-tests.yml` + owner activation step — the agent
  credential cannot push workflow files), 57 new tests (136 total).
- Stream signing stays deferred until R-01 resolves (unchanged gate).
- Exit: full UX flow (home→album→track→player→queue) on owned data;
  hash-link redirects work. Verify on localhost/staging per
  `PHASE_2_5_REPORT.md` §5 checklist → merge when CI green + approved.

## Phase 3 — User libraries
- Playlists, favorites, reactions, history, follows; `localStorage→API` first-login merge;
  profiles + settings; notifications skeleton.
- Exit: cross-device library; old local data preserved via merge.

## Phase 4 — Admin + security hardening
- Dashboard, user/music/upload/review management, audit logs, analytics v1, 2FA-ready,
  rate limits verified, backup runbook executed once.
- Exit: penetration self-check per SECURITY_PLAN.md passes; OWNER protection tests green.

## Phase 5 — Ads + Download Center (flag-gated V1)
- Placements/campaigns/creatives + impression/click pipeline + reports; Download Center
  with tier modes, quotas, logging (local-disk driver).
- Exit: ads render + tracked; downloads gated correctly per flag mode; module kills cleanly when off.

## Phase 6 — Music Lab (PHP-only tools)
- Tag Editor, Cover Editor, Metadata Fixer (all shared-host safe); job table + polling UI.
- Exit: tools work within shared limits with honest capability messaging.

## Phase 7 — VPS migration + FFmpeg tools
- Redis/queue/CDN cutover per runbook; Audio Cutter + Converter; background transcoding;
  uptime monitoring; load test.
- Exit: rollback window closed; Lab fully enabled for entitled tiers.

## Phase 8 — Launch hardening
- Full QA matrix (§Testing), performance pass (Lighthouse + query audit), legal review
  (terms/privacy/DMCA-takedown flow), launch checklist, post-launch metrics dashboard.

---

## Testing strategy (applies from Phase 1)

**Functional (Pest/PHPUnit + feature tests):** auth flows, RBAC matrix, upload→review→publish,
player/stream signing, playlists/favorites/reactions/history, downloads quota+tier,
flag on/off behavior, locale fallback, SEO meta presence.

**Security:** horizontal/vertical escalation suites, upload polyglot fixtures, signed-URL
expiry/tamper, rate-limit probes, admin idle/2FA flows, `grep` gate for `user_id == 1` in CI.

**UI:** mobile (360/390/414) + desktop (1280/1440) + RTL/LTR screenshot checklist per release;
keyboard/ARIA pass on player + uploader; reduced-motion respected.

**Performance:** N+1 query assertions on catalogue endpoints; cache-hit checks; upload
large-file behavior documented per host.

## Milestone summary

| Milestone | Phases | Business value |
|---|---|---|
| M1 Bilingual platform live | 0–1 | SEO + auth foundation on production URL |
| M2 Playable catalogue | 2–3 | Full listener experience, owned data |
| M3 Operated platform | 4–5 | Admin + revenue (ads) + downloads |
| M4 Differentiated product | 6–7 | Music Lab + VPS scale |
| M5 Launch | 8 | Hardened public release |
