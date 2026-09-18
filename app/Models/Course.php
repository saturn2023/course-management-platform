<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Course extends Model
{
    protected $fillable = [
        'title',
        'code',
        'slug',
        'description',
        'price',
        'status',
        'ams_enrolment_code',
         'ams_plan_id',

        // Frontend homepage card fields.
        'card_title',
        'image_path',
        'icon_path',
        'banner_text',
        'course_url',
        'display_order',
        'show_on_homepage',

        // Public course detail page (/courses/{slug}).
        'course_type',
        'duration',
        'nationally_accredited',
        'regular_price',
        'enrolment_url',
        'overview',
        'entry_requirements_warning',
        'entry_requirements',
        'course_information',
        'assessment',
        'qualification',
        'equipment_materials',
        'delivery_mode',
        'support_information',
        'student_handbook',
        'vet_transcript',
        'usi_information',
        'complaints_appeals',
        'cancellations_refunds',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'show_on_homepage' => 'boolean',
        'nationally_accredited' => 'boolean',
    ];

    /**
     * Fill an empty slug from the title + code on save, so an administrator
     * never has to invent one — but a slug typed by hand is left alone, since
     * changing it would break any URL already published.
     */
    protected static function booted(): void
    {
        static::saving(function (self $course): void {
            $slug = static::normaliseSlug($course->slug);

            $course->slug = $slug !== ''
                ? $slug
                : static::makeSlug($course->title, $course->code);
        });
    }

    /** e.g. "Work Safely At Heights" + "RIIWHS204E" -> work-safely-at-heights-riiwhs204e */
    public static function makeSlug(?string $title, ?string $code): string
    {
        return Str::slug(trim(($title ?? '').' '.($code ?? ''))) ?: Str::slug((string) $code);
    }

    /**
     * Accept either a bare slug or a whole pasted course URL.
     *
     * Copying the address out of the browser is the natural way to fill this
     * field in, and storing that verbatim silently breaks /courses/{slug}, so
     * keep only the last path segment.
     */
    public static function normaliseSlug(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (str_contains($value, '/')) {
            $path = parse_url($value, PHP_URL_PATH) ?: $value;

            $segments = array_values(array_filter(
                explode('/', $path),
                static fn (string $segment): bool => trim($segment) !== ''
            ));

            $value = $segments === [] ? '' : end($segments);
        }

        return Str::slug($value);
    }

    /**
     * Where this course's "View course" button points, or null when there is
     * nothing to link to. The admin's Slug field is the single source of truth
     * for the public page, so homepage cards derive the link rather than
     * carrying a second, separately-typed URL that can drift out of step.
     */
    public function detailUrl(): ?string
    {
        if (blank($this->slug) || $this->status !== 'active') {
            return null;
        }

        return route('courses.show', $this->slug);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The accordion sections of the detail page, in display order.
     *
     * Kept here rather than in the Blade so the template never names a column:
     * a section with no content is dropped, so a course that has not filled in
     * Assessment simply shows one fewer card.
     *
     * @return array<int, array{title: string, content: string}>
     */
    public function detailSections(): array
    {
        $map = [
            'entry_requirements' => 'Entry requirements',
            'course_information' => 'Course information',
            'assessment' => 'Assessment',
            'qualification' => 'Qualification',
            'equipment_materials' => 'Equipment / Materials',
            'delivery_mode' => 'Delivery mode',
            'support_information' => 'Support information',
            'student_handbook' => 'Student handbook',
            'vet_transcript' => 'VET transcript',
            'usi_information' => 'Unique student identifier (USI)',
            'complaints_appeals' => 'Complaints & appeals',
            'cancellations_refunds' => 'Cancellations & refunds',
        ];

        $sections = [];

        foreach ($map as $field => $title) {
            if (filled($this->{$field})) {
                $sections[] = [
                    'key' => $field,
                    'title' => $title,
                    'content' => $this->{$field},
                    // Only Entry requirements carries the red notice box.
                    'warning' => $field === 'entry_requirements' ? $this->entry_requirements_warning : null,
                ];
            }
        }

        return $sections;
    }

    /**
     * Whether a "was" price higher than the payable price has been set. Only
     * then does the detail page show the strike-through and the save badge.
     */
    public function hasDiscount(): bool
    {
        return filled($this->regular_price)
            && filled($this->price)
            && (float) $this->regular_price > (float) $this->price;
    }

    /**
     * Saving as a whole percentage, derived rather than stored so it can never
     * disagree with the two prices. Null when there is no discount.
     */
    public function discountPercentage(): ?int
    {
        if (! $this->hasDiscount()) {
            return null;
        }

        $regular = (float) $this->regular_price;

        return (int) round((($regular - (float) $this->price) / $regular) * 100);
    }

    /**
     * Where ENROL NOW points, using the enrolment path that already exists:
     * /checkout/load?code=…&plan=… (CheckoutLoadController). An explicit
     * enrolment_url overrides it. Null when the course has no AMS mapping yet,
     * in which case the detail page renders the button disabled rather than
     * linking somewhere that would fail — the same convention the package cards
     * use for a missing URL.
     */
    public function enrolmentLink(): ?string
    {
        if (filled($this->enrolment_url)) {
            return $this->enrolment_url;
        }

        if (blank($this->ams_enrolment_code) || blank($this->ams_plan_id)) {
            return null;
        }

        return '/checkout/load?'.http_build_query([
            'code' => $this->ams_enrolment_code,
            'plan' => $this->ams_plan_id,
            'quantity' => 1,
        ]);
    }
}
