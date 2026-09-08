# SHIRIN — Database Corrections (Phase 0.5)

> Review of DATABASE_DESIGN.md. Each item: problem → correction → rationale.
> Conventions reaffirmed: `utf8mb4_unicode_ci`, BIGINT PKs, UUIDs for public refs,
> soft deletes on user content, hashed IPs only.

## C-01 — Missing table: `slug_redirects` (referenced but undefined)
- **Problem:** ARCHITECTURE.md promises a redirect table for forced slug renames; no schema given.
- **Correction — add:**
  `slug_redirects`: id, subject_type (`artist|album|track|playlist|genre`),
  old_slug (unique per type → unique(`subject_type`,`old_slug`)), new_slug, created_at.
- Lookup in route binding: exact slug → else redirect row → 301 → else 404.

## C-02 — `playlist_items`: unique constraint breaks reordering
- **Problem:** Unique `(playlist_id, track_id, position)` fails on tracks added twice
  (legit: same song twice in a playlist) and complicates reorder.
- **Correction:** NO unique constraint. Integrity via transaction in `PlaylistService::reorder`
  (delete + bulk insert within DB transaction, or position swap). Index `(playlist_id, position)`.
- Allow duplicate tracks deliberately (matches Spotify behavior).

## C-03 — Soft deletes vs unique slugs/usernames
- **Problem:** `slug` unique + soft delete = cannot re-create a slug after deleting the row.
- **Correction:** keep plain unique in v1 + rule "slugs are never reused" (takedown keeps the
  row soft-deleted; slug stays reserved). Document; revisit partial indexes only if reuse
  is ever required. Same rule for `username`.

## C-04 — Track slug collisions across artists
- **Problem:** Two artists can release a track with the same title → same slug candidate.
- **Correction:** slug generation = `{title-slug}` + suffix `-{artist-short-slug}` on collision,
  falling back to `-{uuid8}`. Uniqueness enforced at DB (unique `slug`) + app retry loop.

## C-05 — `track_files`: "exactly one original" unenforceable in plain DDL
- **Problem:** Design states the rule but MySQL has no partial unique indexes (MariaDB has, MySQL 8 doesn't for this shape).
- **Correction:** enforce in `MediaService` (transaction + check) + a nightly consistency
  command `shirin:media-audit` that reports violations. Add unique(`track_id`,`quality`,`driver`)
  to prevent double-derivation.

## C-06 — `analytics_daily` unique key needs the hash column
- **Problem:** Unique `(date, metric, dimensions_hash)` references a column not listed.
- **Correction — columns:** date, metric, dimensions (JSON), dimensions_hash
  (`char(32)`, md5 of canonical JSON), value (bigint). Unique `(date, metric, dimensions_hash)`.

## C-07 — `audit_logs.actor_id` must be nullable
- **Problem:** System/cron actions have no actor; NOT NULL blocks them.
- **Correction:** `actor_id` nullable FK (null = system). Add `actor_type` (`user|system`).

## C-08 — Ad fact tables will explode on shared hosting
- **Problem:** One row per impression/click unbounded = disk + inode risk on small plans.
- **Correction:** keep raw tables but add retention policy from day one:
  nightly rollup into `analytics_daily` + prune raws older than 90 days
  (`ads.raw_retention_days` setting). Add index `(ad_id, created_at)` for prune/rollup speed.

## C-09 — `listening_history` cap needs an enforcement point
- **Problem:** "Retention job caps 100/user" — no job defined.
- **Correction:** `PruneHistory` scheduled weekly: per user keep latest 100
  (`delete where id not in (select … order by played_at desc limit 100)` — batched).
  Index `(user_id, played_at desc)` required (add `desc` explicitly for MySQL 8).

## C-10 — Missing framework tables in the design doc
- **Problem:** `personal_access_tokens` (Sanctum), `failed_jobs`, `job_batches` (if used),
  `cache`/`cache_locks` (only if database cache chosen — it isn't; file cache is),
  `password_reset_tokens` only mentioned in passing.
- **Correction:** list them explicitly; Sanctum + `failed_jobs` are REQUIRED at Phase 1
  migration set. Queue tables: `jobs`, `job_batches`, `failed_jobs`.

## C-11 — `premium_grants` overlap + check path
- **Problem:** No rule against overlapping grants; check path undefined.
- **Correction:** overlaps allowed (history-preserving); `PremiumAccessService::hasAccess($user)`
  = `exists(valid grant covering now)` OR `hasRole('owner')`. Index `(user_id, ends_at)`.
  Lifetime = `ends_at NULL`.

## C-12 — `reactions` counters + toggle race
- **Problem:** `like_count`/`dislike_count` caches + concurrent toggles can drift.
- **Correction:** toggle in transaction (`updateOrCreate` + recompute from `reactions`
  count, not +/-1). Recompute is cheap at this scale and self-healing. Nightly
  `shirin:counters-audit` as backstop (same command family as C-05).

## C-13 — Favorites morph index shape
- **Problem:** Design lists unique triple but query pattern is "all favorites of type X by user".
- **Correction:** unique `(user_id, favoritable_type, favoritable_id)` (kept) PLUS
  index `(user_id, favoritable_type, created_at)` for library listing.

## C-14 — Fulltext index caveat (MariaDB)
- **Problem:** Fulltext over `title_fa,title_en` behaves differently across MySQL/MariaDB
  versions and poorly for Persian in older builds.
- **Correction:** v1 search = `LIKE %q%` on titles + exact slug match + artist-name join,
  capped with `LIMIT 25`, behind 30/min throttle. Fulltext/Scout is an optimization,
  not a launch dependency. Remove fulltext from the v1 migration set.

## C-15 — `uploads.metadata` + `tool_jobs.params` JSON size
- **Problem:** Unbounded JSON invites bloat (embedded tag dumps).
- **Correction:** allow-list keys in service validation
  (`duration_sec, bitrate, sample_rate, tags{title,artist,album,year,genre}`); reject/trim rest.

## C-16 — `users.locale` type
- **Correction:** `char(2)` with app-level `in:fa,en` validation (portable; MySQL ENUM
  alters are painful on shared hosts). Default `fa`.

## C-17 — Timestamps on `ad_impressions`/`ad_clicks`
- **Correction:** `created_at` only (no `updated_at`) — append-only facts. Same for
  `downloads`, `audit_logs` (+ `played_at` already on history).

## Correction checklist for Phase 1 migrations
- [ ] Add `slug_redirects`, `personal_access_tokens`, `jobs`, `failed_jobs` tables
- [ ] `playlist_items`: no unique; index `(playlist_id, position)`
- [ ] `analytics_daily.dimensions_hash` column + unique triple
- [ ] `audit_logs.actor_id` nullable + `actor_type`
- [ ] `premium_grants` index `(user_id, ends_at)`
- [ ] `favorites` secondary index `(user_id, favoritable_type, created_at)`
- [ ] `listening_history` index `(user_id, played_at desc)`
- [ ] Drop fulltext from v1; LIKE-based search
- [ ] `users.locale` char(2) default `fa`
- [ ] Created-at-only on fact tables
