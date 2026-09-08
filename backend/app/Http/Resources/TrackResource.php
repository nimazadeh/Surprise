<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackResource extends JsonResource
{
    /**
     * Player-ready track shape (mirrors the Vanilla JS normalized record:
     * id/title/artistName/albumTitle/artwork/duration/source). Track artwork
     * resolves from the parent album cover; per-track artwork is a later
     * additive column. No audio URLs exist in this phase (R-01 stands).
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
            'track_number' => $this->track_number,
            'duration' => $this->duration_sec,
            'duration_human' => $this->humanDuration(),
            'genre' => $this->whenLoaded('genre', fn () => [
                'id' => $this->genre->id,
                'name' => $this->genre->name,
                'slug' => $this->genre->slug,
            ]),
            'language' => $this->language,
            'lyrics_available' => (bool) $this->lyrics_available,
            'source' => $this->source,
            'artist_name' => $this->artist?->name,
            'artist_slug' => $this->artist?->slug,
            'album_title' => $this->album?->title,
            'album_slug' => $this->album?->slug,
            'artwork' => $this->album?->cover_url,
            'url' => route('tracks.show', $this->slug),
        ];
    }

    protected function humanDuration(): ?string
    {
        return static::format($this->duration_sec);
    }

    /**
     * Shared m:ss formatting (also used by provider-sourced items so both
     * sources render identically).
     */
    public static function format(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $total = max(0, $seconds);

        return intdiv($total, 60).':'.str_pad((string) ($total % 60), 2, '0', STR_PAD_LEFT);
    }
}
