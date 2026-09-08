@php($pageTitle = $genre->name)
<x-layouts.app :title="$pageTitle" :description="__('music.public.catalogue_description')">
    @push('head')
        <link rel="canonical" href="{{ route('genres.show', $genre->slug) }}">
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $genre->name,
                'url' => route('genres.show', $genre->slug),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
        </script>
    @endpush

    <div class="card">
        <div class="media-head">
            <div>
                <p class="muted">{{ __('music.public.genre_label') }}</p>
                <h1>{{ $genre->name }}</h1>
                @if ($genre->description)
                    <p class="muted">{{ $genre->description }}</p>
                @endif
                <ul class="media-meta">
                    <li>{{ __('music.list.results', ['count' => $tracks->total()]) }}</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>{{ __('music.public.tracks_heading') }}</h2>
        @if ($tracks->isEmpty())
            <p class="muted">{{ __('music.public.tracks_empty') }}</p>
        @else
            <ol class="track-list">
                @foreach ($tracks as $track)
                    <li>
                        <a href="{{ route('tracks.show', $track->slug) }}">{{ $track->title }}</a>
                        <span class="muted">
                            {{ $track->artist?->name }}@if ($track->album) · {{ $track->album->title }}@endif
                        </span>
                    </li>
                @endforeach
            </ol>
            @include('web.music.partials.pager', ['paginator' => $tracks])
        @endif
    </div>
</x-layouts.app>
