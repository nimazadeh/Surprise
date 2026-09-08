# SHIRIN — Feature Priority Matrix (Phase 0.5)

> Single source of truth for "what ships when". Host column: SH = shared hosting OK.

## Must Have V1 (launch blockers)

| Feature | Why V1 | Host | Phase |
|---|---|---|---|
| Bilingual shell (fa RTL default + en LTR) | Core product promise | SH | 1 |
| Auth (register/login/verify/reset) + RBAC + OWNER | Accounts + governance | SH | 1 |
| Feature-flag system | Kill-switch for everything below | SH | 1 |
| Legal pages (terms/privacy/takedown contact) | R-01 acceptance requirement | SH | 1 |
| SEO public pages (artists/albums/tracks/genres) | Traffic + product promise | SH | 1–2 |
| Owned catalogue CRUD (admin) + dual-source read API | Independence from provider | SH | 2 |
| Player on `/api/v1` (stream signing, history hook) | The product IS the player | SH | 2 |
| Search (SQL, throttled) | Discovery baseline | SH | 2 |
| Playlists / favorites / reactions / history | Library promise | SH | 3 |
| Direct uploader (modal, progress, validation, probe) | Content pipeline | SH | 2–3 |
| Upload review queue (approve/reject) | Anti-abuse + R-01 gate | SH | 4 |
| Admin (users/music/uploads/flags/audit/analytics-v1) | Operability | SH | 4 |
| Ads (placements/campaigns/tracking) | Revenue model | SH | 5 |
| Download Center (flag-gated, local driver) | V1 premium promise (may launch OFF) | SH | 5 |
| Backups + health + rate limits + headers | Survival basics | SH | 1–4 |

## Should Have (important, can slip one phase)

| Feature | Notes | Host | Phase |
|---|---|---|---|
| `localStorage → API` first-login merge | Protects existing users' data | SH | 3 |
| Public profiles + public playlists | Social discovery | SH | 3 |
| 2FA (TOTP, admin/owner) | Raise before real traffic | SH | 4 |
| CSV analytics export | Owner reporting | SH | 4 |
| Music Lab PHP tools (tag/cover/metadata) | Differentiator, no FFmpeg | SH | 6 |
| Sitemap auto-ping + Search Console docs | SEO maturity | SH | 2+ |

## Future (VPS or growth-gated — explicitly NOT V1)

| Feature | Gate | Phase |
|---|---|---|
| Audio Cutter / Converter (FFmpeg) | VPS + workers | 7 |
| Background transcoding + multi-quality | VPS + workers | 7 |
| Redis / Horizon / Reverb realtime | VPS | 7 |
| CDN + S3 media | Traffic/cost trigger | 7 |
| Chunked/resumable uploads | VPS timeouts | 7 |
| Scout/Meilisearch search | Catalogue size trigger | 7+ |
| Mobile apps (native) | API v1 stable + demand | 8+ |
| Social login, comments, follows-feed | Demand-gated | 8+ |
| Billing/subscriptions | Out of scope until legal + provider review | — |

## Anti-scope (will NOT build)
Billing system, video streaming, podcasts, artist self-claim portal, multi-tenant SaaS —
revisit only with a new Phase 0 for that product.
