<?php

namespace App\Models;

use App\Contracts\CoverArtService;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Artist extends Model
{
    /** @use HasFactory<\Database\Factories\ArtistFactory> */
    use HasFactory, HasSlug, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const SOURCE_OWNED = 'owned';

    public const SOURCE_DEEZER = 'deezer';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'bio',
        'image',
        'country',
        'language',
        'is_featured',
        'status',
        'source',
        'provider_id',
        'seo_title',
        'seo_description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
        ];
    }

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
        return SlugRedirect::SUBJECT_ARTIST;
    }

    /**
     * @return HasMany<Album, $this>
     */
    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    /**
     * @return HasMany<Track, $this>
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Public proxy URL for the artist image (presentation helper).
     */
    public function getImageUrlAttribute(): ?string
    {
        return app(CoverArtService::class)->url($this->image);
    }

    /**
     * @param  Builder<Artist>  $query
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * @param  Builder<Artist>  $query
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * @param  Builder<Artist>  $query
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')->orderBy('name');
    }

    /**
     * Admin list search (LIKE-based per C-14; no fulltext in v1).
     *
     * @param  Builder<Artist>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like, $term): void {
            $q->where('name', 'like', $like)->orWhere('slug', $term);
        });
    }
}
