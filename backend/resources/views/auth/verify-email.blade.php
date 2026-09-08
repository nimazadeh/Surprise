<x-layouts.app :title="__('auth.verify_title')" narrow>
    <div class="card">
        <h1>{{ __('auth.verify_title') }}</h1>
        <p class="muted">{{ __('auth.verify_body') }}</p>
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button class="btn" type="submit">{{ __('auth.verify_resend') }}</button>
        </form>
    </div>
</x-layouts.app>
