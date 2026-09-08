<x-layouts.admin :title="__('music.artists.create')">
    <div class="toolbar">
        <h1>{{ __('music.artists.create') }}</h1>
        <a class="btn btn-secondary" href="{{ route('admin.artists.index') }}">{{ __('music.actions.back') }}</a>
    </div>

    <div class="card">
        @include('admin.artists._form', [
            'action' => route('admin.artists.store'),
            'method' => 'POST',
            'artist' => null,
        ])
    </div>
</x-layouts.admin>
