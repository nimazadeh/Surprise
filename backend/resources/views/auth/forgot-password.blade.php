<x-layouts.app :title="__('auth.forgot')" narrow>
    <div class="card">
        <h1>{{ __('auth.forgot') }}</h1>
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="field">
                <label for="email">{{ __('auth.email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                @error('email')<div class="error">{{ $message }}</div>@enderror
            </div>
            <button class="btn" type="submit">{{ __('auth.send_reset_link') }}</button>
        </form>
    </div>
</x-layouts.app>
