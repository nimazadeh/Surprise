<x-layouts.app :title="__('site.home_title')">
    <div class="card">
        <h1>{{ __('site.home_title') }}</h1>
        <p class="muted">{{ __('site.home_body') }}</p>
        @guest
            <p>
                <a class="btn" href="{{ route('register') }}">{{ __('nav.register') }}</a>
                <a class="btn btn-secondary" href="{{ route('login') }}">{{ __('nav.login') }}</a>
            </p>
        @endguest
    </div>
</x-layouts.app>
