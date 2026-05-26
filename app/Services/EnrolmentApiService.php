<?php

namespace App\Services;

use App\Models\EnrolmentSubmission;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EnrolmentApiService
{
    protected const STATE_MAP = [
        'NSW' => '01',
        'VIC' => '02',
        'QLD' => '03',
        'SA'  => '04',
        'WA'  => '05',
        'TAS' => '06',
        'NT'  => '07',
        'ACT' => '08',
    ];

    protected const ATSI_MAP = [
        'Yes, Aboriginal' => '1',
        'Yes, Torres Strait Islander' => '2',
        'Yes, Aboriginal and Torres Strait Islander' => '3',
        'Yes, Aboriginal AND Torres Strait Islander' => '3',
        'No' => '4',
        'No, Neither Aboriginal nor Torres Strait Islander' => '4',
        'Not Specified' => '@',
        '' => '@',
    ];

    protected const DISABILITY_CODES = ['11', '12', '13', '14', '15', '16', '17', '18', '19', '99'];

    public function buildPayload(EnrolmentSubmission $submission): array
    {
        $submission->loadMissing(['enrolment', 'student', 'course', 'order']);

        $formData = (array) ($submission->form_data ?? []);
        $enrolArgs = $this->buildEnrolArgs($submission, $formData);

        return [
            'submission_id' => $submission->id,
            'enrolment_id' => $submission->enrolment_id,
            'order_id' => $submission->order_id,
            'student_id' => $submission->student_id,
            'course_id' => $submission->course_id,

            'code' => $submission->code,
            'plan' => $submission->plan,

            // These are the exact values converted into http_build_query()
            // and sent to RTO Data under the "values" key.
            'enrol_args' => $enrolArgs,

            'documents' => [
                'id_document_path' => $submission->id_document_path,
                'vet_transcript_path' => $submission->vet_transcript_path,
            ],

            'submitted_at' => $submission->submitted_at?->toDateTimeString(),
        ];
    }

    public function submit(EnrolmentSubmission $submission): array
    {
        $submission->loadMissing(['enrolment', 'student', 'course', 'order']);

        $config = $this->config();
        $payload = $this->buildPayload($submission);

        $requestBody = [
            'public_key' => $config['public_key'],
            'subdomain' => $config['subdomain'],
            'values' => http_build_query($payload['enrol_args']),
        ];

        if (! $config['enabled']) {
            return [
                'skipped' => true,
                'success' => false,
                'message' => 'Enrolment API is disabled. No real API request was sent.',
                'endpoint' => $this->endpoint('enrol'),
                'payload' => $payload,
                'request_body' => $requestBody,
            ];
        }

        $this->validateConfig($config);

        /*
        |--------------------------------------------------------------------------
        | Step 1: Create enrolment
        |--------------------------------------------------------------------------
        |
        | Retry-safety:
        | If external_reference already exists, we do not call /enrol again.
        | This avoids duplicate enrolments if a retry happens after enrolment
        | creation but before document upload fully completed.
        |
        */

        if ($submission->external_reference) {
            $externalEnrolmentId = $submission->external_reference;
            $enrolResponse = [
                'success' => true,
                'enrolment_id' => $externalEnrolmentId,
                'message' => 'Using existing external reference; enrol endpoint not called again.',
            ];
        } else {
            $enrolResponse = $this->callEnrol($payload['enrol_args']);

            if (empty($enrolResponse['success'])) {
                throw new RuntimeException(
                    'RTO enrol API returned failure: ' . json_encode($enrolResponse)
                );
            }

            $externalEnrolmentId = $enrolResponse['enrolment_id']
                ?? $enrolResponse['data']['enrolment_id']
                ?? null;

            if (! $externalEnrolmentId) {
                throw new RuntimeException(
                    'RTO enrol API succeeded but did not return enrolment_id: ' . json_encode($enrolResponse)
                );
            }

            // Save immediately for retry safety.
            $submission->update([
                'external_reference' => $externalEnrolmentId,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Step 2: Upload documents
        |--------------------------------------------------------------------------
        */

        $documentResults = [];

        foreach ($this->collectDocumentPaths($submission) as $path) {
            $documentResults[] = $this->callReceive($externalEnrolmentId, $path);
        }

        $failedDocuments = collect($documentResults)
            ->filter(fn (array $result) => ($result['success'] ?? false) !== true)
            ->values()
            ->all();

        if (! empty($failedDocuments)) {
            throw new RuntimeException(
                'One or more RTO document uploads failed: ' . json_encode($failedDocuments)
            );
        }

        return [
            'skipped' => false,
            'success' => true,
            'stage' => 'complete',
            'endpoint' => $this->endpoint('enrol'),
            'external_reference' => $externalEnrolmentId,
            'response' => $enrolResponse,
            'documents' => $documentResults,
            'payload' => $payload,
            'request_body' => $requestBody,
        ];
    }

    protected function buildEnrolArgs(EnrolmentSubmission $submission, array $formData): array
    {
        $args = [
            'first_name' => trim((string) ($formData['first_name'] ?? $submission->student?->first_name ?? '')),
            'last_name' => trim((string) ($formData['last_name'] ?? $submission->student?->last_name ?? '')),
            'date_of_birth' => $this->normaliseDate($formData['date_of_birth'] ?? null),

            // RTO Data expects these names.
            'course_id' => (int) ($submission->course?->ams_enrolment_code ?? $submission->code ?? 0),
            'plan_id' => (int) ($submission->course?->ams_plan_id ?? $submission->plan ?? 0),

            'salutation' => $formData['title'] ?? '',
            'gender' => $formData['gender'] ?? 'M',

            'town_of_birth' => $formData['city_of_birth'] ?? '',
            'country_of_birth' => $formData['country_of_birth'] ?? '',

            'street_number' => $formData['street_number'] ?? '',
            'street_name' => $formData['street_name'] ?? '',
            'suburb' => $formData['suburb'] ?? '',
            'state' => $this->mapState($formData['state'] ?? null),
            'postcode' => $formData['postcode'] ?? '',
            'country' => '1101',

            'main_language' => $formData['main_language_home'] ?? $formData['main_language'] ?? '1201',
            'disability_flag' => $formData['disability'] ?? '',
            'mobile_phone' => $formData['mobile_phone'] ?? $formData['phone'] ?? $submission->student?->phone ?? '',

            'disability_type' => $this->buildDisabilityTypes($formData['disability_type'] ?? []),
            'labour_force' => $formData['labour_force'] ?? $formData['employment_status'] ?? '',
            'individual_needs' => $this->individualNeedsText($formData),

            'email' => $submission->student?->email
                ?? $formData['email']
                ?? $formData['email_address']
                ?? '',

            'usi_number' => $formData['usi_number'] ?? $formData['usi'] ?? '',
            'highest_school_level' => (int) ($formData['highest_school_level'] ?? $formData['school_level'] ?? 0),
            'at_school_flag' => (string) ($formData['currently_at_school'] ?? ''),
            'client_id' => $formData['client_id'] ?? '',

            'indigenous_status' => $this->convertIndigenousStatus($formData['atsi_status'] ?? ''),
        ];

        if (($formData['postal_address_type'] ?? '') === 'different') {
            $args['postal_flag'] = 1;
            $args['postal_box'] = trim((string) ($formData['postal_box'] ?? ''));
            $args['postal_building_property'] = trim((string) ($formData['postal_building_property'] ?? ''));
            $args['postal_unit_flat'] = trim((string) ($formData['postal_unit_flat'] ?? ''));
            $args['postal_street_number'] = trim((string) ($formData['postal_street_number'] ?? ''));
            $args['postal_street_name'] = trim((string) ($formData['postal_street_name'] ?? ''));
            $args['postal_suburb'] = trim((string) ($formData['postal_suburb'] ?? ''));
            $args['postal_state'] = $this->mapState($formData['postal_state'] ?? null);
            $args['postal_postcode'] = trim((string) ($formData['postal_postcode'] ?? ''));
        } else {
            $args['postal_flag'] = 0;
        }

        $priorAchievement = $this->buildPriorAchievement($formData);

        if (! empty($priorAchievement)) {
            $args['prior_achievement_flag'] = 'Y';
            $args['prior_achievement'] = $priorAchievement;
        } else {
            $args['prior_achievement_flag'] = 'N';
        }

        foreach (['first_name', 'last_name', 'date_of_birth', 'course_id', 'plan_id'] as $requiredField) {
            if (empty($args[$requiredField])) {
                throw new RuntimeException("Missing required enrolment field: {$requiredField}");
            }
        }

        return $args;
    }

    protected function callEnrol(array $args): array
    {
        $body = [
            'public_key' => $this->config()['public_key'],
            'subdomain' => $this->config()['subdomain'],
            'values' => http_build_query($args),
        ];

        $response = $this->client()->post($this->endpoint('enrol'), $body);

        if (! $response->successful()) {
            throw new RuntimeException(
                'RTO enrol API /enrol HTTP ' . $response->status() . ': ' . $response->body()
            );
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('RTO enrol API /enrol returned non-JSON: ' . $response->body());
        }

        return $json;
    }

    protected function callReceive(string|int $externalEnrolmentId, string $relativePath): array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($relativePath)) {
            return [
                'success' => false,
                'path' => $relativePath,
                'error' => 'File not found in local storage.',
            ];
        }

        $absolutePath = $disk->path($relativePath);
        $mime = mime_content_type($absolutePath) ?: 'application/octet-stream';

        if (! in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'], true)) {
            return [
                'success' => false,
                'path' => $relativePath,
                'error' => 'Unsupported file type: ' . $mime,
            ];
        }

        $contents = $disk->get($relativePath);
        $base64 = base64_encode($contents);

        $body = [
            'type' => 'receive',
            'enrolment_id' => (string) $externalEnrolmentId,
            'files' => [
                [
                    'name' => basename($relativePath),
                    'uri' => "data:{$mime};base64,{$base64}",
                ],
            ],
            'public_key' => $this->config()['public_key'],
            'subdomain' => $this->config()['subdomain'],
        ];

        $response = $this->client()->post($this->endpoint('receive'), $body);
        $rawBody = $response->body();

        // Old WordPress logic checked for the raw string "success":1.
        $success = $response->successful() && str_contains($rawBody, '"success":1');

        return [
            'success' => $success,
            'path' => $relativePath,
            'http_status' => $response->status(),
            'response' => $response->json() ?? $rawBody,
        ];
    }

    protected function client(): PendingRequest
    {
        $config = $this->config();

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if (! empty($config['origin'])) {
            $headers['Origin'] = $config['origin'];
        }

        return Http::withHeaders($headers)
            ->timeout($config['timeout'])
            ->connectTimeout($config['connect_timeout'])
            ->acceptJson();
    }

    protected function endpoint(string $endpoint): string
    {
        return rtrim($this->config()['base_url'], '/') . '/' . ltrim($endpoint, '/');
    }

    protected function config(): array
    {
        return [
            'enabled' => (bool) config('services.enrolment_api.enabled', false),
            'base_url' => (string) config('services.enrolment_api.base_url'),
            'public_key' => (string) config('services.enrolment_api.public_key'),
            'subdomain' => (string) config('services.enrolment_api.subdomain', 'amstraining'),
            'origin' => (string) config('services.enrolment_api.origin'),
            'timeout' => (int) config('services.enrolment_api.timeout', 30),
            'connect_timeout' => (int) config('services.enrolment_api.connect_timeout', 10),
        ];
    }

    protected function validateConfig(array $config): void
    {
        if (empty($config['base_url'])) {
            throw new RuntimeException('ENROLMENT_API_BASE_URL is missing.');
        }

        if (empty($config['public_key'])) {
            throw new RuntimeException('ENROLMENT_API_PUBLIC_KEY is missing.');
        }

        if (empty($config['subdomain'])) {
            throw new RuntimeException('ENROLMENT_API_SUBDOMAIN is missing.');
        }
    }

    protected function collectDocumentPaths(EnrolmentSubmission $submission): array
    {
        return array_values(array_filter([
            $submission->id_document_path,
            $submission->vet_transcript_path,
        ]));
    }

    protected function normaliseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    protected function mapState(?string $state): ?string
    {
        if (! $state) {
            return null;
        }

        $state = strtoupper(trim($state));

        if (preg_match('/^\d{2}$/', $state)) {
            return $state;
        }

        return self::STATE_MAP[$state] ?? null;
    }

    protected function convertIndigenousStatus(?string $value): string
    {
        $value = trim((string) $value);

        if (in_array($value, ['1', '2', '3', '4', '@'], true)) {
            return $value;
        }

        return self::ATSI_MAP[$value] ?? '@';
    }

    protected function buildDisabilityTypes(array|string|null $selected): array
    {
        if (is_string($selected)) {
            $selected = [$selected];
        }

        $selected = $selected ?? [];

        $flags = [];

        foreach (self::DISABILITY_CODES as $code) {
            $flags[$code] = in_array((string) $code, array_map('strval', $selected), true) ? 1 : 0;
        }

        if (($flags['99'] ?? 0) === 1) {
            foreach ($flags as $code => $value) {
                $flags[$code] = $code === '99' ? 1 : 0;
            }
        }

        return $flags;
    }

    protected function buildPriorAchievement(array $formData): ?array
    {
        if (($formData['other_qualifications'] ?? '') !== 'Yes') {
            return null;
        }

        $levels = $formData['qualification_level'] ?? [];

        if (! is_array($levels) || empty($levels)) {
            return null;
        }

        $codes = [];

        foreach ($levels as $value) {
            if (preg_match('/\[(\d+)\]$/', (string) $value, $matches)) {
                $codes[] = $matches[1];
                continue;
            }

            if (preg_match('/^\d+$/', (string) $value)) {
                $codes[] = (string) $value;
            }
        }

        if (empty($codes)) {
            return null;
        }

        $result = [
            'all' => [],
            'vic' => [],
        ];

        foreach ($codes as $code) {
            $result['all'][$code] = 1;
            $result['vic'][$code] = 'A';
        }

        return $result;
    }

    protected function individualNeedsText(array $formData): string
    {
        $individualNeeds = $formData['individual_needs'] ?? null;

        if (
            $individualNeeds === false ||
            strtolower((string) $individualNeeds) === 'no' ||
            $individualNeeds === 'N'
        ) {
            return '';
        }

        return (string) (
            $formData['individual_needs_specify']
            ?? $formData['support_needs']
            ?? ''
        );
    }
}