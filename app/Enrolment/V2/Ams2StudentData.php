<?php

namespace App\Enrolment\V2;

/**
 * Turns a posted enrolment form into the `student_data` blob.
 *
 * Port of the middle of ams2_submit(): the strip list, the recursive
 * sanitise, the state map and the one field the two flows name differently.
 *
 * The whole POST is kept, not a whitelist. That is the reference's design and
 * it matters: `Ams2EnrolArgs` reads two dozen keys out of this blob, and a
 * whitelist here would silently empty any field someone later adds to the form.
 */
class Ams2StudentData
{
    /**
     * Keys that must never reach `student_data`.
     *
     * Three groups: Laravel's and WordPress's own form plumbing, the values the
     * server re-reads from the link instead of trusting (`course_id`,
     * `plan_id`, `client_id`), and the upload manifest, which belongs in
     * `docs_expected`.
     *
     * This form renders no course_id/plan_id/client_id inputs at all, unlike the
     * reference's shared markup, so normally there is nothing to strip. They
     * stay listed because a crafted POST can send anything.
     */
    public const STRIP = [
        '_token',
        '_method',
        'action',
        'ams2_nonce',
        'ams2_noop',
        'ams_action',
        'enrolment_nonce',
        '_wp_http_referer',
        'submit_enrolment',
        'secretKey',
        'course_id',
        'plan_id',
        'client_id',
        'doc_expected',
    ];

    /**
     * AVETMISS state identifiers. Anything unrecognised becomes '', which is
     * what the reference does — an invalid code would be rejected on enrol.
     */
    public const STATES = [
        'NSW' => '01',
        'VIC' => '02',
        'QLD' => '03',
        'SA' => '04',
        'WA' => '05',
        'TAS' => '06',
        'NT' => '07',
        'ACT' => '08',
    ];

    /**
     * @param  array<string, mixed>  $posted
     * @return array<string, mixed>
     */
    public function build(array $posted, Ams2LinkInfo $link): array
    {
        $data = $posted;

        foreach (self::STRIP as $key) {
            unset($data[$key]);
        }

        array_walk_recursive($data, function (&$value): void {
            $value = $this->sanitiseText($value);
        });

        $data['state'] = self::STATES[strtoupper(trim((string) ($data['state'] ?? '')))] ?? '';

        /*
         * The two forms name this field differently — the old Laravel form
         * posts `main_language_home`, the reference posts `main_language` — and
         * Ams2EnrolArgs reads `main_language`. Verified to be the same AVETMISS
         * field with the same ASCL code list and the same 1201 default, so this
         * is a rename, not a mapping.
         */
        if (! isset($data['main_language']) && isset($data['main_language_home'])) {
            $data['main_language'] = $data['main_language_home'];
        }

        // From the link, never from the browser.
        $data['client_id'] = $link->clientId;

        return $data;
    }

    /**
     * Equivalent of sanitize_text_field().
     *
     * Note that this collapses newlines in the free-text fields too, exactly as
     * the reference's array_walk_recursive does. A learner's support-needs
     * paragraph arrives at RTO Data as one line.
     */
    private function sanitiseText(mixed $value): mixed
    {
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        /*
         * wp_strip_all_tags() removes script and style BLOCKS before stripping
         * tags, which strip_tags() alone does not: on its own it would turn
         * "<script>alert(1)</script>" into the text "alert(1)" and keep it.
         */
        $text = preg_replace('@<(script|style)[^>]*?>.*?</\1>@si', '', (string) $value) ?? '';
        $text = strip_tags($text);

        // Drop control characters, then collapse the remaining whitespace.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }
}
