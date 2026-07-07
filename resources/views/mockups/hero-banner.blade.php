{{--
    Standalone mockup for the hero banner, shown directly below the site header
    (its intended position) plus dummy content to test scrolling / sticky header.
    Does NOT include the current homepage.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mockup — Hero Banner</title>

    {{-- Intro section paragraph font: Outfit (Google Fonts) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">

    {{-- Intro heading font: Normalidad Compact (Adobe Fonts / Typekit, project sat8ost) --}}
    <link rel="stylesheet" href="https://use.typekit.net/sat8ost.css">

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

        .mockup-body h2 {
            color: #1b2a4a;
        }

        .mockup-body p {
            line-height: 1.6;
            color: #55606e;
            max-width: 720px;
        }

        .mockup-spacer {
            height: 120vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b3bcc7;
            border-top: 1px dashed #d6dce3;
        }
    </style>
</head>
<body>
    <x-layout.header />

    <x-home.hero-banner />

    <x-home.intro-text />

    <main class="mockup-body">
        <div class="mockup-spacer">Keep scrolling to test the sticky header…</div>
    </main>
</body>
</html>
