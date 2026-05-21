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

        $existingEnrolment = Enrolment::where('order_id', $order->id)
            ->whereNotIn('status', ['failed'])
            ->first();

        if ($existingEnrolment) {
            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'enrolment_api',
                'action' => 'create_enrolment_link',
                'status' => 'skipped',
                'request_payload' => json_encode([
                    'order_id' => $order->id,
                    'existing_enrolment_id' => $existingEnrolment->id,
                    'existing_external_enrolment_id' => $existingEnrolment->external_enrolment_id,
                    'existing_enrolment_link' => $existingEnrolment->enrolment_link,
                    'email_sent_at' => $existingEnrolment->email_sent_at,
                ]),
                'response_payload' => json_encode([
                    'message' => 'Skipped enrolment link creation because this order already has an enrolment.',
                ]),
            ]);

            if (! $existingEnrolment->email_sent_at) {
                SendEnrolmentEmailJob::dispatch($existingEnrolment->id);

                IntegrationLog::create([
                    'order_id' => $order->id,
                    'service' => 'email',
                    'action' => 'send_enrolment_link',
                    'status' => 'queued',
                    'request_payload' => json_encode([
                        'enrolment_id' => $existingEnrolment->id,
                        'student_email' => $existingEnrolment->student?->email,
                        'message' => 'Existing enrolment found, email was not sent yet, so email job was queued.',
                    ]),
                ]);
            }

            return;
        }

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
            'action' => 'create_enrolment_link',
            'status' => 'processing',
            'request_payload' => json_encode($payload),
        ]);

        try {
            // Fake enrolment link API response for now.
            // Later this will be replaced with the real AMS enrolment link API call.
            $externalEnrolmentId = 'ENR-' . Str::upper(Str::random(10));

            $enrolmentLink = 'https://example.com/enrolment/' . $externalEnrolmentId;

            $enrolment = Enrolment::create([
                'order_id' => $order->id,
                'student_id' => $order->student_id,
                'course_id' => $firstItem?->course_id,
                'external_enrolment_id' => $externalEnrolmentId,
                'enrolment_link' => $enrolmentLink,
                'status' => 'link_created',
                'request_payload' => json_encode($payload),
                'response_payload' => json_encode([
                    'external_enrolment_id' => $externalEnrolmentId,
                    'enrolment_link' => $enrolmentLink,
                    'message' => 'Fake enrolment link created successfully.',
                ]),
            ]);

            $order->update([
                'enrolment_status' => 'link_created',
            ]);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'enrolment_api',
                'action' => 'create_enrolment_link',
                'status' => 'success',
                'request_payload' => json_encode($payload),
                'response_payload' => json_encode([
                    'enrolment_id' => $enrolment->id,
                    'external_enrolment_id' => $externalEnrolmentId,
                    'enrolment_link' => $enrolmentLink,
                    'message' => 'Fake enrolment link created successfully.',
                ]),
            ]);

            SendEnrolmentEmailJob::dispatch($enrolment->id);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'email',
                'action' => 'send_enrolment_link',
                'status' => 'queued',
                'request_payload' => json_encode([
                    'enrolment_id' => $enrolment->id,
                    'student_email' => $order->student?->email,
                    'message' => 'Email job queued after enrolment link was created.',
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
                'action' => 'create_enrolment_link',
                'status' => 'failed',
                'request_payload' => json_encode($payload),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}