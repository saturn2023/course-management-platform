<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout — {{ $session->course_title }}</title>

    <style>
        body {
            font-family: Arial, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f4f4f4;
            color: #223A74;
            margin: 0;
            padding: 2rem;
        }

        .container {
            max-width: 820px;
            margin: 2rem auto;
        }

        .card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #dddddd;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            padding: 2rem;
            margin-bottom: 1.5rem;
        }

        h1 {
            font-size: 1.7rem;
            margin: 0 0 0.35rem;
            color: #223A74;
        }

        h2 {
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #666666;
            margin: 0 0 1rem;
        }

        .course-code {
            color: #666666;
            margin: 0.25rem 0 1.5rem;
        }

        dl {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 0.65rem 1rem;
            margin: 0;
        }

        dt {
            color: #666666;
            font-weight: 600;
        }

        dd {
            margin: 0;
            color: #333333;
        }

        .totals {
            border-top: 1px solid #dddddd;
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            font-size: 1.1rem;
            color: #333333;
        }

        .totals strong {
            font-size: 1.35rem;
            color: #223A74;
        }

        .actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            flex: 1;
            min-width: 220px;
            padding: 1rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 700;
            cursor: not-allowed;
            opacity: 0.65;
        }

        .btn-primary {
            background: #87c65a;
            color: #ffffff;
            border: 1px solid #87c65a;
        }

        .btn-secondary {
            background: #ffffff;
            color: #223A74;
            border: 1px solid #223A74;
        }

        .note {
            font-size: 0.9rem;
            color: #666666;
            margin-top: 0.85rem;
        }

        .dates {
            margin: 0;
            padding-left: 1.2rem;
        }

        @media (max-width: 640px) {
            body {
                padding: 1rem;
            }

            dl {
                grid-template-columns: 1fr;
            }

            dt {
                margin-top: 0.4rem;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>{{ $session->course_title }}</h1>

        <p class="course-code">
            Course code: {{ $session->course_code }}
        </p>

        <h2>Your enrolment</h2>

        <dl>
            <dt>Payment plan</dt>
            <dd>{{ $session->plan_title ?? $session->plan_id }}</dd>

            @if ($session->start_date)
                <dt>Start date</dt>
                <dd>{{ $session->start_date->format('j M Y') }}</dd>
            @endif

            @if ($session->end_date)
                <dt>End date</dt>
                <dd>{{ $session->end_date->format('j M Y') }}</dd>
            @endif

            @if (! empty($session->dates) && is_array($session->dates))
                <dt>Scheduled dates</dt>
                <dd>
                    <ul class="dates">
                        @foreach ($session->dates as $date)
                            <li>{{ is_array($date) ? ($date['date'] ?? json_encode($date)) : $date }}</li>
                        @endforeach
                    </ul>
                </dd>
            @endif

            <dt>Students</dt>
            <dd>{{ $session->quantity }}</dd>

            <dt>Unit price</dt>
            <dd>${{ number_format((float) $session->unit_price, 2) }}</dd>
        </dl>

        <div class="totals">
            Subtotal: <strong>${{ number_format((float) $session->subtotal, 2) }}</strong>
        </div>
    </div>

    <div class="card">
        <h2>Choose how to pay</h2>

        <div class="actions">
            <button type="button" class="btn btn-primary" disabled>Pay by card</button>
            <button type="button" class="btn btn-secondary" disabled>Pay by purchase order</button>
        </div>

        <p class="note">
            Payment options will be enabled in the next release.
        </p>
    </div>
</div>
</body>
</html>