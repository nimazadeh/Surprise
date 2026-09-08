<?php

namespace App\Models\Concerns;

use App\Models\SlugRedirect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * URL-safe slugs with collision handling (C-04) and automatic 301 records
 * for renames (C-01). Soft-deleted rows keep their slugs reserved (C-03).
 */
trait HasSlug
{
    /**
     * Human source the slug is derived from (name/title) when none is given.
     */
    abstract public function slugSource(): string;

    /**
     * Subject type stored in `slug_redirects` (artist|album|track|genre).
     */
    abstract public function slugSubject(): string;

    /**
     * Secondary suffix used on collision (e.g. the owning artist slug).
     */
    public function slugSecondary(): ?string
    {
        return null;
    }

    public static function bootHasSlug(): void
    {
        static::creating(function (Model $model): void {
            /** @var HasSlug $model */
            $source = $model->slug ?: $model->slugSource();
            $model->slug = static::makeUniqueSlug(
                $source,
                $model->slug ? null : $model->slugSecondary()
            );
        });

        static::updating(function (Model $model): void {
            /** @var HasSlug $model */
            if ($model->isDirty('slug') && $model->getOriginal('slug')) {
                SlugRedirect::updateOrCreate(
                    [
                        'subject_type' => $model->slugSubject(),
                        'old_slug' => $model->getOriginal('slug'),
                    ],
                    ['new_slug' => $model->slug]
                );
            }
        });
    }

    /**
     * Build a unique slug: base, then base-secondary (C-04), then numeric
     * suffixes, then a uuid8 fallback. Validation rejects duplicates first;
     * this is the race-safe backstop (DB unique stays authoritative).
     */
    public static function makeUniqueSlug(string $source, ?string $secondary = null, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'item';
        $secondary = $secondary ? Str::slug($secondary) : null;

        $candidates = [$base];

        if ($secondary) {
            $candidates[] = $base.'-'.$secondary;
        }

        foreach ($candidates as $candidate) {
            if (! static::slugExists($candidate, $ignoreId)) {
                return $candidate;
            }
        }

        $stem = end($candidates);

        for ($i = 2; $i < 100; $i++) {
            if (! static::slugExists($stem.'-'.$i, $ignoreId)) {
                return $stem.'-'.$i;
            }
        }

        return $stem.'-'.substr((string) Str::uuid(), 0, 8);
    }

    protected static function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $query = static::query();

        // Soft-deleted rows keep their slugs reserved (C-03).
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withTrashed();
        }

        return $query->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}
