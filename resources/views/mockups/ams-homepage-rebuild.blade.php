{{--
    Standalone page for rebuilding the AMS Training homepage/header design.

    Isolated from the live homepage — starts as a single empty Flexbox
    container. Content (logo, menu, search, etc.) will be added later.
    Temporary — for development/review only.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMS Homepage Rebuild</title>
    <x-layout.fonts />
    <style>
        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        .ams-header {
            display: flex;
            align-items: center;
            width: 100%;
            max-width: 1950px;
            padding: 2%;
            background-color: #2C2C2C;
            margin: 0 auto;
            box-sizing: border-box;
        }

        .ams-header__logo {
            width: 225px;
            flex-shrink: 0;
        }

        .ams-header__logo img {
            width: 60%;
            height: auto;
            display: block;
            object-fit: contain;
        }
    </style>
</head>
<body>
    <div class="ams-header">
        <div class="ams-header__logo">
            <img src="{{ asset('images/ams-logo.webp') }}" alt="AMS Training">
        </div>
        {{-- Hamburger menu, search field, and navigation will be added later. --}}
    </div>
</body>
</html>
