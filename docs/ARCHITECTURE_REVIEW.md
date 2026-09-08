# SHIRIN — Architecture Review (Phase 0.5)

> Reviewer role: Principal Architect. Scope: all Phase 0 docs.
> Method: challenge every abstraction against solo-developer + shared-hosting reality.
> Verdict summary is at the bottom; details first.

## 1. Complexity verdict: KEEP / SIMPLIFY / REMOVE / POSTPONE

| Area | Verdict | Notes |
|---|---|---|
| Laravel 12 + Blade + API hybrid | **KEEP** | Correct for SEO + future mobile. No simpler stack covers both. |
| Dual-source catalogue (`owned\|deezer`) | **KEEP** | The single most important modeling decision. Protects launch when owned catalogue is small. |
| RBAC roles/permissions/policies | **KEEP** | Required by the 7-role model. But do NOT hand-roll: use Spatie Permission (see §2). |
| Feature flags in `settings` | **KEEP** | Small table, huge operational value (kill-switch without deploy). |
| `AudioProbeService` / `MediaDisk` interfaces | **KEEP** | Two interfaces, real driver swaps (getID3→FFmpeg, local→S3). Justified. |
| API v1 envelope + versioning | **KEEP** | Cheap to adopt early, expensive to retrofit. |
| Repository pattern (if anyone proposes it) | **REMOVE** | Eloquent + service classes are enough. Repositories add files without value at this scale. |
| Events/Listeners everywhere | **SIMPLIFY** | Use only for: user registered → welcome tasks; upload approved → attach; admin write → audit. Everything else: direct service calls. |
| Notifications module (v2) | **POSTPONE** | Table can exist; UI waits until Phase 3+. |
| Realtime (Reverb/WebSockets) | **POSTPONE** | Polling suffices for lab jobs/uploads in v1. |
| Chunked upload implementation | **POSTPONE** | Design kept (STORAGE_PLAN §3), code deferred to VPS phase. Direct ≤100MB now. |
| Scout/Meilisearch | **POSTPONE** | SQL search now; interface boundary kept. |
| Social login | **POSTPONE** | v2. Email auth only in v1. |
| Multi-quality transcoding | **POSTPONE** | Needs FFmpeg = VPS. v1 stores original + serves it (with bitrate cap). |

## 2. Laravel structure review (practical, not ceremonial)

**Recommended skeleton (solo-dev maintainable):**

```text
app/
├── Http/Controllers/{Web,Api,Admin}/   # thin: validate → service → response
├── Http/Requests/                      # one FormRequest per write op
├── Http/Resources/                     # API Resources (mirror JS record shape)
├── Models/                             # relationships + scopes + accessors only
├── Services/                           # domain logic: Catalogue, Media, Uploads,
│                                       # Downloads, Flags, Analytics, Search
├── Policies/                           # one per manageable model
└── Jobs/                               # ProbeUpload, IncrementCounters, RollupAnalytics…
```

- **No repositories, no DTO library, no action classes** in v1. A service method with
  typed args is clearer than 3 files per operation for a one-person team.
- **RBAC decision (final): use `spatie/laravel-permission`.** It is shared-host safe
  (pure PHP + migrations), removes ~400 lines of hand-rolled RBAC, and already handles
  caching. OWNER semantics (`is_protected` + `Gate::before`) are layered on top in
  `AuthServiceProvider` — documented in SECURITY_PLAN.md, no ID checks.
- **Jobs on shared hosting:** `database` queue + one cron `schedule:run` + a scheduled
  `queue:work --stop-when-empty` every minute. Caveat (§5): cheap hosts allow 15-min cron
  minimum → job latency up to 15 min; UX copy must say "processing" honestly. Counter
  increments must therefore be **read-tolerant** (cached counts, eventual consistency).
- **Events:** 3 max in v1 (listed above). Audit logging is a service call in admin
  controllers, not an event maze (explicit > magic for audit trails).

## 3. Module separation review

- Core/Admin/Premium split is sound. One correction: **Reactions vs Favorites looked
  duplicative — they are not**, but the docs must state the semantic split:
  Favorite = "save to my library" (collection), Reaction = "taste signal"
  (like/dislike, drives charts/recommendations). Keep both, document the difference
  in MODULES.md (done in this review; apply at Phase 1 start).
- `music_lab.*` and `downloads.*` correctly isolated behind flags + gates; verified no
  core route depends on them.
- Missing module acknowledgment: **Legal/CMS pages** (terms, privacy, takedown/DMCA
  contact). Add as a tiny `Pages` concern in Phase 1 (Blade views + settings-driven
  body text) — required before any public launch and before R-01 acceptance.

## 4. Media & upload review (critical area)

- **Shared-hosting feasibility: YES with documented limits.** Local disk + getID3 +
  direct uploads + DB queue all run on typical cPanel hosts. Verified against the
  STORAGE_PLAN validation matrix — no binary dependencies in the v1 path.
- **Migration to VPS: YES, genuinely config-level** for disk/cache/queue. Two honest
  exceptions (docs now corrected): Horizon needs a `config/horizon.php` + supervisor
  (small code, VPS-only), and Reverb needs an Echo client addition (deferred anyway).
- **Gaps found and fixed in this review:**
  1. Cover derivatives (thumbs) need GD or Imagick — must be on the host checklist
     (GD is near-universal; prefer GD code path, Imagick optional).
  2. `slug_redirects` table was referenced but never defined → added in
     DATABASE_CORRECTIONS.md.
  3. Rendition strategy clarified: v1 = original only (+ regenerated preview slice
     only if getID3+pure-PHP slicing proves reliable; otherwise preview = full file
     with client-side 30s limit clearly labeled, or provider preview for Deezer rows).
  4. Orphan sweeper + tmp purge jobs must exist from Phase 2 (disk fills silently otherwise).
- **Uploader UX:** architecture fully supports drag&drop + modal + progress + cover +
  metadata + client duration hint; server probe is truth. No changes needed.

## 5. Roles, flags, SEO, admin, API reviews

- **Owner & permissions: APPROVED.** `is_protected` + `Gate::before` + policy refusals +
  audit-on-attempt is the right pattern. Added requirement: break-glass recovery
  procedure documented offline (see SECURITY_REVIEW.md), and a CI grep gate for `== 1`.
- **Feature flags: APPROVED.** Modes (`off/public/registered/premium`) + 404/403 behavior
  verified. Added: flags must be cached (`Cache::rememberForever` + flush on change)
  to avoid a settings query per request.
- **SEO: APPROVED with upgrades.** SSR Blade + slugs + JSON-LD + sitemap is right.
  Upgrades: `hreflang` fa/en pairs, canonical on every public page, `lastmod` in
  sitemap from `updated_at`, and NOINDEX on `/admin`, `/settings`, unlisted playlists.
  Added: sitemap must be cached daily, not generated per hit.
- **Shared hosting: APPROVED.** No daemons, no Redis, no FFmpeg in v1 paths. Watch items:
  cron granularity (§2), `upload_max_filesize`/`post_max_size` variance (installer must
  display effective limits in admin), inodes on tiny plans (many small cache files).
- **Admin dashboard: DIRECTION SET.** Phase 1–2 admin may be clean CRUD (velocity!),
  but premium feel comes from: review-queue workflow (uploads), KPI header (plays/users/
  storage), and audit visibility — not from styling. Charts deferred to Phase 4+ (server
  -rendered SVG or lightweight Canvas; no heavy BI dependency).
- **API v1: NEEDED, correctly timed.** Not "too early": the player consumes it in Phase 2,
  and designing the envelope now prevents a rewrite. No duplicate logic: web controllers
  and API controllers both call the same Services.

## 6. Performance review (bottlenecks → mitigations)

| Hot path | Mitigation (designed) |
|---|---|
| Album/track lists | Eager loads + `Cache::remember` 60–300s + pagination; N+1 assertions in tests |
| Covers/avatars | Proxy route + ETag + long `Cache-Control`; CDN later |
| Search | Fulltext index + 12-row limit + 30/min throttle; Scout later |
| Stream signing | Signed-URL generation is cheap (HMAC); no file reads in the signing path |
| Analytics writes | Queued increments + nightly `analytics_daily` rollups; raw fact pruning policy |
| Ad tables growth | Aggregate nightly, prune raws after 90 days (configurable) |

## 7. Decisions / corrections register (must apply at Phase 1 start)

1. Use `spatie/laravel-permission`; drop hand-rolled RBAC plan.
2. No repository/action/DTO layers in v1; services + FormRequests + Resources.
3. Add `slug_redirects` table; fix `playlist_items` unique (see DATABASE_CORRECTIONS.md).
4. Add Legal/CMS pages concern to Phase 1 scope.
5. Cache flags forever + flush; cache sitemap daily.
6. NOINDEX admin/settings/unlisted; hreflang pairs; canonical everywhere.
7. Cron-granularity UX copy + installer displays PHP upload limits.
8. Orphan sweeper + tmp purge jobs from Phase 2.

## Verdict

**Architecture is APPROVED for Phase 1, conditional on applying the 8 corrections above**
(all are documentation/config-level, zero rework of the target design).
No structural redesign required. See PHASE_1_READINESS_REPORT.md for the formal gate.
