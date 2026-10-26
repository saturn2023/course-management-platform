<?php

namespace App\Filament\Resources\EnrolmentEmailTemplates\Actions;

use App\Models\EnrolmentEmailTemplate;
use App\Services\EnrolmentTemplateTestSender;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * "Send test" — previews a template by emailing it to the signed-in admin.
 *
 * Defined once and reused by both the table row action and the edit page header
 * so the two can never drift apart.
 */
class SendTestEmailAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendTest';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Send test');
        $this->icon(Heroicon::OutlinedPaperAirplane);
        $this->color('gray');

        /*
         * Confirmed rather than one-click, and the address is named in the
         * dialog: an admin should see exactly where it is going before a real
         * email leaves the system.
         */
        $this->requiresConfirmation();
        $this->modalHeading('Send a test of this template');
        $this->modalDescription(fn (): string => sprintf(
            'This sends the template to %s and nowhere else, using "%s %s" as the learner. '
                .'Links built from {site_url} will point at %s. Attachments are included.',
            auth()->user()?->email ?? 'your account',
            EnrolmentTemplateTestSender::TEST_FIRST_NAME,
            EnrolmentTemplateTestSender::TEST_LAST_NAME,
            EnrolmentEmailTemplate::siteUrl(),
        ));
        $this->modalSubmitActionLabel('Send test');

        $this->action(function (EnrolmentEmailTemplate $record, EnrolmentTemplateTestSender $sender): void {
            try {
                $recipient = $sender->sendToCurrentUser($record);
            } catch (Throwable $exception) {
                Notification::make()
                    ->danger()
                    ->title('Test email not sent')
                    ->body($exception->getMessage())
                    ->persistent()
                    ->send();

                return;
            }

            $missing = count($record->attachmentRelativePaths()) - count($record->attachmentPaths());

            Notification::make()
                ->success()
                ->title('Test email sent to '.$recipient)
                ->body($missing > 0
                    ? $missing.' attachment(s) were skipped because the file is missing from storage.'
                    : null)
                ->send();
        });
    }
}
