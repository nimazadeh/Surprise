<x-layouts.admin :title="__('music.artists.title')">
    <div class="toolbar">
        <div>
            <h1>{{ __('music.artists.title') }}</h1>
            <p class="muted">{{ __('music.list.results', ['count' => $artists->total()]) }}</p>
        </div>
        <a class="btn" href="{{ route('admin.artists.create') }}">{{ __('music.artists.create') }}</a>
    </div>

    <form class="search-form" method="GET" action="{{ route('admin.artists.index') }}" role="search">
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('music.actions.search_placeholder') }}" aria-label="{{ __('music.actions.search') }}">
        <button class="btn btn-secondary" type="submit">{{ __('music.actions.search') }}</button>
    </form>

    <div class="card">
        @if ($artists->isEmpty())
            <p class="muted">{{ __('music.artists.empty') }}</p>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('music.list.image') }}</th>
                            <th>{{ __('music.list.name') }}</th>
                            <th>{{ __('music.list.counts') }}</th>
                            <th>{{ __('music.list.status') }}</th>
                            <th><span class="visually-hidden">{{ __('music.actions.edit') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($artists as $artist)
                            <tr>
                                <td>
                                    @if ($artist->image_url)
                                        <img class="thumb" src="{{ $artist->image_url }}" alt="">
                                    @else
                                        <span class="muted">{{ __('music.list.none') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $artist->name }}</strong>
                                    <div class="muted small">/{{ $artist->slug }}</div>
                                </td>
                                <td>{{ $artist->albums_count }} / {{ $artist->tracks_count }}</td>
                                <td><span class="status-pill status-{{ $artist->status }}">{{ __('music.status.'.$artist->status) }}</span></td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.artists.edit', $artist) }}">{{ __('music.actions.edit') }}</a>
                                        @if ($artist->isPublished())
                                            <a class="btn btn-secondary btn-sm" href="{{ route('artists.show', $artist->slug) }}">{{ __('music.actions.view') }}</a>
                                        @endif
                                        <form method="POST" action="{{ route('admin.artists.toggle', $artist) }}" style="display:inline">
                                            @csrf
                                            <button class="btn btn-secondary btn-sm" type="submit">{{ $artist->isPublished() ? __('music.actions.unpublish') : __('music.actions.publish') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.artists.destroy', $artist) }}" style="display:inline" onsubmit="return confirm('{{ __('music.actions.confirm_delete') }}')">
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

    @include('admin.partials.pager', ['paginator' => $artists])
</x-layouts.admin>
