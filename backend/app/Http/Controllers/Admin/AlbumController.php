<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\CoverArtService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAlbumRequest;
use App\Http\Requests\UpdateAlbumRequest;
use App\Models\Album;
use App\Models\Artist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
    public function index(Request $request)
    {
        $albums = Album::query()
            ->search($request->string('q')->toString() ?: null)
            ->ordered()
            ->with('artist')
            ->withCount('tracks')
            ->paginate((int) config('shirin.music.admin_per_page', 20))
            ->withQueryString();

        return view('admin.albums.index', [
            'albums' => $albums,
            'q' => $request->string('q')->toString(),
        ]);
    }

    public function create()
    {
        return view('admin.albums.create', [
            'artists' => $this->artistOptions(),
        ]);
    }

    public function store(StoreAlbumRequest $request, CoverArtService $covers): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['slug'])) {
            unset($validated['slug']);
        }

        if ($request->hasFile('cover')) {
            $validated['cover'] = $covers->store($request->file('cover'), 'albums');
        } else {
            unset($validated['cover']);
        }

        $album = Album::create($validated);

        return redirect()
            ->route('admin.albums.edit', $album)
            ->with('status', __('music.albums.created'));
    }

    public function edit(Album $album)
    {
        return view('admin.albums.edit', [
            'album' => $album,
            'artists' => $this->artistOptions(),
        ]);
    }

    public function update(UpdateAlbumRequest $request, Album $album, CoverArtService $covers): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['slug'])) {
            unset($validated['slug']);
        }

        if ($request->hasFile('cover')) {
            $covers->delete($album->cover);
            $validated['cover'] = $covers->store($request->file('cover'), 'albums');
        } else {
            unset($validated['cover']);
        }

        $album->update($validated);

        return redirect()
            ->route('admin.albums.edit', $album)
            ->with('status', __('music.albums.updated'));
    }

    public function destroy(Album $album, CoverArtService $covers): RedirectResponse
    {
        $covers->delete($album->cover);
        $album->delete();

        return redirect()
            ->route('admin.albums.index')
            ->with('status', __('music.albums.deleted'));
    }

    /**
     * Activate/deactivate: published <-> draft. Archived items publish.
     */
    public function toggle(Album $album): RedirectResponse
    {
        $album->update([
            'status' => $album->isPublished() ? Album::STATUS_DRAFT : Album::STATUS_PUBLISHED,
        ]);

        return back()->with('status', $album->isPublished()
            ? __('music.albums.published')
            : __('music.albums.unpublished'));
    }

    /**
     * @return \Illuminate\Support\Collection<int, Artist>
     */
    protected function artistOptions()
    {
        return Artist::query()->ordered()->get(['id', 'name', 'slug']);
    }
}
