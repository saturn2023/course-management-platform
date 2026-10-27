<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Single-row store for the editable /about page. Edited from the Filament
 * "About Page" page. Mirrors App\Models\HomepageSetting.
 *
 * Read it with AboutPageSetting::current(): that never writes and never
 * returns null, so the front end can always call the resolved accessors below
 * and get the defaults when nothing has been configured yet.
 */
class AboutPageSetting extends Model
{
    /** Fallbacks used whenever a field has not been filled in Filament. */
    public const DEFAULT_HERO_TITLE = 'About us';

    /** The mine-site banner from the live page, shipped in public/. */
    public const DEFAULT_HERO_IMAGE = '/images/about-hero.jpg';

    /** Describes DEFAULT_HERO_IMAGE only — see heroImageAlt(). */
    public const DEFAULT_HERO_IMAGE_ALT = 'Worker in a hard hat and high-visibility shirt walking across red earth at an open-cut mine site, with a haul truck in the distance';

    public const DEFAULT_CONTENT_HEADING = 'About AMS Training';

    public const DEFAULT_TRAINERS_HEADING = 'Trainers';

    public const DEFAULT_MISSION_HEADING = 'Mission Statement';

    /** The live page's <title> and meta description. */
    public const DEFAULT_META_TITLE = 'About AMS Training in Darwin';

    public const DEFAULT_META_DESCRIPTION = 'AMS Training was established 13 January 2021. AMS Training was registered with ASQA on the 23 September 2021, registration number 45735.';

    /**
     * Shown for a trainer with no photo uploaded — the live page uses the AMS
     * logo in that spot too.
     */
    public const DEFAULT_TRAINER_PHOTO = '/images/AMS-Training-Logo.png';

    public const DEFAULT_TRAINER_PHOTO_ALT = 'AMS Training logo';

    protected $fillable = [
        'hero_image',
        'hero_image_alt',
        'hero_title',
        'content_heading',
        'content_body',
        'trainers_heading',
        'trainers',
        'mission_heading',
        'mission_points',
        'show_reviews',
        'meta_title',
        'meta_description',
    ];

    protected $attributes = [
        'show_reviews' => false,
    ];

    protected function casts(): array
    {
        return [
            'trainers' => 'array',
            'mission_points' => 'array',
            'show_reviews' => 'boolean',
        ];
    }

    /**
     * The settings row for reading (front end). Returns an unsaved instance
     * when the row does not exist yet, so every accessor falls back to its
     * default instead of blowing up on null.
     */
    public static function current(): self
    {
        return static::query()->orderBy('id')->first() ?? new static();
    }

    /**
     * The settings row for editing (Filament). Creates the single row on first
     * use so the admin page always has something to update.
     */
    public static function instance(): self
    {
        return static::query()->orderBy('id')->first() ?? static::query()->create([]);
    }

    /**
     * Same rules as HomepageSetting::assetUrl(): uploads are paths on the
     * `public` disk served from /storage; absolute URLs and root-relative
     * paths (the shipped defaults) pass through.
     */
    protected static function assetUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }

    public function heroImageUrl(): string
    {
        return static::assetUrl($this->hero_image) ?? self::DEFAULT_HERO_IMAGE;
    }

    /**
     * The default alt describes the default photo, so it only applies while
     * that photo is in use. The admin form requires alt text once a photo is
     * uploaded; the empty-alt branch only covers a row saved some other way,
     * and is better than describing a different picture.
     */
    public function heroImageAlt(): string
    {
        if (filled($this->hero_image_alt)) {
            return $this->hero_image_alt;
        }

        return blank($this->hero_image) ? self::DEFAULT_HERO_IMAGE_ALT : '';
    }

    public function heroTitle(): string
    {
        return filled($this->hero_title) ? $this->hero_title : self::DEFAULT_HERO_TITLE;
    }

    public function contentHeading(): string
    {
        return filled($this->content_heading) ? $this->content_heading : self::DEFAULT_CONTENT_HEADING;
    }

    /**
     * RichEditor HTML, printed raw like Course::$overview. No default: null
     * hides the whole About content section.
     */
    public function contentBody(): ?string
    {
        return filled(strip_tags((string) $this->content_body)) ? $this->content_body : null;
    }

    public function trainersHeading(): string
    {
        return filled($this->trainers_heading) ? $this->trainers_heading : self::DEFAULT_TRAINERS_HEADING;
    }

    /**
     * Trainers in display order, each normalised so the view never has to
     * null-check. Rows without a name are skipped; an empty list hides the
     * section.
     *
     * Alt text: the trainer's photo_alt, else their name. When no photo is
     * uploaded the AMS logo stands in, and the alt says so.
     *
     * @return Collection<int, array{name: string, bio: ?string, photoUrl: string, photoAlt: string}>
     */
    public function trainerList(): Collection
    {
        return collect($this->trainers ?? [])
            ->filter(fn ($trainer) => filled($trainer['name'] ?? null))
            ->map(function ($trainer) {
                $photoUrl = static::assetUrl($trainer['photo'] ?? null);

                return [
                    'name' => $trainer['name'],
                    'bio' => filled(strip_tags((string) ($trainer['bio'] ?? ''))) ? $trainer['bio'] : null,
                    'photoUrl' => $photoUrl ?? self::DEFAULT_TRAINER_PHOTO,
                    'photoAlt' => match (true) {
                        $photoUrl === null => self::DEFAULT_TRAINER_PHOTO_ALT,
                        filled($trainer['photo_alt'] ?? null) => $trainer['photo_alt'],
                        default => $trainer['name'],
                    },
                ];
            })
            ->values();
    }

    public function missionHeading(): string
    {
        return filled($this->mission_heading) ? $this->mission_heading : self::DEFAULT_MISSION_HEADING;
    }

    /**
     * No default: an empty list hides the Mission statement section.
     *
     * @return Collection<int, string>
     */
    public function missionPointList(): Collection
    {
        return collect($this->mission_points ?? [])->filter(fn ($point) => filled($point))->values();
    }

    public function showReviews(): bool
    {
        return (bool) $this->show_reviews;
    }

    public function metaTitle(): string
    {
        return filled($this->meta_title) ? $this->meta_title : self::DEFAULT_META_TITLE;
    }

    public function metaDescription(): string
    {
        return filled($this->meta_description) ? $this->meta_description : self::DEFAULT_META_DESCRIPTION;
    }
}
