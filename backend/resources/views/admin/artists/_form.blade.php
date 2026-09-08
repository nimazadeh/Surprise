<form method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="form-grid">
        <div class="field">
            <label for="name">{{ __('music.fields.name') }}</label>
            <input id="name" type="text" name="name" value="{{ old('name', $artist?->name) }}" required maxlength="255">
            @error('name')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="slug">{{ __('music.fields.slug') }}</label>
            <input id="slug" type="text" name="slug" value="{{ old('slug', $artist?->slug) }}" maxlength="255" dir="ltr">
            <div class="hint">{{ __('music.fields.slug_hint') }}</div>
            @error('slug')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="field">
        <label for="bio">{{ __('music.fields.bio') }}</label>
        <textarea id="bio" name="bio" rows="4" maxlength="5000">{{ old('bio', $artist?->bio) }}</textarea>
        @error('bio')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="image">{{ __('music.fields.image') }}</label>
            @if ($artist?->image_url)
                <div><img class="preview" src="{{ $artist->image_url }}" alt=""></div>
            @endif
            <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <div class="hint">{{ __('music.fields.image_hint') }}</div>
            @error('image')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="country">{{ __('music.fields.country') }}</label>
            <input id="country" type="text" name="country" value="{{ old('country', $artist?->country) }}" maxlength="100">
            @error('country')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="language">{{ __('music.fields.language') }}</label>
            <select id="language" name="language" required>
                @foreach (['fa', 'en'] as $locale)
                    <option value="{{ $locale }}" @selected(old('language', $artist?->language ?? 'fa') === $locale)>{{ $locale }}</option>
                @endforeach
            </select>
            @error('language')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="status">{{ __('music.fields.status') }}</label>
            <select id="status" name="status" required>
                @foreach (['draft', 'published', 'archived'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $artist?->status ?? 'draft') === $status)>{{ __('music.status.'.$status) }}</option>
                @endforeach
            </select>
            @error('status')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="source">{{ __('music.fields.source') }}</label>
            <select id="source" name="source">
                @foreach (['owned', 'deezer'] as $source)
                    <option value="{{ $source }}" @selected(old('source', $artist?->source ?? 'owned') === $source)>{{ $source }}</option>
                @endforeach
            </select>
            @error('source')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="provider_id">{{ __('music.fields.provider_id') }}</label>
            <input id="provider_id" type="text" name="provider_id" value="{{ old('provider_id', $artist?->provider_id) }}" maxlength="255" dir="ltr">
            @error('provider_id')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="field">
        <label class="check">
            <input type="hidden" name="is_featured" value="0">
            <input type="checkbox" name="is_featured" value="1" @checked((bool) old('is_featured', $artist?->is_featured ?? false))>
            {{ __('music.fields.is_featured') }}
        </label>
        @error('is_featured')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="seo_title">{{ __('music.fields.seo_title') }}</label>
        <input id="seo_title" type="text" name="seo_title" value="{{ old('seo_title', $artist?->seo_title) }}" maxlength="255">
        @error('seo_title')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="seo_description">{{ __('music.fields.seo_description') }}</label>
        <textarea id="seo_description" name="seo_description" rows="2" maxlength="500">{{ old('seo_description', $artist?->seo_description) }}</textarea>
        @error('seo_description')<div class="error">{{ $message }}</div>@enderror
    </div>

    <button class="btn" type="submit">{{ __('music.actions.save') }}</button>
    <a class="btn btn-secondary" href="{{ route('admin.artists.index') }}">{{ __('music.actions.cancel') }}</a>
</form>
