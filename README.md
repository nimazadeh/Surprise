# SHIRIN

### The Shirin David Music Experience

> A premium, mobile-first, **unofficial fan-made** music discovery and authorized-preview web application for Shirin David.

SHIRIN is a deliberately framework-free music product built with semantic HTML, modern CSS, and modular Vanilla JavaScript. It is designed around a native-feeling mobile player while providing intentional tablet, desktop, and ultrawide layouts.

**This project is not affiliated with, endorsed by, or an official website of Shirin David.**

---

## Highlights

- Cinematic mobile-first home, artist, albums, album detail, tracks, search, library, queue, lyrics, and full-player experiences
- Live Shirin David artist, release, track, artwork, and 30-second preview metadata — served by the SHIRIN Laravel backend when `apiBaseUrl` is configured (owned catalogue first, provider metadata merged behind a kill switch), or directly from the public Deezer catalogue API
- A provider abstraction (`MusicProvider`) rather than provider-shaped UI code
- JSONP transport kept as the automatic fallback so the static app works without exposing a backend credential or relying on inconsistent public CORS headers
- One global HTML5 audio controller with one active preview element at a time; retired preview events cannot contaminate the next selection
- Authorized Deezer previews only; no audio downloading, scraping, proxying, caching, or DRM circumvention
- Persistent mini-player on mobile and desktop transport player
- Full-screen immersive player with artwork-derived ambient colors, a lightweight Canvas visualizer, progress, volume, speed, shuffle, repeat, sharing, favorites, and queue controls
- Lyrics controller architecture that supports timed and untimed licensed lyrics when a future provider supplies them; it honestly reports that Deezer's public API does not provide licensed lyric text/timing
- Local favorites, artist follow state, recent plays, queue, volume, repeat, shuffle, and speed preferences with `localStorage`
- Queue ordering via drag and drop plus button alternatives; bottom-sheet queue on small screens
- Debounced global search with loading, empty, and error states
- Instant local-catalogue first paint, graceful live API/cache/fallback states, a 6.5-second provider watchdog, offline indicator, image fallbacks, and toast feedback
- Accessible icon labels, visible focus handling, semantic headings, keyboard Escape handling, and reduced-motion support
- Hash routing with shareable `#/album/{id}` and `#/track/{id}` paths
- Manifest and static-only service-worker preparation for PWA installation

---

## Player stability update (September 2026)

This maintenance release fixes the player interaction regressions reported against the GitHub Pages build:

- **Reliable next/previous preview playback:** each newly selected authorized preview receives a fresh native HTML5 audio element. Retired URLs are stopped and their late `error`, `pause`, `loadstart`, and `timeupdate` events are ignored by a load-token guard, so they cannot overwrite state for the next track. The visualizer remains decorative Canvas motion and never routes or mutes third-party audio through Web Audio. A signed-preview failure also refreshes once automatically before showing an error.
- **No player-shell flashing:** player transport state uses a dedicated DOM synchronizer, not a renderer. Play/pause, progress, volume, shuffle, repeat, speed, favorite, status, queue changes, and lyrics changes cannot reach the mini-player/full-player markup builders. The player shell, controls, canvas, and artwork image elements are kept mounted and their attributes/text are patched in place.
- **Working close controls:** panel and sheet backdrops now use separate dismissal metadata instead of sharing `data-action` with their close × buttons. The Lyrics and Track options × buttons therefore always dispatch their own close action; backdrop dismissal and Escape remain available.
- **Sharp handset artwork:** responsive image candidates are generated from the large Deezer artwork URL with truthful physical width descriptors. The real 56×56 `cover_small` resource is no longer mislabelled as a 250px candidate. Full-player art is constrained to 640px-or-larger candidates when the provider offers them.
- **Cache rollout:** the static service-worker cache key is bumped to `shirin-static-v7`, so an installed copy receives this JavaScript update rather than retaining the older player implementation offline.

---

## Backend catalogue integration (September 2026, Phase 2.5)

The player can now be pointed at the SHIRIN Laravel backend (`backend/`) instead of calling Deezer directly:

- **`ShirinApiProvider`** (in `js/api.js`) implements the provider contract over the backend's `/api/v1` endpoints: a one-round-trip featured bootstrap for the home screen, merged search, and resolve-driven deep loads. The backend serves the owned catalogue first and merges provider metadata behind its own kill switch, so the static app stays honest about sources (`SHIRIN API` vs `Deezer` in the data-source label).
- **Stable owned ids:** owned catalogue records use their immutable backend slug as the record id, which keeps queues, favorites, and shareable links stable.
- **Hash-link redirects:** a cold entry (shared link or reload) to `#/album/{id}` or `#/track/{id}` lands on the backend SEO page when the item is owned content — including legacy provider ids that have an owned twin. In-app navigation never redirects mid-session.
- **Automatic fallback:** if the backend is unreachable, the app falls back to the direct Deezer JSONP transport, then to the offline demo catalogue. With `apiBaseUrl` empty (the default) the app behaves exactly as the direct-provider version.
- **Cache rollout:** the service-worker cache key is bumped to `shirin-static-v8` so installed copies receive the API-mode player. The API origin is never cached (network-only).

To enable it, set `apiBaseUrl` in `js/config.js` to the backend origin and add that origin pair to the backend's `FRONTEND_ORIGINS` (see `docs/PHASE_2_5_SETUP.md`). Both values are public configuration — no secrets ship to the browser.

---

## Stack

No package manager or build step is needed.

- HTML5
- CSS3 (custom properties, responsive grid, safe-area support, media queries, animation)
- Vanilla ES Modules
- HTML5 Audio API
- Canvas visualizer enhancement (never placed in the audio-output path)
- Deezer public catalogue API / authorized preview URLs

**Not used:** React, Vue, Angular, Svelte, jQuery, TypeScript, Tailwind, Bootstrap, Node build tooling, or a frontend framework.

---

## Run locally

> **Arena file viewer note:** a sandboxed file preview may block linked CSS/ES modules and third-party provider scripts. `index.html` includes a small self-contained escape hatch so that constrained preview will never remain on the splash screen; use the **Live Preview** or a local static server for the full interactive application.

Serve the project from a local HTTP server. Do **not** open `index.html` through `file://`, because ES modules, provider requests, and the service worker need an HTTP origin.

```bash
cd /path/to/shirin
python -m http.server 5500
```

Then visit:

```text
http://localhost:5500
```

Alternative static servers are also fine, for example:

```bash
python3 -m http.server 5500
# or
npx serve .
```

Node.js is **not required** for the frontend itself.

---

## Project structure

```text
.
├── index.html                  # Semantic application shell + SEO/PWA metadata
├── manifest.json               # PWA-ready manifest
├── service-worker.js           # Static-only cache strategy
├── README.md
├── assets/
│   ├── icons/
│   │   └── icon.svg            # App icon
│   └── images/
│       ├── artist-fallback.svg # Local abstract fallback art
│       ├── artwork-fallback.svg
│       └── og-cover.svg
├── css/
│   ├── reset.css
│   ├── variables.css           # Design tokens
│   ├── base.css
│   ├── layout.css              # Mobile-first page / navigation layout
│   ├── components.css          # Cards, buttons, rows, sheets, states
│   ├── player.css              # Mini and immersive player layouts
│   ├── animations.css
│   └── responsive.css          # Narrow / large-screen refinements
└── js/
    ├── app.js                  # Bootstrap and catalog lifecycle
    ├── api.js                  # Provider contract, ShirinApiProvider, Deezer adapter, normalizer, fallback
    ├── animations.js           # Ambient artwork color / press feedback helpers
    ├── config.js               # Public-only runtime configuration
    ├── favorites.js            # Favorites and recent history persistence
    ├── lyrics.js               # Lyrics lifecycle and time synchronization contract
    ├── icons.js                # Inline SVG icon system
    ├── player.js               # Single global Audio player controller
    ├── player-view.js          # Player / lyrics / queue rendering layer
    ├── queue.js                # Queue state, ordering, navigation
    ├── router.js               # Lightweight hash routing
    ├── search.js               # Debounced search controller
    ├── state.js                # Central observable application state
    ├── ui.js                   # Interaction delegation, UI lifecycle, and routing renders
    ├── views.js                # Page and reusable card / list renderers
    └── utils.js                # Safe formatting, storage, normalization helpers
```

---

## Architecture

```text
SHIRIN Laravel backend (/api/v1)      ← when apiBaseUrl is set (Phase 2.5)
        ↓  falls back automatically to
Deezer public API / future lawful provider
                ↓
       api.js provider adapter
                ↓
    normalization to artist / album / track records
                ↓
     state.js observable AppState
                ↓
      ui.js components / renderer
                ↓
     player.js + lyrics.js controllers
```

### Provider contract

`js/api.js` defines a small provider interface:

```js
class MusicProvider {
  async searchArtist(query) {}
  async getArtist(artistId) {}
  async getAlbums(artistId) {}
  async getAlbum(albumId) {}
  async getAlbumTracks(albumId) {}
  async getTrack(trackId) {}
  async search(query) {}
  async getLyrics(trackId) {}
  async getPlayback(track) {}
}
```

The UI consumes normalized records (`artist`, `album`, `track`) and does not need to know about Deezer response shapes. A future official/authorized Spotify, Apple Music, or other provider adapter can implement the same methods without rewriting the player UI.

Three implementations now exist behind this contract: `ShirinApiProvider` (the Laravel backend, owned-first), the direct Deezer JSONP adapter (automatic fallback), and the offline demo catalogue (last resort). The active chain is chosen by `CONFIG.provider` / `CONFIG.apiBaseUrl` in `js/config.js` — see "Backend catalogue integration" above.

### State

`AppState` is intentionally small and centralized. It manages catalogue status, current route, player state, queue, playback preferences, favorites, recently played metadata, lyrics status, online state, and transient UI state. UI work is selectively updated — progress updates do not rerender the whole page.

---

## API configuration and keys

The stock app needs **no API key**.

`js/config.js` contains public configuration only:

```js
export const CONFIG = {
  provider: 'shirin-api',            // 'shirin-api' | 'deezer-public'
  apiBaseUrl: '',                    // backend origin, e.g. 'https://api.example.com';
                                     // '' keeps the app on the direct Deezer provider
  deezerBaseUrl: 'https://api.deezer.com',
  artistQuery: 'Shirin David',
  deezerArtistId: '7312776'
};
```

### Why no hidden API key?

A static browser application cannot keep a secret. Any value shipped to a browser can be viewed by a user. This project therefore does **not** put private provider secrets in client-side code.

If you replace Deezer with a service requiring a confidential client secret, add a small secure backend/proxy and keep the secret there. Never commit it to `config.js`, HTML, or a public repository.

### Why JSONP?

Deezer's public endpoints can have inconsistent browser CORS behavior across environments. The adapter requests only public metadata through a short-lived script tag callback. This keeps the app static and avoids a proxy. The app does not use JSONP for anything other than provider metadata returned by the Deezer endpoint.

---

## Playback limitations and legal behavior

### What works

When Deezer supplies a `preview` URL for a track, SHIRIN plays that **provider-authorized, time-limited (typically 30-second) preview** with the single HTML5 Audio controller.

### What SHIRIN intentionally does not do

- It does not claim to provide full-length streaming.
- It does not download, save, cache, redistribute, decrypt, or proxy copyrighted audio.
- It does not scrape unofficial music sites.
- It does not bypass DRM, subscription gates, or browser autoplay policies.
- It does not attempt autoplay before a user initiates playback.

If no preview is provided, the selected track still has a polished player state, but the app transparently says that an authorized preview is unavailable. The track’s Deezer page can be opened from the context menu when the provider supplies a link.

Preview URLs can expire. The player refreshes the provider track before requesting playback, then gracefully handles a failed/expired preview.

For real full playback, integrate an official provider SDK, embed, or authenticated server-side flow in accordance with that provider’s terms.

---

## Lyrics limitations

Deezer's public catalogue API does not expose licensed lyric text or timestamped lyrics. For that reason the default UI shows:

> Lyrics are currently unavailable.

It never fabricates, scrapes, or synchronizes lyrics that it does not have rights to display.

`LyricsController` is still implemented as a proper timed-lyrics interface. An authorized future provider can return data such as:

```js
{
  status: 'ready',
  synced: true,
  lines: [
    { time: 12.4, text: 'Licensed lyric line' },
    { time: 16.1, text: 'Next licensed lyric line' }
  ]
}
```

The controller then synchronizes the active line to `audio.currentTime`, highlights it, and smoothly keeps it centered. Untimed licensed lines can be shown with an explicit “Not synchronized” label.

---

## Offline and fallback behavior

- The browser detects `navigator.onLine` changes and shows a quiet offline notice.
- A small local no-audio catalogue renders immediately on first paint, then live provider data replaces it when available. This means third-party API slowness can never hold the UI on an empty loader.
- A 6.5-second catalogue watchdog switches to cache/demo data if the provider does not complete.
- A recent safe metadata snapshot is stored in local storage for a limited time.
- If live data and cache are both unavailable, a **clearly separated, no-audio fallback demo catalogue** is used. It has limited artist / album / track metadata and local abstract placeholder art only.
- The fallback does not contain copyrighted audio or pretend that playback is available.
- The service worker caches same-origin static app files only; it deliberately does **not** cache third-party music APIs, artwork CDNs, or audio previews.

---

## Local persistence

The following non-sensitive data is stored under the `shirin.music.v1` localStorage namespace:

- Favorited track metadata
- Favorited album metadata
- Local artist-follow preference
- Up to 20 recently played track metadata records
- Queue metadata and order
- Volume, mute, shuffle, repeat, and playback-rate preferences
- Short-lived safe catalogue metadata snapshot

No credentials, payment data, personal profile data, or music files are stored.

To reset local product data in development, clear site storage in browser DevTools.

---

## PWA readiness

`manifest.json` and `service-worker.js` are included. The current service worker is intentionally conservative and network-first: it caches only same-origin static assets as an offline fallback, while always preferring a fresh deployment response. It never caches copyrighted audio or third-party provider responses. Arena preview hosts intentionally unregister prior service workers during iteration so an old preview cache cannot hide newly written modules. Install behavior varies by browser and must be validated on the deployment origin.

---

## Accessibility and UX notes

- Mobile layouts reserve room for `env(safe-area-inset-bottom)`.
- Controls have touch-friendly target sizes.
- All icon buttons have meaningful `aria-label` values.
- Keyboard focus is visible; Escape closes sheets and player layers.
- Queue reordering has both drag/drop and move button affordances.
- Motion reduces automatically under `prefers-reduced-motion: reduce`.
- Images have useful text alternatives (or empty `alt` where they are purely decorative).
- Search is debounced by 350ms to avoid request-per-keystroke behavior.

---

## Deployment notes

Deploy the folder to any static host (GitHub Pages, Netlify, Cloudflare Pages, S3 + CDN, etc.) over HTTPS. Verify the host permits script tags to `https://api.deezer.com` and media playback from Deezer’s preview CDN. No backend is required for the current preview-and-metadata product.

If you add a privileged music API, user authentication, a private credential, or a licensed lyric API, add a secure backend before deployment.

---

## Future improvements

- Official full-playback SDK integration after obtaining appropriate authorization
- Authorized lyric provider adapter
- Provider pagination and richer release filters
- Frontend sign-in and user library sync (playlists, favorites, history) against the SHIRIN backend — Phase 3; local-only persistence today
- Explicit PWA install prompt and offline page
- More provider-supplied related-artist and editorial metadata
- Automated accessibility, visual-regression, and mobile-device tests

---

## Attribution / legal

This is a fan-made, educational product experience. Shirin David’s name, music, images, and release metadata remain the property of their respective rights holders. Deezer names and assets are subject to Deezer’s terms and branding requirements. Review all relevant provider terms, image usage rights, and jurisdictional requirements before public deployment.
