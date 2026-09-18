<?php

namespace App\Mail;

use App\Models\Order;
use App\Mail\Concerns\EmbedsAmsLogo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class XeroInvoiceMail extends Mailable
{
    use EmbedsAmsLogo, Queueable, SerializesModels;

    /**
     * @param  string  $pdfContents  Raw bytes of the official Xero invoice PDF.
     */
    public function __construct(
        public Order $order,
        public string $pdfContents
    ) {}

    public function envelope(): Envelope
    {
        // Hooked here rather than in the constructor: envelope() runs at
        // send time, so the embed closure is never part of a queued payload.
        $this->embedAmsLogo();

        return new Envelope(
            subject: 'AMS Training Invoice ' . $this->order->xero_invoice_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.xero-invoice',
            with: [
                'order' => $this->order,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $filename = 'invoice-' . $this->order->xero_invoice_number . '.pdf';

        return [
            Attachment::fromData(fn (): string => $this->pdfContents, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
