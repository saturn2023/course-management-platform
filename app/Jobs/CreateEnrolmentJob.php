<?php

namespace App\Jobs;

use App\Models\Enrolment;
use App\Models\IntegrationLog;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class CreateEnrolmentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $orderId
    ) {}

    public function handle(): void
    {
        $order = Order::with(['student', 'items.course'])->findOrFail($this->orderId);

        $firstItem = $order->items->first();

        $payload = [
            'order_id' => $order->id,
            'student' => [
                'first_name' => $order->student?->first_name,
                'last_name' => $order->student?->last_name,
                'email' => $order->student?->email,
                'phone' => $order->student?->phone,
            ],
            'course' => [
                'course_id' => $firstItem?->course_id,
                'course_name' => $firstItem?->name,
                'course_code' => $firstItem?->course?->code,
            ],
        ];

        $order->update([
            'enrolment_status' => 'processing',
        ]);

        IntegrationLog::create([
            'order_id' => $order->id,
            'service' => 'enrolment_api',
            'action' => 'create_enrolment',
            'status' => 'processing',
            'request_payload' => json_encode($payload),
        ]);

        try {
            // Fake enrolment API response for now.
            // Later this will be replaced with the real enrolment API call.
            $externalEnrolmentId = 'ENR-' . Str::upper(Str::random(10));

            $enrolmentLink = 'https://example.com/enrolment/' . $externalEnrolmentId;

            $enrolment = Enrolment::create([
                'order_id' => $order->id,
                'student_id' => $order->student_id,
                'course_id' => $firstItem?->course_id,
                'external_enrolment_id' => $externalEnrolmentId,
                'enrolment_link' => $enrolmentLink,
                'status' => 'success',
                'request_payload' => json_encode($payload),
                'response_payload' => json_encode([
                    'external_enrolment_id' => $externalEnrolmentId,
                    'enrolment_link' => $enrolmentLink,
                    'message' => 'Fake enrolment created successfully.',
                ]),
            ]);

            $order->update([
                'enrolment_status' => 'success',
            ]);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'enrolment_api',
                'action' => 'create_enrolment',
                'status' => 'success',
                'request_payload' => json_encode($payload),
                'response_payload' => json_encode([
                    'enrolment_id' => $enrolment->id,
                    'external_enrolment_id' => $externalEnrolmentId,
                    'enrolment_link' => $enrolmentLink,
                    'message' => 'Fake enrolment created successfully.',
                ]),
            ]);
        } catch (Throwable $exception) {
            $order->update([
                'enrolment_status' => 'failed',
            ]);

            Enrolment::create([
                'order_id' => $order->id,
                'student_id' => $order->student_id,
                'course_id' => $firstItem?->course_id,
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'request_payload' => json_encode($payload),
            ]);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'enrolment_api',
                'action' => 'create_enrolment',
                'status' => 'failed',
                'request_payload' => json_encode($payload),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}