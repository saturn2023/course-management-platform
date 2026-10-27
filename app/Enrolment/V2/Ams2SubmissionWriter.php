<?php

namespace App\Enrolment\V2;

use App\Models\Ams2Enrolment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * Creates the v2 enrolment row from a submitted form. Port of the back half of
 * ams2_submit().
 *
 * Two things here are not obvious:
 *
 *  1. A resubmit of a key that already has a row is a SUCCESS, returning the
 *     same id. The reference's comment: "Resubmitted after a dropped
 *     connection: return the same row so documents can still attach." It is
 *     also what lets the browser say "You will not be enrolled twice" and be
 *     telling the truth.
 *
 *  2. `docs_final` is set from whether the browser says it holds any files, not
 *     from whether any have arrived. No files expected means the row is final
 *     immediately and the documents step never waits for it.
 */
class Ams2SubmissionWriter
{
    /**
     * Fields the reference refuses to create a row without. Only these four —
     * every other field on the form is optional.
     */
    public const REQUIRED = ['first_name', 'last_name', 'date_of_birth', 'email'];

    public function __construct(private Ams2StudentData $studentData) {}

    /**
     * Which of the required fields are blank.
     *
     * @param  array<string, mixed>  $posted
     * @return array<int, string>
     */
    public function missingRequired(array $posted): array
    {
        return array_values(array_filter(
            self::REQUIRED,
            static fn (string $field): bool => trim((string) ($posted[$field] ?? '')) === '',
        ));
    }

    /**
     * The learner's real address, or null when it is not a usable one.
     *
     * Validated as a real address because every later step depends on it: the
     * update step writes it into RTO Data and the confirmation email is sent to
     * it. The placeholder is never involved here — it is derived from the row
     * id much later, by Ams2Enrolment::placeholderEmail().
     *
     * @param  array<string, mixed>  $posted
     */
    public function realEmail(array $posted): ?string
    {
        $email = trim((string) ($posted['email'] ?? ''));
        $email = (string) filter_var($email, FILTER_SANITIZE_EMAIL);

        return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? null : $email;
    }

    /**
     * The document types the browser says it is holding, narrowed to the ones
     * this flow knows about.
     *
     * @return array<int, string>
     */
    public function expectedDocuments(?string $manifest): array
    {
        $claimed = array_map(
            static fn (string $type): string => strtolower(trim($type)),
            explode(',', (string) $manifest),
        );

        return array_values(array_intersect(Ams2DocumentTypes::keys(), $claimed));
    }

    /**
     * Create the row, or return the one this key already has.
     *
     * @param  array<string, mixed>  $posted
     */
    public function write(string $secretKey, Ams2LinkInfo $link, array $posted, ?string $manifest): Ams2Enrolment
    {
        $existing = $this->existing($secretKey);

        if ($existing) {
            return $existing;
        }

        $expected = $this->expectedDocuments($manifest);

        try {
            $row = Ams2Enrolment::create([
                'secret_key' => $secretKey,
                'order_id' => $link->orderId,
                'course_id' => $link->courseId,
                'plan_id' => $link->planId,
                'course_code' => $link->courseCode,
                'student_data' => $this->studentData->build($posted, $link),
                'real_email' => (string) $this->realEmail($posted),
                'docs_expected' => implode(',', $expected),

                /*
                 * Nothing to wait for when no files are coming, so the
                 * documents step can run as soon as the enrolment exists.
                 */
                'docs_final' => $expected === [],

                'status' => Ams2Status::RECEIVED,
                'attempts' => 0,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            /*
             * Two submits raced for the same key and the other one won. The
             * reference re-checks here for the same reason: the loser must
             * return the winner's row, not an error, or the learner is told
             * their enrolment failed when it did not.
             */
            $raced = $this->existing($secretKey);

            if (! $raced) {
                throw $exception;
            }

            return $raced;
        }

        Log::info('[AMS2] enrolment_saved', [
            'reference' => $row->displayRef(),
            'docs_expected' => $row->docs_expected,
        ]);

        return $row;
    }

    public function existing(string $secretKey): ?Ams2Enrolment
    {
        return Ams2Enrolment::query()->where('secret_key', $secretKey)->first();
    }
}
