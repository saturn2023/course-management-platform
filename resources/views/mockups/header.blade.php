{{--
    Standalone mockup for reviewing the AMS site header on its own.

    Renders ONLY the header component plus neutral dummy content so sticky
    scroll behaviour can be tested. Does NOT include the current homepage.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mockup — Site Header</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #f4f6f8;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #333;
        }

        .mockup-body {
            max-width: 1100px;
            margin: 0 auto;
            padding: 48px 24px;
        }

        .mockup-body h1 {
            color: #1b2a4a;
        }

        .mockup-body p {
            line-height: 1.6;
            color: #55606e;
            max-width: 720px;
        }

        .mockup-spacer {
            height: 140vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b3bcc7;
            font-size: 1.1rem;
            border-top: 1px dashed #d6dce3;
        }
    </style>
</head>
<body>
    <x-layout.header />

    <main class="mockup-body">
        <h1>Header sticky-scroll test page</h1>
        <p>
            This is a neutral mockup page for reviewing the AMS site header in isolation.
            Scroll down — the header should stay pinned to the top of the viewport.
        </p>
        <div class="mockup-spacer">Keep scrolling…</div>
        <div class="mockup-spacer">Header should still be pinned above.</div>
        <div class="mockup-spacer">End of dummy content.</div>
    </main>
</body>
</html>
