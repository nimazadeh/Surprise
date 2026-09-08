<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\CoverArtService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreArtistRequest;
use App\Http\Requests\UpdateArtistRequest;
use App\Models\Artist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ArtistController extends Controller
{
    public function index(Request $request)
    {
        $artists = Artist::query()
            ->search($request->string('q')->toString() ?: null)
            ->ordered()
            ->withCount(['albums', 'tracks'])
            ->paginate((int) config('shirin.music.admin_per_page', 20))
            ->withQueryString();

        return view('admin.artists.index', [
            'artists' => $artists,
            'q' => $request->string('q')->toString(),
        ]);
    }

    public function create()
    {
        return view('admin.artists.create');
    }

    public function store(StoreArtistRequest $request, CoverArtService $covers): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['slug'])) {
            unset($validated['slug']);
        }

        if ($request->hasFile('image')) {
            $validated['image'] = $covers->store($request->file('image'), 'artists');
        } else {
            unset($validated['image']);
        }

        $artist = Artist::create($validated);

        return redirect()
            ->route('admin.artists.edit', $artist)
            ->with('status', __('music.artists.created'));
    }

    public function edit(Artist $artist)
    {
        return view('admin.artists.edit', ['artist' => $artist]);
    }

    public function update(UpdateArtistRequest $request, Artist $artist, CoverArtService $covers): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['slug'])) {
            unset($validated['slug']);
        }

        if ($request->hasFile('image')) {
            $covers->delete($artist->image);
            $validated['image'] = $covers->store($request->file('image'), 'artists');
        } else {
            unset($validated['image']);
        }

        $artist->update($validated);

        return redirect()
            ->route('admin.artists.edit', $artist)
            ->with('status', __('music.artists.updated'));
    }

    public function destroy(Artist $artist, CoverArtService $covers): RedirectResponse
    {
        $covers->delete($artist->image);
        $artist->delete();

        return redirect()
            ->route('admin.artists.index')
            ->with('status', __('music.artists.deleted'));
    }

    /**
     * Activate/deactivate: published <-> draft. Archived items publish.
     */
    public function toggle(Artist $artist): RedirectResponse
    {
        $artist->update([
            'status' => $artist->isPublished() ? Artist::STATUS_DRAFT : Artist::STATUS_PUBLISHED,
        ]);

        return back()->with('status', $artist->isPublished()
            ? __('music.artists.published')
            : __('music.artists.unpublished'));
    }
}
