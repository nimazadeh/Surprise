<?php

namespace App\Http\Requests;

use App\Models\Artist;
use Illuminate\Foundation\Http\FormRequest;

class StoreArtistRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:artists,slug'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'country' => ['nullable', 'string', 'max:100'],
            'language' => ['required', 'string', 'in:fa,en'],
            'is_featured' => ['sometimes', 'boolean'],
            'status' => ['required', 'string', 'in:'.implode(',', [Artist::STATUS_DRAFT, Artist::STATUS_PUBLISHED, Artist::STATUS_ARCHIVED])],
            'source' => ['sometimes', 'string', 'in:'.implode(',', [Artist::SOURCE_OWNED, Artist::SOURCE_DEEZER])],
            'provider_id' => ['nullable', 'string', 'max:255'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
