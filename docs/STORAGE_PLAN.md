# SHIRIN — Storage & Upload Plan (Phase 0)

> Design only. Must work on **shared hosting today**, scale to **VPS + S3/CDN tomorrow**
> with configuration changes, not rewrites.

## 1. Disk layout

```text
storage/app/
├── media/
│   ├── audio/original/          # approved masters (private)
│   ├── audio/derived/           # 128k/320k/preview renditions (private)
│   ├── covers/                  # album/track covers (public via /media proxy w/ cache)
│   ├── avatars/                 # user avatars (public via proxy)
│   └── ads/                     # ad creatives (public via proxy)
├── uploads/
│   ├── quarantine/              # pending review (private, no direct URL ever)
│   └── tmp/chunks/              # chunked upload assembly (auto-purged 24h)
└── lab/                         # Music Lab inputs/outputs (private, per-user)
```

- **Flysystem disks:** `local` (now) → `s3` (later). All code uses `Storage::disk(config('media.disk'))`
  via a `MediaDisk` wrapper; no `public_path()` or hardcoded URLs in domain code.
- **Serving:** private files via signed routes (`/media/{uuid}?signature=…`, 5-min expiry);
  public-ish files (covers/avatars) via long-cache proxy route with `ETag` + CDN later.
- **Database is truth:** every file has a row (`track_files`, `uploads`, …) with
  `disk/path/mime/size/checksum`; orphan sweeper job runs weekly.

## 2. Validation matrix

| Kind | Extensions | MIME sniff | Max size (shared) | Extra checks |
|---|---|---|---|---|
| Audio | mp3 ogg opus wav flac m4a | `audio/*` via Fileinfo | 100 MB | duration probe, bitrate cap 320k |
| Cover | jpg jpeg png webp | `image/*` | 5 MB | min 500×500, re-encode via GD/Imagick, strip EXIF |
| Avatar | jpg png webp | `image/*` | 2 MB | square crop, 3 sizes generated |
| Ad creative | jpg png webp gif | `image/*` | 3 MB | dimension match vs placement |

Rejected files: user-friendly message + `upload.rejected` audit entry (malicious patterns flagged).

## 3. Upload system architecture (professional, not a form)

```text
Dropzone UI (drag&drop + modal + progress)
        │  direct POST (≤100MB) · chunked (VPS / large)
        ▼
UploadController → StoreUploadRequest (validate) → UploadService
        │  virus-scan hook · checksum · quarantine write · Uploads row (pending)
        ▼
ProbeJob (getID3 now / ffprobe later): duration, tags, bitrate → uploads.metadata
        ▼
Review queue (admin) → approve → AttachToTrack (transaction: tracks + track_files)
                     → reject (+ reason → user notification)
```

- **Client:** modal uploader with drag&drop, per-file progress (XHR `progress` events),
  cover picker + metadata fields (title/artist/album/genre), client-side duration via
  `Audio` element (hint only — server probe is truth).
- **Chunked protocol (design now, enable on VPS):** `POST /api/v1/uploads/chunks`
  `{uuid, index, total}` → assemble → checksum verify → quarantine.
- **Quotas:** per-user daily MB + file count (configurable; premium multipliers).
- **Failures:** resumable state in `uploads` table (`received_chunks` JSON); 24h tmp purge.

## 4. Duration & metadata detection

- **Shared hosting:** `getID3` (pure PHP) for duration/tags/bitrate. No binaries needed.
- **VPS:** `ffprobe` for accuracy + `ffmpeg` for renditions (128k stream, 30s preview,
  waveform JSON for the player seekbar).
- `AudioProbeService` interface with `GetId3Driver` / `FfmpegDriver`; driver chosen by config.

## 5. Security notes (see SECURITY_PLAN.md §4)

Outside-webroot storage, UUID filenames, double-extension rejection, cover re-encode,
quarantine-only flow, signed delivery URLs, AV hook.

## 6. Scaling path (shared → VPS → CDN)

1. **Now:** local disk + signed routes + file cache on covers.
2. **VPS:** same code, `MEDIA_DISK=s3`; queue workers generate renditions async.
3. **CDN:** covers/avatars/previews behind CDN with signed-URL fallback for originals;
   cache-bust via content hash in proxy URLs.
4. **Backups:** nightly DB dump + weekly media snapshot (VPS); shared-hosting manual
   export documented in DEPLOYMENT_PLAN.md.
