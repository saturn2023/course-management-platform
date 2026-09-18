{{--
    Reusable homepage section: EXPERIENCE (below the About banner)

    Usage:
        <x-home.experience-section />                       (reads HomepageSetting itself)
        <x-home.experience-section :settings="$settings" /> (pass one in to avoid a query)

    - Reproduces the Elementor architecture: the pipe/workers photograph is the
      BACKGROUND of the 1360px boxed outer container, and a white card sits on
      top of it in the left 42.5% of that box. It is deliberately NOT two
      side-by-side columns.
    - Content (background image, heading, description, button text, button URL)
      is managed in Filament at Admin → Homepage Settings → Experience Section,
      stored on the single homepage_settings row. Every field falls back to a
      default, so the section renders correctly before anything has been
      configured — and even before the migration has been run.
    - Fonts: Outfit (heading + paragraph, Google Fonts) and azo-sans-web
      (button, Adobe Fonts kit sat8ost). Both must be loaded in the page <head>
      — see resources/views/mockups/hero-banner.blade.php.
    - Colours reuse the AMS palette already used by the About banner
      (#39B54A green, #2C2C2C dark) and the body grey used by <x-home.intro-text />.
    - All styles are scoped under .experience-section so no other section,
      global type rule or global button style is affected.
--}}

@props(['settings' => null])

@php
    // rescue() so a missing table/column (migration not run yet) renders
    // defaults rather than a 500 — the component is never allowed to break the
    // page. Same guard as <x-home.about-banner />.
    $experienceSettings = $settings ?? rescue(
        fn () => \App\Models\HomepageSetting::current(),
        new \App\Models\HomepageSetting(),
        report: false,
    );

    /*
     * Background photograph (the two workers inspecting a pipe). Null until one
     * is uploaded in Filament — the section then falls back to a neutral
     * placeholder fill rather than an unrelated stock photo.
     */
    $background = $experienceSettings->experienceBackgroundImageUrl();

    /*
     * Optional tighter crop for phones. The accessor already falls back to the
     * main image, so this is never null when $background isn't; comparing the
     * two is how we tell whether a distinct crop was actually uploaded.
     */
    $backgroundMobile = $experienceSettings->experienceBackgroundImageMobileUrl();
    $hasMobileCrop = $backgroundMobile !== null && $backgroundMobile !== $background;
    $heading = $experienceSettings->experienceHeading();
    $description = $experienceSettings->experienceDescription();
    $buttonText = $experienceSettings->experienceButtonText();
    $buttonUrl = $experienceSettings->experienceButtonUrl();

    /*
     * Same treatment as the About banner heading: escape everything, then
     * re-enable <br> only, so a break typed in Filament (newline) or written
     * as a literal <br> becomes a real break while all other markup stays
     * escaped. The default has no break — the two-line layout in the reference
     * comes from natural wrapping inside the card.
     */
    $experienceHeadingHtml = preg_replace('/&lt;br\s*\/?&gt;/i', '<br>', nl2br(e($heading)));
@endphp

<section class="experience-section" @if ($background) style="background-image: url('{{ $background }}');" @endif>
    <style>
        /*
         * Scoped to .experience-section — every rule below is a descendant of
         * the section, so nothing here can reach the header, hero, courses,
         * package or About-banner sections.
         *
         * Elementor outer container: the photograph is this element's
         * background (Full / Center Center / No-repeat / Cover) and it is
         * FULL WIDTH, so the photo runs to both screen edges and starts
         * immediately under the section above it — there is no external top
         * spacing of any kind here.
         *
         * The Boxed 1360px content, and the 60px top padding, live on
         * .experience-inner below, i.e. INSIDE the area the photo covers.
         * Only the 60px bottom margin is external, per Elementor.
         */
        .experience-section {
            /* AMS palette, same values as .ams-about-banner. */
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

            /* Elementor background settings, verbatim. The neutral fill is a
               no-image placeholder only — it is covered as soon as the real
               photograph is supplied. */
            background-color: #d9dde0;
            background-position: center center;
            background-repeat: no-repeat;
            background-size: cover;
        }

        .experience-section * {
            box-sizing: border-box;
        }

        /*
         * Below-900px rendering of the photograph. Above that, desktop keeps
         * the CSS background exactly as it was; at 900px and below the
         * background is switched off and this <img> becomes the section's
         * first row, so the photo is never hidden behind the card. Same image
         * either way — see the 900px media query.
         */
        .experience-section .experience-mobile-image {
            display: none;
        }

        /*
         * Elementor "Content Width: Boxed / 1360px" plus its padding
         * (60px 10px) and Flexbox Row / nowrap / gap 0. This sits inside the
         * background, so the 60px top padding is a band of photograph above
         * the white card rather than white space above the photograph.
         */
        .experience-section .experience-inner {
            width: 100%;
            max-width: 1360px;

            margin-left: auto;
            margin-right: auto;
            padding: 60px 10px;

            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            gap: 0;

            box-sizing: border-box;
        }

        /*
         * Elementor left container: Full Width / 42.5% / Row / gap 20px /
         * nowrap. flex-shrink is pinned so the percentage is the real width
         * and flex cannot squeeze it.
         */
        .experience-section .experience-column {
            width: 42.5%;
            flex: 0 0 42.5%;

            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            gap: 20px;
        }

        /*
         * The white card. Padding is the four Elementor values as-is
         * (103 / 77 / 89 / 77) — not simplified to one number, the top is
         * deliberately deeper than the bottom.
         *
         * The shadow reuses the value already used by the course and package
         * cards (0 4px 14px rgba(0,0,0,.10)) rather than inventing a new one,
         * and is scoped to this card only.
         */
        .experience-section .experience-card {
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
        .experience-section .experience-heading {
            margin: 0;
            padding: 0;
            color: var(--ams-dark);
            font-family: var(--font-body);
            font-size: 34px;
            font-weight: 400;
            line-height: 1.3;
            text-align: left;
        }

        .experience-section .experience-copy {
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
        .experience-section .experience-actions {
            width: 100%;
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            flex-wrap: nowrap;
            gap: 5px;
        }

        /* Same treatment as the About banner: no decorative bar image assets
           exist in the project, so these are CSS blocks at the reference 46px. */
        .experience-section .experience-bar {
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
        .experience-section .experience-btn {
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

        .experience-section .experience-btn:hover {
            background: var(--ams-green-dark);
        }

        .experience-section .experience-btn:focus-visible {
            outline: 3px solid var(--ams-dark);
            outline-offset: 2px;
        }

        /* Tablet: the card grows so the copy keeps a sensible measure, and the
           generous desktop padding comes in. Desktop is untouched. */
        @media (max-width: 1200px) {
            .experience-section .experience-column {
                width: 55%;
                flex-basis: 55%;
            }

            .experience-section .experience-card {
                padding: 70px 50px 60px 50px;
            }

            .experience-section .experience-heading {
                font-size: 30px;
            }
        }

        /*
         * ── THE LAYOUT SWITCH ─────────────────────────────────────────────
         *
         * 900px is the single breakpoint where the Elementor overlap stops and
         * the section becomes two normal-flow rows: photograph, then card.
         * Below this the card would cover most of the photo, so overlapping it
         * hides the subject rather than framing it.
         *
         * The ≤540px query further down only refines sizing and gutters — it
         * must not re-introduce any of the structure set here.
         */
        @media (max-width: 900px) {
            /*
             * The photo stops being a background and becomes a real <img> row
             * below. !important is required because the background-image is an
             * inline style on the section (that is how the Filament URL gets
             * in), and an inline style otherwise beats any stylesheet rule.
             *
             * The placeholder grey goes with it: with the photo now in its own
             * row, that colour would only show as a grey frame around the
             * card's gutters.
             */
            .experience-section {
                background-image: none !important;
                background-color: #FFFFFF;
                margin-bottom: 40px;
            }

            /*
             * ROW 1. A fixed, viewport-scaled height with an overflow crop:
             * the source photo carries a wide, near-blank left portion, so
             * letting it run at its natural ratio would fill the row with
             * empty sky. object-position pins the crop to the right, where the
             * workers are.
             */
            .experience-section .experience-mobile-image {
                display: block;
                width: 100%;
                height: clamp(260px, 38vw, 380px);
                overflow: hidden;
            }

            /* <picture> is inline by default and would otherwise not fill the
               row it sits in. It is a pass-through wrapper, nothing more. */
            .experience-section .experience-mobile-image picture {
                display: block;
                width: 100%;
                height: 100%;
            }

            .experience-section .experience-mobile-image img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: cover;
                object-position: right center;
            }

            /*
             * ROW 2. A plain block following row 1 — no top padding reserving
             * space for a photo, and nothing that could pull it back over the
             * image. 24px tablet gutter; ≤540px trims it to 16px.
             */
            .experience-section .experience-inner {
                width: 100%;
                flex-direction: column;
                padding: 0 24px 40px;
                box-sizing: border-box;
            }

            .experience-section .experience-column {
                width: 100%;
                flex: 0 0 100%;
            }

            /* Internal padding only — no margin, max-width or positioning, so
               the card cannot overlap the photograph above it. */
            .experience-section .experience-card {
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
            /*
             * Phone row height. Shorter than tablet, and the crop recentres:
             * the <picture> above has by now swapped in the phone image, which
             * is already tight on the subject, so pushing it right would cut
             * into it. With no phone crop uploaded this still applies to the
             * main image — centre is the safer default of the two there too.
             */
            .experience-section .experience-mobile-image {
                height: clamp(220px, 58vw, 320px);
            }

            .experience-section .experience-mobile-image img {
                object-position: center center;
            }

            /* 24px tablet gutter → 16px. Nothing else about .experience-inner
               changes, so only the two sides are overridden. */
            .experience-section .experience-inner {
                padding-left: 16px;
                padding-right: 16px;
            }

            .experience-section .experience-card {
                padding: 40px 24px 36px 24px;
                gap: 22px;
            }

            .experience-section .experience-heading {
                font-size: 24px;
            }

            .experience-section .experience-copy {
                font-size: 17px;
            }

            /* The row still must not wrap or overflow on a narrow screen. */
            .experience-section .experience-bar {
                width: 10px;
                height: 44px;
            }

            .experience-section .experience-btn {
                padding: 14px 20px;
                font-size: 15px;
            }
        }
    </style>

    {{--
        Row 1 at 900px and below (hidden above that by CSS). Same $background URL that feeds
        the desktop CSS background above, so one Filament upload drives both.
        Decorative: the heading and paragraph beside it carry the meaning, so
        the alt is deliberately empty rather than invented copy.
    --}}
    @if ($background)
        <div class="experience-mobile-image">
            {{--
                <picture> does the swap natively, with no JS and no second
                request. The 900px here is deliberately the SAME breakpoint as
                the stacked-layout switch in the CSS: the moment the photo stops
                being a background behind the card and becomes its own row, it
                needs the responsive crop. Keeping the two in sync is the whole
                point — at 541–900px they used to disagree, which left the wide
                desktop frame (and its pale left third) filling the image row.

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

    <div class="experience-inner">
        <div class="experience-column">
            <div class="experience-card">
                {{-- $experienceHeadingHtml is escaped above; only <br> is re-enabled. --}}
                <h2 class="experience-heading">{!! $experienceHeadingHtml !!}</h2>

                <p class="experience-copy">{{ $description }}</p>

                <div class="experience-actions">
                    <span class="experience-bar" aria-hidden="true"></span>
                    <span class="experience-bar" aria-hidden="true"></span>

                    <a class="experience-btn" href="{{ $buttonUrl }}">{{ $buttonText }}</a>
                </div>
            </div>
        </div>
    </div>
</section>
