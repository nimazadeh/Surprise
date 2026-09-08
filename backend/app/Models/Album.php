<?php

namespace App\Models;

use App\Contracts\CoverArtService;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Album extends Model
{
    /** @use HasFactory<\Database\Factories\AlbumFactory> */
    use HasFactory, HasSlug, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const TYPE_ALBUM = 'album';

    public const TYPE_SINGLE = 'single';

    public const TYPE_EP = 'ep';

    public const TYPE_COMPILATION = 'compilation';

    public const SOURCE_OWNED = 'owned';

    public const SOURCE_DEEZER = 'deezer';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'artist_id',
        'title',
        'slug',
        'description',
        'cover',
        'release_date',
        'release_year',
        'type',
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
            'release_date' => 'date',
            'release_year' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function slugSource(): string
    {
        return $this->title ?? '';
    }

    public function slugSubject(): string
    {
        return SlugRedirect::SUBJECT_ALBUM;
    }

    public function slugSecondary(): ?string
    {
        return $this->artist?->slug;
    }

    /**
     * @return BelongsTo<Artist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
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
     * Public proxy URL for the album cover (presentation helper).
     */
    public function getCoverUrlAttribute(): ?string
    {
        return app(CoverArtService::class)->url($this->cover);
    }

    /**
     * @param  Builder<Album>  $query
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * @param  Builder<Album>  $query
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('release_year')->orderBy('title');
    }

    /**
     * Admin list search (LIKE-based per C-14; no fulltext in v1).
     *
     * @param  Builder<Album>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like, $term): void {
            $q->where('title', 'like', $like)->orWhere('slug', $term);
        });
    }
}
