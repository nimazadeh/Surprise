<x-layouts.admin :title="__('music.albums.edit')">
    <div class="toolbar">
        <h1>{{ __('music.albums.edit') }}</h1>
        <div class="row-actions">
            @if ($album->isPublished())
                <a class="btn btn-secondary" href="{{ route('albums.show', $album->slug) }}">{{ __('music.actions.view') }}</a>
            @endif
            <a class="btn btn-secondary" href="{{ route('admin.albums.index') }}">{{ __('music.actions.back') }}</a>
        </div>
    </div>

    <div class="card">
        @include('admin.albums._form', [
            'action' => route('admin.albums.update', $album),
            'method' => 'PUT',
            'album' => $album,
            'artists' => $artists,
        ])
    </div>
</x-layouts.admin>
