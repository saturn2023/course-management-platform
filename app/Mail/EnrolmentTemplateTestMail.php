<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A test send of an Enrolment Email template, to the admin previewing it.
 *
 * Deliberately NOT a markdown mailable: the point of a test send is to see what
 * the learner would receive, and the real confirmation sends the template's raw
 * HTML body as-is. Wrapping it in the shared AMS mail layout would show the
 * admin something the learner never gets — so this carries no logo header and
 * does not use EmbedsAmsLogo.
 *
 * The subject is the template's real composed subject, unprefixed, for the same
 * reason.
 */
class EnrolmentTemplateTestMail extends Mailable
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
