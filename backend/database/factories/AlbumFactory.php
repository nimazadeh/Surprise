<?php

namespace Database\Factories;

use App\Models\Album;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Album>
 */
class AlbumFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'artist_id' => Artist::factory(),
            'title' => ucfirst(trim(fake()->unique()->words(3, true))),
            'description' => fake()->paragraph(),
            'release_year' => (int) fake()->year(),
            'type' => Album::TYPE_ALBUM,
            'status' => Album::STATUS_DRAFT,
            'source' => Album::SOURCE_OWNED,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Album::STATUS_PUBLISHED,
        ]);
    }
}
