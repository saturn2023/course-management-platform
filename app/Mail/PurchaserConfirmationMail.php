<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaserConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function envelope(): Envelope
    {
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