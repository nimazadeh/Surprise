@php($pageTitle = $album->seo_title ?: $album->title)
@php($pageDescription = $album->seo_description ?: \Illuminate\Support\Str::limit((string) $album->description, 160))
<x-layouts.app :title="$pageTitle" :description="$pageDescription">
    @push('head')
        <link rel="canonical" href="{{ route('albums.show', $album->slug) }}">
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'MusicAlbum',
                'name' => $album->title,
                'url' => route('albums.show', $album->slug),
                'image' => $album->cover_url,
                'byArtist' => $album->artist ? ['@type' => 'MusicGroup', 'name' => $album->artist->name] : null,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
        </script>
    @endpush

    <div class="card">
        <div class="media-head">
            @if ($album->cover_url)
                <img class="media-art" src="{{ $album->cover_url }}" alt="{{ $album->title }}">
            @endif
            <div>
                <h1>{{ $album->title }}</h1>
                @if ($album->artist)
                    <p class="muted">{{ __('music.public.by', ['name' => $album->artist->name]) }}</p>
                @endif
                @if ($album->description)
                    <p class="muted">{{ $album->description }}</p>
                @endif
                <ul class="media-meta">
                    <li>{{ __('music.type.'.$album->type) }}</li>
                    @if ($album->release_year)
                        <li>{{ __('music.public.release_label') }}: {{ $album->release_year }}</li>
                    @endif
                    <li>{{ __('music.public.tracks_count', ['count' => $tracks->count()]) }}</li>
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
                        <a href="{{ route('tracks.show', $track->slug) }}">{{ $track->track_number ? $track->track_number.'. ' : '' }}{{ $track->title }}</a>
                        @if ($track->duration_sec !== null)
                            <span class="muted" dir="ltr">{{ intdiv($track->duration_sec, 60).':'.str_pad((string) ($track->duration_sec % 60), 2, '0', STR_PAD_LEFT) }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</x-layouts.app>
