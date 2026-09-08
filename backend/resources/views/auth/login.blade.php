<x-layouts.app :title="__('auth.login')" narrow>
    <div class="card">
        <h1>{{ __('auth.login') }}</h1>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="email">{{ __('auth.email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                @error('email')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="password">{{ __('auth.password') }}</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
                @error('password')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label style="display:flex;gap:.5rem;align-items:center">
                    <input type="checkbox" name="remember" value="1" style="width:auto">
                    {{ __('auth.remember') }}
                </label>
            </div>
            <button class="btn" type="submit">{{ __('auth.login') }}</button>
            <a class="btn btn-secondary" href="{{ route('password.request') }}">{{ __('auth.forgot') }}</a>
        </form>
    </div>
</x-layouts.app>
