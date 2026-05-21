<?php

namespace App\Mail;

use App\Models\Enrolment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnrolmentLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Enrolment $enrolment
    ) {}

    public function envelope(): Envelope
    {
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