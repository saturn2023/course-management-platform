<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card payment — AMS Training</title>

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

        a {
            display: inline-block;
            margin-top: 1.5rem;
            padding: 0.8rem 1.4rem;
            background: #2e9e5b;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Card payment</h1>

        <p>
            Your student and billing details have been saved successfully.
            Card payment will be enabled in the next development phase.
        </p>

        <p>
            Total: <strong>${{ number_format((float) $session->subtotal, 2) }}</strong>
        </p>

        <a href="{{ route('checkout.show', $session) }}">
            Return to checkout
        </a>
    </div>
</body>
</html>