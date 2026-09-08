<x-layouts.admin :title="__('music.tracks.title')">
    <div class="toolbar">
        <div>
            <h1>{{ __('music.tracks.title') }}</h1>
            <p class="muted">{{ __('music.list.results', ['count' => $tracks->total()]) }}</p>
        </div>
        <a class="btn" href="{{ route('admin.tracks.create') }}">{{ __('music.tracks.create') }}</a>
    </div>

    <form class="search-form" method="GET" action="{{ route('admin.tracks.index') }}" role="search">
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('music.actions.search_placeholder') }}" aria-label="{{ __('music.actions.search') }}">
        <button class="btn btn-secondary" type="submit">{{ __('music.actions.search') }}</button>
    </form>

    <div class="card">
        @if ($tracks->isEmpty())
            <p class="muted">{{ __('music.tracks.empty') }}</p>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('music.list.name') }}</th>
                            <th>{{ __('music.list.artist') }}</th>
                            <th>{{ __('music.list.album') }}</th>
                            <th>{{ __('music.list.genre') }}</th>
                            <th>{{ __('music.list.duration') }}</th>
                            <th>{{ __('music.list.status') }}</th>
                            <th><span class="visually-hidden">{{ __('music.actions.edit') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tracks as $track)
                            <tr>
                                <td>
                                    <strong>{{ $track->title }}</strong>
                                    <div class="muted small">/{{ $track->slug }}</div>
                                </td>
                                <td>{{ $track->artist?->name ?? __('music.list.none') }}</td>
                                <td>{{ $track->album?->title ?? __('music.list.none') }}</td>
                                <td>{{ $track->genre?->name ?? __('music.list.none') }}</td>
                                <td dir="ltr">{{ $track->duration_sec !== null ? intdiv($track->duration_sec, 60).':'.str_pad((string) ($track->duration_sec % 60), 2, '0', STR_PAD_LEFT) : __('music.list.none') }}</td>
                                <td><span class="status-pill status-{{ $track->status }}">{{ __('music.status.'.$track->status) }}</span></td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.tracks.edit', $track) }}">{{ __('music.actions.edit') }}</a>
                                        @if ($track->isPublished())
                                            <a class="btn btn-secondary btn-sm" href="{{ route('tracks.show', $track->slug) }}">{{ __('music.actions.view') }}</a>
                                        @endif
                                        <form method="POST" action="{{ route('admin.tracks.toggle', $track) }}" style="display:inline">
                                            @csrf
                                            <button class="btn btn-secondary btn-sm" type="submit">{{ $track->isPublished() ? __('music.actions.unpublish') : __('music.actions.publish') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.tracks.destroy', $track) }}" style="display:inline" onsubmit="return confirm('{{ __('music.actions.confirm_delete') }}')">
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

    @include('admin.partials.pager', ['paginator' => $tracks])
</x-layouts.admin>
