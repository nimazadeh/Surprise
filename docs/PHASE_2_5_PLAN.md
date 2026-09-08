# SHIRIN — Phase 2.5 Implementation Plan (Catalogue delivery)

Date: 2026-09-08 · Branch: `arena/01a0826b-surprise` (stands in for
`phase-2-5-catalogue-delivery` per `docs/DEVELOPMENT_WORKFLOW.md` §2).

> **STATUS: APPROVED 2026-09-08 and IMPLEMENTED — see
> `docs/PHASE_2_5_REPORT.md` (deviations recorded in its §9).** This
> document remains the scope contract for the phase (workflow §5, merge
> gate 4: no scope expansion beyond the approved plan). It was drafted from
> `SESSION_HANDOFF.md` + `git log` + `ROADMAP.md` + the Phase 2 reports,
> after the owner fixed the scope on 2026-09-08 (§1).

---

## 1. Scope contract (fixed by owner, 2026-09-08)

**In scope:**

1. Dual-source **provider adapter** (backend)
2. **Vanilla player pointed at the Laravel API** (frontend)
3. **Search API**
4. **Nested endpoints** (`/artists/{slug}/albums`, `/albums/{slug}/tracks`, …)
5. **Public catalogue pages** (index/browse pages on the backend)
6. **Hash-link redirects** (`#/album/{id}` → `/albums/{slug}`)
7. **PWA update** (service-worker cache bump + asset re-registration)

**Explicitly out of scope (owner instruction + R-01 unresolved):**

- Audio upload · stream signing · audio processing — **NOT implemented**
- Owned audio files of any kind (R-01 status: unresolved → conservative)
- Database schema changes (**zero migrations planned**)
- Auto-import / persistence of provider rows into the catalogue tables
  (enrichment stays a later phase; the adapter serves live, cached metadata)
- Lyrics (unchanged honest "unavailable" state), sitemap/OG/hreflang,
  Phase 3 user libraries, model policies (Phase 4)

---

## 2. Design overview

The static frontend keeps its existing `MusicProvider` contract (`js/api.js`)
and gains a **`ShirinApiProvider`** that speaks `/api/v1` instead of Deezer
JSONP. The dual-source logic moves **into the backend**: a `CatalogueService`
merges owned DB rows (`source=owned`) with live provider metadata
(`source=deezer`) behind the existing flag system. The current
`DeezerProvider` (JSONP) is retained purely as an **automatic fallback**
when the API is unreachable, so the GitHub Pages app never regresses.

```text
today:      UI → Deezer JSONP (direct)
Phase 2.5:  UI → /api/v1 (Laravel) → owned DB  (source=owned, precedence)
                                  → Deezer HTTP (source=deezer, cached, flag-gated)
            fallback (API unreachable): UI → Deezer JSONP (unchanged)
```

**Legal posture (unchanged, R-01-safe):** metadata and provider-served
preview URLs only. The backend never proxies, caches, or stores audio; no
audio URLs are generated for owned tracks; Deezer attribution (`link`) is
passed through; only a short-TTL *metadata* cache exists server-side
(consistent with the app's existing client-side snapshot practice).

---

## 3. Backend deliverables

### 3.1 Provider contract + Deezer adapter

| File | Purpose |
|---|---|
| `app/Contracts/MusicProvider.php` | `search`, `artist`, `artistAlbums`, `album`, `albumTracks`, `track` |
| `app/Services/Providers/DeezerMusicProvider.php` | Laravel HTTP client; **no API key**; base URL from config (never user input); connect 4 s / max 8 s; single retry; returns normalized arrays |
| `app/Services/CatalogueService.php` | merge rules (§3.4); shapes provider items into the same player-ready shape as owned rows |

- Config additions (`config/shirin.php`): `providers.deezer.{base_url, enabled, cache_ttl, timeout}` and `catalogue.featured_provider_id` (default `7312776`) + `catalogue.owned_first` (default `true`).
- New feature flag **`features.catalogue_provider`** (settings table, default
  enabled): kill-switch — when off, the API serves owned-only and the
  provider is never called (workflow §9 kill-switch discipline).
- Metadata cache: file cache driver (shared-host safe, R-02), TTL 24 h,
  bounded key space. It is a cache, not a copy — nothing is persisted to the
  catalogue tables.
- Provider outage/timeout → never a 5xx: `CatalogueService` degrades to
  owned-only and the envelope `message` discloses the degradation.

### 3.2 API additions (all enveloped, published-only, slug-redirect-aware)

| Endpoint | Purpose | Throttle |
|---|---|---|
| `GET /api/v1/search?q=&type=all\|artist\|album\|track&per_page=` | merged search (owned `LIKE` + exact-slug, then provider; deduped) | dedicated 30/min |
| `GET /api/v1/artists/{slug}/albums` | nested: artist's published albums | existing 120/min group |
| `GET /api/v1/artists/{slug}/tracks` | nested: artist's published tracks | existing 120/min group |
| `GET /api/v1/albums/{slug}/tracks` | nested: album tracks, `track_number` order | existing 120/min group |
| `GET /api/v1/catalogue/featured` | player bootstrap: featured artist (owned `is_featured` wins, else configured provider artist) + latest albums + top tracks, one round-trip | existing 120/min group |
| `GET /api/v1/resolve?type=artist\|album\|track&provider_id=` | hash-link resolution → `{source, slug?, url?}` or miss | dedicated 30/min |

- Validation: `q` 2–100 chars; `type` enum; `provider_id` digits 1–64; slug
  regex as existing. Errors use the established 404/422/429 envelopes.
- Search has **no fulltext** (C-14): escaped `LIKE %q%` + exact-slug boost,
  paginated, capped (`per_page` ≤ 50 for search).
- Nested endpoints 301 on renamed slugs (same lookup order as show routes:
  exact → redirect row → 404).
- Existing 6 endpoints stay owned-DB-only (unchanged contract).

### 3.3 CORS for the static frontend origin

- New `config/cors.php`: paths `api/v1/*`; `allowed_origins` from a new
  `FRONTEND_ORIGINS` env (comma-separated: GitHub Pages URL + localhost
  dev); methods `GET, OPTIONS`; **no credentials** (public token-less
  reads); never `*` in production.
- `.env.example` gains `FRONTEND_ORIGINS=` and `DEEZER_ENABLED=true`.

### 3.4 Merge rules (`CatalogueService`)

1. Owned published rows always come first; provider items are appended.
2. Dedupe on `(source, provider_id)`: an owned row with a `provider_id`
   suppresses the provider twin (owned wins).
3. Provider items are **never persisted** — cache only.
4. Every item carries `source: owned|deezer` (frontend already tags source).
5. Flag off → provider never called, owned-only served.

### 3.5 Public catalogue index pages (Blade)

| Route | Behavior |
|---|---|
| `GET /artists` | published artists, paginated cards, `?q=` LIKE search box, featured first |
| `GET /albums` | published albums, paginated, `?type=album\|single\|ep\|compilation` filter |
| `GET /tracks` | published tracks, paginated, `?genre={slug}` filter |
| `GET /genres/{slug}` | tracks by genre (**stretch** — included unless owner trims; 8 seeded genres make it cheap) |

- Reuse Phase 2 card partials; SEO meta + canonical per page; simple
  `CollectionPage` JSON-LD; hreflang deferred (as in Phase 2).
- Nav entries in `config/shirin_nav.php` + new `lang/{en,fa}/music.php`
  keys (parity-verified); additive RTL-safe CSS in `public/css/shirin.css`.
- Existing detail pages (`/artists|/albums|/tracks/{slug}`) unchanged.

---

## 4. Frontend deliverables (root static files — first phase that touches them; diff disclosed in PR)

### 4.1 `ShirinApiProvider` (player-on-API)

- New class **inside `js/api.js`** (keeps `index.html` module list and SW
  asset list stable) implementing the existing `MusicProvider` contract via
  `fetch()` against `CONFIG.apiBaseUrl + '/api/v1/…'`; normalizes enveloped
  responses into the existing normalized record shape (`source:
  'owned'|'deezer'`; Phase 2 resources already mirror it).
- `js/config.js`: `provider: 'shirin-api'`, `apiBaseUrl: '<backend origin>'`
  (deploy-time public value, no secrets), Deezer fields kept for fallback.
- **Fallback chain:** API → `DeezerProvider` (JSONP, only on network
  error/5xx/timeout) → `FallbackProvider` (local demo). Existing 6.5 s
  watchdog unchanged.
- Playback semantics unchanged: preview URLs stay provider-served
  pass-throughs; owned tracks have no audio → existing honest
  "preview unavailable" state. `getPlayback` untouched.

### 4.2 Hash-link redirects + share links

- `js/router.js` / `js/ui.js`: on `#/album/{id}`, `#/track/{id}`,
  `#/artist/{id}` → call `/api/v1/resolve`:
  - owned match → `location.replace(apiBaseUrl + '/albums/{slug}')` (SEO page);
  - provider-only / miss → current in-app rendering (legacy links never break).
- Share/copy-link: `source: owned` items share backend SEO URLs;
  provider items keep hash links (current behavior).

### 4.3 PWA re-registration

- `service-worker.js`: `CACHE_NAME` bump `shirin-static-v7` → `v8`; add any
  new static assets to `STATIC_ASSETS`; **API + provider requests stay
  network-only** (existing never-cache policy extended explicitly to the
  API origin). `manifest.json` unchanged (verified).

---

## 5. Files summary

**Backend — add:** `Contracts/MusicProvider.php`, `Services/Providers/DeezerMusicProvider.php`,
`Services/CatalogueService.php`, `Controllers/Api/V1/{SearchController, CatalogueController,
ResolveController}.php`, `Controllers/Web/Music/{CatalogueController, GenreController}.php`,
4–5 Blade views + card partial, `config/cors.php`, ~8 test files.
**Backend — modify:** `routes/{api,web}.php`, `config/{shirin, shirin_nav}.php`,
`SettingsSeeder` (+1 flag), `lang/{en,fa}/music.php`, `public/css/shirin.css` (additive),
`.env.example`, `phpunit.xml` (throttle env), `app/Providers/AppServiceProvider.php`
(provider binding + rate-limiter definitions).
**Frontend — modify:** `js/config.js`, `js/api.js`, `js/router.js`, `js/ui.js` (touch points),
`service-worker.js`. `index.html` target: unchanged.
**Docs:** `PHASE_2_5_REPORT.md` (implementation + tests + security, Phase 2
consolidation pattern), `PHASE_2_5_SETUP.md` (new env vars), then
`ROADMAP.md` / `PROJECT_STATUS.md` / `SESSION_HANDOFF.md` sync. **No database
report (zero migrations).**

---

## 6. Test plan

Backend suites (PHPUnit; no live network — `Http::fake` + a `FakeMusicProvider`
test double bound over the contract):

| Suite | Covers |
|---|---|
| `DeezerProviderTest` | normalization, cache hit/miss, timeout, flag-off never calls |
| `CatalogueServiceTest` | merge order, owned-wins dedupe, outage degradation, flag-off |
| `SearchApiTest` | envelope, published-only, `LIKE` escaping of `%`/`_`, type filter, 422 short `q`, 429 throttle |
| `NestedApiTest` | 200 + `track_number` order, draft parent hidden, 301 on renamed slug, 404 envelope |
| `FeaturedApiTest` | owned featured artist, provider fallback, one-round-trip shape |
| `ResolveApiTest` | owned → slug/url, provider-only → miss payload, 404, validation |
| `CorsTest` | allowed-origin preflight OK; foreign origin gets no CORS headers |
| `WebCatalogueTest` | index pages 200, pagination, filters, draft exclusion, canonical, fa/en, nav links |

Estimated **~45–55 new tests** (79 existing must stay green). Honest disclosure,
same as P1/P2: the sandbox has no PHP runtime, so execution happens in CI —
**do not merge red.** Static verification in-sandbox: route/view/name target
checks, lang parity, brace balance, zero-ID-check grep, frontend diff review.

Frontend manual checklist (per ROADMAP testing strategy): API mode (owned +
provider mix), API-down → Deezer fallback, hash-link redirect for owned,
in-app view for provider-only, SW v7→v8 update, mobile 360/390/414 + desktop
1280/1440, RTL/LTR, reduced motion, offline indicator.

---

## 7. Sequencing (one logical commit per step; tests ride each feature commit)

1. `feat(api): add CORS allowlist for the static frontend`
2. `feat(provider): add music provider contract and Deezer adapter`
3. `feat(api): add nested catalogue endpoints`
4. `feat(api): add merged search endpoint`
5. `feat(api): add featured catalogue and resolve endpoints`
6. `feat(web): add public catalogue index pages`
7. `feat(web): point the player at the Laravel API`
8. `feat(web): add hash-link redirects to catalogue pages`
9. `feat(pwa): bump service-worker cache and re-register assets`
10. `docs(phase-2.5): add phase report and sync status docs`

One PR from the session branch, body per workflow §4 template, frontend diff
explicitly disclosed (this is **not** a backend-only phase, so the
frontend-preservation check does not apply — the changes are deliberate).

---

## 8. Definition of done (workflow §10)

- Exit criteria met: **home → album → track → player → queue on owned data**
  (owned content entered via the existing admin CMS, `source=owned`,
  placeholder/local fallback art acceptable — no Deezer artwork downloads);
  **hash-link redirects work** for owned items.
- `PHASE_2_5_REPORT.md` + `PHASE_2_5_SETUP.md` + living docs synced
  (incl. a fresh `SESSION_HANDOFF.md` for the next session).
- CI green on the PR head + owner approval → squash-merge → delete branch
  (session-branch mapping noted in the PR body, PR #1/#2 precedent).

---

## 9. Entry criteria — owner actions requested before/at merge

1. **Activate CI**: copy `docs/ci/backend-tests.yml` → `.github/workflows/backend-tests.yml`.
   The 79 existing tests have never executed anywhere; Phase 2.5 must not
   merge red (workflow §1.4/§5).
2. **Generate and commit `composer.lock`** on localhost (PKG-01; workflow
   §3/§8 require it, and CI's `composer install` should be reproducible).
3. **Provide the backend public origin** (for `apiBaseUrl`, `APP_URL`,
   `FRONTEND_ORIGINS`) and confirm the GitHub Pages URL.
4. **Confirm comfort with server-side Deezer metadata calls + 24 h metadata
   cache** (metadata-only, attribution kept, no audio — R-01-adjacent).
5. Optional but recommended: seed an owned "Shirin David" artist + a few
   albums/tracks via the admin CMS to exercise the owned path end-to-end.

---

## 10. Open questions (defaults used if no answer)

1. Mix provider results into public search? **Default: yes, owned-first.**
2. Include `/genres/{slug}` page now? **Default: yes (stretch, trimmable).**
3. Featured bootstrap preference? **Default: owned `is_featured` artist wins,
   else the configured provider artist (`7312776`).**
4. Throttles: search 30/min, resolve 30/min, catalogue group 120/min? **Default: yes.**
5. `features.catalogue_provider` default enabled (instantly killable)? **Default: yes.**

---

## 11. Risks

| Risk | Mitigation |
|---|---|
| Runtime dependency on Deezer from the backend | owned-first, 24 h cache, timeouts + single retry, flag kill-switch, client-side fallback chain |
| CORS misconfiguration blocks the player | env-driven allowlist + `CorsTest` + localhost dev origin + manual checklist |
| Deezer ToS discomfort (server-side use) | metadata-only, attribution, short cache; owner confirmation requested (§9.4) |
| Shared-host outbound latency (R-02) | cache-first, 4 s/8 s timeouts, no retry storm |
| Stale service-worker caches | v8 bump + network-only for API/provider requests |
| First frontend-touching phase → regression | fallback chain preserved; UI manual checklist; PR diff disclosure |

---

## 12. Rollback plan

Zero migrations. Code rollback = revert the phase commits; the SW version
bump makes clients pick up the reverted build on their next update cycle;
`features.catalogue_provider` can be switched off instantly (no redeploy) to
disable all provider calls; CORS config revert restores the pre-2.5 API
surface. Frontend rollback restores direct Deezer JSONP as primary.
