<x-layouts.app :title="__('music.artists.title')" :description="__('music.public.catalogue_description')">
    @push('head')
        <link rel="canonical" href="{{ route('artists.index') }}">
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => __('music.artists.title'),
                'url' => route('artists.index'),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
        </script>
    @endpush

    <div class="toolbar">
        <div>
            <h1>{{ __('music.artists.title') }}</h1>
            <p class="muted">{{ __('music.list.results', ['count' => $artists->total()]) }}</p>
        </div>
    </div>

    <form class="search-form" method="GET" action="{{ route('artists.index') }}" role="search">
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('music.actions.search_placeholder') }}" aria-label="{{ __('music.actions.search') }}">
        <button class="btn btn-secondary" type="submit">{{ __('music.actions.search') }}</button>
    </form>

    <div class="card">
        @if ($artists->isEmpty())
            <p class="muted">{{ $q !== '' ? __('music.public.search_empty') : __('music.public.artists_empty') }}</p>
        @else
            <div class="album-grid">
                @foreach ($artists as $artist)
                    <a class="album-card" href="{{ route('artists.show', $artist->slug) }}">
                        @if ($artist->image_url)
                            <img src="{{ $artist->image_url }}" alt="{{ $artist->name }}" loading="lazy">
                        @endif
                        <strong>{{ $artist->name }}</strong>
                        <span>{{ __('music.public.albums_count', ['count' => $artist->albums_count]) }}</span>
                    </a>
                @endforeach
            </div>
            @include('web.music.partials.pager', ['paginator' => $artists])
        @endif
    </div>
</x-layouts.app>
