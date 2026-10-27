<?php

namespace App\Models;

use App\Enrolment\V2\Ams2Status;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Enrolment v2 row — the WordPress `ams2_enrolments` row.
 *
 * Status changes go through Ams2Claim, never through save(): every transition
 * has to be an atomic claim so two processor runs cannot both advance the same
 * row. The plain `update()` calls here only ever write non-status fields, or
 * are reached from code that already holds the claim.
 */
class Ams2Enrolment extends Model
{
    use HasFactory;

    protected $table = 'ams2_enrolments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'student_data' => 'array',
            'docs_final' => 'boolean',
            'course_id' => 'integer',
            'plan_id' => 'integer',
            'order_id' => 'integer',
            'attempts' => 'integer',
            'verify_checks' => 'integer',
            'reenrol_count' => 'integer',
            'email_sent_at' => 'datetime',
            'verify_after' => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Ams2EnrolmentDocument::class, 'enrolment_row');
    }

    public function isComplete(): bool
    {
        return $this->status === Ams2Status::COMPLETE;
    }

    public function needsReview(): bool
    {
        return $this->status === Ams2Status::NEEDS_REVIEW;
    }

    /**
     * The learner's name, for logs and alerts.
     */
    public function studentName(): string
    {
        $data = (array) ($this->student_data ?? []);

        $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

        return $name !== '' ? $name : 'unknown';
    }

    /**
     * Offset applied to the row id to produce the operator-facing number.
     *
     * The table's AUTO_INCREMENT cannot be relied on to start high: it is table
     * state rather than schema, so a TRUNCATE resets it, recreating the table
     * skips the migration that set it (that migration is already recorded as
     * run), and MariaDB 10.4 recomputes it as MAX(id)+1 on every restart.
     *
     * Deriving the number instead makes it stable under all three.
     */
    public const REFERENCE_OFFSET = 100000;

    /**
     * The operator-facing number for this row. Never the raw id.
     */
    public function displayNumber(): int
    {
        return self::REFERENCE_OFFSET + (int) $this->id;
    }

    /**
     * Reference quoted in logs and alerts, in the WordPress "AMS2-{n}" format.
     */
    public function displayRef(): string
    {
        return 'AMS2-'.$this->displayNumber();
    }

    /**
     * The address sent to RTO Data in email and other_email while the enrolment
     * is being created, replaced with the learner's real address afterwards.
     *
     * Deliberately NOT a valid address — no '@' — reproducing the reference
     * exactly, so no email validation may be applied to this field.
     *
     * Built from displayNumber() rather than the raw id: two rows that reused
     * an id after a counter reset would otherwise be handed the same
     * placeholder in RTO Data.
     */
    public function placeholderEmail(): string
    {
        return 'processing'.$this->displayNumber().'hold.invalid';
    }
}
