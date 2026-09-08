@use('App\Models\Album')
<x-layouts.app :title="__('music.albums.title')" :description="__('music.public.catalogue_description')">
    @push('head')
        <link rel="canonical" href="{{ route('albums.index', array_filter(['type' => $type])) }}">
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => __('music.albums.title'),
                'url' => route('albums.index'),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
        </script>
    @endpush

    <div class="toolbar">
        <div>
            <h1>{{ __('music.albums.title') }}</h1>
            <p class="muted">{{ __('music.list.results', ['count' => $albums->total()]) }}</p>
        </div>
    </div>

    <form class="search-form" method="GET" action="{{ route('albums.index') }}" role="search">
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('music.actions.search_placeholder') }}" aria-label="{{ __('music.actions.search') }}">
        <button class="btn btn-secondary" type="submit">{{ __('music.actions.search') }}</button>
    </form>

    <nav class="filter-row" aria-label="{{ __('music.public.filter_type') }}">
        <a class="btn {{ $type === '' ? '' : 'btn-secondary' }}" href="{{ route('albums.index') }}">{{ __('music.public.all_types') }}</a>
        @foreach ([Album::TYPE_ALBUM, Album::TYPE_SINGLE, Album::TYPE_EP, Album::TYPE_COMPILATION] as $one)
            <a class="btn {{ $type === $one ? '' : 'btn-secondary' }}" href="{{ route('albums.index', ['type' => $one]) }}">{{ __('music.type.'.$one) }}</a>
        @endforeach
    </nav>

    <div class="card">
        @if ($albums->isEmpty())
            <p class="muted">{{ $q !== '' ? __('music.public.search_empty') : __('music.public.albums_empty') }}</p>
        @else
            <div class="album-grid">
                @foreach ($albums as $album)
                    <a class="album-card" href="{{ route('albums.show', $album->slug) }}">
                        @if ($album->cover_url)
                            <img src="{{ $album->cover_url }}" alt="{{ $album->title }}" loading="lazy">
                        @endif
                        <strong>{{ $album->title }}</strong>
                        <span>{{ $album->artist?->name }}@if ($album->release_year) · {{ $album->release_year }}@endif</span>
                    </a>
                @endforeach
            </div>
            @include('web.music.partials.pager', ['paginator' => $albums])
        @endif
    </div>
</x-layouts.app>
