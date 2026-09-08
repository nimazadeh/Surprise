<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;

class GenreController extends Controller
{
    public function index()
    {
        $genres = Genre::query()
            ->ordered()
            ->withCount('tracks')
            ->paginate((int) config('shirin.music.admin_per_page', 20));

        return view('admin.genres.index', ['genres' => $genres]);
    }

    public function create()
    {
        return view('admin.genres.create');
    }

    public function store(StoreGenreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['slug'])) {
            unset($validated['slug']);
        }

        $genre = Genre::create($validated);

        return redirect()
            ->route('admin.genres.edit', $genre)
            ->with('status', __('music.genres.created'));
    }

    public function edit(Genre $genre)
    {
        return view('admin.genres.edit', ['genre' => $genre]);
    }

    public function update(UpdateGenreRequest $request, Genre $genre): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['slug'])) {
            unset($validated['slug']);
        }

        $genre->update($validated);

        return redirect()
            ->route('admin.genres.edit', $genre)
            ->with('status', __('music.genres.updated'));
    }

    public function destroy(Genre $genre): RedirectResponse
    {
        $genre->delete();

        return redirect()
            ->route('admin.genres.index')
            ->with('status', __('music.genres.deleted'));
    }
}
