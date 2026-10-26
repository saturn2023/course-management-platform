<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Per-course enrolment confirmation email.
 *
 * Port of the WordPress `ams_enrol_email` post type. Behaviour follows
 * reference/wp-ams2.php (`ams2_has_enrolment_email()` and `ams2_step_email()`),
 * not this file's structure. Notably, and deliberately:
 *
 *  - there is no subject field; the subject is composed at send time
 *  - substituted names are UPPERCASED, not title-cased
 *  - all three attachments are optional, and a missing file is skipped
 *
 * Placeholders: {first_name} {last_name} {course_title} {site_url}. The first
 * three are ports; {site_url} is a Laravel addition — see siteUrl().
 *
 * A published row for a course/plan pair is the gate that puts that pair on the
 * Enrolment v2 flow. Pairs without one stay on the old flow.
 */
class EnrolmentEmailTemplate extends Model
{
    use HasFactory;

    /**
     * Attachments are mail attachments, never public URLs, so they live on the
     * private disk.
     */
    public const DISK = 'local';

    public const ATTACHMENT_SLOTS = 3;

    protected $fillable = [
        'course_id',
        'plan_id',
        'is_published',
        'name',
        'email_body',
        'attachment_1_path',
        'attachment_2_path',
        'attachment_3_path',
        'storage_key',
    ];

    protected function casts(): array
    {
        return [
            'course_id' => 'integer',
            'plan_id' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            if (blank($template->storage_key)) {
                $template->storage_key = (string) Str::ulid();
            }
        });

        /*
         * One published template per course/plan pair.
         *
         * WordPress takes the first of however many published posts match, which
         * is silent and arbitrary. MySQL cannot express "unique among published
         * rows only", so the rule is enforced here — it therefore applies to
         * every write path, not just the Filament form. Unpublished duplicates
         * are fine; they are drafts.
         */
        static::saving(function (self $template): void {
            if (! $template->is_published) {
                return;
            }

            $clash = static::query()
                ->where('is_published', true)
                ->where('course_id', $template->course_id)
                ->where('plan_id', $template->plan_id)
                ->when($template->exists, fn (Builder $query) => $query->whereKeyNot($template->getKey()))
                ->first();

            if ($clash) {
                throw ValidationException::withMessages([
                    'is_published' => sprintf(
                        'Course %d / plan %d already has a published template ("%s"). Unpublish that one first.',
                        $template->course_id,
                        $template->plan_id,
                        $clash->name,
                    ),
                ]);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | The v2 gate
    |--------------------------------------------------------------------------
    */

    public function scopePublishedFor(Builder $query, int $courseId, int $planId): Builder
    {
        return $query
            ->where('is_published', true)
            ->where('course_id', $courseId)
            ->where('plan_id', $planId)
            ->orderBy('id');
    }

    public static function findPublishedFor(int $courseId, int $planId): ?self
    {
        return static::query()->publishedFor($courseId, $planId)->first();
    }

    /**
     * Whether this course/plan pair runs the Enrolment v2 flow.
     *
     * Memoised per request, mirroring the `static $cache` in
     * ams2_has_enrolment_email(): the gate is consulted on route, form render
     * and submit for the same pair.
     *
     * @var array<string, bool>
     */
    protected static array $gateCache = [];

    public static function hasPublishedFor(int $courseId, int $planId): bool
    {
        $key = $courseId.':'.$planId;

        return static::$gateCache[$key] ??= static::query()
            ->publishedFor($courseId, $planId)
            ->exists();
    }

    public static function flushGateCache(): void
    {
        static::$gateCache = [];
    }

    /**
     * The local course this template is keyed to, if there is one.
     *
     * Not a relation: the template stores RTO Data's own course_id / plan_id,
     * while the local mapping columns are nullable strings. Compared as strings
     * so the match behaves the same on MySQL and SQLite.
     */
    public function course(): ?Course
    {
        return Course::query()
            ->where('ams_enrolment_code', (string) $this->course_id)
            ->where('ams_plan_id', (string) $this->plan_id)
            ->first();
    }

    /**
     * Course title for a send that has no enrolment row to read it from.
     *
     * In the real flow the title always comes from the enrolment row; this is
     * only for previewing a template on its own.
     */
    public function resolvedCourseTitle(): ?string
    {
        return $this->course()?->title;
    }

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    */

    /**
     * Build the confirmation email for one learner.
     *
     * @param  array<string, mixed>  $student  The enrolment row's student data.
     * @param  string|null  $courseTitle  The enrolment row's course title.
     * @return array{subject: string, body: string, attachments: array<int, string>}
     */
    public function render(array $student, ?string $courseTitle): array
    {
        $title = trim((string) $courseTitle);

        return [
            // WordPress has no subject field; it composes this literal.
            'subject' => trim('Enrolment confirmation '.$title),
            'body' => $this->renderBody($student, $title),
            'attachments' => $this->attachmentPaths(),
        ];
    }

    protected function renderBody(array $student, string $courseTitle): string
    {
        return str_replace(
            ['{first_name}', '{last_name}', '{course_title}', '{site_url}'],
            [
                $this->upper($student['first_name'] ?? ''),
                $this->upper($student['last_name'] ?? ''),
                e($courseTitle),
                e(self::siteUrl()),
            ],
            (string) $this->email_body,
        );
    }

    /**
     * The site's base URL, with any trailing slash removed so a template can
     * write {site_url}/courses without producing a double slash.
     *
     * {site_url} is a Laravel addition with no WordPress equivalent: the
     * reference hardcodes amstraining.com.au in its templates, which breaks on
     * staging and behind a tunnel.
     */
    public static function siteUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /**
     * Names are uppercased, then escaped — exactly as ams2_step_email() does.
     */
    protected function upper(mixed $value): string
    {
        return e(mb_strtoupper(trim((string) $value), 'UTF-8'));
    }

    /**
     * Absolute paths of the attachments that actually exist on disk.
     *
     * A missing file is skipped rather than failing the send, because a learner
     * getting their confirmation matters more than a handbook being attached —
     * but it is logged, so a deleted attachment does not stay invisible.
     *
     * @return array<int, string>
     */
    public function attachmentPaths(): array
    {
        $disk = Storage::disk(self::DISK);
        $paths = [];

        foreach ($this->attachmentRelativePaths() as $slot => $relativePath) {
            if ($disk->exists($relativePath)) {
                $paths[] = $disk->path($relativePath);

                continue;
            }

            Log::warning('Enrolment email template attachment is missing from disk.', [
                'template_id' => $this->id,
                'template_name' => $this->name,
                'slot' => $slot,
                'path' => $relativePath,
                'disk' => self::DISK,
            ]);
        }

        return $paths;
    }

    /**
     * Configured attachment paths, keyed by slot number, empty slots removed.
     *
     * @return array<int, string>
     */
    public function attachmentRelativePaths(): array
    {
        $paths = [];

        for ($slot = 1; $slot <= self::ATTACHMENT_SLOTS; $slot++) {
            $path = trim((string) $this->{"attachment_{$slot}_path"});

            if ($path !== '') {
                $paths[$slot] = $path;
            }
        }

        return $paths;
    }

    /**
     * Each template keeps its attachments in its own directory, so two templates
     * uploading the same filename cannot overwrite each other.
     */
    public function attachmentDirectory(): string
    {
        return static::directoryFor($this->storage_key);
    }

    public static function directoryFor(?string $storageKey): string
    {
        return 'enrolment-templates/'.($storageKey ?: 'unassigned');
    }
}
