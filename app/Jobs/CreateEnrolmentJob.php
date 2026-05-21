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
        $order = Order::with([
            'student',
            'students',
            'items.course',
        ])->findOrFail($this->orderId);

        $firstItem = $order->items->first();

        if (! $firstItem) {
            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'enrolment_api',
                'action' => 'create_enrolment_link',
                'status' => 'failed',
                'request_payload' => json_encode([
                    'order_id' => $order->id,
                    'reason' => 'Order has no order items.',
                ]),
                'error_message' => 'Cannot create enrolment links because the order has no order items.',
            ]);

            $order->update([
                'enrolment_status' => 'failed',
            ]);

            return;
        }

        $students = $order->students;

        // Temporary fallback while we are transitioning from one-student orders
        // to multiple-student orders.
        if ($students->isEmpty() && $order->student) {
            $students = collect([$order->student]);
        }

        if ($students->isEmpty()) {
            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'enrolment_api',
                'action' => 'create_enrolment_link',
                'status' => 'failed',
                'request_payload' => json_encode([
                    'order_id' => $order->id,
                    'reason' => 'Order has no students attached.',
                ]),
                'error_message' => 'Cannot create enrolment links because the order has no students attached.',
            ]);

            $order->update([
                'enrolment_status' => 'failed',
            ]);

            return;
        }

        $order->update([
            'enrolment_status' => 'processing',
        ]);

        IntegrationLog::create([
            'order_id' => $order->id,
            'service' => 'enrolment_api',
            'action' => 'create_enrolment_links',
            'status' => 'processing',
            'request_payload' => json_encode([
                'order_id' => $order->id,
                'student_count' => $students->count(),
                'course_id' => $firstItem?->course_id,
                'course_name' => $firstItem?->name,
            ]),
        ]);

        try {
            $createdCount = 0;
            $skippedCount = 0;
            $emailQueuedCount = 0;

            foreach ($students as $student) {
                $payload = [
                    'order_id' => $order->id,
                    'student' => [
                        'student_id' => $student->id,
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'email' => $student->email,
                        'phone' => $student->phone,
                    ],
                    'course' => [
                        'course_id' => $firstItem?->course_id,
                        'course_name' => $firstItem?->name,
                        'course_code' => $firstItem?->course?->code,
                    ],
                ];

                $existingEnrolment = Enrolment::where('order_id', $order->id)
                    ->where('student_id', $student->id)
                    ->where('course_id', $firstItem?->course_id)
                    ->whereNotIn('status', ['failed'])
                    ->first();

                if ($existingEnrolment) {
                    $skippedCount++;

                    IntegrationLog::create([
                        'order_id' => $order->id,
                        'service' => 'enrolment_api',
                        'action' => 'create_enrolment_link',
                        'status' => 'skipped',
                        'request_payload' => json_encode([
                            'order_id' => $order->id,
                            'student_id' => $student->id,
                            'existing_enrolment_id' => $existingEnrolment->id,
                            'existing_external_enrolment_id' => $existingEnrolment->external_enrolment_id,
                            'existing_enrolment_link' => $existingEnrolment->enrolment_link,
                            'email_sent_at' => $existingEnrolment->email_sent_at,
                        ]),
                        'response_payload' => json_encode([
                            'message' => 'Skipped enrolment link creation because this student already has an enrolment for this order and course.',
                        ]),
                    ]);

                    if (! $existingEnrolment->email_sent_at) {
                        SendEnrolmentEmailJob::dispatch($existingEnrolment->id);
                        $emailQueuedCount++;

                        IntegrationLog::create([
                            'order_id' => $order->id,
                            'service' => 'email',
                            'action' => 'send_enrolment_link',
                            'status' => 'queued',
                            'request_payload' => json_encode([
                                'enrolment_id' => $existingEnrolment->id,
                                'student_id' => $student->id,
                                'student_email' => $student->email,
                                'message' => 'Existing enrolment found. Email was not sent yet, so email job was queued.',
                            ]),
                        ]);
                    }

                    continue;
                }

                // Fake enrolment link API response for now.
                // Later this will be replaced with the real AMS enrolment link API call.
                $externalEnrolmentId = 'ENR-' . Str::upper(Str::random(10));

                $enrolmentLink = 'https://example.com/enrolment/' . $externalEnrolmentId;

                $enrolment = Enrolment::create([
                    'order_id' => $order->id,
                    'student_id' => $student->id,
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

                $createdCount++;

                IntegrationLog::create([
                    'order_id' => $order->id,
                    'service' => 'enrolment_api',
                    'action' => 'create_enrolment_link',
                    'status' => 'success',
                    'request_payload' => json_encode($payload),
                    'response_payload' => json_encode([
                        'enrolment_id' => $enrolment->id,
                        'student_id' => $student->id,
                        'external_enrolment_id' => $externalEnrolmentId,
                        'enrolment_link' => $enrolmentLink,
                        'message' => 'Fake enrolment link created successfully.',
                    ]),
                ]);

                SendEnrolmentEmailJob::dispatch($enrolment->id);
                $emailQueuedCount++;

                IntegrationLog::create([
                    'order_id' => $order->id,
                    'service' => 'email',
                    'action' => 'send_enrolment_link',
                    'status' => 'queued',
                    'request_payload' => json_encode([
                        'enrolment_id' => $enrolment->id,
                        'student_id' => $student->id,
                        'student_email' => $student->email,
                        'message' => 'Email job queued after enrolment link was created.',
                    ]),
                ]);
            }

            $order->update([
                'enrolment_status' => 'link_created',
            ]);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'enrolment_api',
                'action' => 'create_enrolment_links',
                'status' => 'success',
                'response_payload' => json_encode([
                    'message' => 'Enrolment link processing completed for order students.',
                    'student_count' => $students->count(),
                    'created_count' => $createdCount,
                    'skipped_count' => $skippedCount,
                    'email_queued_count' => $emailQueuedCount,
                ]),
            ]);
        } catch (Throwable $exception) {
            $order->update([
                'enrolment_status' => 'failed',
            ]);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'enrolment_api',
                'action' => 'create_enrolment_links',
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}