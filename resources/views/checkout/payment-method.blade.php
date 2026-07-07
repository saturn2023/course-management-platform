<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose payment method — AMS Training</title>

    <style>
        body {
            margin: 0;
            padding: 2rem;
            background: #f4f6f9;
            color: #34404f;
            font-family: Arial, sans-serif;
        }

        .card {
            max-width: 620px;
            margin: 5rem auto;
            padding: 2.5rem;
            background: white;
            border: 1px solid #dfe4ec;
            border-radius: 8px;
            text-align: center;
        }

        h1 {
            color: #14223f;
        }

        .choices {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.75rem;
            flex-wrap: wrap;
        }

        a.choice {
            display: inline-block;
            padding: 0.9rem 1.6rem;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 700;
        }

        a.card-choice { background: #2e9e5b; }
        a.po-choice { background: #14223f; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Choose payment method</h1>

        <p>
            Total: <strong>${{ number_format((float) $session->subtotal, 2) }}</strong>
        </p>

        <div class="choices">
            <a class="choice card-choice" href="{{ route('checkout.card-payment.show', $session) }}">
                Pay by card
            </a>

            <a class="choice po-choice" href="{{ route('checkout.purchase-order.show', $session) }}">
                Purchase Order
            </a>
        </div>
    </div>
</body>
</html>
