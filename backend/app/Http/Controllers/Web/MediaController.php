<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public cover proxy (STORAGE_PLAN §1): long-cache + ETag responses for
 * approved catalogue artwork. Filenames are server-generated UUIDs so the
 * strict pattern below makes traversal impossible.
 */
class MediaController extends Controller
{
    public function cover(string $collection, string $file): BinaryFileResponse
    {
        $allowed = config('shirin.music.artwork_collections', ['artists', 'albums']);

        abort_unless(in_array($collection, $allowed, true), 404);
        abort_unless((bool) preg_match('/^[a-f0-9-]+\.(jpg|jpeg|png|webp)$/i', $file), 404);

        $disk = Storage::disk((string) config('shirin.media.disk', 'media'));
        $path = "covers/{$collection}/{$file}";

        abort_unless($disk->exists($path), 404);

        $absolute = $disk->path($path);

        // Statement form on purpose: setAutoEtag()'s return type differs
        // across Symfony versions, and the declared BinaryFileResponse
        // return must hold on all of them.
        $response = response()->file($absolute, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
        $response->setAutoEtag();

        return $response;
    }
}
