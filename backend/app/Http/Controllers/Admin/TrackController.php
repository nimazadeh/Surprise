<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrackRequest;
use App\Http\Requests\UpdateTrackRequest;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Track;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrackController extends Controller
{
    public function index(Request $request)
    {
        $tracks = Track::query()
            ->search($request->string('q')->toString() ?: null)
            ->ordered()
            ->with(['artist', 'album', 'genre'])
            ->paginate((int) config('shirin.music.admin_per_page', 20))
            ->withQueryString();

        return view('admin.tracks.index', [
            'tracks' => $tracks,
            'q' => $request->string('q')->toString(),
        ]);
    }

    public function create()
    {
        return view('admin.tracks.create', $this->formOptions());
    }

    public function store(StoreTrackRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['slug'])) {
            unset($validated['slug']);
        }

        $track = Track::create($validated);

        return redirect()
            ->route('admin.tracks.edit', $track)
            ->with('status', __('music.tracks.created'));
    }

    public function edit(Track $track)
    {
        return view('admin.tracks.edit', array_merge(
            ['track' => $track],
            $this->formOptions()
        ));
    }

    public function update(UpdateTrackRequest $request, Track $track): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['slug'])) {
            unset($validated['slug']);
        }

        $track->update($validated);

        return redirect()
            ->route('admin.tracks.edit', $track)
            ->with('status', __('music.tracks.updated'));
    }

    public function destroy(Track $track): RedirectResponse
    {
        $track->delete();

        return redirect()
            ->route('admin.tracks.index')
            ->with('status', __('music.tracks.deleted'));
    }

    /**
     * Activate/deactivate: published <-> draft. Archived items publish.
     */
    public function toggle(Track $track): RedirectResponse
    {
        $track->update([
            'status' => $track->isPublished() ? Track::STATUS_DRAFT : Track::STATUS_PUBLISHED,
        ]);

        return back()->with('status', $track->isPublished()
            ? __('music.tracks.published')
            : __('music.tracks.unpublished'));
    }

    /**
     * @return array{artists: \Illuminate\Support\Collection<int, Artist>, albums: \Illuminate\Support\Collection<int, Album>, genres: \Illuminate\Support\Collection<int, Genre>}
     */
    protected function formOptions(): array
    {
        return [
            'artists' => Artist::query()->ordered()->get(['id', 'name', 'slug']),
            'albums' => Album::query()->ordered()->with('artist:id,name')->get(['id', 'artist_id', 'title', 'slug']),
            'genres' => Genre::query()->ordered()->get(['id', 'name', 'slug']),
        ];
    }
}
