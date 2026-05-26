<?php

namespace App\Services;

use App\Models\EnrolmentSubmission;
use Illuminate\Support\Facades\Http;

class EnrolmentApiService
{
    public function buildPayload(EnrolmentSubmission $submission): array
    {
        $submission->loadMissing(['enrolment', 'student', 'course', 'order']);

        return [
            'submission_id' => $submission->id,
            'enrolment_id' => $submission->enrolment_id,
            'order_id' => $submission->order_id,
            'student_id' => $submission->student_id,
            'course_id' => $submission->course_id,

            'code' => $submission->code,
            'plan' => $submission->plan,

            'student' => [
                'first_name' => $submission->student?->first_name,
                'last_name' => $submission->student?->last_name,
                'email' => $submission->student?->email,
                'phone' => $submission->student?->phone,
            ],

            'course' => [
                'title' => $submission->course?->title,
                'code' => $submission->course?->code,
                'ams_enrolment_code' => $submission->course?->ams_enrolment_code,
                'ams_plan_id' => $submission->course?->ams_plan_id,
            ],

            'form_data' => $submission->form_data,

            'documents' => [
                'id_document_path' => $submission->id_document_path,
                'vet_transcript_path' => $submission->vet_transcript_path,
            ],

            'submitted_at' => $submission->submitted_at?->toDateTimeString(),
        ];
    }

    public function submit(EnrolmentSubmission $submission): array
    {
        $enabled = filter_var(env('ENROLMENT_API_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $baseUrl = rtrim((string) env('ENROLMENT_API_BASE_URL'), '/');
        $token = env('ENROLMENT_API_TOKEN');

        $payload = $this->buildPayload($submission);

        if (! $enabled) {
            return [
                'skipped' => true,
                'success' => false,
                'message' => 'Enrolment API is disabled. No real API request was sent.',
                'payload' => $payload,
            ];
        }

        if (! $baseUrl) {
            throw new \Exception('ENROLMENT_API_BASE_URL is missing.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($baseUrl . '/enrolments', $payload);

        if (! $response->successful()) {
            throw new \Exception('Enrolment API request failed: ' . $response->body());
        }

        return [
            'skipped' => false,
            'success' => true,
            'status' => $response->status(),
            'response' => $response->json(),
            'payload' => $payload,
        ];
    }
}