{{--
    Public About page — /about

    Content comes from App\Models\AboutPageSetting (Admin → About Page).
    Structure follows the live amstraining.com.au/about/ page — hero, About
    content, green Trainers band, grey trainer rows, Mission statement,
    reviews — restyled with this site's fonts and palette (normalidad-compact
    headings, Outfit body, #39B54A green) rather than the WordPress korolev /
    navy theme.

    Reuses the existing site components verbatim:
        <x-layout.header />   the AMS header, unmodified
        <x-home.reviews />    the homepage reviews carousel (toggle in admin)
        <x-layout.footer />   the AMS footer

    About content, Trainers and Mission statement have no default copy: when
    their content is empty the whole section (heading included) is hidden.

    Rich text (content body, trainer bios) is RichEditor HTML from the admin,
    printed raw the same way Course::$overview is.

    This project has no shared HTML layout: every page renders its own document
    and includes the font links in <head>. Kept consistent with
    resources/views/courses/show.blade.php.
--}}
@php
    $contentBody = $settings->contentBody();
    $trainers = $settings->trainerList();
    $missionPoints = $settings->missionPointList();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $settings->metaTitle() }}</title>
    <meta name="description" content="{{ $settings->metaDescription() }}">

    <x-layout.fonts />

    {{-- Outfit (body) — same weights the homepage loads. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- normalidad-* and azo-sans-web (Adobe Fonts, project sat8ost) — used by
         the header, headings, reviews and footer. --}}
    <link rel="stylesheet" href="https://use.typekit.net/sat8ost.css">

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #FFFFFF;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #333;
        }

        /* Scoped to .ams-about so nothing here reaches the header, footer or
           reviews components. */
        .ams-about {
            --ams-green: #39B54A;
            --ams-dark: #2C2C2C;
            --ams-body: #7a7a7a;
            /* The live page's trainer-row grey. */
            --ams-panel: #E9E6E9;
            --font-heading: 'normalidad-compact', 'Segoe UI', Arial, sans-serif;
            --font-display: 'normalidad-wide', 'Segoe UI', Arial, sans-serif;
            --font-body: 'Outfit', 'Segoe UI', Arial, sans-serif;
        }

        .ams-about * {
            box-sizing: border-box;
        }

        /* 1500px — the live page's content width. */
        .ams-about__inner {
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
            padding: 0 30px;
        }

        /* ---------- HERO ----------
         * A real <img> (not a CSS background) so the alt text is announced.
         * object-fit gives the same cover crop. The worker in the default
         * photo stands ~67% across, so narrower screens keep him in frame
         * via object-position instead of cropping to the empty centre. */
        .ams-about-hero {
            position: relative;
            display: flex;
            align-items: center;
            min-height: clamp(280px, 34vw, 645px);
            overflow: hidden;
            background: var(--ams-dark);
        }

        .ams-about-hero__img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
        }

        /* Live page: #00000066 flat overlay. Slightly stronger here so the
           white title clears contrast even over the bright sky. */
        .ams-about-hero__overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
        }

        .ams-about-hero .ams-about__inner {
            position: relative;
            z-index: 1;
        }

        .ams-about-hero__title {
            margin: 0;
            color: #FFFFFF;
            font-family: var(--font-display);
            font-size: 79px;
            font-weight: 700;
            line-height: 1.1;
            overflow-wrap: anywhere;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.35);
        }

        /* ---------- SECTION HEADINGS (same treatment as the homepage intro) */
        .ams-about__heading {
            margin: 0 0 28px;
            color: var(--ams-green);
            font-family: var(--font-heading);
            font-size: 34px;
            font-weight: 600;
            line-height: 1.3;
            text-transform: uppercase;
        }

        /* ---------- RICH TEXT (admin editor output) */
        .ams-about__rich {
            color: var(--ams-body);
            font-family: var(--font-body);
            font-size: 20px;
            font-weight: 300;
            line-height: 1.5;
        }

        .ams-about__rich > :first-child { margin-top: 0; }
        .ams-about__rich > :last-child { margin-bottom: 0; }
        .ams-about__rich p { margin: 0 0 16px; }
        .ams-about__rich strong,
        .ams-about__rich b { font-weight: 600; }
        .ams-about__rich a { color: var(--ams-green); }
        .ams-about__rich ul,
        .ams-about__rich ol { margin: 0 0 16px; padding-left: 1.4em; }
        .ams-about__rich h2,
        .ams-about__rich h3 {
            margin: 24px 0 12px;
            color: var(--ams-dark);
            font-family: var(--font-heading);
            font-weight: 600;
            line-height: 1.3;
        }

        /* ---------- ABOUT CONTENT */
        .ams-about-content {
            padding: 80px 0;
            text-align: center;
        }

        .ams-about-content .ams-about__rich {
            max-width: 1100px;
            margin: 0 auto;
        }

        /* ---------- TRAINERS */
        .ams-about-band {
            padding: 14px 0;
            background: var(--ams-green);
        }

        .ams-about-band__title {
            margin: 0;
            color: #FFFFFF;
            font-family: var(--font-heading);
            font-size: 30px;
            font-weight: 600;
            line-height: 1.3;
            text-align: center;
            text-transform: uppercase;
        }

        .ams-about-trainers {
            padding: 30px 0 50px;
            background: var(--ams-panel);
        }

        .ams-about-trainer {
            display: grid;
            grid-template-columns: 29% 1fr;
            align-items: center;
            gap: 50px;
            padding: 50px 0;
        }

        .ams-about-trainer + .ams-about-trainer {
            border-top: 2px solid rgba(0, 0, 0, 0.15);
        }

        .ams-about-trainer__photo {
            display: block;
            width: 100%;
            max-width: 300px;
            height: auto;
            margin: 0 auto;
        }

        .ams-about-trainer__name {
            margin: 0 0 14px;
            color: var(--ams-dark);
            font-family: var(--font-heading);
            font-size: 30px;
            font-weight: 600;
            line-height: 1.3;
            text-transform: uppercase;
        }

        .ams-about-trainer .ams-about__rich {
            color: var(--ams-dark);
            font-size: 18px;
        }

        /* ---------- MISSION STATEMENT */
        .ams-about-mission {
            padding: 80px 0;
        }

        .ams-about-mission__list {
            margin: 0;
            padding: 0;
            list-style: none;
            color: var(--ams-dark);
            font-family: var(--font-body);
            font-size: 18px;
            font-weight: 300;
            line-height: 1.5;
        }

        .ams-about-mission__list li {
            position: relative;
            margin: 0 0 12px;
            padding-left: 28px;
        }

        .ams-about-mission__list li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.55em;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--ams-green);
        }

        /* ---------- RESPONSIVE (live page steps: 1200 / 1024 / 767) */
        @media (max-width: 1200px) {
            .ams-about-hero__title {
                font-size: 64px;
                text-align: center;
            }
        }

        @media (max-width: 1024px) {
            .ams-about-trainer {
                gap: 30px;
            }
        }

        /* Trainers stack at 767px, image on top, as on the live page. */
        @media (max-width: 767px) {
            .ams-about__inner {
                padding: 0 20px;
            }

            .ams-about-hero {
                min-height: 260px;
            }

            .ams-about-hero__img {
                object-position: 67% center;
            }

            .ams-about-hero__title {
                font-size: 44px;
            }

            .ams-about-content,
            .ams-about-mission {
                padding: 48px 0;
            }

            .ams-about__heading {
                font-size: 26px;
                margin-bottom: 20px;
            }

            .ams-about__rich {
                font-size: 18px;
            }

            .ams-about-band__title {
                font-size: 24px;
            }

            .ams-about-trainers {
                padding: 10px 0 20px;
            }

            .ams-about-trainer {
                grid-template-columns: 1fr;
                gap: 24px;
                padding: 36px 0;
                text-align: center;
            }

            .ams-about-trainer__photo {
                max-width: 220px;
            }

            .ams-about-trainer__name {
                font-size: 24px;
            }

            .ams-about-trainer .ams-about__rich {
                font-size: 17px;
            }

            .ams-about-mission__list {
                font-size: 17px;
            }
        }
    </style>
</head>
<body>
    <x-layout.header />

    <main class="ams-about">
        <section class="ams-about-hero">
            <img
                class="ams-about-hero__img"
                src="{{ $settings->heroImageUrl() }}"
                alt="{{ $settings->heroImageAlt() }}"
                fetchpriority="high"
            >
            <div class="ams-about-hero__overlay" aria-hidden="true"></div>
            <div class="ams-about__inner">
                <h1 class="ams-about-hero__title">{{ $settings->heroTitle() }}</h1>
            </div>
        </section>

        @if ($contentBody)
            <section class="ams-about-content" aria-labelledby="ams-about-content-heading">
                <div class="ams-about__inner">
                    <h2 class="ams-about__heading" id="ams-about-content-heading">{{ $settings->contentHeading() }}</h2>
                    <div class="ams-about__rich">{!! $contentBody !!}</div>
                </div>
            </section>
        @endif

        @if ($trainers->isNotEmpty())
            <section aria-labelledby="ams-about-trainers-heading">
                <div class="ams-about-band">
                    <div class="ams-about__inner">
                        <h2 class="ams-about-band__title" id="ams-about-trainers-heading">{{ $settings->trainersHeading() }}</h2>
                    </div>
                </div>

                <div class="ams-about-trainers">
                    <div class="ams-about__inner">
                        @foreach ($trainers as $trainer)
                            <article class="ams-about-trainer">
                                <img
                                    class="ams-about-trainer__photo"
                                    src="{{ $trainer['photoUrl'] }}"
                                    alt="{{ $trainer['photoAlt'] }}"
                                    loading="lazy"
                                >
                                <div>
                                    <h3 class="ams-about-trainer__name">{{ $trainer['name'] }}</h3>
                                    @if ($trainer['bio'])
                                        <div class="ams-about__rich">{!! $trainer['bio'] !!}</div>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($missionPoints->isNotEmpty())
            <section class="ams-about-mission" aria-labelledby="ams-about-mission-heading">
                <div class="ams-about__inner">
                    <h2 class="ams-about__heading" id="ams-about-mission-heading">{{ $settings->missionHeading() }}</h2>
                    <ul class="ams-about-mission__list">
                        @foreach ($missionPoints as $point)
                            <li>{{ $point }}</li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
    </main>

    @if ($settings->showReviews())
        <x-home.reviews :reviews="$reviews" />
    @endif

    <x-layout.footer />
</body>
</html>
