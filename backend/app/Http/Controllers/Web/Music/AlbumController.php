<?php

namespace App\Http\Controllers\Web\Music;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\SlugRedirect;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AlbumController extends Controller
{
    /**
     * Public catalogue index (Phase 2.5): published albums, searchable,
     * filterable by type, paginated.
     */
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $type = (string) $request->query('type', '');

        if (! in_array($type, [Album::TYPE_ALBUM, Album::TYPE_SINGLE, Album::TYPE_EP, Album::TYPE_COMPILATION], true)) {
            $type = '';
        }

        $albums = Album::query()
            ->published()
            ->ordered()
            ->with('artist')
            ->withCount('tracks')
            ->when($type, fn ($query) => $query->where('type', $type))
            ->search($q)
            ->paginate($this->perPage())
            ->withQueryString();

        return response()->view('web.music.albums.index', [
            'albums' => $albums,
            'q' => $q,
            'type' => $type,
        ]);
    }

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

    protected function perPage(): int
    {
        return max(1, (int) config('shirin.music.api_per_page', 15));
    }
}
