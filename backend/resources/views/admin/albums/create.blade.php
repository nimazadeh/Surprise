<x-layouts.admin :title="__('music.albums.create')">
    <div class="toolbar">
        <h1>{{ __('music.albums.create') }}</h1>
        <a class="btn btn-secondary" href="{{ route('admin.albums.index') }}">{{ __('music.actions.back') }}</a>
    </div>

    <div class="card">
        @include('admin.albums._form', [
            'action' => route('admin.albums.store'),
            'method' => 'POST',
            'album' => null,
            'artists' => $artists,
        ])
    </div>
</x-layouts.admin>
