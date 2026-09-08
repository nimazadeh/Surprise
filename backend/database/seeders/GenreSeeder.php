<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    /**
     * Idempotent base taxonomy. Slugs stay Latin/URL-safe (alpha_dash);
     * localized genre display names arrive with the catalogue-delivery phase.
     */
    public function run(): void
    {
        $genres = [
            ['slug' => 'pop', 'name' => 'Pop'],
            ['slug' => 'rock', 'name' => 'Rock'],
            ['slug' => 'hip-hop', 'name' => 'Hip-Hop'],
            ['slug' => 'rnb', 'name' => 'R&B'],
            ['slug' => 'electronic', 'name' => 'Electronic'],
            ['slug' => 'jazz', 'name' => 'Jazz'],
            ['slug' => 'classical', 'name' => 'Classical'],
            ['slug' => 'folk', 'name' => 'Folk'],
        ];

        foreach ($genres as $genre) {
            Genre::firstOrCreate(['slug' => $genre['slug']], $genre);
        }
    }
}
