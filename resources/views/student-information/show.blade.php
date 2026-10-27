{{--
    Public Student Information page — /student-information

    Content comes from App\Models\StudentInformationPageSetting (Admin →
    Student Information Page) and the documents list from
    App\Models\StudentDocument (Admin → Student Documents).

    Structure follows the live amstraining.com.au/student-information/ page:
    hero, intro heading + text, documents list. The sections below the list
    (starting with a full-width green band) are not built yet — see the
    marker at the end of <main>.

    Reuses the existing site components verbatim:
        <x-layout.header />   the AMS header, unmodified
        <x-layout.footer />   the AMS footer

    The intro text is plain text from a Textarea, printed with nl2br(e(...))
    so its line breaks survive and no admin-entered markup is rendered.

    This project has no shared HTML layout: every page renders its own document
    and includes the font links in <head>. Kept consistent with
    resources/views/about/show.blade.php.
--}}
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
         the header and footer. --}}
    <link rel="stylesheet" href="https://use.typekit.net/sat8ost.css">

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #FFFFFF;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #333;
        }

        /* Scoped to .ams-student-info so nothing here reaches the header or
           footer components. */
        .ams-student-info {
            --ams-dark: #2C2C2C;
            /* The live site's green and navy (not this site's #39B54A). */
            --ams-live-green: #87C65A;
            --ams-navy: #223A74;
            --font-korolev: 'korolev', 'Segoe UI', Arial, sans-serif;
            --font-korolev-condensed: 'korolev-condensed', 'Segoe UI', Arial, sans-serif;
        }

        .ams-student-info * {
            box-sizing: border-box;
        }

        /* 1500px — the live page's content width. */
        .ams-student-info__inner {
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
            padding: 0 30px;
        }

        /* ---------- HERO
         * TODO: extract to a shared "page hero" component after about-page
         * and student-information-page are merged.
         *
         * Copied from the About page hero (.ams-about-hero) so the two
         * match. A real <img> (not a CSS background) so the alt text is
         * announced; object-fit gives the same cover crop. */
        .ams-student-info-hero {
            position: relative;
            display: flex;
            align-items: center;
            min-height: clamp(280px, 34vw, 645px);
            overflow: hidden;
            background: var(--ams-dark);
        }

        .ams-student-info-hero__img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
        }

        /* Live page: #00000066 flat overlay. Slightly stronger here so the
           green title stays readable over the bright sky. */
        .ams-student-info-hero__overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
        }

        .ams-student-info-hero .ams-student-info__inner {
            position: relative;
            z-index: 1;
        }

        /* Matches the live Elementor heading: korolev-condensed 700, 79px /
           89px, #87C65A. Line height is the same 89/79 ratio as a unitless
           value so it scales with the smaller breakpoint sizes. */
        .ams-student-info-hero__title {
            margin: 0;
            color: var(--ams-live-green);
            font-family: var(--font-korolev-condensed);
            font-size: 79px;
            font-weight: 700;
            line-height: 1.127;
            overflow-wrap: anywhere;
        }

        /* ---------- INTRO + DOCUMENTS */
        .ams-student-info-content {
            padding: 80px 0;
        }

        /* Live Elementor heading: korolev-condensed 600, 52px / 63px,
           #223A74, centred. Capitals come from the text itself — no
           text-transform. The kit has no korolev-condensed 600, so the
           browser draws 700 — same as live and the About page. */
        .ams-student-info__heading {
            margin: 0 0 20px;
            color: var(--ams-navy);
            font-family: var(--font-korolev-condensed);
            font-size: 52px;
            font-weight: 600;
            line-height: 63px;
            text-align: center;
        }

        /* Live text editor: korolev 400, 20px, default line height, #223A74,
           left-aligned. */
        .ams-student-info__intro {
            margin: 0;
            color: var(--ams-navy);
            font-family: var(--font-korolev);
            font-size: 20px;
            font-weight: 400;
            text-align: left;
        }

        .ams-student-info-docs {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin: 40px 0 0;
            padding: 0;
            list-style: none;
        }

        .ams-student-info-docs__link {
            color: var(--ams-live-green);
            font-family: var(--font-korolev-condensed);
            font-size: 28px;
            font-weight: 600;
            line-height: 1.25;
            text-decoration: none;
            overflow-wrap: anywhere;
        }

        .ams-student-info-docs__link:hover,
        .ams-student-info-docs__link:focus-visible {
            text-decoration: underline;
        }

        .ams-student-info-docs__link:focus-visible {
            outline: 2px solid var(--ams-navy);
            outline-offset: 3px;
        }

        /* ---------- RESPONSIVE (hero steps match the About page exactly,
           plus a 480px step About does not have) */
        @media (max-width: 1200px) {
            .ams-student-info-hero__title {
                font-size: 72px;
                text-align: center;
            }
        }

        @media (max-width: 880px) {
            .ams-student-info-hero__title {
                font-size: 55px;
            }
        }

        @media (max-width: 767px) {
            .ams-student-info__inner {
                padding: 0 20px;
            }

            .ams-student-info-hero {
                min-height: 260px;
            }

            .ams-student-info-hero__img {
                object-position: 67% center;
            }

            .ams-student-info-content {
                padding: 48px 0;
            }

            .ams-student-info__heading {
                font-size: 36px;
                line-height: 1.2;
            }

            .ams-student-info-docs {
                margin-top: 32px;
            }

            .ams-student-info-docs__link {
                font-size: 22px;
            }
        }

        @media (max-width: 480px) {
            .ams-student-info-hero__title {
                font-size: 40px;
            }
        }
    </style>
</head>
<body>
    <x-layout.header />

    <main class="ams-student-info">
        {{-- TODO: extract to <x-page.hero> after about-page and student-information-page are merged. --}}
        <section class="ams-student-info-hero">
            <img
                class="ams-student-info-hero__img"
                src="{{ $settings->heroImageUrl() }}"
                alt="{{ $settings->heroImageAlt() }}"
                fetchpriority="high"
            >
            <div class="ams-student-info-hero__overlay" aria-hidden="true"></div>
            <div class="ams-student-info__inner">
                <h1 class="ams-student-info-hero__title">{{ $settings->heroTitle() }}</h1>
            </div>
        </section>

        <section class="ams-student-info-content" aria-labelledby="ams-student-info-heading">
            <div class="ams-student-info__inner">
                <h2 class="ams-student-info__heading" id="ams-student-info-heading">{{ $settings->introHeading() }}</h2>
                <p class="ams-student-info__intro">{!! nl2br(e($settings->introText())) !!}</p>

                @if ($documents->isNotEmpty())
                    <ul class="ams-student-info-docs">
                        @foreach ($documents as $document)
                            <li>
                                @if ($document->isFile())
                                    <a class="ams-student-info-docs__link" href="{{ route('student-documents.download', $document) }}">{{ $document->title }}</a>
                                @else
                                    <a class="ams-student-info-docs__link" href="{{ $document->url }}" target="_blank" rel="noopener">{{ $document->title }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        {{-- ============================================================
             NEXT SECTIONS GO HERE.
             On the live page the documents list is followed by a
             full-width green (#87C65A) band and further sections — not
             specified yet. Add them as new <section>s below this comment,
             still inside <main class="ams-student-info">.
             ============================================================ --}}
    </main>

    <x-layout.footer />
</body>
</html>
