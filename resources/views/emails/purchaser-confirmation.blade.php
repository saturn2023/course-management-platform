<x-mail::message>
# Student registration confirmation

Dear {{ $order->billing_first_name ?: 'Customer' }},

This is to confirm the following students have been registered.

**Course:** {{ $order->items->first()?->name ?? 'Selected course' }}

**Order total:** ${{ number_format((float) $order->total, 2) }}

@if ($order->xero_invoice_number)
**Xero invoice number:** {{ $order->xero_invoice_number }}
@endif

## Registered students

@foreach ($order->students as $student)
- {{ trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) }} &lt;{{ $student->email }}&gt;
@endforeach

Thanks,<br>
AMS Training
</x-mail::message>