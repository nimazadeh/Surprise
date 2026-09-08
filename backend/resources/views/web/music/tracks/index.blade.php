<x-layouts.app :title="__('music.tracks.title')" :description="__('music.public.catalogue_description')">
    @push('head')
        <link rel="canonical" href="{{ route('tracks.index', array_filter(['genre' => $genreSlug])) }}">
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => __('music.tracks.title'),
                'url' => route('tracks.index'),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
        </script>
    @endpush

    <div class="toolbar">
        <div>
            <h1>{{ __('music.tracks.title') }}</h1>
            <p class="muted">{{ __('music.list.results', ['count' => $tracks->total()]) }}</p>
        </div>
    </div>

    <form class="search-form" method="GET" action="{{ route('tracks.index') }}" role="search">
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('music.actions.search_placeholder') }}" aria-label="{{ __('music.actions.search') }}">
        <select name="genre" aria-label="{{ __('music.public.filter_genre') }}">
            <option value="">{{ __('music.public.all_genres') }}</option>
            @foreach ($genres as $genre)
                <option value="{{ $genre->slug }}" @selected($genre->slug === $genreSlug)>{{ $genre->name }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit">{{ __('music.actions.search') }}</button>
    </form>

    <div class="card">
        @if ($tracks->isEmpty())
            <p class="muted">{{ ($q !== '' || $genreSlug !== '') ? __('music.public.search_empty') : __('music.public.tracks_empty') }}</p>
        @else
            <ol class="track-list">
                @foreach ($tracks as $track)
                    <li>
                        <a href="{{ route('tracks.show', $track->slug) }}">{{ $track->title }}</a>
                        <span class="muted">
                            {{ $track->artist?->name }}@if ($track->album) · {{ $track->album->title }}@endif
                            @if ($track->duration_sec !== null)
                                · <span dir="ltr">{{ intdiv($track->duration_sec, 60).':'.str_pad((string) ($track->duration_sec % 60), 2, '0', STR_PAD_LEFT) }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
            @include('web.music.partials.pager', ['paginator' => $tracks])
        @endif
    </div>
</x-layouts.app>
