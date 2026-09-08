# SHIRIN — Database Design (Phase 0)

> Design only — no migrations in this phase. Engine: **MySQL 8 / MariaDB 10.6+**
> (`utf8mb4_unicode_ci`). Conventions: `id` BIGINT unsigned PK · `uuid` (public refs) ·
> `slug` (SEO, unique per table) · `*_fa` / `*_en` localized columns · soft deletes on
> user content · `created_by` where moderation matters.

## ER overview

```text
users ─┬─ model_has_roles ─ roles ─ role_has_permissions ─ permissions
       ├─ profiles (1:1)            earns ─ premium_grants
       ├─ playlists ─ playlist_items ─ tracks
       ├─ favorites (morph) ─ tracks|albums|artists|playlists
       ├─ reactions (1/user/track) ─ tracks
       ├─ listening_history ─ tracks
       ├─ uploads ─ tracks (after approval)
       ├─ downloads ─ tracks (Download Center log)
       └─ tool_jobs ─ tracks (Music Lab log)

artists ─ albums ─ tracks ─┬─ track_genre ─ genres
                           ├─ track_files (qualities/renditions)
                           └─ lyrics (licensed only, nullable)

advertisements ─ ad_placements (pivot w/ schedule)
ad_impressions / ad_clicks (append-only facts)

settings (key/value + flags) · notifications · analytics_daily (rollups) · audit_logs
```

## Tables

### Identity & access
- `users`: id, uuid, username (unique), name, email (unique), email_verified_at,
  password, locale (`fa|en`), avatar_path, status (`active|banned`), banned_at,
  last_login_at, timestamps, soft deletes.
  - Indexes: `email`, `username`, `status`.
- `roles`: id, name (unique: `owner|super_admin|admin|content_manager|ads_manager|premium_user|normal_user`),
  `is_protected` (bool, true only for owner), description.
- `permissions`: id, name (unique, e.g. `music.manage`), group.
- `model_has_roles`: user_id + role_id (unique pair). `role_has_permissions`: role_id + permission_id.
- `premium_grants`: id, user_id, granted_by, reason, starts_at, ends_at (nullable = lifetime),
  timestamps. History-preserving (never update, append + expire).
- `password_reset_tokens`, `sessions`, standard Laravel tables.

### Catalogue (dual-source: owned + provider-enriched)
- `artists`: id, uuid, slug (unique), name_fa, name_en, bio_fa, bio_en, avatar_path,
  `source` (`owned|deezer`), `provider_id` (nullable, indexed), verified, follower_count (cached),
  `is_published`, meta_title/description (nullable overrides), timestamps, soft deletes.
- `albums`: id, uuid, slug (unique), artist_id (FK), title_fa, title_en, cover_path,
  release_date, record_type (`album|ep|single`), `source`, `provider_id` (nullable),
  track_count (cached), `is_published`, `is_featured`, timestamps, soft deletes.
  - Indexes: `(artist_id, release_date)`, `record_type`, `is_published`.
- `tracks`: id, uuid, slug (unique), album_id (FK, nullable for loose singles),
  artist_id (FK), title_fa, title_en, duration_sec, track_number, disc_number,
  `source` (`owned|deezer`), `provider_id` (nullable), preview_url (nullable, provider only),
  explicit, play_count (cached), like_count/dislikes (cached), `is_published`, `is_featured`,
  lyrics_status (`none|licensed_synced|licensed_plain`), timestamps, soft deletes.
  - Indexes: `(album_id, track_number)`, `(artist_id)`, `source`, `is_published`, fulltext(`title_fa`,`title_en`).
- `track_files`: id, track_id (FK), quality (`original|320|128|preview`), disk, path,
  mime, size_bytes, bitrate, checksum, `is_downloadable`, timestamps.
  - Constraint: exactly one `original` per track; derived rows reference job id.
- `genres`: id, slug (unique), name_fa, name_en, cover_path. `track_genre`: track_id + genre_id.
- `artist_follows`: user_id + artist_id (+ timestamps). Unique pair.
- `lyrics`: id, track_id (unique FK), `license_source`, `synced` (bool),
  `lines` (JSON `[{t, fa?, en?}]`), timestamps. **Licensed text only; empty = unavailable.**

### Library
- `playlists`: id, uuid, slug (unique), user_id (FK), title, description, cover_path,
  visibility (`private|public|unlisted`), items_count (cached), timestamps, soft deletes.
- `playlist_items`: id, playlist_id (FK), track_id (FK), position (int), added_by, timestamps.
  - Unique `(playlist_id, track_id, position)` handling via reorder transaction; index `(playlist_id, position)`.
- `favorites`: id, user_id, favoritable_type, favoritable_id, timestamps. Unique
  `(user_id, favoritable_type, favoritable_id)`.
- `reactions`: id, user_id, track_id, value (`like|dislike`), timestamps.
  Unique `(user_id, track_id)` — toggle updates `value`.
- `listening_history`: id, user_id, track_id, played_at, source (`web|api|playlist|…`),
  progress_sec. Index `(user_id, played_at)`; retention job caps 100/user.

### Uploads & premium
- `uploads`: id, uuid, user_id, kind (`track|cover|avatar`), original_name, disk, path,
  mime, size_bytes, checksum, metadata (JSON: probed duration/tags), status
  (`pending|approved|rejected|processing|failed`), reviewed_by, review_note, timestamps.
- `downloads`: id, user_id (nullable for public mode), track_id, file_id, ip_hash,
  user_agent_hash, timestamps. Append-only; quota checks read this table.
- `tool_jobs`: id, uuid, user_id, tool (`tag|cover|metadata|cut|convert`), track_id/file refs,
  params (JSON), status (`queued|running|done|failed`), result_path, error, timestamps.
  - Driver-agnostic: `driver` column (`php|ffmpeg`) records what executed it.

### Ads
- `ad_placements`: id, key (unique: `home_hero|player_banner|sidebar_box|…`), name,
  sizes (JSON), is_active.
- `advertisements`: id, title, creative_path, link_url, placement_id (FK),
  starts_at, ends_at, weight, max_impressions, max_clicks, is_active, created_by, timestamps.
- `ad_impressions` / `ad_clicks`: id, ad_id, placement_key, user_id (nullable),
  session_hash, ip_hash, user_agent_hash, created_at. Append-only; aggregated nightly
  into `analytics_daily` to keep tables lean.

### Platform
- `settings`: key (PK, e.g. `downloads.enabled`), value (JSON), type, updated_by, timestamps.
  Seeded defaults for every flag in ARCHITECTURE.md §6.
- `notifications`: id, user_id, type, data (JSON), read_at, timestamps.
- `analytics_daily`: date, metric (`plays|views|registrations|downloads|tool_runs|ad_impressions|ad_clicks`),
  dimensions (JSON: track_id/ad_id/…), value. Unique `(date, metric, dimensions_hash)`.
- `audit_logs`: id, actor_id, action (`user.ban|music.update|settings.flags|…`),
  subject_type/id, before/after (JSON), ip_hash, created_at. Append-only, no deletes.

## Key constraints & rules

1. **No orphan media:** `track_files` and `uploads` rows are deleted only via
   `MediaService` (DB + disk in one transaction/outbox).
2. **Slug immutability:** slugs never change after publish (redirect table `slug_redirects`
   if a rename is ever forced).
3. **Counter caches:** `play_count`-style columns updated by queued jobs, never by
   request-path `increment()` on hot paths (except SQLite-dev fallback).
4. **Append-only facts:** impressions, clicks, downloads, plays, audit logs — no updates.
5. **Privacy:** IPs stored hashed (`ip_hash`), never raw, with documented retention.
