{{--
    A standalone outcome page for the v2 enrolment form.

    Port of ams2_page_message() and the already-submitted box. The reference
    renders these inside the WordPress page the shortcode sits on; here they are
    a page of their own, styled to match enrolments.completed and
    enrolments.failed.

    $kind         'ok' (green) or 'bad' (red)
    $heading      short title
    $message      the sentence the learner reads
    $reference    optional AMS2-xxxxxx to quote back
    $documentHelp whether to tell them where to email a document, which only
                  makes sense once their enrolment is actually recorded
--}}
@php
    $reference ??= null;
    $documentHelp ??= false;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $heading }}</title>
    <x-layout.fonts />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            color: #223A74;
            padding: 40px 20px;
        }

        .page {
            max-width: 720px;
            margin: 80px auto;
            background: #ffffff;
            border: 1px solid #dddddd;
            border-radius: 8px;
            padding: 36px;
            text-align: center;
        }

        .icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            margin-bottom: 20px;
        }

        .icon--ok { background: #2f855a; }
        .icon--bad { background: #d9534f; }

        h1 {
            margin: 0 0 12px;
            font-size: 30px;
            color: #223A74;
        }

        p {
            font-size: 16px;
            line-height: 1.6;
            color: #555555;
        }

        .reference {
            font-weight: bold;
            color: #223A74;
        }

        .support {
            margin-top: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="icon icon--{{ $kind }}">{{ $kind === 'ok' ? '✓' : '!' }}</div>

        <h1>{{ $heading }}</h1>

        <p>{{ $message }}</p>

        @if (! empty($reference))
            <p class="reference">Reference {{ $reference }}</p>
        @endif

        @if ($documentHelp)
            <p class="support">
                If you still need to send a document, email it to
                <a href="mailto:sales@amstraining.com.au">sales@amstraining.com.au</a>.
            </p>
        @endif
    </div>
</body>
</html>
