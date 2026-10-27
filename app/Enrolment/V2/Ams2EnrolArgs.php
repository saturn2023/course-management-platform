<?php

namespace App\Enrolment\V2;

use App\Models\Ams2Enrolment;
use DateTime;

/**
 * Builds the RTO Data v2 enrol payload. Port of ams2_enrol_args().
 *
 * Two things here look wrong and are not:
 *
 *  1. The learner's REAL name, date of birth and details are sent. Placeholder
 *     names were tested and ruled out — an update changes the enrolment name
 *     but not the learner profile, which breaks AVETMISS/USI. The names are
 *     uppercased, which is AVETMISS convention, not anonymisation.
 *
 *  2. email and other_email carry a placeholder that is not a valid address:
 *     'processing{n}hold.invalid', with no '@', where {n} is the row's
 *     display number. The real address is written in
 *     a later step, once the enrolment exists. Reproduced exactly.
 */
class Ams2EnrolArgs
{
    /**
     * Fields RTO Data will not accept an enrolment without.
     */
    public const REQUIRED = ['first_name', 'last_name', 'date_of_birth', 'course_id', 'plan_id'];

    /**
     * AVETMISS disability codes. 99 ("not stated") excludes all others.
     */
    private const DISABILITY_CODES = [11, 12, 13, 14, 15, 16, 17, 18, 19, 99];

    private const PRIOR_ACHIEVEMENT_CODES = ['008', '410', '420', '511', '514', '521', '524', '990'];

    /**
     * Australia. Hardcoded in the reference.
     */
    private const COUNTRY_CODE = '1101';

    /**
     * @return array<string, mixed>
     */
    public function build(Ams2Enrolment $row): array
    {
        $data = (array) ($row->student_data ?? []);
        $placeholder = $row->placeholderEmail();

        $args = [
            'first_name' => $this->upper($data['first_name'] ?? ''),
            'last_name' => $this->upper($data['last_name'] ?? ''),
            'date_of_birth' => $this->date($data['date_of_birth'] ?? ''),
            'course_id' => (int) $row->course_id,
            'plan_id' => (int) $row->plan_id,
            'salutation' => $data['title'] ?? '',
            'gender' => $data['gender'] ?? '@',
            'town_of_birth' => $data['city_of_birth'] ?? '',
            'country_of_birth' => $data['country_of_birth'] ?? '',
            'building_property' => $data['building_name'] ?? '',
            'unit_flat' => $data['unit_number'] ?? '',
            'street_number' => $data['street_number'] ?? '',
            'street_name' => $data['street_name'] ?? '',
            'suburb' => $data['suburb'] ?? '',
            'state' => $data['state'] ?? '',
            'postcode' => $data['postcode'] ?? '',
            'country' => self::COUNTRY_CODE,
            'main_language' => $data['main_language'] ?? '',
            'disability_flag' => $data['disability'] ?? '',
            'disability_type' => $this->disabilityTypes($data['disability_type'] ?? []),
            'labour_force' => $data['labour_force'] ?? '',
            'individual_needs' => ($data['individual_needs'] ?? '') === 'Yes'
                ? ($data['individual_needs_specify'] ?? '')
                : '',
            'mobile_phone' => $data['mobile_phone'] ?? '',
            'usi_number' => $this->upper($data['usi'] ?? ''),
            'highest_school_level' => $data['school_level'] ?? '',
            'at_school_flag' => $data['currently_at_school'] ?? '',
            'study_reason' => $data['study_reason'] ?? '',
            'client_id' => $data['client_id'] ?? '',
            'indigenous_status' => $this->indigenousStatus($data['atsi_status'] ?? ''),

            // Held until the enrolment exists; the real address replaces these later.
            'email' => $placeholder,
            'other_email' => $placeholder,
        ];

        return $args + $this->postal($data) + $this->priorAchievement($data);
    }

    /**
     * Which required fields are missing, if any.
     *
     * @param  array<string, mixed>  $args
     * @return array<int, string>
     */
    public function missingRequired(array $args): array
    {
        return array_values(array_filter(
            self::REQUIRED,
            static fn (string $key): bool => empty($args[$key]),
        ));
    }

    private function upper(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }

    /**
     * Strict Y-m-d. Anything else is treated as absent, which the required-field
     * check then turns into a parked row rather than a bad enrolment.
     */
    private function date(mixed $value): string
    {
        $date = DateTime::createFromFormat('!Y-m-d', (string) $value);

        return $date ? $date->format('Y-m-d') : '';
    }

    /**
     * @param  mixed  $selected
     * @return array<int, int>
     */
    private function disabilityTypes(mixed $selected): array
    {
        $types = array_fill_keys(self::DISABILITY_CODES, 0);

        foreach ((array) $selected as $code) {
            $code = (int) $code;

            if (array_key_exists($code, $types)) {
                $types[$code] = 1;
            }
        }

        // "Not stated" is exclusive: it clears every specific type.
        if ($types[99] === 1) {
            foreach ($types as $code => $value) {
                if ($code !== 99) {
                    $types[$code] = 0;
                }
            }
        }

        return $types;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function postal(array $data): array
    {
        if (($data['postal_address_type'] ?? '') !== 'different') {
            return ['postal_flag' => 0];
        }

        return [
            'postal_flag' => 1,
            'postal_box' => $data['postal_box'] ?? '',
            'postal_building_property' => $data['postal_building_property'] ?? '',
            'postal_unit_flat' => $data['postal_unit_flat'] ?? '',
            'postal_street_number' => $data['postal_street_number'] ?? '',
            'postal_street_name' => $data['postal_street_name'] ?? '',
            'postal_suburb' => $data['postal_suburb'] ?? '',
            'postal_state' => $data['postal_state'] ?? '',
            'postal_postcode' => $data['postal_postcode'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function priorAchievement(array $data): array
    {
        $prior = ['all' => [], 'vic' => []];

        if (($data['other_qualifications'] ?? '') === 'Yes') {
            foreach ((array) ($data['qualification_level'] ?? []) as $code) {
                $code = trim((string) $code);

                if (in_array($code, self::PRIOR_ACHIEVEMENT_CODES, true)) {
                    $prior['all'][$code] = 1;
                    $prior['vic'][$code] = 'A';
                }
            }
        }

        if ($prior['all'] === []) {
            return ['prior_achievement_flag' => 'N'];
        }

        return [
            'prior_achievement_flag' => 'Y',
            'prior_achievement' => $prior,
        ];
    }

    /**
     * Port of convert_indigenous_status(). An already-valid code passes through.
     */
    private function indigenousStatus(mixed $value): string
    {
        $value = trim((string) $value);

        if (in_array($value, ['1', '2', '3', '4', '@'], true)) {
            return $value;
        }

        return match ($value) {
            'Yes, Aboriginal' => '1',
            'Yes, Torres Strait Islander' => '2',
            'Yes, Aboriginal and Torres Strait Islander',
            'Yes, Aboriginal AND Torres Strait Islander' => '3',
            'No',
            'No, Neither Aboriginal nor Torres Strait Islander' => '4',
            default => '@',
        };
    }
}
