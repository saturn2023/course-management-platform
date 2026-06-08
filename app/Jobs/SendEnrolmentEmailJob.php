<?php

namespace App\Jobs;

use App\Mail\EnrolmentLinkMail;
use App\Models\Enrolment;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Support\OrderCompletion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendEnrolmentEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $enrolmentId,
        public bool $forceResend = false
    ) {
    }

    public function handle(): void
    {
        $enrolment = Enrolment::with([
            'order',
            'student',
            'course',
        ])->findOrFail($this->enrolmentId);

        /*
        |--------------------------------------------------------------------------
        | Duplicate protection
        |--------------------------------------------------------------------------
        */

        if ($enrolment->email_sent_at && ! $this->forceResend) {
            IntegrationLog::create([
                'order_id' => $enrolment->order_id,
                'service' => 'email',
                'action' => 'send_enrolment_link',
                'status' => 'skipped',
                'request_payload' => json_encode([
                    'enrolment_id' => $enrolment->id,
                    'student_email' => $enrolment->student?->email,
                    'email_sent_at' => $enrolment->email_sent_at,
                ]),
                'response_payload' => json_encode([
                    'message' => 'Skipped email because the enrolment link email was already sent.',
                ]),
            ]);

            /*
             * This email already exists, so check whether all emails for the
             * order have now been sent.
             */
            $this->updateOrderEnrolmentStatus($enrolment->order_id);

            return;
        }

        if (! $enrolment->student?->email) {
            IntegrationLog::create([
                'order_id' => $enrolment->order_id,
                'service' => 'email',
                'action' => 'send_enrolment_link',
                'status' => 'failed',
                'request_payload' => json_encode([
                    'enrolment_id' => $enrolment->id,
                ]),
                'error_message' => 'Student email address is missing.',
            ]);

            throw new \Exception('Student email address is missing.');
        }

        try {
            IntegrationLog::create([
                'order_id' => $enrolment->order_id,
                'service' => 'email',
                'action' => 'send_enrolment_link',
                'status' => 'processing',
                'request_payload' => json_encode([
                    'enrolment_id' => $enrolment->id,
                    'student_email' => $enrolment->student->email,
                    'enrolment_link' => $enrolment->enrolment_link,
                    'force_resend' => $this->forceResend,
                ]),
            ]);

            Mail::to($enrolment->student->email)
                ->send(new EnrolmentLinkMail($enrolment));

            $enrolment->update([
                'status' => 'link_sent',
                'email_sent_at' => now(),
                'link_sent_at' => now(),
            ]);

            IntegrationLog::create([
                'order_id' => $enrolment->order_id,
                'service' => 'email',
                'action' => 'send_enrolment_link',
                'status' => 'success',
                'response_payload' => json_encode([
                    'message' => $this->forceResend
                        ? 'Enrolment link email resent successfully.'
                        : 'Enrolment link email sent successfully.',
                    'enrolment_id' => $enrolment->id,
                    'student_email' => $enrolment->student->email,
                    'force_resend' => $this->forceResend,
                ]),
            ]);

            /*
             * Only mark the order link_sent after all of its enrolment
             * emails have been successfully sent.
             */
            $this->updateOrderEnrolmentStatus($enrolment->order_id);
        } catch (Throwable $exception) {
            IntegrationLog::create([
                'order_id' => $enrolment->order_id,
                'service' => 'email',
                'action' => 'send_enrolment_link',
                'status' => 'failed',
                'request_payload' => json_encode([
                    'enrolment_id' => $enrolment->id,
                    'student_email' => $enrolment->student?->email,
                    'force_resend' => $this->forceResend,
                ]),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Mark the order's enrolment flow as complete only when every enrolment
     * belonging to the order has had its email sent.
     */
    private function updateOrderEnrolmentStatus(?int $orderId): void
    {
        if (! $orderId) {
            return;
        }

        $order = Order::find($orderId);

        if (! $order) {
            return;
        }

        $hasEnrolments = $order->enrolments()->exists();

        if (! $hasEnrolments) {
            return;
        }

        $hasUnsentEmails = $order->enrolments()
            ->whereNull('email_sent_at')
            ->exists();

        if ($hasUnsentEmails) {
            return;
        }

        $order->update([
            'enrolment_status' => 'link_sent',
        ]);

        /*
         * If Xero has also succeeded, this changes the overall order status
         * from processing to processed.
         */
        OrderCompletion::attemptMarkProcessed($order->id);
    }
}