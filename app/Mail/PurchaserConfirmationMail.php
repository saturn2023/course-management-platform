<?php

namespace App\Mail;

use App\Models\Order;
use App\Mail\Concerns\EmbedsAmsLogo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaserConfirmationMail extends Mailable
{
    use EmbedsAmsLogo, Queueable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function envelope(): Envelope
    {
        // Hooked here rather than in the constructor: envelope() runs at
        // send time, so the embed closure is never part of a queued payload.
        $this->embedAmsLogo();

        return new Envelope(
            subject: 'AMS Training Registered Students'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.purchaser-confirmation',
            with: [
                'order' => $this->order,
            ],
        );
    }
}