# SHIRIN — API Architecture (Phase 0)

> Design only. Versioned REST JSON API serving the web player today and mobile apps tomorrow.

## 1. Conventions

- **Base:** `/api/v1` (version in URL; never break v1 — ship v2).
- **Auth:** Sanctum — SPA cookie for first-party web, Bearer tokens with abilities for apps.
- **Envelope:**

```json
{ "data": {}, "meta": { "page": 1 }, "message": "ok", "code": "OK" }
{ "message": "Feature disabled.", "code": "FEATURE_DISABLED", "errors": {} }
```

- **Resources:** Eloquent API Resources mirror the current JS normalized shape
  (`id, title, artistName, albumTitle, artwork, duration, explicit, source…`)
  for a low-friction player migration.
- **Pagination:** cursor pagination on feeds/history (`?cursor=`), offset on admin lists.
- **Locales:** `Accept-Language: fa|en` or `?locale=`; localized fields resolved server-side.
- **Rate limits:** global 120/min; search 30/min; auth 5/min; downloads quota-based.
- **Errors:** stable `code` strings (`VALIDATION`, `UNAUTHENTICATED`, `FORBIDDEN`,
  `FEATURE_DISABLED`, `QUOTA_EXCEEDED`, `NOT_FOUND`, `RATE_LIMITED`, `SERVER_ERROR`).

## 2. Endpoint map (v1)

```text
auth        POST /auth/register|login|logout  GET /auth/me
artists     GET /artists  GET /artists/{id}  GET /artists/{id}/albums|top-tracks
albums      GET /albums   GET /albums/{id}    GET /albums/{id}/tracks
tracks      GET /tracks   GET /tracks/{id}    GET /tracks/{id}/stream (signed)
genres      GET /genres   GET /genres/{id}/tracks
search      GET /search?q=&type=track|album|artist|playlist
player      POST /plays  (history + counters)
playlists   CRUD /playlists  + /playlists/{id}/items (add/move/remove/reorder)
library     GET|POST|DELETE /favorites  POST /reactions (like|dislike toggle)
history     GET|DELETE /history
uploads     POST /uploads  GET /uploads/mine  (chunk protocol on VPS)
downloads   GET /downloads  POST /downloads/{track} (flag + quota enforced)
lab         POST /lab/jobs  GET /lab/jobs/{id}  (flag enforced)
ads         GET /ads?placement=  POST /ads/{id}/click (impression via beacon)
meta        GET /meta/translations  GET /meta/flags (public-safe subset)
admin       /admin/* mirrors with permission middleware (users, music, ads, settings…)
```

## 3. Streaming & downloads

- `GET /tracks/{id}/stream` returns a **5-minute signed URL**, never the file:
  owned → signed `/media` URL; Deezer-sourced → provider preview URL (client-direct, never proxied).
- Download Center: `POST /downloads/{track}` checks flag + tier + quota, logs the row,
  returns signed URL with `Content-Disposition: attachment`.

## 4. Realtime & background (future)

- v1 is request/response + polling-safe. WebSockets (Reverb) deferred to VPS phase for
  upload progress and lab job status; v1 clients poll `GET /lab/jobs/{id}`.
- Webhooks/outbox pattern reserved for v2 third-party integrations.

## 5. Compatibility with the current frontend

| Current (static) | Future (Laravel) |
|---|---|
| `DeezerProvider.search()` | `GET /api/v1/search` (owned first, provider enrichment) |
| `getAlbumTracks()` | `GET /api/v1/albums/{id}/tracks` |
| `getPlayback()` | `GET /api/v1/tracks/{id}/stream` |
| `localStorage` favorites/recents | `POST` merge on first login → API source of truth |
| Hash routes `#/album/{id}` | 301/JS redirect → `/albums/{slug}` (SEO) |

## 6. Versioning & deprecation policy

- Additive changes need no version bump. Breaking changes → `/api/v2` + 6-month v1 sunset
  with `Sunset` + `Deprecation` headers and changelog in `docs/`.
