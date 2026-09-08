# SHIRIN — System Architecture (Phase 0)

> **Status:** Design blueprint only. No implementation in this phase.
> **Date:** 2026-09-08
> **Direction:** Evolution, not destruction. The existing frontend quality is preserved and migrated.

---

## 1. Current State Report (Codebase Audit)

### 1.1 Current structure

```text
/
├── index.html                  # App shell + splash + offline fallback
├── manifest.json               # PWA manifest
├── service-worker.js           # Static-only cache (shirin-static-v7)
├── css/ (8 files)              # reset, variables, base, layout, components,
│                               # player, animations, responsive
├── js/ (16 modules)            # app, api, config, state, player, player-view,
│                               # lyrics, queue, search, favorites, router,
│                               # views, ui, animations, icons, utils
└── assets/                     # SVG icon + fallback artworks + OG cover
```

- **Stack:** Pure HTML5 + CSS3 + Vanilla ES Modules. No framework, no build step, no Node dependency.
- **Entry:** `index.html` → `js/app.js` (bootstrap) → `Router` + `UI` + catalogue load.
- **State:** Central observable store (`js/state.js` → `AppState`) with selective subscribers.
- **Routing:** Hash routing (`#/home`, `#/search`, `#/album/{id}`, `#/track/{id}`, `#/library`, `#/player`).
- **Player:** Single global `PlayerController` (`js/player.js`), one active `HTMLAudioElement` at a time,
  load-token guards against stale media events, decorative Canvas visualizer (never in audio path).
- **Rendering:** `views.js` (pages/cards/lists) + `player-view.js` (player markup) + `ui.js`
  (event delegation, route rendering, sheet/modal handling, player-chrome patching without remount).
- **API layer:** `MusicProvider` interface + `DeezerProvider` (JSONP, normalized records) +
  `FallbackProvider` (no-audio local demo catalogue). UI consumes normalized
  `artist / album / track` records only.
- **Persistence:** `localStorage` namespace `shirin.music.v1` (favorites, recents, queue,
  playback prefs, catalogue snapshot). No credentials stored.
- **PWA:** Manifest + conservative network-first service worker (same-origin static only;
  never caches third-party audio/metadata).
- **Resilience:** Instant local-catalogue first paint, 6.5s provider watchdog, cache → fallback
  degradation, offline indicator, image fallbacks, toast feedback, reduced-motion support.

### 1.2 Reusable assets (migration value: HIGH)

| Asset | Reuse path |
|---|---|
| `css/variables.css` design tokens | Migrate 1:1 into Laravel layout (CSS custom properties unchanged) |
| `css/*.css` mobile-first system | Keep; add RTL layer + Blade page shells |
| SVG icon system (`js/icons.js`) | Keep as-is; usable from Blade via inline sprite or the same module |
| `player.js` controller logic | Adapt: same state machine, new API endpoints for owned catalogue |
| `player-view.js` markup | Adapt into Blade partials + keep JS patching strategy |
| `queue.js`, `favorites.js`, `lyrics.js` contracts | Keep contracts; swap `localStorage`-only persistence for API-synced persistence |
| `api.js` provider abstraction | Keep the pattern server-side: `MusicProvider` → Laravel service + adapters |
| Normalizer (`makeTrackRecord` / `makeAlbumRecord`) | Port to Laravel API Resources (same field names where possible) |
| PWA manifest + SW strategy | Keep; extend precache list for Laravel-built assets |
| Hash routes | Replace for public SEO pages with server routes; keep hash player states only |

### 1.3 Current limitations (why a backend is needed)

1. No user accounts, no server-side libraries/playlists/history.
2. No owned catalogue: 100% dependent on Deezer public metadata + 30s previews.
3. No uploads, no admin, no ads, no premium modules, no analytics.
4. Hash-only routing → not SEO-indexable; no SSR meta/structured data.
5. English-only, LTR-only; all UI strings hardcoded in JS templates.
6. `localStorage` only → data lost across devices, no sharing, no moderation.
7. No write-path security model (nothing to secure yet — greenfield for Laravel).

### 1.4 Technical debt (minor; codebase is clean)

- View templates are string-based; XSS safety depends on disciplined `escapeHTML` use (good so far).
- No automated tests; QA is manual.
- Catalogue hydration does N+1 album-detail fetches for track counts (small N, acceptable, but note it).
- JSONP transport is a Deezer-specific workaround; owned catalogue will use `fetch` + JSON.

### 1.5 Migration challenges

- Moving from hash routes to SEO server routes without breaking existing shared links
  (mitigation: redirect map `#/album/{id}` → `/albums/{slug}`).
- Splitting "Deezer catalogue" from "owned catalogue" in the data model (both must coexist).
- RTL conversion of a mature LTR stylesheet (logical properties + dir-aware tokens).
- Keeping the player UX identical while changing the data source (contract-first port).

---

## 2. Target System Architecture

```text
                         ┌─────────────────────────┐
                         │  Clients                │
                         │  Web (Blade + Vanilla JS│
                         │  PWA) · Future mobile   │
                         └────────────┬────────────┘
                                      │ HTTPS
                         ┌────────────▼────────────┐
                         │  Laravel 12 Application │
                         │  ┌───────────────────┐  │
                         │  │ Web layer (Blade) │  │  SEO pages, auth views,
                         │  │ SSR, RTL/LTR      │  │  admin dashboard
                         │  └─────────┬─────────┘  │
                         │  ┌─────────▼─────────┐  │
                         │  │ API layer /api/v1 │  │  Player, search, library,
                         │  │ Sanctum + Resources│  │  playlists, uploads, ads
                         │  └─────────┬─────────┘  │
                         │  ┌─────────▼─────────┐  │
                         │  │ Domain services   │  │  Music, Media, Ads,
                         │  │ Policies/Gates    │  │  Billing flags, Analytics
                         │  └─────────┬─────────┘  │
                         └────────────┼────────────┘
                    ┌─────────────────┼─────────────────┐
                    ▼                 ▼                 ▼
              ┌───────────┐    ┌────────────┐    ┌────────────┐
              │ Database  │    │  Storage   │    │   Cache    │
              │ MySQL/    │    │  local now │    │  file/db   │
              │ MariaDB   │    │  S3/CDN    │    │  → redis   │
              └───────────┘    │  later     │    │  on VPS    │
                               └────────────┘    └────────────┘
```

**Principles:**

1. **API-first domain, Blade-first public pages.** Every user-facing capability exists as an
   API endpoint; SEO pages are server-rendered Blade that hydrate the same Vanilla JS player.
2. **Provider-independent media.** No component may assume Deezer, a specific encoder, or a
   specific disk. Adapters sit behind interfaces (`MusicCatalogue`, `AudioProcessor`, `MediaDisk`).
3. **Shared-hosting compatible, VPS ready.** File/database drivers by default; Redis/S3/queues
   are configuration changes, not rewrites. See `DEPLOYMENT_PLAN.md`.
4. **Feature flags gate premium surface.** Download Center and Music Lab are modules that can
   be disabled without breaking core. See `MODULES.md`.
5. **Roles, never IDs.** No `user_id == 1` checks anywhere. OWNER is a role with a protected flag.

---

## 3. Stack Decision

| Layer | Choice | Rationale |
|---|---|---|
| Framework | **Laravel 12** | LTS-adjacent, Blade + API in one codebase, policies/gates, Sanctum, queues, scheduler, sitemap/SEO ecosystem |
| PHP | **8.2+ (8.3 preferred)** | Laravel 12 requirement; typed properties, readonly, performance |
| Database | **MySQL 8 / MariaDB 10.6+** | Universally available on shared PHP hosting; JSON columns for flexible metadata |
| Auth (web) | **Blade + session** | SEO pages + admin dashboard |
| Auth (API) | **Sanctum (token + SPA cookie)** | First-party JS player now, mobile apps later |
| Storage now | **Local disk (`storage/app`)** | Works on every shared host |
| Storage later | **S3-compatible + CDN** | Config-only switch via Flysystem |
| Media probing | **getID3 (PHP) now; FFmpeg later on VPS** | Duration/metadata without binaries on shared hosting |
| Queue now | **Database queue + cron `schedule:run`** | No daemon on shared hosting |
| Queue later | **Redis + Horizon/supervisor on VPS** | Same jobs, different driver |
| Cache now | **File cache** | Zero dependency |
| Cache later | **Redis** | Config change |
| i18n | **Laravel `lang/` + DB localized columns** | `fa` (RTL, default) + `en` (LTR); extensible |
| CSS/JS | **Keep Vanilla, add RTL layer** | Protects existing investment; no framework lock-in |
| Build | **No build required (phase 1–2); Vite optional later** | Keeps shared-host deploy as "upload files" |

**Hosting compatibility matrix:**

| Capability | Shared hosting | VPS |
|---|---|---|
| Core platform, SEO, auth, library, player | ✅ | ✅ |
| Uploads (direct, size-limited) | ✅ | ✅ |
| Chunked/resumable uploads | ⚠️ partial (timeouts) | ✅ |
| Audio cut/convert (FFmpeg) | ❌ | ✅ |
| Tag/cover editing (PHP libs) | ✅ | ✅ |
| Background transcoding | ❌ (cron-simulated only) | ✅ |
| Redis/Horizon/Reverb realtime | ❌ | ✅ |

---

## 4. Module Overview

Full map: [`MODULES.md`](./MODULES.md).

```text
Core      Auth · Users · Profiles · Artists · Albums · Tracks · Genres ·
          Player API · Playlists · Favorites · Reactions · History · Search · i18n/SEO
Admin     Dashboard · Users · Music · Uploads · Ads · Settings · Analytics · Audit logs
Premium   Download Center (flag) · Music Lab (flag)
```

Each module ships with: routes, policies, feature-flag key, analytics events, tests.

---

## 5. Roles & Permissions (summary)

Full design: [`SECURITY_PLAN.md`](./SECURITY_PLAN.md).

```text
OWNER (protected, unlimited) > SUPER ADMIN > ADMIN >
CONTENT MANAGER · ADS MANAGER > PREMIUM USER > NORMAL USER (default)
```

> Phase 1 status: implemented subset is `owner/admin/editor/premium_user/user`
> (Spatie Permission, decided Phase 1). Full 7-role split in Phase 4.

- Spatie-style RBAC *or* first-party `roles/permissions` tables (decision at Phase 1 start;
  both satisfy the "no hardcoded IDs" rule).
- OWNER: `is_protected` flag — cannot be demoted/deleted/locked; bypasses subscription checks
  via `Gate::before`, never via ID comparison.

## 6. Feature Flags (summary)

| Flag | Controls | Modes |
|---|---|---|
| `downloads.enabled` | Download Center visibility + routes + API | off / public / registered / premium |
| `music_lab.enabled` | Music Lab tools | off / on |
| `music_lab.premium_only` | Lab access tier | on / off |
| `ads.enabled` | Ad rendering globally | on / off |
| `uploads.user_uploads` | Public upload availability | off / registered / premium |

Owner manages flags in Admin → Settings. Disabled modules return 404 (web) / `403 feature_disabled` (API).

## 7. Data, Storage, API, Deploy (pointers)

- Database: [`DATABASE_DESIGN.md`](./DATABASE_DESIGN.md)
- Media + uploads: [`STORAGE_PLAN.md`](./STORAGE_PLAN.md)
- REST API: [`API_ARCHITECTURE.md`](./API_ARCHITECTURE.md)
- Environments: [`DEPLOYMENT_PLAN.md`](./DEPLOYMENT_PLAN.md)
- Risks: [`RISKS.md`](./RISKS.md) — **read the music-licensing risk first.**

## 8. Migration Strategy (evolution path)

1. **Phase 1:** Laravel shell + Blade layout reusing `css/*` + RTL layer; SEO routes for
   artists/albums/tracks; existing player JS pointed at `/api/v1` with the same normalized
   record shape (`id/title/artistName/artwork/...`).
2. **Phase 2:** Owned catalogue (artists/albums/tracks) seeded/imported; Deezer becomes an
   *optional enrichment adapter*, not the only source.
3. **Phase 3:** Auth + server libraries (favorites/playlists/history) with
   `localStorage → API` one-time merge on login.
4. **Phase 4+:** Admin, ads, premium modules — all additive, core untouched.

**What is never migrated blindly:** hardcoded English strings (all go through `lang/`),
hash-only public URLs (redirect map to SEO slugs), Deezer-coupled assumptions.

## 9. Final Review Checklist

- [x] Scalable: stateless API + cacheable reads + driver-swappable queue/storage/cache.
- [x] Shared hosting supported: MySQL, file cache, DB queue, local disk, no daemons.
- [x] VPS migration path: env-only switches documented.
- [x] Security considered: RBAC, policies, upload validation, rate limits, admin hardening.
- [x] SEO considered: SSR Blade, slugs, meta/OG/JSON-LD, sitemap.
- [x] Ownership protected: OWNER role + protected flag + audit logs.
- [x] Premium flexible: flags + access modes, modules removable.
- [x] Mobile future: versioned token API from day one.
