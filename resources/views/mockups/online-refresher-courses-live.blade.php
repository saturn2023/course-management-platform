{{--
    Standalone mockup for client review of the SINGLE ONLINE REFRESHER COURSES
    block, powered by real Course records from the database.

    This page renders ONLY the reusable component on a neutral background.
    It intentionally does NOT include the current homepage and is not wired
    into the final homepage yet.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mockup — Single Online Refresher Courses</title>

    {{-- Body font: Outfit (Google Fonts) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

    {{-- Heading/banner/button font: Normalidad Compact (Adobe Fonts, project sat8ost) --}}
    <link rel="stylesheet" href="https://use.typekit.net/sat8ost.css">

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #F5F5F5;
        }
    </style>
</head>
<body>
    <x-home.online-refresher-courses :courses="$courses" />
</body>
</html>
