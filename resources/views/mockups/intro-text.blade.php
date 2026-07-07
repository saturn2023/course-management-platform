{{--
    Standalone mockup for the intro text section. Renders only the component on
    a neutral page. Does NOT include the current homepage.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mockup — Intro Text</title>

    {{-- Paragraph font: Outfit (Google Fonts) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">

    {{-- Heading font: Normalidad Compact (Adobe Fonts / Typekit, project sat8ost) --}}
    <link rel="stylesheet" href="https://use.typekit.net/sat8ost.css">
    <link rel="preconnect" href="https://use.typekit.net" crossorigin>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
    </style>
</head>
<body>
    <x-home.intro-text />
</body>
</html>
