<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class XeroInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $pdfContents  Raw bytes of the official Xero invoice PDF.
     */
    public function __construct(
        public Order $order,
        public string $pdfContents
    ) {}

    public function envelope(): Envelope
    {
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
