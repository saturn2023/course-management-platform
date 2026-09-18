{{--
    Standalone mockup for the PACKAGE ONLINE REFRESHER COURSES block, powered by
    real CoursePackage records. Renders only the component on a neutral page.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mockup — Package Online Refresher Courses</title>
    <x-layout.fonts />

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
    <x-home.package-refresher-courses :packages="$packages" />
</body>
</html>
