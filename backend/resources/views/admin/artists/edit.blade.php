<x-layouts.admin :title="__('music.artists.edit')">
    <div class="toolbar">
        <h1>{{ __('music.artists.edit') }}</h1>
        <div class="row-actions">
            @if ($artist->isPublished())
                <a class="btn btn-secondary" href="{{ route('artists.show', $artist->slug) }}">{{ __('music.actions.view') }}</a>
            @endif
            <a class="btn btn-secondary" href="{{ route('admin.artists.index') }}">{{ __('music.actions.back') }}</a>
        </div>
    </div>

    <div class="card">
        @include('admin.artists._form', [
            'action' => route('admin.artists.update', $artist),
            'method' => 'PUT',
            'artist' => $artist,
        ])
    </div>
</x-layouts.admin>
