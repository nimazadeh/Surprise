<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Track extends Model
{
    /** @use HasFactory<\Database\Factories\TrackFactory> */
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
        'album_id',
        'artist_id',
        'title',
        'slug',
        'description',
        'track_number',
        'duration_sec',
        'genre_id',
        'language',
        'lyrics_available',
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
            'track_number' => 'integer',
            'duration_sec' => 'integer',
            'lyrics_available' => 'boolean',
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
        return SlugRedirect::SUBJECT_TRACK;
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
     * @return BelongsTo<Album, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * @return BelongsTo<Genre, $this>
     */
    public function genre(): BelongsTo
    {
        return $this->belongsTo(Genre::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * @param  Builder<Track>  $query
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * @param  Builder<Track>  $query
     */
    public function scopeOrdered(Builder $query): Builder
    {
        // Trailing id keeps paginated lists deterministic on ties.
        return $query->orderBy('track_number')->orderBy('title')->orderBy('id');
    }

    /**
     * Admin list search (LIKE-based per C-14; no fulltext in v1).
     *
     * @param  Builder<Track>  $query
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
