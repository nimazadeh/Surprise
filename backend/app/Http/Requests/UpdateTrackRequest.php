<?php

namespace App\Http\Requests;

use App\Models\Track;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('music.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'album_id' => ['nullable', 'integer', 'exists:albums,id'],
            'artist_id' => ['sometimes', 'integer', 'exists:artists,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('tracks', 'slug')->ignore($this->route('track'))],
            'description' => ['nullable', 'string', 'max:5000'],
            'track_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'duration_sec' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'genre_id' => ['nullable', 'integer', 'exists:genres,id'],
            'language' => ['sometimes', 'string', 'in:fa,en'],
            'lyrics_available' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', [Track::STATUS_DRAFT, Track::STATUS_PUBLISHED, Track::STATUS_ARCHIVED])],
            'source' => ['sometimes', 'string', 'in:'.implode(',', [Track::SOURCE_OWNED, Track::SOURCE_DEEZER])],
            'provider_id' => ['nullable', 'string', 'max:255'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
