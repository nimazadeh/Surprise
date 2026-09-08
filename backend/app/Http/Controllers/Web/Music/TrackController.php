<?php

namespace App\Http\Controllers\Web\Music;

use App\Http\Controllers\Controller;
use App\Models\SlugRedirect;
use App\Models\Track;
use Symfony\Component\HttpFoundation\Response;

class TrackController extends Controller
{
    /**
     * SEO foundation page. Drafts behave as missing; renamed slugs 301
     * to the current URL (C-01); anything else is a friendly 404.
     * No audio is served here (R-01 stands; player arrives later).
     */
    public function show(string $slug): Response
    {
        $track = Track::query()
            ->published()
            ->where('slug', $slug)
            ->with(['artist', 'album', 'genre'])
            ->first();

        if ($track) {
            return response()->view('web.music.tracks.show', ['track' => $track]);
        }

        $redirect = SlugRedirect::query()
            ->where('subject_type', SlugRedirect::SUBJECT_TRACK)
            ->where('old_slug', $slug)
            ->first();

        if ($redirect) {
            return redirect()->route('tracks.show', $redirect->new_slug, 301);
        }

        abort(404);
    }
}
