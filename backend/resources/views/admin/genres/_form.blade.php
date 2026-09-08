<form method="POST" action="{{ $action }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="form-grid">
        <div class="field">
            <label for="name">{{ __('music.fields.name') }}</label>
            <input id="name" type="text" name="name" value="{{ old('name', $genre?->name) }}" required maxlength="255">
            @error('name')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="slug">{{ __('music.fields.slug') }}</label>
            <input id="slug" type="text" name="slug" value="{{ old('slug', $genre?->slug) }}" maxlength="255" dir="ltr">
            <div class="hint">{{ __('music.fields.slug_hint') }}</div>
            @error('slug')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="field">
        <label for="description">{{ __('music.fields.description') }}</label>
        <textarea id="description" name="description" rows="3" maxlength="2000">{{ old('description', $genre?->description) }}</textarea>
        @error('description')<div class="error">{{ $message }}</div>@enderror
    </div>

    <button class="btn" type="submit">{{ __('music.actions.save') }}</button>
    <a class="btn btn-secondary" href="{{ route('admin.genres.index') }}">{{ __('music.actions.cancel') }}</a>
</form>
