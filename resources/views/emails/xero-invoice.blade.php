<x-mail::message>
# Your AMS Training Invoice

Dear {{ $order->billing_first_name ?: 'Customer' }},

Please find your tax invoice attached.

@if ($order->xero_invoice_number)
**Invoice number:** {{ $order->xero_invoice_number }}
@endif

**Amount:** ${{ number_format((float) $order->total, 2) }}

The invoice is attached to this email as a PDF.

Thanks,<br>
AMS Training
</x-mail::message>
