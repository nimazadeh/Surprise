<?php

namespace App\Http\Controllers\Web\Music;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use App\Models\SlugRedirect;
use App\Models\Track;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackController extends Controller
{
    /**
     * Public catalogue index (Phase 2.5): published tracks, searchable,
     * filterable by genre, paginated. Title order — track numbers only
     * mean something inside an album.
     */
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $genreSlug = (string) $request->query('genre', '');

        $genre = $genreSlug !== '' ? Genre::query()->where('slug', $genreSlug)->first() : null;

        $tracks = Track::query()
            ->published()
            ->with(['artist', 'album', 'genre'])
            ->when($genre, fn ($query) => $query->where('genre_id', $genre->id))
            ->search($q)
            ->orderBy('title')
            ->orderBy('id')
            ->paginate($this->perPage())
            ->withQueryString();

        return response()->view('web.music.tracks.index', [
            'tracks' => $tracks,
            'genres' => Genre::query()->ordered()->get(),
            'genreSlug' => $genre !== null ? $genre->slug : '',
            'q' => $q,
        ]);
    }

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

    protected function perPage(): int
    {
        return max(1, (int) config('shirin.music.api_per_page', 15));
    }
}
