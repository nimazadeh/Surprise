# SHIRIN — Phase 2 Database Design (Music Core)

Date: 2026-09-08 · Branch: `arena/01a08237-surprise` (stands in for
`phase-2-music-core`) · Engine: MySQL 8 / MariaDB 10.6+ (`utf8mb4_unicode_ci`),
SQLite-compatible for CI.

> This document is the Phase 2 schema authority. It also serves as the
> workflow-required `PHASE_2_DATABASE_REPORT` (schema after migrate, seeders,
> rollback plan).

## 1. Migration set (additive only, fully reversible)

| Order | Migration | Tables / change | Rollback |
|---|---|---|---|
| 1–7 | Phase 1 set (skeleton + packages + profile + settings) | 17 tables, unchanged | ✅ untouched |
| 8 | `2026_09_08_000003_create_genres_table` | `genres` | ✅ drop |
| 9 | `2026_09_08_000004_create_artists_table` | `artists` | ✅ drop |
| 10 | `2026_09_08_000005_create_albums_table` | `albums` + FK → artists | ✅ drop |
| 11 | `2026_09_08_000006_create_tracks_table` | `tracks` + FKs → albums/artists/genres | ✅ drop |
| 12 | `2026_09_08_000007_create_slug_redirects_table` | `slug_redirects` (C-01) | ✅ drop |

Post-migrate total: **20 data tables** (15 pre-existing + 5 new), 21 including
Laravel's `migrations` bookkeeping table. (The Phase 1 report's "17" counts one
table too many — the profile-fields migration ALTERs `users` but creates none;
verified pre-existing inventory: 9 skeleton/settings + 5 Spatie + 1 Sanctum = 15.)
Rollback plan per release:
`php artisan migrate:rollback --step=5` removes exactly the Phase 2 set, in
reverse dependency order (redirects → tracks → albums → artists → genres).

## 2. Schema

### `genres` (taxonomy, no soft deletes)

| Column | Type | Constraints |
|---|---|---|
| id | bigint unsigned PK | |
| name | string(255) | |
| slug | string(255) | unique |
| description | text | nullable |
| created_at / updated_at | timestamps | |

Deleted genres release their tracks to `genre_id = NULL` (see §3).

### `artists`

| Column | Type | Constraints |
|---|---|---|
| id | bigint unsigned PK | |
| name | string(255) | |
| slug | string(255) | unique, never reused (C-03) |
| bio | text | nullable |
| image | string(512) | nullable (disk-relative cover path) |
| country | string(100) | nullable |
| language | char(2) | default `fa`, app-validated `in:fa,en` (C-16 pattern) |
| is_featured | boolean | default false, indexed |
| status | string(16) | default `draft` ∈ draft/published/archived, indexed |
| source | string(16) | default `owned` ∈ owned/deezer, indexed |
| provider_id | string(255) | nullable, indexed |
| seo_title | string(255) | nullable |
| seo_description | string(500) | nullable |
| created_at / updated_at, deleted_at | timestamps + soft deletes | |

### `albums`

| Column | Type | Constraints |
|---|---|---|
| id | bigint unsigned PK | |
| artist_id | FK → artists.id | `cascadeOnDelete` |
| title | string(255) | |
| slug | string(255) | unique, never reused |
| description | text | nullable |
| cover | string(512) | nullable (disk-relative cover path) |
| release_date | date | nullable |
| release_year | unsigned smallint | nullable |
| type | string(16) | default `album` ∈ album/single/ep/compilation, indexed |
| status / source / provider_id / seo_* | as artists | same defaults/indexes |
| created_at / updated_at, deleted_at | timestamps + soft deletes | |

### `tracks`

| Column | Type | Constraints |
|---|---|---|
| id | bigint unsigned PK | |
| album_id | FK → albums.id, nullable | `nullOnDelete` (loose singles) |
| artist_id | FK → artists.id | `cascadeOnDelete` |
| title | string(255) | |
| slug | string(255) | unique, never reused |
| description | text | nullable |
| track_number | unsigned smallint | nullable |
| duration_sec | unsigned integer | nullable (integer seconds, probe-ready) |
| genre_id | FK → genres.id, nullable | `nullOnDelete` |
| language | char(2) | default `fa` |
| lyrics_available | boolean | default false (licensed lyrics only; no text stored) |
| status / source / provider_id / seo_* | as artists | same defaults/indexes |
| created_at / updated_at, deleted_at | timestamps + soft deletes | |
| composite index | `(album_id, track_number)` | album ordering hot path |

### `slug_redirects` (C-01)

| Column | Type | Constraints |
|---|---|---|
| id | bigint unsigned PK | |
| subject_type | string(16) | artist/album/track/genre (+ playlist reserved) |
| old_slug | string(255) | |
| new_slug | string(255) | |
| created_at | datetime | `useCurrent` |
| unique | `(subject_type, old_slug)` | |

Lookup order in public show routes: exact slug → redirect row (301) → 404.
Rows are written automatically by the `HasSlug` model trait on rename.

## 3. Foreign keys & delete behavior

| From | To | On delete | Rationale |
|---|---|---|---|
| albums.artist_id | artists.id | CASCADE (hard delete only) | Albums cannot be orphaned; soft delete keeps rows |
| tracks.artist_id | artists.id | CASCADE (hard delete only) | Same |
| tracks.album_id | albums.id | SET NULL | Deleting an album must not destroy tracks |
| tracks.genre_id | genres.id | SET NULL | Deleting a genre must not destroy tracks |

Cover *files* are deleted with their rows by the admin controllers via
`CoverArtService` (DB + disk together; the future `MediaService` generalizes
this per DATABASE_DESIGN rule 1).

## 4. Indexes (query performance)

- Unique: `genres.slug`, `artists.slug`, `albums.slug`, `tracks.slug`,
  `(slug_redirects.subject_type, old_slug)` — all slug-lookup hot paths.
- Single-column: `status` (artists/albums/tracks — every public query filters
  `published`), `type` (albums), `is_featured` (artists), `source`,
  `provider_id` (future enrichment joins).
- Composite: `(album_id, track_number)` (album track listing order).
- FK columns are indexed by `foreignId()->constrained()` automatically.
- **No fulltext** (C-14): MariaDB/MySQL Persian fulltext is unreliable and
  SQLite-incompatible. v1 search is `LIKE %q%` + exact-slug with `LIMIT` via
  paginator, behind admin auth (public search API arrives later, throttled).

## 5. Scalability notes

- Public reads are `status`-filtered, paginated, and N+1-safe (`with` /
  `withCount` in every index/show path); no request-path `increment()` exists.
- Counter caches (`play_count` etc.) and rollup jobs stay deferred to Phase 4
  (DATABASE_REVIEW §3) — current `withCount` aggregates are fine at this scale.
- `provider_id` indexes keep the future dual-source enrichment join cheap;
  `source` defaults to `owned` so no backfill is ever needed.
- Append-only / cached-count patterns from DATABASE_DESIGN §5 are unaffected:
  this phase adds no fact tables.

## 6. Seeders

| Seeder | Rows | Idempotent |
|---|---|---|
| RolesAndPermissionsSeeder (P1) | 11 permissions, 5 roles | ✅ findOrCreate/sync |
| SettingsSeeder (P1) | 9 settings | ✅ |
| GenreSeeder (P2, new) | 8 base genres (pop…folk) | ✅ firstOrCreate by slug |

OWNER creation stays interactive via `php artisan shirin:install`
(no default credentials, ever).

## 7. Deviations from the Phase 0 design (all deliberate, owner-spec driven)

| Phase 0 | Phase 2 | Why |
|---|---|---|
| `name_fa`/`name_en` … localized columns | single `name`/`title` + `language` (fa/en) | Prompt §8: no table duplication; translations expand later via JSON/translations table without restructuring |
| `is_published` boolean | `status` draft/published/archived | Superset: supports drafts + archive workflow; activate/deactivate = published/draft |
| `track_genre` pivot | single `genre_id` FK | Prompt spec: one primary genre; multi-genre pivot is a future additive migration |
| `record_type` album/ep/single | `type` + `compilation` | Prompt spec; compilations attribute tracks via their own `artist_id` |
| required `album_id` | **nullable** `album_id` | Loose singles must exist (also Phase 0: "nullable for loose singles"); the prompt's "required" list is satisfied as a field, relaxed as a constraint |
| `uuid` public refs | integer ids in v1 API | Prompt/JS-shape compatibility (provider ids are integers); uuid remains addable |
| — | `slug_redirects` added | C-01: mandated to land with catalogue tables |

## 8. Licensing & safety (R-01)

This phase stores **metadata and cover images only**. No audio columns,
no audio uploads, no stream URLs, no lyrics text (`lyrics_available` is a
boolean flag; `lines` JSON arrives only with a licensed provider). R-01 is
therefore unaffected; the gate stands for any future owned-audio work.
