<x-layouts.app :title="__('site.home_title')">
    <div class="card">
        <h1>{{ __('site.home_title') }}</h1>
        <p class="muted">{{ __('site.home_body') }}</p>
        <p>
            <a class="btn" href="{{ route('artists.index') }}">{{ __('nav.artists') }}</a>
            <a class="btn" href="{{ route('albums.index') }}">{{ __('nav.albums') }}</a>
            <a class="btn" href="{{ route('tracks.index') }}">{{ __('nav.tracks') }}</a>
        </p>
        @guest
            <p>
                <a class="btn btn-secondary" href="{{ route('register') }}">{{ __('nav.register') }}</a>
                <a class="btn btn-secondary" href="{{ route('login') }}">{{ __('nav.login') }}</a>
            </p>
        @endguest
    </div>
</x-layouts.app>
