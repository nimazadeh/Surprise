# SHIRIN — Phase 2 Music Core Report

Date: 2026-09-08 · Branch: `arena/01a08237-surprise` (stands in for
`phase-2-music-core`) · Scope: Music Domain Architecture & Foundation.

> This report is the Phase 2 implementation record. It also covers the
> workflow-required `PHASE_2_IMPLEMENTATION` (here), `PHASE_2_TEST_REPORT`
> (§6), `PHASE_2_SECURITY_CHECK` (§8), and `PHASE_2_DATABASE_REPORT`
> (`PHASE_2_DATABASE_DESIGN.md`). No `PHASE_2_SETUP` deltas: setup is
> unchanged (no new env vars, drivers, or daemons).

## 1. Overview

Phase 2 builds the music-content foundation the whole platform stands on:
catalogue database (genres/artists/albums/tracks + slug redirects), lean
Eloquent models, a premium-quality admin CMS, validated write paths, an
enveloped read API shaped for the future player migration, SEO detail pages,
cover-art storage behind an interface, bilingual structures, and 43 tests.

Out of scope by design (future phases): downloader, payments/premium,
audio processing/waveforms/converters, full player frontend, ads,
upload pipeline, playlists/user library, provider enrichment, search API,
stream signing. The original ROADMAP Phase 2 delivery items
(dual-source adapter, player-on-API, search, signing, hash-link redirects,
PWA re-register) move to **Phase 2.5 — Catalogue delivery** (see ROADMAP.md):
this phase ships the domain they all consume.

## 2. Database changes

5 additive migrations (`2026_09_08_000003`–`000007`), fully reversible,
MySQL + SQLite compatible (no fulltext per C-14):

- `genres` — taxonomy (name/slug/description, no soft deletes).
- `artists` — name/slug/bio/image/country/language/featured/status +
  `source`/`provider_id` (dual-source ready) + seo fields + soft deletes.
- `albums` — artist FK (cascade), title/slug/description/cover/release
  date+year/type (album/single/ep/compilation)/status + source/provider/seo
  + soft deletes.
- `tracks` — nullable album FK (nullOnDelete, loose singles), artist FK
  (cascade), title/slug/description/track_number/`duration_sec` (integer
  seconds, probe-ready)/nullable genre FK (nullOnDelete)/language/
  `lyrics_available` flag/status + source/provider/seo + soft deletes.
- `slug_redirects` — C-01 table (unique per type + old slug).

Post-migrate: **22 tables**. Rollback: `migrate:rollback --step=5`.
Seeders: + `GenreSeeder` (8 idempotent base genres) in `DatabaseSeeder`.
Full schema, indexes, FK rationale, and Phase 0 deviations:
`docs/PHASE_2_DATABASE_DESIGN.md`.

## 3. Models created

`Artist`, `Album`, `Track`, `Genre` (+ `SlugRedirect`, `Concerns\HasSlug`):

- Relationships exactly per spec (Artist hasMany Albums/Tracks; Album
  belongsTo Artist, hasMany Tracks; Track belongsTo Artist/Album/Genre;
  Genre hasMany Tracks).
- `$fillable` allow-lists, `casts()` (booleans, dates, integers), slug route
  keys, `published`/`featured`/`ordered`/`search` scopes, `isPublished()`.
- `HasSlug`: auto-generation, C-04 collision suffixes
  (`{slug}` → `{slug}-{artist}` → numeric → uuid8), automatic C-01 redirect
  rows on rename, C-03 reservation across soft-deleted rows.
- No business logic in models; artwork URLs via a thin presentation accessor
  resolving the `CoverArtService` contract.

## 4. Admin features (`/admin`, `music.manage` permission)

- Artists / Albums / Tracks / Genres: paginated file-card UI in the existing
  admin shell (toolbar, LIKE search, status pills, counts, artwork thumbs,
  view-page links, publish/unpublish toggles, delete with confirm).
- Create/edit forms with old-input repopulation, per-field errors, relation
  selects (artist/album/genre), file inputs for artist image + album cover
  (validated, UUID-stored, old file purged on replace/delete).
- 8 Form Requests (Store/Update × 4): required fields, length limits,
  `alpha_dash` + unique slugs (self-ignored on update), `in:` enumerations,
  `exists:` FK guards, image mimes/size caps.
- Config-driven nav wired (`admin.artists/albums/tracks/genres.index`),
  dashboard music stats, full en/fa strings (`lang/*/music.php`, 122 keys,
  parity-verified), additive CSS only (no framework, RTL-safe).
- Authorization: `auth` + `admin` middleware, `can:music.manage` route group,
  permission checks in every Form Request, role-based owner bypass
  (zero ID checks; CI grep gate clean).

## 5. API endpoints (all `GET`, enveloped, throttled 120/min)

| Endpoint | Behavior |
|---|---|
| `GET /api/v1/artists` | paginated published artists (+ album/track counts) |
| `GET /api/v1/artists/{slug}` | single artist, player-ready shape |
| `GET /api/v1/albums` | paginated published albums (+ artist, track count) |
| `GET /api/v1/albums/{slug}` | single album (+ artist_name/slug) |
| `GET /api/v1/tracks` | paginated published tracks (+ artist/album/genre) |
| `GET /api/v1/tracks/{slug}` | single track (relations, `duration` + `duration_human`) |

- Envelope `{success, data, message}` via `ApiResponse`; pagination nests as
  `data: {data, links, meta}`; unknown/draft/trashed slugs → enveloped
  `NOT_FOUND`; `per_page` capped at 100.
- Resources mirror the Vanilla JS normalized record (`artistName`,
  `albumTitle`, `artwork`, `duration`, `source`, `url`) for a low-friction
  player migration. Track artwork resolves from the album cover.
- No audio URLs exist anywhere (R-01 stands).

## 6. Tests (43 new; 79 total with Phase 1's 36)

| Suite | Tests | Covers |
|---|---|---|
| `MusicModelTest` | 15 | relationships, nullable relations, scopes, slug gen/collision/artist-suffix, rename redirects, slug reservation, FK null/cascade |
| `MusicAdminTest` | 14 | guest→login, user→403, editor indexes, artist/album/track/genre CRUD, invalid-data rejection, cover store/replace, role-based owner bypass (id≠1) |
| `MusicApiTest` | 8 | envelope, draft invisibility, player shape, 404 envelopes, relation embeds, duration_human, pagination cap |
| `MusicWebTest` | 6 | SEO meta/canonical/JSON-LD, album track list, track metadata, draft 404s, 301 on rename, cover proxy 200/404 + cache headers |

**Execution disclosure (honest):** the sandbox has no PHP runtime and no
packagist egress, so `php artisan test` could not run here (same constraint
as Phase 1). CI runs the full suite on push/PR — **do not merge red.**
Static verification passed instead: 58/58 route→controller@method targets,
30/30 view targets, 45/45 `route()` names, en/fa parity on all 5 lang files
(music 122, admin 33, nav 9, site 9, auth 19), brace balance, zero
hardcoded-ID patterns, `{!! !!}` only in hex-escaped JSON-LD, 10/10
migrations reversible, zero frontend files touched.

Localhost/staging manual checklist (post-merge):

- [ ] `composer setup` → migrate/seed clean on MySQL (22 tables, 8 genres)
- [ ] Editor login → artists/albums/tracks/genres CRUD + search + toggles
- [ ] Cover upload → visible in admin + on SEO pages + via `/media/covers/*`
- [ ] Slug rename in admin → old URL 301s to the new one
- [ ] `/api/v1/{artists,albums,tracks}[/{slug}]` envelopes + `per_page` cap
- [ ] Locale switch fa↔en on admin + SEO pages; `/.env` unreachable; `/up` 200

## 7. Future compatibility

- **Player:** API shape already matches the JS normalized record; `duration`
  (seconds) feeds seekbars; `url` links to SEO pages.
- **Uploads:** `duration_sec` awaits the probe job; `CoverArtService`
  contract absorbs resize/re-encode/CDN without caller changes; quarantine
  disk + validation matrix untouched and ready.
- **Playlists/library:** stable `tracks.id` PKs + published scopes are the
  join surface; favorites morph targets (artist/album/track) already exist.
- **Dual-source:** `source`/`provider_id` on all three catalogue models with
  defaults + indexes — enrichment lands without backfill.
- **i18n:** `language` discriminator today; translations expand via JSON
  columns or a translations table later (no restructure needed).
- **SEO:** slugs immutable-after-publish + 301 table + canonical + JSON-LD;
  sitemap/OG/hreflang are additive.
- **Multi-genre:** `track_genre` pivot can be added without touching `genre_id`
  consumers (primary genre stays).

## 8. Security notes (self-review vs SECURITY_PLAN)

| # | Check | Result |
|---|---|---|
| 1 | No hardcoded owner IDs | ✅ grep gate clean; owner bypass role-based + tested |
| 2 | Authorization on all music writes | ✅ route `can:music.manage` + request `authorize()` + admin middleware + banned blocks |
| 3 | Validation | ✅ 8 Form Requests; lengths, enums, exists-guards, image mimes/size |
| 4 | Mass assignment | ✅ explicit `$fillable` on all 5 models |
| 5 | XSS | ✅ `{{ }}` everywhere; only `{!! !!}` is hex-escaped JSON-LD (`JSON_HEX_TAG/APOS/AMP/QUOT`) |
| 6 | Upload safety | ✅ UUID names, collection allow-list, covers-only prefix guard, no traversal (server-built paths + strict route regex), 5 MB cap |
| 7 | SQL injection | ✅ Eloquent only; LIKE search uses bindings |
| 8 | Information disclosure | ✅ drafts/trashed → 404 (no existence oracle beyond slug); API envelope hides internals |
| 9 | Rate limiting | ✅ 120/min on music API group |
| 10 | Secrets | ✅ none committed; no `.env` |

Deferred (tracked, not missing): model policies (Phase 4 admin hardening;
middleware + request gates cover Phase 2), cover min-dimensions + re-encode
(upload pipeline phase), AV hook (VPS), `composer audit` in CI (active once
vendor installs run in CI).

## 9. Known limitations

- No catalogue index/browse pages yet (detail pages + API only) — Phase 2.5.
- No nested API routes (`/artists/{slug}/albums`, `/albums/{slug}/tracks`),
  no search/stream endpoints — Phase 2.5.
- No sitemap/OG/hreflang, no `slug` reuse, Latin-only slugs — later phases.
- No per-track artwork column (artwork inherits album cover) — additive later.
- Single primary genre per track (pivot later). No `composer.lock` yet
  (PKG-01, owner generates on localhost — unchanged, no new packages).
- PHPUnit executed in CI only (sandbox limitation, disclosed above).

## 10. Decisions

1. Prompt spec wins over Phase 0 where they differ (documented in
   `PHASE_2_DATABASE_DESIGN.md` §7); `source`/`provider_id` kept from Phase 0
   as cheap, defaulted dual-source readiness.
2. `album_id` nullable (loose singles) despite the prompt's "required" list —
   a field can be required-by-spec yet nullable-by-constraint; documented.
3. Status enum over `is_published`; single `language` over `*_fa/*_en`
   columns; `genre_id` FK over pivot (all reversible/additive later).
4. Authorization via middleware + Form Requests (Phase 1 pattern); policies
   deferred to Phase 4 with the rest of admin hardening.
5. Incidental one-line fix: `routes/web.php` referenced `MetaController`
   without import (latent 500 on `/meta/ping`) — fixed and covered by the
   existing route-target verification.

## 11. Files changed (vs `main`)

- Added: 5 migrations, 6 model files, 1 contract, 1 service (+ provider
  binding), 8 Form Requests, 4 admin + 3 API + 3 web-music + 1 media
  controllers, 3 API resources, 17 admin + 3 public Blade views,
  2 lang files, 4 factories, 1 seeder, 4 test files, 2 docs.
- Modified: `routes/web.php` + `routes/api.php`, `config/shirin.php` +
  `shirin_nav.php`, `DashboardController` + dashboard view, `layouts/app`
  (`@stack('head')`), `lang/*/admin.php`, `public/css/shirin.css`
  (additive), `DatabaseSeeder`, `ROADMAP.md`, `PROJECT_STATUS.md`,
  `SESSION_HANDOFF.md`.
- Frontend files touched: **NONE** (verified: `git diff main...HEAD` over
  root static paths is empty).
- Packages added: **NONE**.

## 12. Git

Commits on `arena/01a08237-surprise` (stands in for `phase-2-music-core`):

```text
feat(music): add catalogue database and Eloquent foundation
feat(music): add cover-art storage service
feat(admin): add music management foundation
feat(web): add music SEO foundation pages
feat(api): add music catalogue read endpoints
feat(music): add catalogue factories and genre seeds
test(music): add model, admin, API and web coverage
fix(music): restore route imports, SEO routes and artwork accessors
docs(phase-2): add database design and music core report
```

PR: `Phase 2: Music Core Domain Foundation` (base `main`). Merge only when
CI is green + owner approved. This branch also carries the two pre-Phase 2
workflow-doc commits (disclosed in the PR body, 1.5-style).
