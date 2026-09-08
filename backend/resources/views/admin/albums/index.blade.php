<x-layouts.admin :title="__('music.albums.title')">
    <div class="toolbar">
        <div>
            <h1>{{ __('music.albums.title') }}</h1>
            <p class="muted">{{ __('music.list.results', ['count' => $albums->total()]) }}</p>
        </div>
        <a class="btn" href="{{ route('admin.albums.create') }}">{{ __('music.albums.create') }}</a>
    </div>

    <form class="search-form" method="GET" action="{{ route('admin.albums.index') }}" role="search">
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('music.actions.search_placeholder') }}" aria-label="{{ __('music.actions.search') }}">
        <button class="btn btn-secondary" type="submit">{{ __('music.actions.search') }}</button>
    </form>

    <div class="card">
        @if ($albums->isEmpty())
            <p class="muted">{{ __('music.albums.empty') }}</p>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('music.list.image') }}</th>
                            <th>{{ __('music.list.name') }}</th>
                            <th>{{ __('music.list.artist') }}</th>
                            <th>{{ __('music.list.type') }}</th>
                            <th>{{ __('music.list.tracks') }}</th>
                            <th>{{ __('music.list.status') }}</th>
                            <th><span class="visually-hidden">{{ __('music.actions.edit') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($albums as $album)
                            <tr>
                                <td>
                                    @if ($album->cover_url)
                                        <img class="thumb" src="{{ $album->cover_url }}" alt="">
                                    @else
                                        <span class="muted">{{ __('music.list.none') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $album->title }}</strong>
                                    <div class="muted small">/{{ $album->slug }}</div>
                                </td>
                                <td>{{ $album->artist?->name ?? __('music.list.none') }}</td>
                                <td>{{ __('music.type.'.$album->type) }}</td>
                                <td>{{ $album->tracks_count }}</td>
                                <td><span class="status-pill status-{{ $album->status }}">{{ __('music.status.'.$album->status) }}</span></td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.albums.edit', $album) }}">{{ __('music.actions.edit') }}</a>
                                        @if ($album->isPublished())
                                            <a class="btn btn-secondary btn-sm" href="{{ route('albums.show', $album->slug) }}">{{ __('music.actions.view') }}</a>
                                        @endif
                                        <form method="POST" action="{{ route('admin.albums.toggle', $album) }}" style="display:inline">
                                            @csrf
                                            <button class="btn btn-secondary btn-sm" type="submit">{{ $album->isPublished() ? __('music.actions.unpublish') : __('music.actions.publish') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.albums.destroy', $album) }}" style="display:inline" onsubmit="return confirm('{{ __('music.actions.confirm_delete') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-sm" type="submit">{{ __('music.actions.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @include('admin.partials.pager', ['paginator' => $albums])
</x-layouts.admin>
