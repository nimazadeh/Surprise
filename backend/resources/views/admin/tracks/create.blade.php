<x-layouts.admin :title="__('music.tracks.create')">
    <div class="toolbar">
        <h1>{{ __('music.tracks.create') }}</h1>
        <a class="btn btn-secondary" href="{{ route('admin.tracks.index') }}">{{ __('music.actions.back') }}</a>
    </div>

    <div class="card">
        @include('admin.tracks._form', [
            'action' => route('admin.tracks.store'),
            'method' => 'POST',
            'track' => null,
            'artists' => $artists,
            'albums' => $albums,
            'genres' => $genres,
        ])
    </div>
</x-layouts.admin>
