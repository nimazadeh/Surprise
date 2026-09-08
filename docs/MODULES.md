# SHIRIN — Module Map (Phase 0)

> Design only. No code in this phase. Each module lists its **flag**, **routes**,
> **permissions**, **dependencies**, and **build phase**.

```text
Core
├── Authentication .............. login/register/verify/reset, Sanctum tokens
├── Users & Profiles ............ public profiles, avatars, preferences
├── Localization ................ fa (RTL) + en (LTR), language switcher
├── Artists ..................... profiles, discography, follow
├── Albums ...................... releases, tracklists
├── Tracks ...................... detail, streaming, reactions
├── Genres ...................... taxonomy + discovery
├── Search ...................... tracks/albums/artists/playlists
├── Player API .................. queue-safe playback, history hook
├── Playlists ................... CRUD, items, public/private, collaborate(v2)
├── Favorites ................... tracks/albums/artists
├── Reactions ................... likes / dislikes (tracks)
├── History ..................... listening history (server-side)
└── Notifications ............... system + content updates (v2)

Admin
├── Dashboard ................... KPIs, charts, health
├── User Management ............. roles, bans, premium grants
├── Music Management ............ artists/albums/tracks CRUD + moderation
├── Upload Management ........... review queue, approve/reject
├── Advertisement Management .... placements, campaigns, creatives
├── Settings .................... flags, SEO defaults, player defaults
├── Analytics ................... plays/views/users/downloads/ads
└── Audit Logs .................. who changed what, when

Premium (flag-gated)
├── Download Center ............. {off|public|registered|premium}
└── Music Lab ................... tag/cover/metadata/cut/convert tools
```

---

## Core modules

### Authentication
- **Routes (web):** `/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`
- **API:** `POST /api/v1/auth/{register,login,logout}`, `GET /api/v1/auth/me`
- **Permissions:** guest-only for forms; `auth` for logout/me.
- **Dependencies:** mail (verify/reset), rate limiter `auth`.
- **Phase:** 1. Notes: session for web, Sanctum tokens for API; social login is v2.

### Users & Profiles
- **Public:** `/users/{username}` (public playlists, favorites count — privacy-aware).
- **Private:** `/settings/profile`, `/settings/account` (language, avatar, password, delete).
- **Permissions:** users edit own profile (`UserPolicy`); admins manage all.
- **Phase:** 1–2.

### Localization
- **Mechanism:** Laravel `lang/fa`, `lang/en` + `locale` session/user preference + `/{locale?}` prefix strategy.
- **DB strategy:** localized columns (`title_fa`, `title_en`) on artists/albums/tracks/genres/playlists;
  fallback chain `requested → fa → en → any`.
- **UI:** `<html dir>` switching + RTL CSS layer; never hardcode strings in Blade or JS
  (JS strings via `window.__trans` payload or `/api/v1/meta/translations`).
- **Phase:** 1 (all new strings), existing player strings migrated in Phase 2.

### Artists / Albums / Tracks / Genres
- **SEO routes:** `/artists/{slug}`, `/albums/{slug}`, `/tracks/{slug}`, `/genres/{slug}`.
- **API:** `/api/v1/artists|albums|tracks|genres` + `/{id|slug}` + nested
  (`/artists/{id}/albums`, `/albums/{id}/tracks`, `/artists/{id}/top-tracks`).
- **Record shape:** API Resources mirror the current JS normalizer field names
  (`id, title, artistName, artwork, duration, preview/streamUrl, explicit…`)
  so the existing player needs minimal adaptation.
- **Dual catalogue:** `source = owned|deezer` on tracks/albums; owned rows carry files,
  Deezer rows carry `provider_id` + preview URL.
- **Permissions:** public read; `music.manage` for write.
- **Phase:** 1 (read + SEO), 2 (owned catalogue + enrichment).

### Search
- **Web:** `/search?q=` (SSR results for SEO on popular queries).
- **API:** `GET /api/v1/search?q=&type=` (debounced client, 350ms as today).
- **Engine:** SQL `LIKE`/fulltext now; Scout + Meilisearch on VPS later (interface first).
- **Phase:** 2.

### Player API
- **Endpoints:** `GET /api/v1/tracks/{id}/stream` (signed URL or preview URL),
  `POST /api/v1/plays` (history + counters), `GET /api/v1/queue/resolve`.
- **Rules:** stream URLs are short-lived signed URLs for owned files; Deezer previews
  proxied by rule **never** — client uses provider URL directly as today.
- **Phase:** 2.

### Playlists / Favorites / Reactions / History
- **Playlists:** `playlist` + `playlist_items` (ordered, `position`); public/private;
  detail `/playlists/{slug}` (public only indexed).
- **Favorites:** polymorphic (`favoritable_type/id`) for tracks/albums/artists/playlists.
- **Reactions:** one row per user+track (`like|dislike`), toggle endpoints.
- **History:** capped (last 100 server-side; client shows 20).
- **Migration:** on first login, merge `localStorage` favorites/recents/queue into API (idempotent).
- **Phase:** 3.

## Admin modules

| Module | Key screens | Key permissions |
|---|---|---|
| Dashboard | KPIs (plays, users, uploads, ad revenue events), charts, queue health | `admin.access` |
| Users | list, roles assign, ban/unban, premium grant/revoke | `users.manage`, `roles.assign` |
| Music | artists/albums/tracks CRUD, slug editor, publish/unpublish, feature flags per item | `music.manage` |
| Uploads | review queue, preview player, approve/reject + reason | `uploads.review` |
| Ads | placements, campaigns, creatives, schedule, impression/click stats | `ads.manage` |
| Settings | feature flags, SEO defaults, player defaults, mail, storage | `settings.manage` (OWNER + SUPER ADMIN only for flags) |
| Analytics | plays/views/users/downloads/tools/ads reports + CSV export | `analytics.view` |
| Audit logs | filterable log of admin actions | `audit.view` (OWNER + SUPER ADMIN) |

Admin URL prefix: `/admin` (configurable), all routes behind `auth` + `admin.access` + 2FA-ready.

## Premium modules

### Download Center (`downloads.*`)
- **Flag:** `downloads.enabled ∈ {off, public, registered, premium}`.
- **What it does:** governed file delivery (quality selection, per-user quotas, expiry),
  **not** a direct file dump. Every download is logged (analytics + abuse control).
- **Provider independence:** `AudioDownloadService` interface; local-disk driver now,
  transcoding driver (FFmpeg) on VPS later. Adding a driver must not touch controllers.
- **Routes:** `/downloads` (library), `/downloads/{track}`; API mirrors.
- **Permissions:** `downloads.access` resolved dynamically from flag + tier.
- **Phase:** 5 (exists in V1 scope but shippable disabled).

### Music Lab (`music_lab.*`)
- **Flag:** `music_lab.enabled`, `music_lab.premium_only`.
- **Tools:** Tag Editor · Cover Editor · Metadata Fixer · Audio Cutter · Audio Converter.
- **Hosting split:**

| Tool | Shared hosting | VPS |
|---|---|---|
| Tag Editor (PHP lib) | ✅ | ✅ |
| Cover Editor (GD/Imagick) | ✅ | ✅ |
| Metadata Fixer | ✅ | ✅ |
| Audio Cutter (FFmpeg) | ❌ | ✅ |
| Audio Converter (FFmpeg/queue) | ❌ | ✅ |

- Unavailable tools show an honest "requires full server — coming with VPS" state (same
  honesty pattern as today's lyrics/preview states).
- **Phase:** 6 (PHP-only tools), 7 (FFmpeg tools on VPS).

## Cross-cutting concerns per module

Every module ships with: feature-flag key (if user-visible) · policy · request validation ·
API Resource · analytics events · audit coverage (admin writes) · tests (see ROADMAP).
