<form method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="form-grid">
        <div class="field">
            <label for="artist_id">{{ __('music.fields.artist') }}</label>
            <select id="artist_id" name="artist_id" required>
                @foreach ($artists as $artist)
                    <option value="{{ $artist->id }}" @selected((int) old('artist_id', $album?->artist_id) === $artist->id)>{{ $artist->name }}</option>
                @endforeach
            </select>
            @error('artist_id')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="type">{{ __('music.fields.type') }}</label>
            <select id="type" name="type" required>
                @foreach (['album', 'single', 'ep', 'compilation'] as $type)
                    <option value="{{ $type }}" @selected(old('type', $album?->type ?? 'album') === $type)>{{ __('music.type.'.$type) }}</option>
                @endforeach
            </select>
            @error('type')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="title">{{ __('music.fields.title') }}</label>
            <input id="title" type="text" name="title" value="{{ old('title', $album?->title) }}" required maxlength="255">
            @error('title')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="slug">{{ __('music.fields.slug') }}</label>
            <input id="slug" type="text" name="slug" value="{{ old('slug', $album?->slug) }}" maxlength="255" dir="ltr">
            <div class="hint">{{ __('music.fields.slug_hint') }}</div>
            @error('slug')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="field">
        <label for="description">{{ __('music.fields.description') }}</label>
        <textarea id="description" name="description" rows="4" maxlength="5000">{{ old('description', $album?->description) }}</textarea>
        @error('description')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="cover">{{ __('music.fields.cover') }}</label>
            @if ($album?->cover_url)
                <div><img class="preview" src="{{ $album->cover_url }}" alt=""></div>
            @endif
            <input id="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp">
            <div class="hint">{{ __('music.fields.image_hint') }}</div>
            @error('cover')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="release_date">{{ __('music.fields.release_date') }}</label>
            <input id="release_date" type="date" name="release_date" value="{{ old('release_date', $album?->release_date?->toDateString()) }}">
            @error('release_date')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="release_year">{{ __('music.fields.release_year') }}</label>
            <input id="release_year" type="number" name="release_year" value="{{ old('release_year', $album?->release_year) }}" min="1900" max="2100" dir="ltr">
            @error('release_year')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="status">{{ __('music.fields.status') }}</label>
            <select id="status" name="status" required>
                @foreach (['draft', 'published', 'archived'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $album?->status ?? 'draft') === $status)>{{ __('music.status.'.$status) }}</option>
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
                    <option value="{{ $source }}" @selected(old('source', $album?->source ?? 'owned') === $source)>{{ $source }}</option>
                @endforeach
            </select>
            @error('source')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="provider_id">{{ __('music.fields.provider_id') }}</label>
            <input id="provider_id" type="text" name="provider_id" value="{{ old('provider_id', $album?->provider_id) }}" maxlength="255" dir="ltr">
            @error('provider_id')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="field">
        <label for="seo_title">{{ __('music.fields.seo_title') }}</label>
        <input id="seo_title" type="text" name="seo_title" value="{{ old('seo_title', $album?->seo_title) }}" maxlength="255">
        @error('seo_title')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="seo_description">{{ __('music.fields.seo_description') }}</label>
        <textarea id="seo_description" name="seo_description" rows="2" maxlength="500">{{ old('seo_description', $album?->seo_description) }}</textarea>
        @error('seo_description')<div class="error">{{ $message }}</div>@enderror
    </div>

    <button class="btn" type="submit">{{ __('music.actions.save') }}</button>
    <a class="btn btn-secondary" href="{{ route('admin.albums.index') }}">{{ __('music.actions.cancel') }}</a>
</form>
