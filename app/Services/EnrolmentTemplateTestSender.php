<?php

namespace App\Services;

use App\Mail\EnrolmentTemplateTestMail;
use App\Models\EnrolmentEmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Sends a one-off preview of an Enrolment Email template to the admin who asked
 * for it.
 *
 * A test send must never reach a learner, so the recipient is not a parameter
 * anywhere in this class — it is read from the authenticated user and nothing
 * else. There is no argument a caller could pass the wrong address to.
 *
 * Uses the application's normal mailer. The Two Moons SMTP2GO channel is only
 * for failure alerts, which must survive the client's mail setup being broken;
 * a template preview has the opposite requirement — it should go out exactly
 * the way a real confirmation would.
 */
class EnrolmentTemplateTestSender
{
    /**
     * Stand-in learner details. The real flow uppercases these at render time.
     */
    public const TEST_FIRST_NAME = 'Test';

    public const TEST_LAST_NAME = 'Student';

    /**
     * @return string The address the preview was sent to.
     *
     * @throws RuntimeException when there is no signed-in user to send to.
     */
    public function sendToCurrentUser(EnrolmentEmailTemplate $template): string
    {
        $recipient = $this->currentUserEmail();

        $rendered = $template->render(
            [
                'first_name' => self::TEST_FIRST_NAME,
                'last_name' => self::TEST_LAST_NAME,
            ],
            $template->resolvedCourseTitle(),
        );

        Mail::to($recipient)->send(new EnrolmentTemplateTestMail(
            subjectLine: $rendered['subject'],
            body: $rendered['body'],
            attachmentPaths: $rendered['attachments'],
        ));

        return $recipient;
    }

    private function currentUserEmail(): string
    {
        $user = auth()->user();

        if (! $user instanceof User || blank($user->email)) {
            throw new RuntimeException(
                'A test email can only be sent to a signed-in administrator.'
            );
        }

        return $user->email;
    }
}
