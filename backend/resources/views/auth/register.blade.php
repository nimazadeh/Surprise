<x-layouts.app :title="__('auth.register')" narrow>
    <div class="card">
        <h1>{{ __('auth.register') }}</h1>
        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="field">
                <label for="name">{{ __('auth.name') }}</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                @error('name')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="username">{{ __('auth.username') }}</label>
                <input id="username" type="text" name="username" value="{{ old('username') }}" required autocomplete="username">
                @error('username')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="email">{{ __('auth.email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                @error('email')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="password">{{ __('auth.password') }}</label>
                <input id="password" type="password" name="password" required autocomplete="new-password">
                @error('password')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation">{{ __('auth.password_confirmation') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button class="btn" type="submit">{{ __('auth.register') }}</button>
        </form>
    </div>
</x-layouts.app>
