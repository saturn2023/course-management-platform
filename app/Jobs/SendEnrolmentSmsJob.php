<?php

namespace App\Jobs;

use App\Models\Enrolment;
use App\Models\IntegrationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendEnrolmentSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $enrolmentId,
        public bool $forceResend = false
    ) {}

    public function handle(): void
    {
        $enrolment = Enrolment::with(['order', 'student', 'course'])->findOrFail($this->enrolmentId);

        if ($enrolment->sms_sent_at && ! $this->forceResend) {
            IntegrationLog::create([
                'order_id' => $enrolment->order_id,
                'service' => 'sms',
                'action' => 'send_enrolment_sms',
                'status' => 'skipped',
                'request_payload' => json_encode([
                    'enrolment_id' => $enrolment->id,
                    'student_phone' => $enrolment->student?->phone,
                    'sms_sent_at' => $enrolment->sms_sent_at,
                ]),
                'response_payload' => json_encode([
                    'message' => 'Skipped SMS because the enrolment SMS was already sent.',
                ]),
            ]);

            return;
        }

        if (! $enrolment->student?->phone) {
            IntegrationLog::create([
                'order_id' => $enrolment->order_id,
                'service' => 'sms',
                'action' => 'send_enrolment_sms',
                'status' => 'failed',
                'request_payload' => json_encode([
                    'enrolment_id' => $enrolment->id,
                ]),
                'error_message' => 'Student phone number is missing.',
            ]);

            throw new \Exception('Student phone number is missing.');
        }

        $twilioEnabled = filter_var(env('TWILIO_ENABLED', false), FILTER_VALIDATE_BOOLEAN);

        $message = $this->buildMessage($enrolment);

        if (! $twilioEnabled) {
            IntegrationLog::create([
                'order_id' => $enrolment->order_id,
                'service' => 'sms',
                'action' => 'send_enrolment_sms',
                'status' => 'skipped',
                'request_payload' => json_encode([
                    'enrolment_id' => $enrolment->id,
                    'student_phone' => $enrolment->student->phone,
                    'message' => $message,
                    'twilio_enabled' => false,
                ]),
                'response_payload' => json_encode([
                    'message' => 'Twilio SMS sending is disabled in .env. No real SMS was sent.',
                ]),
            ]);

            return;
        }

        try {
            IntegrationLog::create([
                'order_id' => $enrolment->order_id,
                'service' => 'sms',
                'action' => 'send_enrolment_sms',
                'status' => 'processing',
                'request_payload' => json_encode([
                    'enrolment_id' => $enrolment->id,
                    'student_phone' => $enrolment->student->phone,
                    'force_resend' => $this->forceResend,
                ]),
            ]);

            $accountSid = env('TWILIO_ACCOUNT_SID');
            $authToken = env('TWILIO_AUTH_TOKEN');
            $fromNumber = env('TWILIO_FROM_NUMBER');

            if (! $accountSid || ! $authToken || ! $fromNumber) {
                throw new \Exception('Twilio credentials are missing from .env.');
            }

            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => $fromNumber,
                    'To' => $enrolment->student->phone,
                    'Body' => $message,
                ]);

            if (! $response->successful()) {
                throw new \Exception('Twilio SMS failed: ' . $response->body());
            }

            $enrolment->update([
                'sms_sent_at' => now(),
                'sms_error_message' => null,
            ]);

            IntegrationLog::create([
                'order_id' => $enrolment->order_id,
                'service' => 'sms',
                'action' => 'send_enrolment_sms',
                'status' => 'success',
                'response_payload' => json_encode([
                    'message' => $this->forceResend
                        ? 'Enrolment SMS resent successfully.'
                        : 'Enrolment SMS sent successfully.',
                    'enrolment_id' => $enrolment->id,
                    'student_phone' => $enrolment->student->phone,
                    'twilio_sid' => $response->json('sid'),
                    'force_resend' => $this->forceResend,
                ]),
            ]);
        } catch (Throwable $exception) {
            $enrolment->update([
                'sms_error_message' => $exception->getMessage(),
            ]);

            IntegrationLog::create([
                'order_id' => $enrolment->order_id,
                'service' => 'sms',
                'action' => 'send_enrolment_sms',
                'status' => 'failed',
                'request_payload' => json_encode([
                    'enrolment_id' => $enrolment->id,
                    'student_phone' => $enrolment->student?->phone,
                    'force_resend' => $this->forceResend,
                ]),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function buildMessage(Enrolment $enrolment): string
    {
        $firstName = $enrolment->student?->first_name ?? 'there';
        $company = $enrolment->order?->billing_company ?: 'AMS Training';
        $course = $enrolment->course?->title ?? 'your course';

        return "Dear {$firstName}, you have been registered by {$company} for {$course}. Complete your registration: {$enrolment->enrolment_link} - AMS Training";
    }
}