{{--
    Public course detail page — /courses/{slug}

    Reuses the existing site components verbatim:
        <x-layout.header />   the AMS header, unmodified
        <x-home.reviews />    the homepage reviews carousel
        <x-layout.footer />   the AMS footer

    Reviews and the footer are deliberately SIBLINGS of <x-course.detail />,
    not children — that is what stops the sticky price card at the end of the
    course content instead of letting it travel over them.

    This project has no shared HTML layout: every page renders its own document
    and includes the font links in <head>. Kept consistent with
    resources/views/mockups/hero-banner.blade.php.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $course->title }}@if ($course->code) — {{ $course->code }}@endif | AMS Training</title>

    <x-layout.fonts />

    {{-- Outfit (body/headings) — same weights the homepage loads. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- normalidad-* and azo-sans-web (Adobe Fonts, project sat8ost) — used by
         the header, reviews heading and footer button. --}}
    <link rel="stylesheet" href="https://use.typekit.net/sat8ost.css">

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #FFFFFF;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #333;
        }
    </style>
</head>
<body>
    <x-layout.header />

    <main>
        <x-course.detail :course="$course" />
    </main>

    <x-home.reviews :reviews="$reviews" />

    <x-layout.footer />
</body>
</html>
