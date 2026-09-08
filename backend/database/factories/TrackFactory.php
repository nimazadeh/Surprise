<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Track>
 */
class TrackFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'artist_id' => Artist::factory(),
            'album_id' => null,
            'title' => ucfirst(trim(fake()->unique()->words(3, true))),
            'track_number' => 1,
            'duration_sec' => 180,
            'language' => 'fa',
            'status' => Track::STATUS_DRAFT,
            'source' => Track::SOURCE_OWNED,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Track::STATUS_PUBLISHED,
        ]);
    }
}
