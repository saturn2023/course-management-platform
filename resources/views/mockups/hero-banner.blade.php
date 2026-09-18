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
    <x-layout.fonts />

    {{-- Intro section paragraph font: Outfit (Google Fonts) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- 500 (accordion titles) and 700 (enrolment heading) added for
         <x-home.enrolment-process />; the other weights are unchanged. --}}
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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

        /*
         * The .mockup-body / .mockup-spacer dummy-content rules were removed
         * with the markup that used them: a 120vh spacer that existed only to
         * give the sticky header something to scroll against. The real homepage
         * sections now provide far more than a viewport of scroll, and it was
         * sitting after the footer, adding a screen and a bit of empty page
         * below it. Nothing else on this page used those classes.
         */
    </style>
</head>
<body>
    <x-layout.header />

    <x-home.hero-banner />

    <x-home.intro-text />

    <x-home.online-refresher-courses :courses="$courses" />

    <x-home.package-refresher-courses :packages="$packages" />

    <x-home.about-banner />

    <x-home.experience-section />

    <x-home.first-field-section />

    <x-home.enrolment-process :steps="$enrolmentSteps" />

    <x-home.reviews :reviews="$reviews" />

    <x-layout.footer />
</body>
</html>
