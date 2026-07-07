{{--
    Checkout thank-you page.
    Self-contained plain CSS (no Tailwind, no Bootstrap, no external libraries).
    AMS palette: light page, navy headings, green accent, bordered white card.

    The order id is passed via session flash ('order_id') from the controller.
    $session is available from the route.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($order?->payment_method ?? null) === 'card' ? 'Payment successful' : 'Purchase order received' }} — AMS Training</title>
    <style>
        :root {
            --ams-navy: #14223f;
            --ams-green: #2e9e5b;
            --ams-page: #f4f6f9;
            --ams-card: #ffffff;
            --ams-border: #dfe4ec;
            --ams-text: #34404f;
            --ams-muted: #71808f;
            --ams-amber-bg: #fdf4e3;
            --ams-amber-text: #8a6d1f;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--ams-page);
            color: var(--ams-text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 15px;
            line-height: 1.6;
        }

        .ams-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 64px 20px;
            display: flex;
            justify-content: center;
        }

        .ams-card {
            background: var(--ams-card);
            border: 1px solid var(--ams-border);
            border-radius: 8px;
            padding: 44px 36px;
            max-width: 560px;
            width: 100%;
            text-align: center;
        }

        .ams-check {
            width: 56px;
            height: 56px;
            margin: 0 auto 22px;
            border-radius: 50%;
            background: #e7f5ec;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ams-check svg { width: 30px; height: 30px; stroke: var(--ams-green); }

        .ams-title {
            color: var(--ams-navy);
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 8px;
        }

        .ams-order-ref {
            color: var(--ams-muted);
            font-size: 14px;
            margin: 0 0 18px;
        }
        .ams-order-ref strong { color: var(--ams-navy); }

        .ams-status {
            display: inline-block;
            background: var(--ams-amber-bg);
            color: var(--ams-amber-text);
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 999px;
            margin-bottom: 20px;
        }

        .ams-body {
            color: var(--ams-text);
            font-size: 14px;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="ams-container">
        <div class="ams-card">

            <div class="ams-check" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>

            @php
                $isCard = ($order?->payment_method ?? null) === 'card';
                $orderReference = $order?->id ?? session('order_id');
            @endphp

            <h1 class="ams-title">{{ $isCard ? 'Payment successful' : 'Purchase order received' }}</h1>

            @if ($orderReference)
                <p class="ams-order-ref">
                    Your order reference is <strong>#{{ $orderReference }}</strong>.
                </p>
            @endif

            <div class="ams-status">Status: {{ $isCard ? 'Payment received' : 'Purchase order received' }}</div>

            @if ($isCard)
                <p class="ams-body">
                    Thank you. Your card payment was received successfully. We're now setting up the
                    enrolment, and the enrolment details will be emailed to the address provided.
                    You don't need to do anything further right now.
                </p>
            @else
                <p class="ams-body">
                    Thank you. AMS Training has received your purchase order and will review it shortly.
                    Once it has been processed, we'll set up the enrolment and send the enrolment details
                    to the email address provided. You don't need to do anything further right now.
                </p>
            @endif

        </div>
    </div>
</body>
</html>