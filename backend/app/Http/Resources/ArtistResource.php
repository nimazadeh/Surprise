<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtistResource extends JsonResource
{
    /**
     * Player-ready artist shape (mirrors the Vanilla JS normalized record).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'bio' => $this->bio,
            'artwork' => $this->image_url,
            'country' => $this->country,
            'language' => $this->language,
            'featured' => (bool) $this->is_featured,
            'source' => $this->source,
            'albums_count' => $this->whenCounted('albums'),
            'tracks_count' => $this->whenCounted('tracks'),
            'albums' => AlbumResource::collection($this->whenLoaded('albums')),
            'url' => route('artists.show', $this->slug),
        ];
    }
}
