<form method="POST" action="{{ $action }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="form-grid">
        <div class="field">
            <label for="artist_id">{{ __('music.fields.artist') }}</label>
            <select id="artist_id" name="artist_id" required>
                @foreach ($artists as $artist)
                    <option value="{{ $artist->id }}" @selected((int) old('artist_id', $track?->artist_id) === $artist->id)>{{ $artist->name }}</option>
                @endforeach
            </select>
            @error('artist_id')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="album_id">{{ __('music.fields.album') }}</label>
            <select id="album_id" name="album_id">
                <option value="">{{ __('music.fields.no_album') }}</option>
                @foreach ($albums as $album)
                    <option value="{{ $album->id }}" @selected((string) old('album_id', $track?->album_id ?? '') === (string) $album->id)>{{ $album->artist?->name }} — {{ $album->title }}</option>
                @endforeach
            </select>
            @error('album_id')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="title">{{ __('music.fields.title') }}</label>
            <input id="title" type="text" name="title" value="{{ old('title', $track?->title) }}" required maxlength="255">
            @error('title')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="slug">{{ __('music.fields.slug') }}</label>
            <input id="slug" type="text" name="slug" value="{{ old('slug', $track?->slug) }}" maxlength="255" dir="ltr">
            <div class="hint">{{ __('music.fields.slug_hint') }}</div>
            @error('slug')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="field">
        <label for="description">{{ __('music.fields.description') }}</label>
        <textarea id="description" name="description" rows="3" maxlength="5000">{{ old('description', $track?->description) }}</textarea>
        @error('description')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="genre_id">{{ __('music.fields.genre') }}</label>
            <select id="genre_id" name="genre_id">
                <option value="">{{ __('music.fields.no_genre') }}</option>
                @foreach ($genres as $genre)
                    <option value="{{ $genre->id }}" @selected((string) old('genre_id', $track?->genre_id ?? '') === (string) $genre->id)>{{ $genre->name }}</option>
                @endforeach
            </select>
            @error('genre_id')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="language">{{ __('music.fields.language') }}</label>
            <select id="language" name="language" required>
                @foreach (['fa', 'en'] as $locale)
                    <option value="{{ $locale }}" @selected(old('language', $track?->language ?? 'fa') === $locale)>{{ $locale }}</option>
                @endforeach
            </select>
            @error('language')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="track_number">{{ __('music.fields.track_number') }}</label>
            <input id="track_number" type="number" name="track_number" value="{{ old('track_number', $track?->track_number) }}" min="1" max="999" dir="ltr">
            @error('track_number')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="duration_sec">{{ __('music.fields.duration_sec') }}</label>
            <input id="duration_sec" type="number" name="duration_sec" value="{{ old('duration_sec', $track?->duration_sec) }}" min="1" max="86400" dir="ltr">
            @error('duration_sec')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="status">{{ __('music.fields.status') }}</label>
            <select id="status" name="status" required>
                @foreach (['draft', 'published', 'archived'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $track?->status ?? 'draft') === $status)>{{ __('music.status.'.$status) }}</option>
                @endforeach
            </select>
            @error('status')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="source">{{ __('music.fields.source') }}</label>
            <select id="source" name="source">
                @foreach (['owned', 'deezer'] as $source)
                    <option value="{{ $source }}" @selected(old('source', $track?->source ?? 'owned') === $source)>{{ $source }}</option>
                @endforeach
            </select>
            @error('source')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="field">
        <label for="provider_id">{{ __('music.fields.provider_id') }}</label>
        <input id="provider_id" type="text" name="provider_id" value="{{ old('provider_id', $track?->provider_id) }}" maxlength="255" dir="ltr">
        @error('provider_id')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label class="check">
            <input type="hidden" name="lyrics_available" value="0">
            <input type="checkbox" name="lyrics_available" value="1" @checked((bool) old('lyrics_available', $track?->lyrics_available ?? false))>
            {{ __('music.fields.lyrics_available') }}
        </label>
        <div class="hint">{{ __('music.fields.lyrics_hint') }}</div>
        @error('lyrics_available')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="seo_title">{{ __('music.fields.seo_title') }}</label>
        <input id="seo_title" type="text" name="seo_title" value="{{ old('seo_title', $track?->seo_title) }}" maxlength="255">
        @error('seo_title')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="seo_description">{{ __('music.fields.seo_description') }}</label>
        <textarea id="seo_description" name="seo_description" rows="2" maxlength="500">{{ old('seo_description', $track?->seo_description) }}</textarea>
        @error('seo_description')<div class="error">{{ $message }}</div>@enderror
    </div>

    <button class="btn" type="submit">{{ __('music.actions.save') }}</button>
    <a class="btn btn-secondary" href="{{ route('admin.tracks.index') }}">{{ __('music.actions.cancel') }}</a>
</form>
