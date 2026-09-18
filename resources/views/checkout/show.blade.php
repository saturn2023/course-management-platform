<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Registration — {{ $session->course_title }}</title>
    <x-layout.fonts />

    @if (auth()->user() === null || auth()->user()->canPayByCard())
        {{-- Pin Payments official Hosted Fields library. --}}
        <script src="https://cdn.pinpayments.com/pin.hosted_fields.v1.js"></script>
    @endif

    <style>
        :root {
            --ams-navy: #1a2d5a;
            --ams-red: #d6202c;
            --ams-green: #7cb342;
            --ams-green-hover: #6aa033;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #ffffff;
            --page-bg: #f7f7f8;
            --divider-green: #b3d68c;
            --error: #dc2626;
            --success-bg: #dcfce7;
            --success-border: #86efac;
            --success-text: #166534;
        }

        * { box-sizing: border-box; }

        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--page-bg);
            color: var(--text);
            margin: 0;
            padding: 2rem 1rem;
            line-height: 1.5;
        }

        .container { max-width: 900px; margin: 0 auto; }

        h1.page-title {
            text-align: center;
            color: var(--ams-navy);
            font-size: 2rem;
            margin: 0 0 1.5rem;
        }

        .intro { color: var(--ams-navy); margin-bottom: 1.5rem; }
        .intro p { margin: 0 0 1rem; }

        .divider {
            border: 0;
            border-top: 2px solid var(--divider-green);
            margin: 1.5rem 0;
        }

        .company-note {
            color: var(--ams-red);
            font-weight: 600;
            margin-bottom: 2rem;
        }

        /* Product / quantity row */
        .product-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr 1fr;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            background: #f3f4f6;
            border-radius: 4px;
            margin-bottom: 2.5rem;
        }

        .product-row .header { font-weight: 600; color: var(--text); }
        .product-row .header-row { display: contents; }
        .product-name { color: var(--ams-navy); font-weight: 600; }

        .quantity-control {
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            background: #fff;
            border-radius: 4px;
            width: fit-content;
            margin: 0 auto;
        }

        .quantity-control button {
            background: transparent;
            border: 0;
            width: 36px;
            height: 36px;
            font-size: 1.2rem;
            color: var(--ams-navy);
            cursor: pointer;
        }

        .quantity-control button:hover:not(:disabled) { background: #f3f4f6; }
        .quantity-control button:disabled { color: #cbd5e1; cursor: not-allowed; }

        .quantity-control input {
            width: 50px;
            text-align: center;
            border: 0;
            border-left: 1px solid var(--border);
            border-right: 1px solid var(--border);
            height: 36px;
            font-size: 1rem;
            -moz-appearance: textfield;
        }

        .quantity-control input::-webkit-outer-spin-button,
        .quantity-control input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .card {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 2rem;
            margin-bottom: 1.5rem;
        }

        .card h2 {
            color: var(--ams-navy);
            font-size: 1.5rem;
            margin: 0 0 1.5rem;
            font-weight: 600;
        }

        .card h3 {
            color: var(--ams-navy);
            font-weight: 700;
            font-size: 0.95rem;
            margin: 0 0 1.5rem;
        }

        .field { margin-bottom: 1.5rem; }

        .field label {
            display: block;
            color: var(--ams-navy);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .field label .required { color: var(--ams-red); }
        .field label .optional { color: var(--ams-navy); font-weight: 400; }

        .field input[type="text"],
        .field input[type="email"],
        .field input[type="tel"] {
            width: 100%;
            border: 0;
            border-bottom: 1px solid #cbd5e1;
            padding: 0.5rem 0;
            font-size: 1rem;
            background: transparent;
            color: var(--text);
        }

        .field input:focus { outline: 0; border-bottom-color: var(--ams-navy); }

        .field input.has-error { border-bottom-color: var(--error); }

        .field-error {
            color: var(--error);
            font-size: 0.85rem;
            margin-top: 0.35rem;
        }

        .student-block {
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px dashed var(--border);
        }

        .student-block:last-child { border-bottom: 0; margin-bottom: 0; }

        .billing-note {
            color: var(--ams-red);
            font-weight: 600;
            margin: -1rem 0 1.5rem;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-top: 1px solid var(--border);
            font-size: 1.05rem;
            margin: 2rem 0;
        }

        .payment-card { text-align: center; }

        /* ---- Inline payment section ---- */

        /* Admin-only method selector. Only rendered when both panes exist. */
        .method-tabs {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.75rem;
            flex-wrap: wrap;
        }

        .method-tab {
            flex: 1 1 180px;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.85rem 1rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            color: var(--ams-navy);
            background: #fff;
            transition: border-color 0.15s ease, background 0.15s ease;
        }

        .method-tab:hover { border-color: var(--ams-navy); }

        .method-tab:has(input:checked) {
            border-color: var(--ams-green);
            background: #f3f9ec;
        }

        .method-tab input { accent-color: var(--ams-green); }

        .method-pane[hidden] { display: none; }

        .pane-note {
            color: var(--muted);
            font-size: 0.875rem;
            margin: 0 0 1.25rem;
        }

        .field .pane-note { margin: 0.5rem 0 0; }

        /* Pin renders each card input as an iframe inside these containers. */
        .pin-field {
            width: 100%;
            height: 44px;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0 0.75rem;
            background: #fff;
            display: flex;
            align-items: center;
        }

        .pin-row { display: flex; gap: 1rem; }
        .pin-row > .field { flex: 1; }

        .field input[type="file"] {
            width: 100%;
            padding: 0.5rem 0;
            font-size: 0.95rem;
        }

        .pin-alert {
            display: none;
            margin-top: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .pin-alert.error {
            background: #fdecea;
            border: 1px solid #f1b0a8;
            color: var(--error);
        }

        .pin-alert.status {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
        }

        .payment-errors {
            background: #fdecea;
            border: 1px solid #f1b0a8;
            border-radius: 6px;
            padding: 0.875rem 1.125rem;
            margin-bottom: 1.5rem;
            color: var(--error);
            font-size: 0.9rem;
        }

        .payment-errors ul { margin: 0; padding-left: 1.125rem; }
        .payment-errors li { margin: 0.125rem 0; }

        .secure-note {
            color: var(--muted);
            font-size: 0.8rem;
            margin: 0.75rem 0 0;
            text-align: center;
        }

        @media (max-width: 540px) {
            .pin-row { flex-direction: column; gap: 0; }
        }

        .actions-row {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
.btn {
    padding: 0.85rem 2rem;
    border: 0;
    border-radius: 30px;
    font-size: 1rem;
    font-weight: 600;
    min-width: 200px;
    text-decoration: none;
    text-align: center;
    display: inline-block;
}
       

        .btn-continue {
            background: var(--ams-green);
            color: #fff;
            cursor: pointer;
        }

        .btn-continue:hover { background: var(--ams-green-hover); }

        .btn-disabled {
            cursor: not-allowed;
            opacity: 0.55;
        }

        .btn-primary { background: var(--ams-green); color: #fff; }
        .btn-secondary { background: #fff; color: var(--ams-navy); border: 1px solid var(--ams-navy); }

        .placeholder-note {
            font-size: 0.85rem;
            color: var(--muted);
            margin-top: 1rem;
        }

        .flash {
            padding: 0.85rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.95rem;
            display: none;
        }

        .flash.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .flash.visible { display: block; }

        .alert-success {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
            padding: 1rem 1.25rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 1rem 1.25rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .alert-error ul { margin: 0.5rem 0 0; padding-left: 1.25rem; }

        @media (max-width: 720px) {
            .product-row { grid-template-columns: 1fr 1fr; }
            .product-row .header-row { display: none; }
            .product-row > div::before {
                content: attr(data-label);
                display: block;
                font-size: 0.8rem;
                color: var(--muted);
                margin-bottom: 0.25rem;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h1 class="page-title">Registration Page</h1>

    <div class="intro">
        <p>Please ensure you have checked all entry requirements on the relevant course page prior to completing your registration below.</p>
        <p>Please complete the registration details below and make your payment.<br>
        Once payment is confirmed you will receive a receipt of payment and a link to complete your online enrolment.</p>
        <p>To reduce delays ensure you provide your entry requirements for online courses.<br>
        For online courses login information is provided once entry requirements have been met.</p>
    </div>

    <hr class="divider">

    <p class="company-note">
        For Companies: If you increase the quantity amount, the system will allow you to add multiple students on one invoice.
    </p>

    @if (session('status'))
        <div class="alert-success" role="status">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert-error" role="alert">
            <strong>Please correct the following:</strong>
            <ul>
                @foreach ($errors->unique() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="flash" class="flash error" role="alert"></div>

    <!-- Product / quantity row -->
    <div class="product-row" id="productRow">
        <div class="header-row">
            <div class="header">Product</div>
            <div class="header" style="text-align:center;">Price</div>
            <div class="header" style="text-align:center;">Quantity</div>
            <div class="header" style="text-align:right;">Subtotal</div>
        </div>

        <div class="product-name" data-label="Product">
            {{ $session->course_code }} {{ $session->course_title }}
        </div>

        <div style="text-align:center;" data-label="Price">
            ${{ number_format((float) $session->unit_price, 2) }}
        </div>

        <div data-label="Quantity">
            <div class="quantity-control">
                <button type="button" id="qtyDec" aria-label="Decrease quantity">−</button>
                <input
                    type="number"
                    id="qtyInput"
                    value="{{ $session->quantity }}"
                    min="1"
                    max="50"
                    inputmode="numeric"
                    aria-label="Quantity"
                >
                <button type="button" id="qtyInc" aria-label="Increase quantity">+</button>
            </div>
        </div>

        <div style="text-align:right;" data-label="Subtotal" id="rowSubtotal">
            ${{ number_format((float) $session->subtotal, 2) }}
        </div>
    </div>

    {{--
        Which payment methods this visitor may use. This is the SAME rule set
        the endpoints enforce (CardChargeRequest / SubmitPurchaseOrderRequest
        authorize()), evaluated server-side: a section the viewer is not
        entitled to is never rendered, not merely hidden.

          guest / normal customer -> card only
          purchase-order client   -> purchase order only
          admin                   -> both, with an inline selector
    --}}
    @php
        $paymentUser = auth()->user();
        $canPayCard  = $paymentUser === null || $paymentUser->canPayByCard();
        $canPayPo    = $paymentUser !== null && $paymentUser->canPayByPurchaseOrder();
        $bothMethods = $canPayCard && $canPayPo;

        $cardAction = route('checkout.card-payment.store', $session);
        $poAction   = route('checkout.purchase-order.store', $session);
    @endphp

    <!-- Form: students + billing + payment -->
    <form id="checkoutForm" method="POST" enctype="multipart/form-data"
          action="{{ $canPayCard ? $cardAction : $poAction }}">
        @csrf

        <!-- Student details (dynamic) -->
        <div class="card" id="studentsCard">
            <h2>Student details</h2>
            <div id="studentsContainer">
                {{-- Rendered by JavaScript on load and on quantity change --}}
            </div>
        </div>

        <!-- Billing details -->
        <div class="card">
            <h2>Billing Details</h2>
            <p class="billing-note">(The following information is required for your Tax invoice)</p>

            @php
                $billing = old('billing', $session->billing_details ?? []);
                $billingField = function (string $key) use ($billing) {
                    return $billing[$key] ?? '';
                };
            @endphp

            <div class="field">
                <label>First Name <span class="required">(Required *)</span></label>
                <input type="text" name="billing[first_name]"
                       value="{{ $billingField('first_name') }}"
                       class="@error('billing.first_name') has-error @enderror"
                       required>
                @error('billing.first_name')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Last Name <span class="required">(Required *)</span></label>
                <input type="text" name="billing[last_name]"
                       value="{{ $billingField('last_name') }}"
                       class="@error('billing.last_name') has-error @enderror"
                       required>
                @error('billing.last_name')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Company/Business Name <span class="optional">(optional)</span></label>
                <input type="text" name="billing[company]" value="{{ $billingField('company') }}">
                @error('billing.company')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Street Address 1 <span class="optional">(optional)</span></label>
                <input type="text" name="billing[address_1]" value="{{ $billingField('address_1') }}">
                @error('billing.address_1')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Street Address 2 <span class="optional">(optional)</span></label>
                <input type="text" name="billing[address_2]" value="{{ $billingField('address_2') }}">
                @error('billing.address_2')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>City <span class="optional">(optional)</span></label>
                <input type="text" name="billing[city]" value="{{ $billingField('city') }}">
                @error('billing.city')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Postcode <span class="optional">(optional)</span></label>
                <input type="text" name="billing[postcode]" value="{{ $billingField('postcode') }}">
                @error('billing.postcode')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Phone <span class="optional">(optional)</span></label>
                <input type="tel" name="billing[phone]" value="{{ $billingField('phone') }}">
                @error('billing.phone')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Email <span class="required">(Required *)</span></label>
                <input type="email" name="billing[email]"
                       value="{{ $billingField('email') }}"
                       class="@error('billing.email') has-error @enderror"
                       required>
                @error('billing.email')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>ABN <span class="optional">(optional)</span></label>
                <input type="text" name="billing[abn]" value="{{ $billingField('abn') }}">
                @error('billing.abn')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <!-- Totals -->
        <div class="totals-row">
            <span>Subtotal</span>
            <span id="footerSubtotal">${{ number_format((float) $session->subtotal, 2) }}</span>
        </div>

        <!-- Payment -->
        <div class="card" id="paymentCard">
            <h2>Payment</h2>

            @if ($errors->any())
                <div class="payment-errors" role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($bothMethods)
                {{-- Admins are the only users entitled to both methods. --}}
                <div class="method-tabs" id="methodTabs">
                    <label class="method-tab">
                        <input type="radio" name="payment_method_choice" value="card" checked>
                        <span>Pay by card</span>
                    </label>
                    <label class="method-tab">
                        <input type="radio" name="payment_method_choice" value="purchase_order">
                        <span>Purchase Order</span>
                    </label>
                </div>
            @endif

            @if ($canPayCard)
                {{--
                    Card number / expiry / CVC / name are entered into
                    Pin-controlled iframes mounted in these containers, so raw
                    card data never enters this page's DOM or our server. Only
                    the publishable key is exposed; the secret key is never
                    rendered. The opaque card_token below is the only card
                    value posted to Laravel.
                --}}
                <div
                    class="method-pane"
                    id="cardPane"
                    data-method="card"
                    data-action="{{ $cardAction }}"
                    data-publishable-key="{{ config('services.pin.publishable_key') }}"
                    data-sandbox="{{ config('services.pin.sandbox') ? 'true' : 'false' }}"
                >
                    <p class="pane-note">Pay using credit card. Your payment is processed securely by Pin Payments.</p>

                    <div class="field">
                        <label>Name on card <span class="required">(Required *)</span></label>
                        <div id="pin-card-name" class="pin-field"></div>
                    </div>

                    <div class="field">
                        <label>Card number <span class="required">(Required *)</span></label>
                        <div id="pin-card-number" class="pin-field"></div>
                    </div>

                    <div class="pin-row">
                        <div class="field">
                            <label>Expiry <span class="required">(Required *)</span></label>
                            <div id="pin-card-expiry" class="pin-field"></div>
                        </div>
                        <div class="field">
                            <label>CVC <span class="required">(Required *)</span></label>
                            <div id="pin-card-cvc" class="pin-field"></div>
                        </div>
                    </div>

                    <input type="hidden" name="card_token" id="pin-card-token" disabled>

                    <button id="pin-pay-button" type="button" class="btn btn-continue" disabled>
                        Preparing secure form…
                    </button>

                    <div id="pin-error" class="pin-alert error" role="alert"></div>
                    <div id="pin-status" class="pin-alert status" role="status"></div>

                    <p class="secure-note">
                        Your card details are entered directly into Pin Payments' secure fields.
                    </p>
                </div>
            @endif

            @if ($canPayPo)
                <div
                    class="method-pane"
                    id="poPane"
                    data-method="purchase_order"
                    data-action="{{ $poAction }}"
                    @if ($bothMethods) hidden @endif
                >
                    <p class="pane-note">Pay by purchase order. Your invoice will be issued on your account terms.</p>

                    <div class="field">
                        <label>Purchase Order Number <span class="required">(Required *)</span></label>
                        <input type="text" name="purchase_order_number"
                               value="{{ old('purchase_order_number') }}"
                               class="@error('purchase_order_number') has-error @enderror"
                               required>
                        @error('purchase_order_number')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label>Purchase Order Document <span class="required">(Required *)</span></label>
                        <input type="file" name="purchase_order_document"
                               accept=".pdf,.jpg,.jpeg,.png"
                               class="@error('purchase_order_document') has-error @enderror"
                               required>
                        <p class="pane-note">PDF, JPG or PNG, up to 25 MB.</p>
                        @error('purchase_order_document')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    <button type="submit" class="btn btn-continue">Submit Purchase Order</button>
                </div>
            @endif
        </div>

    </form>
</div>

<script>
(function () {
    const csrfToken    = document.querySelector('meta[name="csrf-token"]').content;
    const updateUrl    = @json(route('checkout.quantity.update', $session));
    const minQty       = 1;
    const maxQty       = 50;

    // Server-side data carried into the page for prefill
    const savedStudents = @json(old('students', $session->student_details ?? []));
    const studentErrors = @json($errors->getMessages());

    const qtyInput    = document.getElementById('qtyInput');
    const qtyInc      = document.getElementById('qtyInc');
    const qtyDec      = document.getElementById('qtyDec');
    const rowSubtotal = document.getElementById('rowSubtotal');
    const footerSub   = document.getElementById('footerSubtotal');
    const container   = document.getElementById('studentsContainer');
    const flash       = document.getElementById('flash');

    let currentQty = parseInt(qtyInput.value, 10) || 1;
    let inFlight   = false;

    function clampQty(value) {
        const n = parseInt(value, 10);
        if (isNaN(n)) return minQty;
        if (n < minQty) return minQty;
        if (n > maxQty) return maxQty;
        return n;
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function errorFor(index, field) {
        const key = `students.${index}.${field}`;
        if (studentErrors && studentErrors[key] && studentErrors[key].length) {
            return studentErrors[key][0];
        }
        return null;
    }

    function studentBlockHtml(index, prefill) {
        const num = index + 1;
        const v = prefill || {};

        const fields = [
            { name: 'first_name', label: 'Student First Name', type: 'text',  required: true,  placeholder: '' },
            { name: 'last_name',  label: 'Student Last Name',  type: 'text',  required: true,  placeholder: '' },
            { name: 'email',      label: 'Student Email',      type: 'email', required: true,  placeholder: '' },
            { name: 'phone',      label: 'Student Phone Number', type: 'tel', required: true,  placeholder: '04XXXXXXXX' },
        ];

        let inner = '';
        fields.forEach(f => {
            const value = escapeHtml(v[f.name] ?? '');
            const err   = errorFor(index, f.name);
            const errCls = err ? ' has-error' : '';
            const errHtml = err ? `<div class="field-error">${escapeHtml(err)}</div>` : '';
            const ph = f.placeholder ? ` placeholder="${escapeHtml(f.placeholder)}"` : '';
            inner += `
                <div class="field">
                    <label>${f.label} <span class="required">(Required *)</span></label>
                    <input type="${f.type}" name="students[${index}][${f.name}]" value="${value}"${ph} class="${errCls.trim()}" required>
                    ${errHtml}
                </div>`;
        });

        return `
        <div class="student-block" data-student-index="${index}">
            <h3>Student Number : ${num}</h3>
            ${inner}
        </div>`;
    }

    function readCurrentValues() {
        const out = [];
        container.querySelectorAll('.student-block').forEach(block => {
            const idx = parseInt(block.dataset.studentIndex, 10);
            const row = {};
            block.querySelectorAll('input').forEach(input => {
                // input.name is like students[0][first_name] — extract the field name
                const match = input.name.match(/\[(\w+)\]$/);
                if (match) row[match[1]] = input.value;
            });
            out[idx] = row;
        });
        return out;
    }

    function renderStudentBlocks(qty, initial = false) {
        // On initial render, seed from server-side savedStudents.
        // On subsequent renders, preserve what's currently typed in the DOM.
        const source = initial ? (Array.isArray(savedStudents) ? savedStudents : []) : readCurrentValues();

        let html = '';
        for (let i = 0; i < qty; i++) {
            html += studentBlockHtml(i, source[i]);
        }
        container.innerHTML = html;
    }

    function updateButtonStates(qty) {
        qtyDec.disabled = qty <= minQty;
        qtyInc.disabled = qty >= maxQty;
    }

    function showError(message) {
        flash.textContent = message;
        flash.classList.add('visible');
    }

    function clearError() {
        flash.textContent = '';
        flash.classList.remove('visible');
    }

    async function pushQuantity(newQty) {
        if (inFlight) return;
        if (newQty === currentQty) return;

        inFlight = true;
        clearError();

        try {
            const response = await fetch(updateUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ quantity: newQty }),
            });

            if (!response.ok) {
                let message = 'Could not update quantity. Please try again.';
                try {
                    const data = await response.json();
                    if (data && data.message) message = data.message;
                    if (data && data.errors && data.errors.quantity) {
                        message = data.errors.quantity[0];
                    }
                } catch (_) {}
                qtyInput.value = currentQty;
                updateButtonStates(currentQty);
                showError(message);
                return;
            }

            const data = await response.json();
            currentQty = data.quantity;
            qtyInput.value = currentQty;
            rowSubtotal.textContent = data.formatted_subtotal;
            footerSub.textContent  = data.formatted_subtotal;
            renderStudentBlocks(currentQty);
            updateButtonStates(currentQty);
        } catch (err) {
            qtyInput.value = currentQty;
            updateButtonStates(currentQty);
            showError('Network error. Please check your connection and try again.');
        } finally {
            inFlight = false;
        }
    }

    qtyInc.addEventListener('click', () => {
        const next = clampQty(currentQty + 1);
        pushQuantity(next);
    });

    qtyDec.addEventListener('click', () => {
        const next = clampQty(currentQty - 1);
        pushQuantity(next);
    });

    let typingTimer;
    qtyInput.addEventListener('input', () => {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
            const next = clampQty(qtyInput.value);
            qtyInput.value = next;
            pushQuantity(next);
        }, 400);
    });

    qtyInput.addEventListener('blur', () => {
        const next = clampQty(qtyInput.value);
        if (next !== parseInt(qtyInput.value, 10)) {
            qtyInput.value = next;
        }
        pushQuantity(next);
    });

    // Initial render — seed from server (old() or saved details)
    renderStudentBlocks(currentQty, true);
    updateButtonStates(currentQty);
})();
</script>

<script>
/*
 | Inline payment method switching.
 |
 | Only panes the server rendered can be selected, and the endpoints re-check
 | entitlement on every POST, so this is presentation only — it decides which
 | of the user's permitted forms is submitted, never whether they are allowed.
 |
 | Inputs in the inactive pane are disabled so they are neither submitted nor
 | blocking HTML5 validation on a hidden required field.
 */
(function () {
    const form  = document.getElementById('checkoutForm');
    const panes = Array.from(document.querySelectorAll('.method-pane'));

    if (!form || panes.length === 0) {
        return;
    }

    function activate(method) {
        panes.forEach(pane => {
            const isActive = pane.dataset.method === method;

            pane.hidden = !isActive;
            pane.querySelectorAll('input, select, textarea, button').forEach(el => {
                // The pay button stays disabled until Pin's hosted fields
                // report ready, so its enabled state belongs to the card
                // script alone. Disabling on deactivate is still correct.
                if (el.id === 'pin-pay-button' && isActive) {
                    return;
                }

                el.disabled = !isActive;
            });

            if (isActive) {
                form.setAttribute('action', pane.dataset.action);
            }
        });

        // The card token is populated only at tokenisation time.
        const token = document.getElementById('pin-card-token');
        if (token) {
            token.disabled = true;
        }

        document.dispatchEvent(new CustomEvent('checkout:method-changed', {
            detail: { method: method },
        }));
    }

    document.querySelectorAll('#methodTabs input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', () => activate(radio.value));
    });

    /*
     | A card payment is only valid once Pin has returned a token, so an Enter
     | keypress in a text field must not post the form half-finished. Route it
     | through the pay button instead. The card script submits the form
     | programmatically, which does not fire this event.
     */
    form.addEventListener('submit', function (event) {
        const active = panes.find(pane => !pane.hidden);
        const token = document.getElementById('pin-card-token');

        if (!active || active.dataset.method !== 'card') {
            return;
        }

        if (!token || token.disabled || token.value === '') {
            event.preventDefault();

            const payButton = document.getElementById('pin-pay-button');
            if (payButton && !payButton.disabled) {
                payButton.click();
            }
        }
    });

    // A single-method user has exactly one pane; activating it wires the action
    // and clears the disabled state in one place.
    const initial = panes.find(pane => !pane.hidden) || panes[0];
    activate(initial.dataset.method);
})();
</script>

@if ($canPayCard)
    <script src="{{ asset('js/checkout-card-payment.js') }}"></script>
@endif
</body>
</html>