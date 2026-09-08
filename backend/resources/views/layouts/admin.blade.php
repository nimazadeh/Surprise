@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ $htmlLocale ?? app()->getLocale() }}" dir="{{ $htmlDir ?? 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? __('admin.title') }} · SHIRIN</title>
    <link rel="stylesheet" href="{{ asset('css/shirin.css') }}">
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar" aria-label="Admin navigation">
        <a class="brand" href="{{ route('admin.dashboard') }}">SHIRIN · {{ __('admin.title') }}</a>
        @foreach (config('shirin_nav', []) as $group)
            <span class="group-label">{{ __($group['group']) }}</span>
            @foreach ($group['items'] as $item)
                @can($item['permission'])
                    @if ($item['route'] && Route::has($item['route']))
                        <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['route']) ? 'active' : '' }}">
                            {{ __($item['label']) }}
                        </a>
                    @else
                        <span class="disabled" title="{{ __('admin.coming_soon') }}">
                            {{ __($item['label']) }}
                            <span class="badge">{{ __('admin.phase_badge', ['phase' => $item['phase']]) }}</span>
                        </span>
                    @endif
                @endcan
            @endforeach
        @endforeach
        <span class="group-label">· · ·</span>
        <a href="{{ route('home') }}">{{ __('admin.back_to_site') }}</a>
    </aside>

    <main class="admin-main">
        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        {{ $slot }}
    </main>
</div>
</body>
</html>
