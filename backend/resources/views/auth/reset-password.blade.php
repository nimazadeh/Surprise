<x-layouts.app :title="__('auth.reset_password')" narrow>
    <div class="card">
        <h1>{{ __('auth.reset_password') }}</h1>
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="field">
                <label for="email">{{ __('auth.email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autofocus autocomplete="email">
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
            <button class="btn" type="submit">{{ __('auth.reset_password') }}</button>
        </form>
    </div>
</x-layouts.app>
