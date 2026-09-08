<?php

namespace App\Services;

use App\Contracts\CoverArtService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LocalCoverArtService implements CoverArtService
{
    public function store(UploadedFile $file, string $collection): string
    {
        $collection = $this->validatedCollection($collection);

        // UUID filename: no executable bits, no double extensions, no
        // user-controlled path segments (SECURITY_PLAN §4). The extension
        // comes from the server-side mime guess, never the client name.
        $extension = strtolower((string) $file->extension());

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new InvalidArgumentException('Unsupported image type.');
        }

        $path = "covers/{$collection}/".((string) Str::uuid()).".{$extension}";

        Storage::disk($this->disk())->putFileAs(
            dirname($path), $file, basename($path)
        );

        return $path;
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (! str_starts_with($path, 'covers/')) {
            return;
        }

        Storage::disk($this->disk())->delete($path);
    }

    public function url(?string $path): ?string
    {
        if (! $path || ! str_starts_with($path, 'covers/')) {
            return null;
        }

        $relative = substr($path, strlen('covers/'));
        $segments = explode('/', $relative, 2);

        if (count($segments) !== 2) {
            return null;
        }

        return route('media.cover', [
            'collection' => $segments[0],
            'file' => $segments[1],
        ]);
    }

    protected function disk(): string
    {
        return (string) config('shirin.media.disk', 'media');
    }

    /**
     * @return string
     *
     * @throws InvalidArgumentException
     */
    protected function validatedCollection(string $collection): string
    {
        $allowed = config('shirin.music.artwork_collections', ['artists', 'albums']);

        if (! in_array($collection, $allowed, true)) {
            throw new InvalidArgumentException("Unknown artwork collection [{$collection}].");
        }

        return $collection;
    }
}
