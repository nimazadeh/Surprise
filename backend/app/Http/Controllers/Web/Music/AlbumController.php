<?php

namespace App\Http\Controllers\Web\Music;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\SlugRedirect;
use Symfony\Component\HttpFoundation\Response;

class AlbumController extends Controller
{
    /**
     * SEO foundation page. Drafts behave as missing; renamed slugs 301
     * to the current URL (C-01); anything else is a friendly 404.
     */
    public function show(string $slug): Response
    {
        $album = Album::query()
            ->published()
            ->where('slug', $slug)
            ->with('artist')
            ->first();

        if ($album) {
            return response()->view('web.music.albums.show', [
                'album' => $album,
                'tracks' => $album->tracks()->published()->ordered()->with('artist')->get(),
            ]);
        }

        $redirect = SlugRedirect::query()
            ->where('subject_type', SlugRedirect::SUBJECT_ALBUM)
            ->where('old_slug', $slug)
            ->first();

        if ($redirect) {
            return redirect()->route('albums.show', $redirect->new_slug, 301);
        }

        abort(404);
    }
}
