<?php

namespace App\Http\Controllers\Web\Music;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\SlugRedirect;
use Symfony\Component\HttpFoundation\Response;

class ArtistController extends Controller
{
    /**
     * SEO foundation page. Drafts behave as missing; renamed slugs 301
     * to the current URL (C-01); anything else is a friendly 404.
     */
    public function show(string $slug): Response
    {
        $artist = Artist::query()
            ->published()
            ->where('slug', $slug)
            ->withCount(['albums', 'tracks'])
            ->first();

        if ($artist) {
            return response()->view('web.music.artists.show', [
                'artist' => $artist,
                'albums' => $artist->albums()->published()->ordered()->get(),
            ]);
        }

        $redirect = SlugRedirect::query()
            ->where('subject_type', SlugRedirect::SUBJECT_ARTIST)
            ->where('old_slug', $slug)
            ->first();

        if ($redirect) {
            return redirect()->route('artists.show', $redirect->new_slug, 301);
        }

        abort(404);
    }
}
