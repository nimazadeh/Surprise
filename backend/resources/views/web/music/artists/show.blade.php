@php($pageTitle = $artist->seo_title ?: $artist->name)
@php($pageDescription = $artist->seo_description ?: \Illuminate\Support\Str::limit((string) $artist->bio, 160))
<x-layouts.app :title="$pageTitle" :description="$pageDescription">
    @push('head')
        <link rel="canonical" href="{{ route('artists.show', $artist->slug) }}">
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'MusicGroup',
                'name' => $artist->name,
                'url' => route('artists.show', $artist->slug),
                'image' => $artist->image_url,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
        </script>
    @endpush

    <div class="card">
        <div class="media-head">
            @if ($artist->image_url)
                <img class="media-art" src="{{ $artist->image_url }}" alt="{{ $artist->name }}">
            @endif
            <div>
                <h1>{{ $artist->name }}</h1>
                @if ($artist->bio)
                    <p class="muted">{{ $artist->bio }}</p>
                @endif
                <ul class="media-meta">
                    <li>{{ __('music.public.albums_count', ['count' => $artist->albums_count]) }}</li>
                    <li>{{ __('music.public.tracks_count', ['count' => $artist->tracks_count]) }}</li>
                    @if ($artist->country)
                        <li>{{ $artist->country }}</li>
                    @endif
                </ul>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>{{ __('music.public.albums_heading') }}</h2>
        @if ($albums->isEmpty())
            <p class="muted">{{ __('music.public.albums_empty') }}</p>
        @else
            <div class="album-grid">
                @foreach ($albums as $album)
                    <a class="album-card" href="{{ route('albums.show', $album->slug) }}">
                        @if ($album->cover_url)
                            <img src="{{ $album->cover_url }}" alt="{{ $album->title }}" loading="lazy">
                        @endif
                        <strong>{{ $album->title }}</strong>
                        <span>{{ __('music.type.'.$album->type) }}@if ($album->release_year)· {{ $album->release_year }}@endif</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
