<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RtoDataCourseService
{
    public function getCourseDetail(string $courseCode, string $planId, ?string $scheduleId = null): array
    {
        $baseUrl = rtrim((string) config('services.enrolment_api.base_url'), '/');
        $publicKey = config('services.enrolment_api.public_key');
        $subdomain = config('services.enrolment_api.subdomain', 'amstraining');
        $origin = config('services.enrolment_api.origin');
        $timeout = (int) config('services.enrolment_api.timeout', 30);
        $connectTimeout = (int) config('services.enrolment_api.connect_timeout', 10);

        if (empty($baseUrl) || empty($publicKey) || empty($subdomain) || empty($origin)) {
            throw new RuntimeException('RTO Data API credentials are not configured.');
        }

        $response = Http::withHeaders([
                'Origin' => $origin,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->acceptJson()
            ->withOptions([
                'allow_redirects' => true,
                'curl' => [
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_POSTREDIR => 3,
                ],
            ])
            ->post($baseUrl . '/detail', [
                'public_key' => $publicKey,
                'subdomain' => $subdomain,
                'course_id' => $courseCode,
                'plan_id' => $planId,
                'type' => 'course',
            ]);

        if (! $response->successful()) {
            Log::warning('RTO Data /detail request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'course_code' => $courseCode,
                'plan_id' => $planId,
                'schedule_id' => $scheduleId,
            ]);

            throw new RuntimeException('Could not load course details. Please try again shortly.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            Log::warning('RTO Data /detail returned non-JSON response', [
                'body' => $response->body(),
                'course_code' => $courseCode,
                'plan_id' => $planId,
            ]);

            throw new RuntimeException('Invalid course details response.');
        }

        $course = $payload['data'] ?? $payload['detail'] ?? $payload;

        if (! is_array($course) || empty($course)) {
            throw new RuntimeException('Course not found.');
        }

        $plan = $this->findPlan($course, $planId);

        if ($plan === null) {
            throw new RuntimeException('The selected payment plan is no longer available.');
        }

        $schedule = $this->findSchedule($plan, $course, $scheduleId);

        $unitPrice = $this->extractPrice($plan, $course);

        return [
            'course_code' => (string) (
                $course['course_code']
                ?? $course['code']
                ?? $courseCode
            ),

            'course_title' => (string) (
                $course['course_title']
                ?? $course['title']
                ?? $course['name']
                ?? ''
            ),

            'plan_id' => (string) $planId,

            'plan_title' => $plan['plan_title']
                ?? $plan['title']
                ?? $plan['name']
                ?? null,

            'schedule_id' => $scheduleId,

            'start_date' => $schedule['start_date']
                ?? $schedule['start']
                ?? null,

            'end_date' => $schedule['end_date']
                ?? $schedule['end']
                ?? null,

            'dates' => $schedule['dates']
                ?? ($schedule ? [$schedule] : null),

            'delivery_mode' => $this->extractDeliveryMode($course, $schedule),

            'unit_price' => $unitPrice,

            'stock_quantity' => isset($schedule['places'])
                ? (int) $schedule['places']
                : (isset($schedule['stock_quantity']) ? (int) $schedule['stock_quantity'] : null),

            'enrolments' => isset($schedule['enrolments'])
                ? (int) $schedule['enrolments']
                : null,

            'raw' => $payload,
        ];
    }

    private function findPlan(array $course, string $planId): ?array
    {
        $plans = $course['plans']
            ?? $course['plan']
            ?? [];

        if (isset($plans['id'])) {
            $plans = [$plans];
        }

        foreach ($plans as $plan) {
            if (! is_array($plan)) {
                continue;
            }

            $id = (string) ($plan['id'] ?? $plan['plan_id'] ?? '');

            if ($id === (string) $planId) {
                return $plan;
            }
        }

        return null;
    }

    private function findSchedule(array $plan, array $course, ?string $scheduleId): ?array
    {
        $scheduleId = trim((string) $scheduleId);

        $candidates = array_merge(
            $plan['schedules'] ?? [],
            $plan['schedule'] ?? [],
            $course['schedules'] ?? [],
            $course['schedule'] ?? []
        );

        if (isset($candidates['id'])) {
            $candidates = [$candidates];
        }

        if ($scheduleId === '') {
            return $candidates[0] ?? null;
        }

        foreach ($candidates as $schedule) {
            if (! is_array($schedule)) {
                continue;
            }

            $id = (string) ($schedule['id'] ?? $schedule['schedule_id'] ?? '');

            if ($id === $scheduleId) {
                return $schedule;
            }
        }

        return null;
    }

    private function extractPrice(array $plan, array $course): float
    {
        $price =
            $plan['payments'][0]['payment_due']
            ?? $plan['payment_due']
            ?? $plan['price']
            ?? $course['payments'][0]['payment_due']
            ?? $course['price']
            ?? null;

        return (float) ($price ?? 0);
    }

    private function extractDeliveryMode(array $course, ?array $schedule): ?string
    {
        $mode = $schedule['delivery_mode']
            ?? $schedule['mode']
            ?? $course['delivery_mode']
            ?? $course['mode']
            ?? null;

        if ($mode !== null) {
            return strtolower((string) $mode);
        }

        $title = strtoupper((string) ($course['course_title'] ?? $course['title'] ?? ''));

        if (str_contains($title, 'ONLINE')) {
            return 'online';
        }

        return null;
    }
}