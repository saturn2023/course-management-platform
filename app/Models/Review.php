<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'reviewer_name',
        'review_date',
        'rating',
        'review_text',
        'reviewer_avatar',
        'source',
        'source_url',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'review_date' => 'date',
        'rating' => 'integer',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * The reviews the homepage shows: active only, in admin order. id is the
     * tie-breaker so equal display_order values still come out in a stable
     * order. Same shape as EnrolmentStep::scopeForHomepage().
     */
    public function scopeForHomepage(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id');
    }

    /**
     * Public URL of the uploaded avatar, or null when none was supplied — the
     * card then falls back to an initial. Same path handling the homepage
     * settings use.
     */
    public function avatarUrl(): ?string
    {
        $path = $this->reviewer_avatar;

        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }

    /**
     * First letter of the reviewer's name for the fallback avatar circle.
     * mb_* so a non-ASCII first letter is not cut in half.
     */
    public function initial(): string
    {
        $name = trim((string) $this->reviewer_name);

        return $name === '' ? '?' : mb_strtoupper(mb_substr($name, 0, 1));
    }

    /**
     * Rating clamped to the 0-5 the star row can actually draw, so a bad value
     * in the database can never render six stars or a negative loop.
     */
    public function starCount(): int
    {
        return max(0, min(5, (int) $this->rating));
    }
}
