<x-layouts.admin :title="__('music.genres.create')">
    <div class="toolbar">
        <h1>{{ __('music.genres.create') }}</h1>
        <a class="btn btn-secondary" href="{{ route('admin.genres.index') }}">{{ __('music.actions.back') }}</a>
    </div>

    <div class="card">
        @include('admin.genres._form', [
            'action' => route('admin.genres.store'),
            'method' => 'POST',
            'genre' => null,
        ])
    </div>
</x-layouts.admin>
