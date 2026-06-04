{{--
    Purchase Order checkout page.
    Self-contained plain CSS (no Tailwind, no Bootstrap, no external libraries).
    AMS palette: light page, navy headings, green primary buttons, bordered white cards.

    Backend variables used (unchanged):
      $session, $billing
      route('checkout.purchase-order.store', $session)
      fields: purchase_order_number, purchase_order_document
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pay by Purchase Order — AMS Training</title>
    <style>
        :root {
            --ams-navy: #14223f;
            --ams-navy-soft: #2a3a5c;
            --ams-green: #2e9e5b;
            --ams-green-dark: #258049;
            --ams-page: #f4f6f9;
            --ams-card: #ffffff;
            --ams-border: #dfe4ec;
            --ams-text: #34404f;
            --ams-muted: #71808f;
            --ams-red: #c0392b;
            --ams-red-bg: #fdecea;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--ams-page);
            color: var(--ams-text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 15px;
            line-height: 1.5;
        }

        .ams-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px 64px;
        }

        .ams-page-title {
            color: var(--ams-navy);
            font-size: 26px;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .ams-page-sub {
            color: var(--ams-muted);
            margin: 0 0 24px;
            font-size: 14px;
        }

        .ams-card {
            background: var(--ams-card);
            border: 1px solid var(--ams-border);
            border-radius: 8px;
            padding: 22px 24px;
            margin-bottom: 18px;
        }

        .ams-card-title {
            color: var(--ams-muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin: 0 0 14px;
        }

        .ams-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 7px 0;
            font-size: 14px;
        }

        .ams-summary-row .label { color: var(--ams-muted); }
        .ams-summary-row .value { color: var(--ams-navy); font-weight: 600; text-align: right; }
        .ams-summary-row .value small { display: block; color: var(--ams-muted); font-weight: 400; }

        .ams-summary-total {
            border-top: 1px solid var(--ams-border);
            margin-top: 4px;
            padding-top: 12px;
        }
        .ams-summary-total .label { color: var(--ams-navy); font-weight: 700; }
        .ams-summary-total .value { font-size: 16px; }

        .ams-contact p { margin: 0 0 3px; }
        .ams-contact .name { color: var(--ams-navy); font-weight: 600; }
        .ams-contact .muted { color: var(--ams-muted); }

        .ams-errors {
            background: var(--ams-red-bg);
            border: 1px solid #f1b0a8;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 18px;
            color: var(--ams-red);
            font-size: 14px;
        }
        .ams-errors ul { margin: 0; padding-left: 18px; }
        .ams-errors li { margin: 2px 0; }

        .ams-field { margin-bottom: 18px; }
        .ams-field:last-of-type { margin-bottom: 0; }

        .ams-label {
            display: block;
            color: var(--ams-navy);
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 6px;
        }
        .ams-label .req { color: var(--ams-red); }

        .ams-input {
            width: 100%;
            border: 1px solid var(--ams-border);
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 15px;
            color: var(--ams-text);
            background: #fff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .ams-input:focus {
            outline: none;
            border-color: var(--ams-green);
            box-shadow: 0 0 0 3px rgba(46, 158, 91, 0.15);
        }

        .ams-file {
            width: 100%;
            font-size: 14px;
            color: var(--ams-text);
        }
        .ams-file::file-selector-button {
            border: 1px solid var(--ams-border);
            background: #eef1f5;
            color: var(--ams-navy);
            font-weight: 600;
            font-size: 14px;
            padding: 8px 14px;
            border-radius: 6px;
            margin-right: 12px;
            cursor: pointer;
        }
        .ams-file::file-selector-button:hover { background: #e3e8ef; }

        .ams-hint { color: var(--ams-muted); font-size: 12px; margin: 6px 0 0; }

        .ams-btn {
            display: block;
            width: 100%;
            border: none;
            border-radius: 6px;
            background: var(--ams-green);
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            padding: 13px 16px;
            margin-top: 24px;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .ams-btn:hover { background: var(--ams-green-dark); }
        .ams-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(46, 158, 91, 0.3);
        }
        .ams-students {
    margin-top: 18px;
}

.ams-student-summary {
    border-top: 1px solid var(--ams-border);
    padding-top: 12px;
    margin-top: 12px;
    font-size: 14px;
}

.ams-student-summary:first-of-type {
    border-top: none;
    padding-top: 0;
    margin-top: 0;
}

.ams-student-summary p {
    margin: 0 0 3px;
}

.ams-student-heading {
    color: var(--ams-muted);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.ams-student-name {
    color: var(--ams-navy);
    font-weight: 600;
}

.ams-muted {
    color: var(--ams-muted);
    font-size: 14px;
}

.ams-edit-link {
    display: inline-block;
    margin-top: 14px;
    color: var(--ams-green);
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
}

.ams-edit-link:hover {
    text-decoration: underline;
}
    </style>
</head>
<body>
    <div class="ams-container">

        <h1 class="ams-page-title">Pay by Purchase Order</h1>
        <p class="ams-page-sub">
            Submit your purchase order details to complete enrolment. AMS Training will review and process it.
        </p>

        {{-- Order summary --}}
        <div class="ams-card">
            <h2 class="ams-card-title">Order summary</h2>

            <div class="ams-summary-row">
                <span class="label">Course</span>
                <span class="value">
                    {{ $session->course_title ?: $session->course_code }}
                    @if ($session->plan_title)
                        <small>{{ $session->plan_title }}</small>
                    @endif
                </span>
            </div>
            <div class="ams-summary-row">
                <span class="label">Quantity</span>
                <span class="value">{{ $session->quantity }}</span>
            </div>
            <div class="ams-summary-row">
                <span class="label">Unit price</span>
                <span class="value">${{ number_format((float) $session->unit_price, 2) }}</span>
            </div>
            <div class="ams-summary-row ams-summary-total">
                <span class="label">Subtotal</span>
                <span class="value">${{ number_format((float) $session->subtotal, 2) }}</span>
            </div>
        </div>

        {{-- Billing contact --}}
        <div class="ams-card ams-contact">
            <h2 class="ams-card-title">Billing contact</h2>
            @php
                $billingName = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
            @endphp
            @if ($billingName !== '')
                <p class="name">{{ $billingName }}</p>
            @endif
            @if (! empty($billing['company']))
                <p>{{ $billing['company'] }}</p>
            @endif
            @if (! empty($billing['email']))
                <p>{{ $billing['email'] }}</p>
            @endif
            @if (! empty($billing['phone']))
                <p>{{ $billing['phone'] }}</p>
            @endif
            @if (! empty($billing['abn']))
                <p class="muted">ABN: {{ $billing['abn'] }}</p>
            @endif
        </div>
       {{-- Student details --}}
<div class="ams-card ams-students">
    <h2 class="ams-card-title">Student details</h2>

    @php
        $students = $session->student_details ?? [];
    @endphp

    @if (! empty($students) && is_array($students))
        @foreach ($students as $index => $student)
            <div class="ams-student-summary">
                <p class="ams-student-heading">Student {{ $index + 1 }}</p>

                <p class="ams-student-name">
                    {{ trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) }}
                </p>

                @if (! empty($student['email']))
                    <p>{{ $student['email'] }}</p>
                @endif

                @if (! empty($student['phone']))
                    <p>{{ $student['phone'] }}</p>
                @endif
            </div>
        @endforeach
    @else
        <p class="ams-muted">No student details found.</p>
    @endif

    <a href="{{ route('checkout.show', $session) }}" class="ams-edit-link">
        Edit student or billing details
    </a>
</div>
        {{-- Validation errors --}}
        @if ($errors->any())
            <div class="ams-errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- PO form --}}
        <div class="ams-card">
            <form
                method="POST"
                action="{{ route('checkout.purchase-order.store', $session) }}"
                enctype="multipart/form-data"
            >
                @csrf

                <div class="ams-field">
                    <label class="ams-label" for="purchase_order_number">
                        Purchase order number <span class="req">*</span>
                    </label>
                    <input
                        type="text"
                        class="ams-input"
                        name="purchase_order_number"
                        id="purchase_order_number"
                        value="{{ old('purchase_order_number') }}"
                        maxlength="100"
                        required
                        placeholder="e.g. PO-2026-00123"
                    >
                </div>

                <div class="ams-field">
                    <label class="ams-label" for="purchase_order_document">
                        Purchase order document <span class="req">*</span>
                    </label>
                    <input
                        type="file"
                        class="ams-file"
                        name="purchase_order_document"
                        id="purchase_order_document"
                        accept=".pdf,.jpg,.jpeg,.png"
                        required
                    >
                    <p class="ams-hint">PDF, JPG or PNG. Max 25 MB.</p>
                </div>

                <button type="submit" class="ams-btn">Submit purchase order</button>
            </form>
        </div>

    </div>
</body>
</html>