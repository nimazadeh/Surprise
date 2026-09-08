# SHIRIN — Phase 2.5 Report (Catalogue delivery)

Date: 2026-09-08 · Branch: `arena/01a0826b-surprise` (stands in for
`phase-2-5-catalogue-delivery` per `docs/DEVELOPMENT_WORKFLOW.md` §2) ·
Plan: `docs/PHASE_2_5_PLAN.md` (owner-approved 2026-09-08).

> This report is the Phase 2.5 implementation record. It also covers the
> workflow-required `PHASE_2_5_IMPLEMENTATION` (here), `PHASE_2_5_TEST_REPORT`
> (§5), `PHASE_2_5_SECURITY_CHECK` (§7), and — with
> `docs/PHASE_2_5_SETUP.md` — the setup deltas. There is **no** database
> report: **zero migrations** this phase (schema unchanged, 20 data tables).

## 1. Overview

Phase 2.5 connects the static player to the Laravel backend and makes the
catalogue deliverable end-to-end: the Vanilla app gains a `ShirinApiProvider`
speaking `/api/v1` (with the unchanged Deezer JSONP transport as automatic
fallback), the backend gains a dual-source provider layer (`MusicProvider`
contract + Deezer adapter + `CatalogueService` merge rules), merged search,
nested endpoints, a one-round-trip featured bootstrap, hash-link resolution,
public catalogue index pages, CORS for the Pages origin, and a CI workflow
staged for activation. **R-01 stands: no owned audio, no stream signing, no audio
processing — metadata, covers and provider-authorized preview pass-throughs
only** (owner instruction 2026-09-08: proceed conservatively).

Out of scope by owner decision: audio upload, stream signing, audio
processing; auto-import/persistence of provider rows (enrichment stays a
later phase); sitemap/OG/hreflang; Phase 3 user libraries.

## 2. CI activation (owner decision #2 — staged, activation is a one-command owner step)

The plan was to move `docs/ci/backend-tests.yml` to
`.github/workflows/backend-tests.yml`. **Environment limitation, disclosed:**
pushing the branch was rejected by GitHub — the sandbox's GitHub App
credential lacks the `workflows` permission required to create files under
`.github/workflows/`. The workflow is therefore **staged in place** at
`docs/ci/backend-tests.yml` with activation instructions in its header;
the owner activates it after merge with one copy-commit
(`cp docs/ci/backend-tests.yml .github/workflows/backend-tests.yml` —
exact commands in `PHASE_2_5_SETUP.md` §4). Once active, Pint, the
hardcoded-owner-ID grep gate, and the full PHPUnit suite run on every
push/PR touching `backend/**`. **Honest disclosure:** activation will be
the workflow's first-ever run — the 79 Phase 1+2 tests have never executed
anywhere. Until then the suite must be run locally (`php artisan test`);
any failures found are fixed forward on the branch (workflow §2, disclosed
in the PR). `DEVELOPMENT_WORKFLOW.md` §5 documents the staged location.

## 3. Backend changes (all additive)

### 3.1 Provider layer (dual-source)

- `app/Contracts/MusicProvider.php` — read-only metadata contract: three
  searches, artist/album/track getters, artist albums/top tracks, album
  tracks. Implementations must never throw for outages (null/[] instead)
  and never touch audio.
- `app/Services/Providers/DeezerMusicProvider.php` — public-catalogue
  adapter over the Laravel HTTP client (`guzzlehttp/guzzle ^7.8` added —
  PKG-03, disclosed in the PR body; framework transport, ext-curl only,
  already on the DEPLOYMENT_PLAN host checklist). Metadata cache (file
  driver, 24 h default, bounded md5 keys), timeouts 3 s connect / 4 s read,
  one retry — worst case ~8 s, under the frontend's 9 s request budget.
  Every failure (disabled config, connection error, HTTP error, malformed
  list payload, provider `error` payload) degrades to null/[]. Provider ids
  are strictly validated (`[A-Za-z0-9_-]{1,64}`) **before** any request path
  is built — invalid ids never reach the network. Provider track items
  carry the provider-authorized `preview` URL as a pass-through; the
  backend never downloads, caches, or proxies audio.
- `config/shirin.php` — new `providers.deezer` (enabled/base_url/cache_ttl/
  timeouts) and `catalogue` (featured provider id, limits, caps) sections;
  `features.catalogue_provider => true` default.
- `FeatureFlagService::catalogueProviderEnabled()` + new `catalogue_provider`
  key in `publicFlags` (kill switch: off = owned-only, provider never
  called). `SettingsSeeder` seeds it `'1'`.
- `AppServiceProvider` binds `MusicProvider` → `DeezerMusicProvider`
  (tests swap the instance or `Http::fake`).

### 3.2 CatalogueService (merge rules, owner-approved)

1. Owned published rows first; provider items appended.
2. `provider_id` dedupe — an owned row in **any status** (draft/archived/
   soft-deleted) suppresses its provider twin: managed items never
   double-list and never fall through to the provider on resolve
   (content governance). One `whereIn` per type, never per item.
3. Provider items are never persisted (cache lives inside the adapter).
4. Every item carries `source: owned|deezer`.
5. Flag off → provider never called.
Outages degrade silently to owned-only; the adapter never throws.

### 3.3 API additions (enveloped, published-only, C-01-aware)

| Endpoint | Throttle | Behavior |
|---|---|---|
| `GET /api/v1/search?q=&type=all\|artist\|album\|track&limit=` | 30/min | merged grouped results `{query, type, artists, albums, tracks}`; owned LIKE first, provider appended, twin-filtered; `q` 2–100, `limit` 1–25 (config caps) |
| `GET /api/v1/resolve?type=artist\|album\|track&provider_id=` | 30/min | owned twin → `{matched, source: owned, slug, url (SEO page), item}`; unpublished managed twin → `matched:false` **without** provider fall-through; provider → item + children (top tracks / album tracks) so deep links render in-app from one response; miss → `matched:false` (never 404) |
| `GET /api/v1/catalogue/featured` | 120/min | one-round-trip bootstrap: featured artist (owned `is_featured` wins, else configured provider id) + owned albums/tracks with provider items appended, twin-filtered |
| `GET /api/v1/artists/{slug}/albums` | 120/min | nested, plain capped array (`shirin.catalogue.nested_max`), exact → 301 → 404 |
| `GET /api/v1/artists/{slug}/tracks` | 120/min | as above |
| `GET /api/v1/albums/{slug}/tracks` | 120/min | track-number order |

- `ThrottleRequestsException` now renders the standard envelope
  (`429 RATE_LIMITED`, `Retry-After` preserved) for every throttled route —
  previously Laravel's bare JSON leaked outside the contract.
- Model `scopeSearch` (Artist/Album/Track) **strips LIKE wildcards** (`%`,
  `_`) from user input before building the pattern — portable across
  MySQL/MariaDB and SQLite (whose LIKE escape rules differ), fixing a
  "`50%` matches everything" injection-by-pattern bug that also affected
  admin search. Exact-slug matches keep the raw term (equality, literals).

### 3.4 CORS

`config/cors.php`: paths `api/v1/*` only; `GET, OPTIONS`; origins from
`FRONTEND_ORIGINS` env (comma-separated; production adds the Pages URL);
no credentials (public token-less reads — cookies never cross origins);
never a wildcard in production. Web/session routes, Sanctum, and the media
proxy stay same-origin.

### 3.5 Public catalogue index pages (Blade)

`/artists`, `/albums`, `/tracks` (+ `?q=` search, albums `?type=`, tracks
`?genre=`) and `/genres/{slug}`: published-only, paginated
(`shirin.music.api_per_page`, `withQueryString()` keeps filters), reusing
Phase 2 card/track-list patterns; canonical + `CollectionPage` JSON-LD;
topbar and home page gain catalogue links (crawl path + discovery).
Genre pages follow the same exact → 301 (C-01, genres carry redirect rows
via `HasSlug`) → 404 order. Track listings order by title — track numbers
only mean something inside an album. Lang keys en/fa parity-verified;
additive RTL-safe CSS (`.filter-row`, genre select).

## 4. Frontend changes (root static files — deliberate, disclosed)

`index.html`, `css/`, `assets/`, `manifest.json`: **untouched**. Nine files
changed (`git diff main...HEAD --stat`: js/api.js, app.js, config.js,
router.js, state.js, ui.js, utils.js, views.js, service-worker.js).

- **`ShirinApiProvider`** (inside `js/api.js` — no new files, so the module
  list and SW asset list stay stable) implements the existing
  `MusicProvider` contract over `/api/v1` with `fetch` + `AbortController`
  (9 s). Featured bootstrap in one round trip; merged search; nested loads;
  resolve-driven deep loads and preview refresh for provider tracks. Owned
  tracks honestly report **no audio** until R-01 resolves.
- **Addressing:** owned records use their **immutable slug as the record
  id** — stable for queue/favorites persistence and shareable links;
  provider records keep provider ids.
- **Fallback chain:** API unreachable (network/5xx/timeout/malformed) →
  unchanged Deezer JSONP → offline demo catalogue. `CONFIG.apiBaseUrl` is
  **empty by default**: GitHub Pages behavior is byte-for-byte unchanged
  until the owner points it at the backend at deploy time (SETUP §3).
- **Hash-link redirects:** cold entries (shared URL/reload) to
  `#/album/{id}`, `#/track/{id}`, `#/artist/{id}` land on the backend SEO
  page when the target is owned (including owned twins of legacy provider
  ids — old shared links keep working, better). In-app navigation never
  redirects; provider-only items render in-app. `Router.parseRoute` gains
  the artist id segment; the initial notify is flagged as a cold entry and
  threaded through `renderRoute` to the album/track renderers (no extra
  fetch — the redirect decision rides the normal item load).
- **PWA:** cache key `shirin-static-v7` → `v8` + registration hint `build=8`
  so installed clients receive the API-mode player; the never-cache policy
  now explicitly covers the API origin (only same-origin GETs are ever
  intercepted, so `/api/v1` and provider pass-throughs always hit the
  network).
- **Honesty details:** the data-source label distinguishes `SHIRIN API`
  from `Deezer`; the track sheet links owned items to "View track page"
  (their SEO page) instead of "Open in Deezer"; records carry
  `slug`/`artistSlug`/`albumSlug` passthroughs.
- Search keeps the focused-artist product posture for **provider** results
  (featured-artist matches only); owned rows are owner-curated and never
  filtered.

## 5. Tests (57 new; 136 total with Phases 1+2)

| Suite | Tests | Covers |
|---|---|---|
| `CorsTest` | 5 | allowed origin headers, preflight, foreign origin denied, web routes same-origin, no credentials |
| `DeezerProviderTest` | 10 | disabled = zero network; normalization per type; preview pass-through; album context inheritance; per-key caching; HTTP-error degradation; single retry; connection-failure degradation; malformed id rejection |
| `CatalogueServiceTest` | 6 | owned-first order; twin suppression (published + draft); flag-off isolation (counters); outage degradation; type filtering — via a `FakeMusicProvider` contract double (`tests/Fixtures/`) |
| `MusicSearchApiTest` | 10 | envelope + groups; drafts hidden; exact-slug; type filter; wildcard neutralization (`%%`, `100%`); provider merge order; provider outage; 422s; 429 envelope + throttle |
| `MusicNestedApiTest` | 7 | artist albums/tracks; track-number order; configured cap; draft parent hidden; rename 301; unknown 404 envelope |
| `MusicCatalogueApiTest` | 10 | featured owned preference; provider fallback; outage degradation; flag-off isolation; twin suppression in featured; resolve → SEO url; resolve children (+preview); draft-twin governance (no provider fall-through); clean miss; validation 422s |
| `MusicWebCatalogueTest` | 9 | artists/albums/tracks listing + drafts; search; type + genre filters; genre page + 404; nav render; filter-preserving pagination |

**Execution disclosure (honest):** the sandbox still has no PHP runtime or
packagist egress, so PHPUnit must run in CI or on localhost — and CI is
staged, not yet active (see §2), so **no suite has ever executed**; run
`php artisan test` locally before merging. In-sandbox static verification
passed: 100 % route→controller targets,
100 % view targets, en/fa lang parity (music 119, nav 12, admin 31, site 9,
auth 19 keys), brace/paren balance on every touched PHP file, zero
hardcoded-ID patterns, zero unused imports, and dynamic `status`/`type` lang
keys confirmed. Frontend: `node --check` on every touched module plus two
runtime smoke suites (14 provider cases + 6 router cases, stubbed fetch —
all green; scripts were one-off verification, not committed).

**Localhost/staging manual checklist (pre-merge):**

- [ ] `composer update` on localhost → commit `composer.lock` (now includes
      guzzle; PKG-01) → `composer setup` clean on MySQL
- [ ] `php artisan test` green locally (first-ever full-suite execution);
      after merge, activate CI (SETUP §4) and confirm a green run
- [ ] `FRONTEND_ORIGINS` set; preflight from the Pages/dev origin OK;
      foreign origin gets no CORS headers
- [ ] Seed owned artist + albums/tracks in the admin CMS (one featured);
      `GET /api/v1/catalogue/featured` returns owned-first payload
- [ ] Static app with `apiBaseUrl` set: home → album → track → player →
      queue on owned data; owned track shows honest "no preview" state;
      provider tracks play 30 s authorized previews
- [ ] `#/album/{providerId-with-owned-twin}` cold link → 301-landing SEO
      page; provider-only deep link renders in-app; in-app clicks never
      redirect mid-session
- [ ] Search box: owned + provider results, owned first, no unrelated
      provider items; `q=%%` returns nothing; 31 rapid searches → 429
- [ ] API down (stop backend): app falls back to Deezer JSONP, then demo
      catalogue; offline indicator works
- [ ] `/artists`, `/albums?type=`, `/tracks?genre=`, `/genres/{slug}` in
      fa + en; pagination keeps filters; drafts invisible
- [ ] SW: installed copy updates v7→v8; API origin never cached
- [ ] Mobile 360/390/414 + desktop 1280/1440, RTL/LTR, reduced motion

## 6. Future compatibility

- **Stream signing (deferred with R-01):** `ShirinApiProvider.getPlayback`
  is the single choke point — a signed-URL source slots in without touching
  the player or UI; owned tracks currently return null honestly.
- **Playlists/library (Phase 3):** slug-as-id gives owned items stable,
  immutable, never-reused client ids — exactly what a first-login
  `localStorage→API` merge needs.
- **Enrichment/import:** `provider_id` twins + resolve make "adopt this
  provider item into the owned catalogue" a small admin action later.
- **Sitemap/OG/hreflang:** additive on the new index pages.
- **Provider pagination on nested/search:** grouped/capped shapes extend
  without breaking consumers (additive keys).

## 7. Security self-review (vs `docs/SECURITY_PLAN.md`)

| # | Check | Result |
|---|---|---|
| 1 | No hardcoded owner IDs | ✅ grep gate clean; no identity checks added |
| 2 | Authorization | ✅ new endpoints are public reads only (no writes this phase); admin surface unchanged |
| 3 | Validation | ✅ search/resolve inline `validate()` (422 envelope); slug regexes on routes; provider ids regex-checked twice (controller + adapter) |
| 4 | SSRF/path injection | ✅ provider ids never reach a URL path unvalidated; adapter base URL is config-only (never user input); provider links surfaced only when http(s) |
| 5 | SQL/pattern injection | ✅ Eloquent bindings everywhere; LIKE wildcards stripped (portable); exact-slug uses equality |
| 6 | Information disclosure | ✅ draft/trashed items invisible everywhere incl. resolve (no existence oracle); provider outages never 5xx; envelope hides internals |
| 7 | Rate limiting | ✅ search/resolve 30/min, catalogue group 120/min; 429 enveloped with Retry-After |
| 8 | CORS | ✅ env allowlist, GET/OPTIONS only, no credentials, api/v1 only; `CorsTest` covers it |
| 9 | Upload safety | ✅ no new upload paths (covers unchanged) |
| 10 | Secrets | ✅ none committed; `DEEZER_*` and `FRONTEND_ORIGINS` are public configuration |

Deferred (tracked): provider egress is unauthenticated public metadata
(no key exists to leak); `composer audit` still pending the lock (PKG-01);
CI secret-scanning not in scope of the workflow template.

## 8. Known limitations

- No stream signing / owned audio (R-01 unresolved — by design this phase).
- Provider search degradation is silent (owned results still returned);
  the endpoint cannot distinguish "provider had no matches" from "provider
  down" without a contract change — deferred.
- `getAlbums` for a **provider-addressed** artist (rare path — only the
  legacy bootstrap uses it) falls back to the direct JSONP transport.
- Featured bootstrap requires a resolvable artist; an API with no owned
  featured artist and a dead/off provider makes the frontend fall back to
  Deezer JSONP for the home screen.
- Nested/search endpoints return capped plain arrays, not paginated
  payloads (documented deviation; catalogues are small in v1).
- `resolve` does not match owned rows **by slug** (only by `provider_id`);
  cold `#/artist/{owned-slug}` links render in-app instead of redirecting
  (album/track slug links redirect correctly via their show endpoints).
- Frontend automated tests still don't exist (README future-work); manual
  checklist above.

## 9. Decisions (including plan deviations)

1. **Grouped search shape** instead of the plan's "paginated": mixing DB
   pagination with a remote source would be pagination theater; the search
   UI shows fixed groups. Additive pagination can come later.
2. **Nested/index endpoints live on the existing per-entity controllers**
   (plus the new `Web\Music\GenreController`) rather than the plan's
   `CatalogueController` — matches the Phase 2 file layout; fewer files,
   same behavior.
3. **`resolve` carries children** (artist top tracks / album tracks) so
   provider deep links render in-app from one response — the plan's
   redirect-only payload would have forced a second round trip.
4. **Owned "top" tracks = latest additions** until Phase 4 play counters
   exist; provider "top" stays the provider's real chart.
5. **`guzzlehttp/guzzle ^7.8` added** (PKG-03): the HTTP client's
   transport; no new extensions beyond the host checklist.
6. **Draft-twin governance:** unpublished managed items suppress their
   provider twins in search/featured AND miss on resolve — consistent
   "the owner manages what's listed" semantics.
7. **Cold-entry-only redirects:** in-app navigation never bounces users
   off-site mid-session; only shared links/reloads land on SEO pages.
8. **`apiBaseUrl: ''` default:** the merged PR does not flip GitHub Pages
   to a backend that does not exist yet; the owner sets it when the
   backend is deployed (SETUP §3). Default behavior = Phase 2 app.
9. **429 envelope** added globally (improves every throttled route; the
   only existing 429 assertion checks status only).
10. **CI workflow staged, not pushed live:** the sandbox GitHub credential
    cannot push files under `.github/workflows/` (missing `workflows`
    permission). The workflow stays at `docs/ci/backend-tests.yml` with
    activation instructions; the owner activates post-merge (SETUP §4).
    This is the one approved deliverable delivered as an owner action
    instead of a direct change — disclosed here and in the PR.

## 10. Files changed (vs `main`)

**Backend — added:** `Contracts/MusicProvider.php`,
`Services/Providers/DeezerMusicProvider.php`, `Services/CatalogueService.php`,
`Controllers/Api/V1/{SearchController, ResolveController, CatalogueController}.php`,
`Controllers/Api/V1/Concerns/ResolvesSlugs.php`,
`Controllers/Web/Music/GenreController.php`, `config/cors.php`,
`resources/views/web/music/{artists,albums}/index.blade.php`,
`web/music/tracks/index.blade.php`, `web/music/genres/show.blade.php`,
`web/music/partials/pager.blade.php`, `tests/Fixtures/FakeMusicProvider.php`,
7 test files (57 tests).
**Backend — modified:** `routes/{api,web}.php`, `config/shirin.php`,
`bootstrap/app.php` (429 envelope), `composer.json` (+guzzle),
`.env.example`, `phpunit.xml` (env), `FeatureFlagService`, `SettingsSeeder`,
`AppServiceProvider`, `TrackResource` (shared `format()`), model
`scopeSearch` ×3, `layouts/app.blade.php`, `web/home.blade.php`,
`lang/{en,fa}/{music,nav}.php`, `public/css/shirin.css` (additive),
`ApiV1Test` (flags key).
**Frontend — modified (9 files, disclosed above):** `js/{api,app,config,
router,state,ui,utils,views}.js`, `service-worker.js`. `index.html`,
`css/`, `assets/`, `manifest.json`: untouched.
**Repo:** `docs/ci/backend-tests.yml` (kept in place; activation
instructions in its header — see §2).
**Migrations: NONE. Packages: +`guzzlehttp/guzzle ^7.8` (PKG-03).**

## 11. Git

Commits on `arena/01a0826b-surprise` (stands in for
`phase-2-5-catalogue-delivery`):

```text
docs(phase-2.5): add implementation plan (pre-approval draft)
ci: stage backend test workflow for activation
feat(api): add CORS allowlist for the static frontend
feat(provider): add music provider contract and Deezer adapter
feat(api): add nested catalogue endpoints
feat(api): add merged search endpoint
feat(api): add featured catalogue and resolve endpoints
feat(web): add public catalogue index pages
feat(web): point the player at the Laravel API
feat(web): add hash-link redirects to catalogue pages
feat(pwa): bump service-worker cache and re-register assets
chore: remove stray empty file from a shell quoting slip
```

PR: `Phase 2.5: Catalogue delivery` (base `main`). Merge only when the
owner has run the suite locally (CI activates post-merge) and approved.
The plan commit predates approval and rides the same PR (locked
single-branch environment, PR #1/#2 precedent).
