<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Cover-art handling behind an interface (STORAGE_PLAN §1): all domain code
 * talks to this contract; the disk and any future variants pipeline
 * (resize/re-encode/CDN) change without touching callers.
 *
 * Phase 2 scope: store validated uploads with UUID names, delete, and
 * resolve public proxy URLs. Re-encoding, dimension floors and AV hooks
 * arrive with the upload pipeline (see STORAGE_PLAN §2).
 */
interface CoverArtService
{
    /**
     * Store an uploaded cover and return its disk-relative path
     * (e.g. `covers/albums/<uuid>.jpg`).
     */
    public function store(UploadedFile $file, string $collection): string;

    /**
     * Delete a stored cover. Missing paths are ignored.
     */
    public function delete(?string $path): void;

    /**
     * Public proxy URL for a stored path, or null when empty.
     */
    public function url(?string $path): ?string;
}
