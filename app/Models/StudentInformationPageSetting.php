<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row store for the editable /student-information page. Edited from
 * the Filament "Student Information Page" page. Mirrors App\Models\AboutPageSetting.
 *
 * Read it with StudentInformationPageSetting::current(): that never writes
 * and never returns null, so the front end can always call the resolved
 * accessors below and get the defaults when nothing has been configured yet.
 *
 * The documents list is not stored here — see App\Models\StudentDocument.
 */
class StudentInformationPageSetting extends Model
{
    /** Fallbacks used whenever a field has not been filled in Filament. */
    public const DEFAULT_HERO_TITLE = 'Student Information';

    /**
     * The same mine-site banner as the About page, shipped in public/.
     * Swap it from Filament if the live page uses a different photo.
     */
    public const DEFAULT_HERO_IMAGE = '/images/about-hero.jpg';

    /** Describes DEFAULT_HERO_IMAGE only — see heroImageAlt(). */
    public const DEFAULT_HERO_IMAGE_ALT = 'Worker in a hard hat and high-visibility shirt walking across red earth at an open-cut mine site, with a haul truck in the distance';

    /** Typed in capitals on purpose — the view applies no text-transform. */
    public const DEFAULT_INTRO_HEADING = 'STUDENT INFORMATION';

    /**
     * Two lines, kept apart by the newline. The comma after "costs" is a
     * deliberate fix from the live page.
     */
    public const DEFAULT_INTRO_TEXT = "Feel free to explore our student services information.\nThis information is provided so informed decisions can be made before commencing courses and all the information around the rules, costs, refunds, etc.";

    public const DEFAULT_META_TITLE = 'Student Information | AMS Training';

    public const DEFAULT_META_DESCRIPTION = 'Student services information for AMS Training: the learner handbook, fees and charges, refunds, complaints and appeals, and certificate checks.';

    protected $fillable = [
        'hero_image',
        'hero_image_alt',
        'hero_title',
        'intro_heading',
        'intro_text',
        'meta_title',
        'meta_description',
    ];

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
     * Same rules as AboutPageSetting::assetUrl(): uploads are paths on the
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
     * that photo is in use. Same reasoning as AboutPageSetting::heroImageAlt().
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

    public function introHeading(): string
    {
        return filled($this->intro_heading) ? $this->intro_heading : self::DEFAULT_INTRO_HEADING;
    }

    /**
     * Plain text. The view prints it with nl2br(e(...)), so line breaks
     * survive and no admin-entered markup is ever rendered.
     */
    public function introText(): string
    {
        return filled($this->intro_text) ? $this->intro_text : self::DEFAULT_INTRO_TEXT;
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
