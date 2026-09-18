<?php

namespace App\Mail;

use App\Models\Enrolment;
use App\Mail\Concerns\EmbedsAmsLogo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnrolmentLinkMail extends Mailable
{
    use EmbedsAmsLogo, Queueable, SerializesModels;

    public function __construct(
        public Enrolment $enrolment
    ) {}

    public function envelope(): Envelope
    {
        // Hooked here rather than in the constructor: envelope() runs at
        // send time, so the embed closure is never part of a queued payload.
        $this->embedAmsLogo();

        return new Envelope(
            subject: 'AMS Registration Form Student to complete'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.enrolment-link',
            with: [
                'enrolment' => $this->enrolment,
            ],
        );
    }
}