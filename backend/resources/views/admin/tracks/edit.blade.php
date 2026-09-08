<x-layouts.admin :title="__('music.tracks.edit')">
    <div class="toolbar">
        <h1>{{ __('music.tracks.edit') }}</h1>
        <div class="row-actions">
            @if ($track->isPublished())
                <a class="btn btn-secondary" href="{{ route('tracks.show', $track->slug) }}">{{ __('music.actions.view') }}</a>
            @endif
            <a class="btn btn-secondary" href="{{ route('admin.tracks.index') }}">{{ __('music.actions.back') }}</a>
        </div>
    </div>

    <div class="card">
        @include('admin.tracks._form', [
            'action' => route('admin.tracks.update', $track),
            'method' => 'PUT',
            'track' => $track,
            'artists' => $artists,
            'albums' => $albums,
            'genres' => $genres,
        ])
    </div>
</x-layouts.admin>
