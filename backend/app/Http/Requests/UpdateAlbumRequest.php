<?php

namespace App\Http\Requests;

use App\Models\Album;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlbumRequest extends FormRequest
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
            'artist_id' => ['sometimes', 'integer', 'exists:artists,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('albums', 'slug')->ignore($this->route('album'))],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'release_date' => ['nullable', 'date'],
            'release_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'type' => ['sometimes', 'string', 'in:'.implode(',', [Album::TYPE_ALBUM, Album::TYPE_SINGLE, Album::TYPE_EP, Album::TYPE_COMPILATION])],
            'status' => ['sometimes', 'string', 'in:'.implode(',', [Album::STATUS_DRAFT, Album::STATUS_PUBLISHED, Album::STATUS_ARCHIVED])],
            'source' => ['sometimes', 'string', 'in:'.implode(',', [Album::SOURCE_OWNED, Album::SOURCE_DEEZER])],
            'provider_id' => ['nullable', 'string', 'max:255'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
