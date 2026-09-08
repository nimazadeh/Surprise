<?php

namespace App\Http\Controllers\Web\Music;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\SlugRedirect;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ArtistController extends Controller
{
    /**
     * Public catalogue index (Phase 2.5): published artists, searchable,
     * paginated. LIKE wildcards are neutralized inside scopeSearch.
     */
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $artists = Artist::query()
            ->published()
            ->ordered()
            ->withCount(['albums', 'tracks'])
            ->search($q)
            ->paginate($this->perPage())
            ->withQueryString();

        return response()->view('web.music.artists.index', [
            'artists' => $artists,
            'q' => $q,
        ]);
    }

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

    protected function perPage(): int
    {
        return max(1, (int) config('shirin.music.api_per_page', 15));
    }
}
