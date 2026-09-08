<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlbumResource extends JsonResource
{
    /**
     * Player-ready album shape (mirrors the Vanilla JS normalized record).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'artwork' => $this->cover_url,
            'release_date' => $this->release_date?->toDateString(),
            'release_year' => $this->release_year,
            'type' => $this->type,
            'source' => $this->source,
            'artist_name' => $this->artist?->name,
            'artist_slug' => $this->artist?->slug,
            'artist' => ArtistResource::make($this->whenLoaded('artist')),
            'tracks_count' => $this->whenCounted('tracks'),
            'tracks' => TrackResource::collection($this->whenLoaded('tracks')),
            'url' => route('albums.show', $this->slug),
        ];
    }
}
