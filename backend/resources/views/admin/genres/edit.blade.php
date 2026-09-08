<x-layouts.admin :title="__('music.genres.edit')">
    <div class="toolbar">
        <h1>{{ __('music.genres.edit') }}</h1>
        <a class="btn btn-secondary" href="{{ route('admin.genres.index') }}">{{ __('music.actions.back') }}</a>
    </div>

    <div class="card">
        @include('admin.genres._form', [
            'action' => route('admin.genres.update', $genre),
            'method' => 'PUT',
            'genre' => $genre,
        ])
    </div>
</x-layouts.admin>
