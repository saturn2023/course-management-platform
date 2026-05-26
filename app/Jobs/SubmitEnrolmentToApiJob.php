<?php

namespace App\Jobs;

use App\Models\EnrolmentSubmission;
use App\Models\IntegrationLog;
use App\Services\EnrolmentApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SubmitEnrolmentToApiJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 300;

    public function __construct(
        public int $submissionId
    ) {}

    public function handle(EnrolmentApiService $service): void
    {
        $submission = EnrolmentSubmission::with(['enrolment', 'student', 'course', 'order'])
            ->findOrFail($this->submissionId);

        if ($submission->api_status === 'success') {
            IntegrationLog::create([
                'order_id' => $submission->order_id,
                'service' => 'enrolment_api',
                'action' => 'submit_enrolment_submission',
                'status' => 'skipped',
                'request_payload' => json_encode([
                    'submission_id' => $submission->id,
                    'api_status' => $submission->api_status,
                    'external_reference' => $submission->external_reference,
                ]),
                'response_payload' => json_encode([
                    'message' => 'Skipped because enrolment submission was already successfully submitted.',
                ]),
            ]);

            return;
        }

        $payload = $service->buildPayload($submission);

        $submission->update([
            'api_status' => 'processing',
            'api_attempts' => $submission->api_attempts + 1,
            'api_last_attempted_at' => now(),
            'api_error_message' => null,
            'api_request_payload' => $payload,
        ]);

        IntegrationLog::create([
            'order_id' => $submission->order_id,
            'service' => 'enrolment_api',
            'action' => 'submit_enrolment_submission',
            'status' => 'processing',
            'request_payload' => json_encode([
                'submission_id' => $submission->id,
                'attempt' => $submission->api_attempts + 1,
                'api_enabled' => filter_var(env('ENROLMENT_API_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            ]),
        ]);

        try {
            $result = $service->submit($submission->fresh(['enrolment', 'student', 'course', 'order']));

            if ($result['skipped'] ?? false) {
                $submission->update([
                    'api_status' => 'skipped',
                    'api_response_payload' => $result,
                    'api_error_message' => $result['message'] ?? null,
                ]);

                IntegrationLog::create([
                    'order_id' => $submission->order_id,
                    'service' => 'enrolment_api',
                    'action' => 'submit_enrolment_submission',
                    'status' => 'skipped',
                    'request_payload' => json_encode($result['payload'] ?? []),
                    'response_payload' => json_encode([
                        'message' => $result['message'] ?? 'Enrolment API disabled.',
                        'submission_id' => $submission->id,
                    ]),
                ]);

                return;
            }

            $externalReference = $result['response']['id']
                ?? $result['response']['reference']
                ?? $result['response']['external_reference']
                ?? null;

            $submission->update([
                'api_status' => 'success',
                'api_submitted_at' => now(),
                'api_response_payload' => $result,
                'api_error_message' => null,
                'external_reference' => $externalReference,
            ]);

            IntegrationLog::create([
                'order_id' => $submission->order_id,
                'service' => 'enrolment_api',
                'action' => 'submit_enrolment_submission',
                'status' => 'success',
                'request_payload' => json_encode($result['payload'] ?? []),
                'response_payload' => json_encode([
                    'message' => 'Enrolment submission sent to API successfully.',
                    'submission_id' => $submission->id,
                    'external_reference' => $externalReference,
                    'response' => $result['response'] ?? null,
                ]),
            ]);
        } catch (Throwable $exception) {
            $submission->update([
                'api_status' => 'failed',
                'api_error_message' => $exception->getMessage(),
            ]);

            IntegrationLog::create([
                'order_id' => $submission->order_id,
                'service' => 'enrolment_api',
                'action' => 'submit_enrolment_submission',
                'status' => 'failed',
                'request_payload' => json_encode($payload),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}