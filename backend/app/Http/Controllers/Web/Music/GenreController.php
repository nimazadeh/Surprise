<?php

namespace App\Http\Controllers\Web\Music;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use App\Models\SlugRedirect;
use App\Models\Track;
use Symfony\Component\HttpFoundation\Response;

class GenreController extends Controller
{
    /**
     * Public genre page (Phase 2.5): the genre's published tracks in
     * title order. Same lookup order as the other catalogue pages:
     * exact slug → 301 (C-01) → 404.
     */
    public function show(string $slug): Response
    {
        $genre = Genre::query()->where('slug', $slug)->first();

        if ($genre) {
            $tracks = Track::query()
                ->published()
                ->with(['artist', 'album', 'genre'])
                ->where('genre_id', $genre->id)
                ->orderBy('title')
                ->orderBy('id')
                ->paginate($this->perPage())
                ->withQueryString();

            return response()->view('web.music.genres.show', [
                'genre' => $genre,
                'tracks' => $tracks,
            ]);
        }

        $redirect = SlugRedirect::query()
            ->where('subject_type', SlugRedirect::SUBJECT_GENRE)
            ->where('old_slug', $slug)
            ->first();

        if ($redirect) {
            return redirect()->route('genres.show', $redirect->new_slug, 301);
        }

        abort(404);
    }

    protected function perPage(): int
    {
        return max(1, (int) config('shirin.music.api_per_page', 15));
    }
}
