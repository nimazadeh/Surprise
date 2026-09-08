<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Genre extends Model
{
    /** @use HasFactory<\Database\Factories\GenreFactory> */
    use HasFactory, HasSlug;

    /**
     * Genres are taxonomy: no soft deletes, deleted genres release their
     * tracks to genre_id NULL (see tracks migration).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function slugSource(): string
    {
        return $this->name ?? '';
    }

    public function slugSubject(): string
    {
        return SlugRedirect::SUBJECT_GENRE;
    }

    /**
     * @return HasMany<Track, $this>
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }

    /**
     * @param  Builder<Genre>  $query
     */
    public function scopeOrdered(Builder $query): Builder
    {
        // Trailing id keeps paginated lists deterministic on ties.
        return $query->orderBy('name')->orderBy('id');
    }
}
