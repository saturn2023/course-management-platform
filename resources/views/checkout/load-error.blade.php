<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enrolment unavailable</title>

    <style>
        body {
            font-family: Arial, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f4f4f4;
            color: #223A74;
            margin: 0;
            padding: 2rem;
        }

        .card {
            max-width: 620px;
            margin: 5rem auto;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #dddddd;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            padding: 2.5rem;
            text-align: center;
        }

        .icon {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #d9534f;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: bold;
            margin-bottom: 1.25rem;
        }

        h1 {
            font-size: 1.6rem;
            margin: 0 0 1rem;
            color: #223A74;
        }

        p {
            line-height: 1.6;
            color: #555555;
            margin: 0;
        }

        .button {
            display: inline-block;
            margin-top: 1.75rem;
            padding: 0.8rem 1.3rem;
            background: #87c65a;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 700;
        }

        .button:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">!</div>

        <h1>We couldn't start your enrolment</h1>

        <p>{{ $message }}</p>

        <a class="button" href="{{ url('/courses') }}">Return to courses</a>
    </div>
</body>
</html>