<x-layouts.admin :title="__('music.genres.title')">
    <div class="toolbar">
        <div>
            <h1>{{ __('music.genres.title') }}</h1>
            <p class="muted">{{ __('music.list.results', ['count' => $genres->total()]) }}</p>
        </div>
        <a class="btn" href="{{ route('admin.genres.create') }}">{{ __('music.genres.create') }}</a>
    </div>

    <div class="card">
        @if ($genres->isEmpty())
            <p class="muted">{{ __('music.genres.empty') }}</p>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('music.list.name') }}</th>
                            <th>{{ __('music.list.tracks') }}</th>
                            <th><span class="visually-hidden">{{ __('music.actions.edit') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($genres as $genre)
                            <tr>
                                <td>
                                    <strong>{{ $genre->name }}</strong>
                                    <div class="muted small">/{{ $genre->slug }}</div>
                                </td>
                                <td>{{ $genre->tracks_count }}</td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.genres.edit', $genre) }}">{{ __('music.actions.edit') }}</a>
                                        <form method="POST" action="{{ route('admin.genres.destroy', $genre) }}" style="display:inline" onsubmit="return confirm('{{ __('music.actions.confirm_delete') }}')">
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

    @include('admin.partials.pager', ['paginator' => $genres])
</x-layouts.admin>
