@props(['title' => null, 'description' => null, 'narrow' => false])
<!DOCTYPE html>
<html lang="{{ $htmlLocale ?? app()->getLocale() }}" dir="{{ $htmlDir ?? 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'SHIRIN') }}</title>
    <meta name="description" content="{{ $description ?? __('site.tagline') }}">
    <link rel="stylesheet" href="{{ asset('css/shirin.css') }}">
</head>
<body>
<header class="topbar">
    <a class="brand" href="{{ route('home') }}">SHIRIN</a>
    <nav aria-label="Primary">
        <a href="{{ route('locale.switch', ['locale' => app()->getLocale() === 'fa' ? 'en' : 'fa']) }}">
            {{ __('nav.language') }}: {{ app()->getLocale() === 'fa' ? 'EN' : 'FA' }}
        </a>
        @auth
            @can('admin.access')
                <a href="{{ route('admin.dashboard') }}">{{ __('nav.admin') }}</a>
            @endcan
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button class="link-button" type="submit">{{ __('nav.logout') }}</button>
            </form>
        @else
            <a href="{{ route('login') }}">{{ __('nav.login') }}</a>
            <a href="{{ route('register') }}">{{ __('nav.register') }}</a>
        @endauth
    </nav>
</header>

<main class="container @isset($narrow) container-narrow @endisset">
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    {{ $slot }}
</main>

<footer class="footer">
    <nav>
        <a href="{{ route('legal.terms') }}">{{ __('nav.terms') }}</a>
        <a href="{{ route('legal.privacy') }}">{{ __('nav.privacy') }}</a>
        <a href="{{ route('legal.takedown') }}">{{ __('nav.takedown') }}</a>
    </nav>
    <span>SHIRIN · {{ __('site.tagline') }}</span>
</footer>
</body>
</html>
