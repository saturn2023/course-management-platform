{{--
    Reusable intro text section: centred green heading + intro paragraph.

    Usage:
        <x-home.intro-text />
        <x-home.intro-text heading="..." body="..." />

    - Text passed via props (heading + body) with defaults matching the design.
    - Same content width as the other blocks (max-width 1900px, centred).
    - Padding: 60px top/bottom, 2% left/right (per design spec).
    - Fonts: heading = Normalidad Compact (Adobe Fonts), body = Outfit (Google).
      These must be loaded in the page <head> (see the mockup page / site layout).
    - All styles scoped under .ams-intro so other pages are unaffected.
--}}

@props([
    'heading' => 'Nationally Accredited 100% Online Refresher Courses Available Australia-Wide',
    'body' => 'AMS Training offers a comprehensive range of nationally accredited training solutions designed to meet the needs of individuals and businesses across the country. With nationally accredited 100% online refresher courses available Australia-wide, AMS Training provides flexible learning opportunities that allow students to update their skills and qualifications from anywhere, at any time. As a Registered Training Organisation (RTO No. 45735), AMS Training is committed to delivering industry-recognised training that meets national standards, ensuring students receive the knowledge, skills, and qualifications required to succeed in their chosen field. AMS Training offers reliable, accredited, and accessible education options to support your professional development goals.',
])

<section class="ams-intro">
    <style>
        /* Scoped to .ams-intro so this block never affects other pages. */
        .ams-intro {
            --ams-green: #45b035;
            background: #ffffff;
            padding-top: 60px;
            padding-bottom: 60px;
            padding-left: 2%;
            padding-right: 2%;
        }

        .ams-intro * {
            box-sizing: border-box;
        }

        .ams-intro__inner {
            max-width: 1900px;
            margin: 0 auto;
            text-align: center;
        }

        .ams-intro__heading {
            margin: 0 auto 28px;
            max-width: 1100px;
            color: var(--ams-green);
            font-family: 'normalidad-compact', 'Segoe UI', Arial, sans-serif;
            font-size: 34px;
            font-weight: 600;
            line-height: 1.3;
            text-transform: uppercase;
        }

        .ams-intro__body {
            margin: 0 auto;
            max-width: 1150px;
            color: #7a7a7a;
            font-family: 'Outfit', 'Segoe UI', Arial, sans-serif;
            font-size: 20px;
            font-weight: 300;
            line-height: 1.3;
        }

        @media (max-width: 640px) {
            .ams-intro {
                padding-top: 40px;
                padding-bottom: 40px;
            }
            .ams-intro__heading {
                font-size: 26px;
            }
            .ams-intro__body {
                font-size: 18px;
            }
        }
    </style>

    <div class="ams-intro__inner">
        <h2 class="ams-intro__heading">{{ $heading }}</h2>
        <p class="ams-intro__body">{{ $body }}</p>
    </div>
</section>
