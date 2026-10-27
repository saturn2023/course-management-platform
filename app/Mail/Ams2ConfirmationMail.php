<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The per-course enrolment confirmation sent to a learner.
 *
 * Deliberately NOT a markdown mailable: the reference passes the template's
 * HTML straight to wp_mail as the whole body, so wrapping it in the shared AMS
 * mail layout would add a header the learner is not meant to receive and that
 * no one editing the template can see. For the same reason it does not use
 * EmbedsAmsLogo.
 *
 * The From address comes from the ams2 config rather than MAIL_FROM_ADDRESS:
 * the reference hardcodes it, and a learner's confirmation must come from AMS
 * whatever this environment sends its own mail as.
 */
class Ams2ConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $attachmentPaths  Absolute paths, already filtered to files that exist.
     */
    public function __construct(
        public string $subjectLine,
        public string $body,
        public array $attachmentPaths = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('services.ams2.mail_from'),
                (string) config('services.ams2.mail_from_name'),
            ),
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->body,
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (string $path) => Attachment::fromPath($path),
            $this->attachmentPaths,
        );
    }
}
