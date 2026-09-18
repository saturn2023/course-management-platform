{{--
    Reusable homepage section: FIRST IN OUR FIELD (below the Experience section)

    Usage:
        <x-home.first-field-section />                       (reads HomepageSetting itself)
        <x-home.first-field-section :settings="$settings" /> (pass one in to avoid a query)

    - This is the MIRROR of <x-home.experience-section />: identical architecture,
      dimensions, typography, spacing and responsive behaviour, with the desktop
      composition reversed — photograph left, white card right. The only
      structural difference is `justify-content: flex-end` on .first-field-inner,
      which pushes the 42.5% column to the right-hand end of the 1360px box. No
      transforms, offsets or absolute positioning are involved.
    - Content is managed in Filament at Admin → Homepage Settings →
      First In Our Field Section, on the single homepage_settings row. Every
      field falls back to a default, so the section renders correctly before
      anything has been configured — and even before the migration has been run.
    - Fonts: Outfit (heading + paragraph, Google Fonts) and azo-sans-web
      (button, Adobe Fonts kit sat8ost). Both must be loaded in the page <head>
      — see resources/views/mockups/hero-banner.blade.php.
    - Styles are deliberately duplicated rather than shared with the Experience
      section: extracting a common stylesheet would mean editing that finished
      component. Every rule is scoped under .first-field-section, so the two
      cannot affect each other.
--}}

@props(['settings' => null])

@php
    // rescue() so a missing table/column (migration not run yet) renders
    // defaults rather than a 500 — the component is never allowed to break the
    // page. Same guard as the Experience section and the About banner.
    $firstFieldSettings = $settings ?? rescue(
        fn () => \App\Models\HomepageSetting::current(),
        new \App\Models\HomepageSetting(),
        report: false,
    );

    /*
     * The carabiner / climbing-equipment photograph. Null until one is uploaded
     * in Filament — the section then falls back to a neutral placeholder fill
     * rather than an unrelated stock photo.
     */
    $background = $firstFieldSettings->firstFieldBackgroundImageUrl();

    /*
     * Optional tighter crop for the stacked layout. The accessor already falls
     * back to the main image, so this is never null when $background isn't;
     * comparing the two is how we tell whether a distinct crop was uploaded.
     */
    $backgroundMobile = $firstFieldSettings->firstFieldBackgroundImageMobileUrl();
    $hasMobileCrop = $backgroundMobile !== null && $backgroundMobile !== $background;

    $heading = $firstFieldSettings->firstFieldHeading();
    $description = $firstFieldSettings->firstFieldDescription();
    $buttonText = $firstFieldSettings->firstFieldButtonText();
    $buttonUrl = $firstFieldSettings->firstFieldButtonUrl();

    // Escape everything, then re-enable <br> only, so a break typed in Filament
    // (newline) or written as a literal <br> becomes a real break while all
    // other markup stays escaped.
    $firstFieldHeadingHtml = preg_replace('/&lt;br\s*\/?&gt;/i', '<br>', nl2br(e($heading)));
@endphp

<section class="first-field-section" @if ($background) style="background-image: url('{{ $background }}');" @endif>
    <style>
        /*
         * Scoped to .first-field-section — every rule below is a descendant of
         * the section, so nothing here can reach the Experience section, the
         * About banner, the header or any other block.
         *
         * The photograph is this element's background (Cover / Center Center /
         * No-repeat) and the element is FULL WIDTH, so the photo runs to both
         * screen edges. The boxed 1360px content, and the 60px top padding,
         * live on .first-field-inner, i.e. INSIDE the area the photo covers.
         * Only the 60px bottom margin is external.
         */
        .first-field-section {
            /* Same AMS palette as the Experience section and the About banner. */
            --ams-green: #39B54A;
            --ams-green-dark: #2f9e3f;
            --ams-dark: #2C2C2C;
            --ams-body: #7a7a7a;
            --font-body: 'Outfit', 'Segoe UI', Arial, sans-serif;
            --font-btn: 'azo-sans-web', 'Segoe UI', Arial, sans-serif;

            width: 100%;
            max-width: none;

            margin: 0 0 60px;
            padding: 0;

            position: relative;

            /*
             * White, not the neutral grey the Experience section uses as its
             * no-image placeholder. The carabiner artwork is a transparent PNG/
             * WebP whose right-hand third is alpha — whatever colour sits here
             * shows through it, beside and behind the white card. Grey exposed
             * that area; white makes the transparent part read as page
             * background, matching the Elementor reference. It is also still
             * the pre-upload placeholder, just an invisible one.
             */
            background-color: #FFFFFF;
            background-position: center center;
            background-repeat: no-repeat;
            background-size: cover;
        }

        .first-field-section * {
            box-sizing: border-box;
        }

        /*
         * Below-900px rendering of the photograph. Above that, desktop keeps
         * the CSS background; at 900px and below the background is switched off
         * and this <img> becomes the section's first row, so the photo is never
         * hidden behind the card. Same image either way.
         */
        .first-field-section .first-field-mobile-image {
            display: none;
        }

        /*
         * Boxed 1360px content with 60px 10px padding, Flexbox Row / nowrap /
         * gap 0 — identical to Experience except for justify-content.
         *
         * THE MIRROR: flex-end puts the single 42.5% column at the right-hand
         * end of the box, so the card overlaps the photograph from the right.
         * That is the whole reversal — done with flex alignment inside the
         * normal boxed wrapper, not with offsets, transforms or positioning.
         */
        .first-field-section .first-field-inner {
            width: 100%;
            max-width: 1360px;

            margin-left: auto;
            margin-right: auto;
            padding: 60px 10px;

            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            justify-content: flex-end;
            gap: 0;

            box-sizing: border-box;
        }

        /* flex-shrink is pinned so the percentage is the real width and flex
           cannot squeeze it. */
        .first-field-section .first-field-column {
            width: 42.5%;
            flex: 0 0 42.5%;

            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            gap: 20px;
        }

        /*
         * The white card. The four padding values are kept as-is
         * (103 / 77 / 89 / 77) — the top is deliberately deeper than the
         * bottom. The shadow is the same value used by the Experience section
         * and the course/package cards, scoped to this card only.
         */
        .first-field-section .first-field-card {
            width: 100%;
            display: flex;
            flex-direction: column;
            flex-wrap: nowrap;
            gap: 30px;

            background: #FFFFFF;
            padding: 103px 77px 89px 77px;

            position: relative;
            z-index: 1;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.10);
        }

        /* Weight is explicit so the <h2> cannot fall back to the UA bold. */
        .first-field-section .first-field-heading {
            margin: 0;
            padding: 0;
            color: var(--ams-dark);
            font-family: var(--font-body);
            font-size: 34px;
            font-weight: 400;
            line-height: 1.3;
            text-align: left;
        }

        .first-field-section .first-field-copy {
            margin: 0;
            padding: 0;
            color: var(--ams-body);
            font-family: var(--font-body);
            font-size: 20px;
            font-weight: 300;
            line-height: 1.3;
            text-align: left;
        }

        /* Two decorative bars + the button on one row, 5px apart. */
        .first-field-section .first-field-actions {
            width: 100%;
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            flex-wrap: nowrap;
            gap: 5px;
        }

        /* No decorative bar image assets exist in the project, so these are CSS
           blocks at the reference 46px — same as Experience and the banner. */
        .first-field-section .first-field-bar {
            flex: 0 0 auto;
            width: 15px;
            height: 46px;
            background: var(--ams-green);
        }

        /*
         * 15 + 16 + 15 = 46px, matching the bars exactly — which is why
         * line-height is 1. Border and radius are zeroed so no global button
         * style can leak in and change the height.
         */
        .first-field-section .first-field-btn {
            display: inline-flex;
            align-items: center;
            background: var(--ams-green);
            color: #FFFFFF;
            padding: 15px 30px;
            border: 0;
            border-radius: 0;
            font-family: var(--font-btn);
            font-size: 16px;
            font-weight: 300;
            line-height: 1;
            text-decoration: none;
            white-space: nowrap;
            transition: background-color 0.15s ease;
        }

        .first-field-section .first-field-btn:hover {
            background: var(--ams-green-dark);
        }

        .first-field-section .first-field-btn:focus-visible {
            outline: 3px solid var(--ams-dark);
            outline-offset: 2px;
        }

        /* Tablet: the card grows so the copy keeps a sensible measure. Still
           right-aligned — only the width changes. */
        @media (max-width: 1200px) {
            .first-field-section .first-field-column {
                width: 55%;
                flex-basis: 55%;
            }

            .first-field-section .first-field-card {
                padding: 70px 50px 60px 50px;
            }

            .first-field-section .first-field-heading {
                font-size: 30px;
            }
        }

        /*
         * ── THE LAYOUT SWITCH ─────────────────────────────────────────────
         *
         * 900px, the same breakpoint the Experience section uses. The overlap
         * stops and the section becomes two normal-flow rows: photograph, then
         * card. The right-hand alignment goes with it — a stacked card is full
         * width, so there is nothing left to align.
         *
         * The ≤540px query below only refines sizing and gutters; it must not
         * re-introduce any of the structure set here.
         */
        @media (max-width: 900px) {
            /*
             * !important is required because the background-image is an inline
             * style on the section (that is how the Filament URL gets in), and
             * an inline style otherwise beats any stylesheet rule.
             */
            .first-field-section {
                background-image: none !important;
                background-color: #FFFFFF;
                margin-bottom: 40px;
            }

            /* ROW 1. Fixed, viewport-scaled height with an overflow crop, so a
               wide landscape source cannot fill the row with empty sky. */
            .first-field-section .first-field-mobile-image {
                display: block;
                width: 100%;
                height: clamp(260px, 38vw, 380px);
                overflow: hidden;
            }

            /* <picture> is inline by default and would otherwise not fill the
               row it sits in. It is a pass-through wrapper, nothing more. */
            .first-field-section .first-field-mobile-image picture {
                display: block;
                width: 100%;
                height: 100%;
            }

            .first-field-section .first-field-mobile-image img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: cover;
                object-position: center center;
            }

            /*
             * ROW 2. A plain block following row 1 — no top padding reserving
             * space for a photo, and nothing that could pull it back over the
             * image. justify-content is reset because in a column flex box
             * flex-end would push content to the BOTTOM rather than the right.
             * 24px tablet gutter; ≤540px trims it to 16px.
             */
            .first-field-section .first-field-inner {
                width: 100%;
                flex-direction: column;
                justify-content: flex-start;
                padding: 0 24px 40px;
                box-sizing: border-box;
            }

            .first-field-section .first-field-column {
                width: 100%;
                flex: 0 0 100%;
            }

            /* Internal padding only — no margin, max-width or positioning, so
               the card cannot overlap the photograph above it. */
            .first-field-section .first-field-card {
                width: 100%;
                max-width: none;
                margin: 0;
                box-sizing: border-box;
            }
        }

        /*
         * REFINEMENT ONLY. The two-row architecture is already in force from
         * 900px down — this query must not restate it, only tighten sizing.
         */
        @media (max-width: 540px) {
            .first-field-section .first-field-mobile-image {
                height: clamp(220px, 58vw, 320px);
            }

            /* 24px tablet gutter → 16px. Nothing else about .first-field-inner
               changes, so only the two sides are overridden. */
            .first-field-section .first-field-inner {
                padding-left: 16px;
                padding-right: 16px;
            }

            .first-field-section .first-field-card {
                padding: 40px 24px 36px 24px;
                gap: 22px;
            }

            .first-field-section .first-field-heading {
                font-size: 24px;
            }

            .first-field-section .first-field-copy {
                font-size: 17px;
            }

            /* The row still must not wrap or overflow on a narrow screen. */
            .first-field-section .first-field-bar {
                width: 10px;
                height: 44px;
            }

            .first-field-section .first-field-btn {
                padding: 14px 20px;
                font-size: 15px;
            }
        }
    </style>

    {{--
        Row 1 at 900px and below (hidden above that by CSS). Same $background
        URL that feeds the desktop CSS background above, so one Filament upload
        drives both. Decorative: the heading and paragraph carry the meaning,
        so the alt is deliberately empty rather than invented copy.
    --}}
    @if ($background)
        <div class="first-field-mobile-image">
            {{--
                <picture> does the swap natively, with no JS and no second
                request. The 900px here is deliberately the SAME breakpoint as
                the stacked-layout switch in the CSS: the moment the photo stops
                being a background behind the card and becomes its own row, it
                needs the responsive crop.

                When no responsive crop has been uploaded the <source> is simply
                not emitted, so the <img> below — the main image — is used at
                every width. That is the fallback.
            --}}
            <picture>
                @if ($hasMobileCrop)
                    <source media="(max-width: 900px)" srcset="{{ $backgroundMobile }}">
                @endif

                <img src="{{ $background }}" alt="">
            </picture>
        </div>
    @endif

    <div class="first-field-inner">
        <div class="first-field-column">
            <div class="first-field-card">
                {{-- $firstFieldHeadingHtml is escaped above; only <br> is re-enabled. --}}
                <h2 class="first-field-heading">{!! $firstFieldHeadingHtml !!}</h2>

                <p class="first-field-copy">{{ $description }}</p>

                <div class="first-field-actions">
                    <span class="first-field-bar" aria-hidden="true"></span>
                    <span class="first-field-bar" aria-hidden="true"></span>

                    <a class="first-field-btn" href="{{ $buttonUrl }}">{{ $buttonText }}</a>
                </div>
            </div>
        </div>
    </div>
</section>
