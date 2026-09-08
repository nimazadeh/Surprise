@php($pageTitle = $track->seo_title ?: $track->title)
@php($pageDescription = $track->seo_description ?: \Illuminate\Support\Str::limit((string) $track->description, 160))
<x-layouts.app :title="$pageTitle" :description="$pageDescription">
    @push('head')
        <link rel="canonical" href="{{ route('tracks.show', $track->slug) }}">
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'MusicRecording',
                'name' => $track->title,
                'url' => route('tracks.show', $track->slug),
                'duration' => $track->duration_sec ? 'PT'.$track->duration_sec.'S' : null,
                'byArtist' => $track->artist ? ['@type' => 'MusicGroup', 'name' => $track->artist->name] : null,
                'inAlbum' => $track->album ? ['@type' => 'MusicAlbum', 'name' => $track->album->title] : null,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
        </script>
    @endpush

    <div class="card">
        <div class="media-head">
            @if ($track->album?->cover_url)
                <img class="media-art" src="{{ $track->album->cover_url }}" alt="{{ $track->album->title }}">
            @endif
            <div>
                <h1>{{ $track->title }}</h1>
                @if ($track->artist)
                    <p class="muted">{{ __('music.public.by', ['name' => $track->artist->name]) }}</p>
                @endif
                @if ($track->description)
                    <p class="muted">{{ $track->description }}</p>
                @endif
                <ul class="media-meta">
                    @if ($track->album)
                        <li><a href="{{ route('albums.show', $track->album->slug) }}">{{ $track->album->title }}</a></li>
                    @endif
                    @if ($track->genre)
                        <li>{{ __('music.public.genre_label') }}: {{ $track->genre->name }}</li>
                    @endif
                    @if ($track->duration_sec !== null)
                        <li dir="ltr">{{ __('music.public.duration_label') }}: {{ intdiv($track->duration_sec, 60).':'.str_pad((string) ($track->duration_sec % 60), 2, '0', STR_PAD_LEFT) }}</li>
                    @endif
                    <li>{{ __('music.public.language_label') }}: {{ $track->language }}</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="card">
        @if ($track->lyrics_available)
            <p class="muted">{{ __('music.public.lyrics_note') }}</p>
        @endif
        <p class="muted">{{ __('music.public.player_note') }}</p>
        @if ($track->artist)
            <p><a class="btn btn-secondary" href="{{ route('artists.show', $track->artist->slug) }}">{{ __('music.public.back_to_artist', ['name' => $track->artist->name]) }}</a></p>
        @endif
    </div>
</x-layouts.app>
