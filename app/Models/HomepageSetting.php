<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row store for editable homepage content that is not a repeatable
 * record. Edited from the Filament "Homepage Settings" page.
 *
 * Read it with HomepageSetting::current(): that never writes and never returns
 * null, so a front-end component can always call the resolved accessors below
 * and get the defaults when nothing has been configured yet.
 */
class HomepageSetting extends Model
{
    /**
     * Fallbacks used whenever a field has not been filled in Filament.
     *
     * The line break after "of" is deliberate (it matches the reference
     * design) — the banner renders newlines, and a literal <br>, as real line
     * breaks.
     */
    public const DEFAULT_ABOUT_HEADING = "Over two decades of\nexperience, dedication and service.";

    public const DEFAULT_ABOUT_BUTTON_TEXT = 'About us';

    public const DEFAULT_ABOUT_BUTTON_URL = '/about';

    /** Shipped assets in public/, used when no image has been uploaded. */
    public const DEFAULT_ABOUT_BACKGROUND_IMAGE = '/images/hero-poster.jpg';

    /** The white A|M|S mark, not the green header logo. */
    public const DEFAULT_ABOUT_LOGO = '/images/ams-hero-logo.svg';

    /**
     * EXPERIENCE SECTION defaults (the white card over the pipe/workers photo,
     * directly below the About banner).
     *
     * The heading wraps to two lines naturally inside the card, so — unlike the
     * About heading — there is no deliberate line break here.
     */
    public const DEFAULT_EXPERIENCE_HEADING = 'Experience, dedication and service.';

    public const DEFAULT_EXPERIENCE_DESCRIPTION = 'At AMS our commitment has always been to providing quality service and delivery of training programs to people looking to upskill and those wishing to maintaining their qualifications.';

    public const DEFAULT_EXPERIENCE_BUTTON_TEXT = 'View our courses';

    public const DEFAULT_EXPERIENCE_BUTTON_URL = '#courses';

    /**
     * FIRST IN OUR FIELD defaults (the mirrored section below Experience —
     * photograph left, white card right).
     *
     * The description is the client's copy verbatim, including "maintain the
     * their qualifications" — that reads as a typo but it is what the live
     * Elementor section says, so it is reproduced rather than silently fixed.
     * Correct it in Filament if it should read differently.
     */
    public const DEFAULT_FIRST_FIELD_HEADING = 'First In Our Field.';

    public const DEFAULT_FIRST_FIELD_DESCRIPTION = 'At AMS we were proud to be one of the first to market with online refresher courses making it easy for people to maintain the their qualifications in Nationally Recognised Courses.';

    public const DEFAULT_FIRST_FIELD_BUTTON_TEXT = 'View our courses';

    public const DEFAULT_FIRST_FIELD_BUTTON_URL = '#courses';

    protected $fillable = [
        'about_background_image',
        'about_logo',
        'about_heading',
        'about_button_text',
        'about_button_url',
        'experience_background_image',
        'experience_background_image_mobile',
        'experience_heading',
        'experience_description',
        'experience_button_text',
        'experience_button_url',
        'first_field_background_image',
        'first_field_background_image_mobile',
        'first_field_heading',
        'first_field_description',
        'first_field_button_text',
        'first_field_button_url',
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
     * Turn a stored value into a URL usable in the browser.
     *
     * Uploads are stored as a path on the `public` disk (e.g.
     * "homepage-images/abc.jpg") and are served from /storage. Absolute URLs
     * and root-relative paths (the shipped defaults) are passed through.
     * Root-relative on purpose, so assets resolve against the current host.
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

    public function aboutBackgroundImageUrl(): ?string
    {
        return static::assetUrl($this->about_background_image)
            ?? static::assetUrl(self::DEFAULT_ABOUT_BACKGROUND_IMAGE);
    }

    public function aboutLogoUrl(): ?string
    {
        return static::assetUrl($this->about_logo)
            ?? static::assetUrl(self::DEFAULT_ABOUT_LOGO);
    }

    public function aboutHeading(): string
    {
        return filled($this->about_heading) ? $this->about_heading : self::DEFAULT_ABOUT_HEADING;
    }

    public function aboutButtonText(): string
    {
        return filled($this->about_button_text) ? $this->about_button_text : self::DEFAULT_ABOUT_BUTTON_TEXT;
    }

    public function aboutButtonUrl(): string
    {
        return filled($this->about_button_url) ? $this->about_button_url : self::DEFAULT_ABOUT_BUTTON_URL;
    }

    /**
     * Deliberately no DEFAULT_EXPERIENCE_BACKGROUND_IMAGE: the pipe/workers
     * photograph from the reference design is not shipped with the project and
     * no other image in public/ is a sensible stand-in. Returning null lets the
     * section fall back to its neutral placeholder fill until the real photo is
     * uploaded in Filament.
     */
    public function experienceBackgroundImageUrl(): ?string
    {
        return static::assetUrl($this->experience_background_image);
    }

    /**
     * The phone crop of the Experience photograph, falling back to the main
     * image when none has been uploaded — so a caller can use this
     * unconditionally and always get something renderable. Returns null only
     * when neither image is set.
     *
     * Compare it against experienceBackgroundImageUrl() to tell whether a
     * distinct mobile crop actually exists.
     */
    public function experienceBackgroundImageMobileUrl(): ?string
    {
        return static::assetUrl($this->experience_background_image_mobile)
            ?? $this->experienceBackgroundImageUrl();
    }

    public function experienceHeading(): string
    {
        return filled($this->experience_heading) ? $this->experience_heading : self::DEFAULT_EXPERIENCE_HEADING;
    }

    public function experienceDescription(): string
    {
        return filled($this->experience_description) ? $this->experience_description : self::DEFAULT_EXPERIENCE_DESCRIPTION;
    }

    public function experienceButtonText(): string
    {
        return filled($this->experience_button_text) ? $this->experience_button_text : self::DEFAULT_EXPERIENCE_BUTTON_TEXT;
    }

    public function experienceButtonUrl(): string
    {
        return filled($this->experience_button_url) ? $this->experience_button_url : self::DEFAULT_EXPERIENCE_BUTTON_URL;
    }

    /**
     * As with the Experience section, deliberately no default image constant:
     * the carabiner photograph from the reference design is not shipped with
     * the project, so this returns null and the section falls back to its
     * neutral placeholder fill until the real photo is uploaded in Filament.
     */
    public function firstFieldBackgroundImageUrl(): ?string
    {
        return static::assetUrl($this->first_field_background_image);
    }

    /**
     * Phone/tablet crop, falling back to the main image when none has been
     * uploaded. Compare against firstFieldBackgroundImageUrl() to tell whether
     * a distinct crop actually exists.
     */
    public function firstFieldBackgroundImageMobileUrl(): ?string
    {
        return static::assetUrl($this->first_field_background_image_mobile)
            ?? $this->firstFieldBackgroundImageUrl();
    }

    public function firstFieldHeading(): string
    {
        return filled($this->first_field_heading) ? $this->first_field_heading : self::DEFAULT_FIRST_FIELD_HEADING;
    }

    public function firstFieldDescription(): string
    {
        return filled($this->first_field_description) ? $this->first_field_description : self::DEFAULT_FIRST_FIELD_DESCRIPTION;
    }

    public function firstFieldButtonText(): string
    {
        return filled($this->first_field_button_text) ? $this->first_field_button_text : self::DEFAULT_FIRST_FIELD_BUTTON_TEXT;
    }

    public function firstFieldButtonUrl(): string
    {
        return filled($this->first_field_button_url) ? $this->first_field_button_url : self::DEFAULT_FIRST_FIELD_BUTTON_URL;
    }
}
