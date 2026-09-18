{{--
    Reusable homepage section: ABOUT BANNER

    Usage:
        <x-home.about-banner />                       (reads HomepageSetting itself)
        <x-home.about-banner :settings="$settings" /> (pass one in to avoid a query)

    - Content (background image, logo, heading, button text, button URL) is
      managed in Filament at Admin → Homepage Settings, stored on the single
      homepage_settings row. Every field falls back to a default, so the
      section renders correctly before anything has been configured — and even
      before the migration has been run.
    - Full-width banner (background image + gradient run edge to edge) with the
      content boxed to 1360px and centred, matching the course / package
      sections' boxed width.
    - Fonts: Normalidad Wide (heading) and Normalidad Compact (button) — must be
      loaded in the page <head> via <x-layout.fonts />. Styles scoped under
      .ams-about-banner.
--}}

@props(['settings' => null])

@php
    // rescue() so a missing table (migration not run yet) renders defaults
    // rather than a 500 — the component is never allowed to break the page.
    $aboutSettings = $settings ?? rescue(
        fn () => \App\Models\HomepageSetting::current(),
        new \App\Models\HomepageSetting(),
        report: false,
    );

    $backgroundUrl = $aboutSettings->aboutBackgroundImageUrl();
    $logoUrl = $aboutSettings->aboutLogoUrl();
    $heading = $aboutSettings->aboutHeading();
    $buttonText = $aboutSettings->aboutButtonText();
    $buttonUrl = $aboutSettings->aboutButtonUrl();

    /*
     * The heading is the only field that may carry a deliberate line break.
     * Escape everything first, then re-enable <br> only: a newline typed in
     * Filament (nl2br) and a literal <br> / <br/> / <br /> typed in the field
     * both become a real break, while any other markup stays escaped.
     */
    $headingHtml = preg_replace('/&lt;br\s*\/?&gt;/i', '<br>', nl2br(e($heading)));
@endphp

<section class="ams-about-banner">
    <style>
        /* Scoped to .ams-about-banner so this block never affects other pages. */
        .ams-about-banner {
            /* Same AMS palette / fonts as the sections above. */
            --ams-green: #39B54A;
            --ams-green-dark: #2f9e3f;
            --ams-dark: #2C2C2C;
            --font-head: 'normalidad-compact', 'Segoe UI', Arial, sans-serif;
            --font-display: 'normalidad-wide', 'Segoe UI', Arial, sans-serif;
            background: #FFFFFF;
            /*
             * No trailing spacing. This used to carry padding: 0 0 60px as
             * "vertical spacing between sections", from when the banner was the
             * last block on the page. The Experience section below now supplies
             * its own leading 60px INSIDE its background image (Elementor does
             * the same), so keeping it here painted a 60px white strip between
             * the banner photo and that background. Nothing about the banner
             * itself — height, photo, gradient, content — depends on this.
             */
            padding: 0;
        }

        .ams-about-banner * {
            box-sizing: border-box;
        }

        /*
         * The banner itself is FULL WIDTH — Elementor's "boxed 1360px" applies
         * to the content, not to the background container, so the photo and
         * the gradient run edge to edge (see .aab-wrap for the 1360px box).
         * The photo lives here as a background so the gradient can sit on top
         * of it without an extra wrapper and the Filament image stays
         * independently editable.
         *
         * Deliberately NO background-color: `contain` leaves a gap whenever the
         * image is taller in aspect than the banner, and a base colour here
         * paints that gap as a solid strip down the right edge. Elementor has no
         * section colour either, so the gap reads as page background. The dark
         * fallback is applied inline instead, only when no image is set at all.
         */
        .ams-about-banner .aab-inner {
            position: relative;
            width: 100%;
            max-width: none;
            min-height: 612px;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background-repeat: no-repeat;
            background-size: contain;
            background-position: 59.5% 0;
        }

        /*
         * Elementor's overlay verbatim: solid #333 to 24%, fading to fully
         * transparent by 58%, so the left is solid dark and the photo takes
         * over around the middle. #F2295B00 is a zero-alpha colour — the hue
         * never shows, it only defines the fade's end point.
         */
        .ams-about-banner .aab-overlay {
            position: absolute;
            inset: 0;
            z-index: 1;
            background: linear-gradient(
                90deg,
                #333333 24%,
                #F2295B00 58%
            );
        }

        /*
         * Elementor's "Content Width: Boxed / 1360px": this wrapper — not the
         * banner — is the 1360px box, centred over the full-width background.
         * It also carries the 612px height so the content stays vertically
         * centred within the banner. No horizontal padding at desktop: the
         * centred box is what places the content (~279px in at a 1918px
         * viewport); a gutter only kicks in below 1360px, where the box would
         * otherwise touch the screen edge.
         */
        .ams-about-banner .aab-wrap {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1360px;
            min-height: 612px;
            margin-left: auto;
            margin-right: auto;
            padding: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 20px;
        }

        /* Logo, heading and button row stacked vertically. */
        .ams-about-banner .aab-content {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 23px;
        }

        /*
         * No manual width: the SVG's own 95x56 is its natural size. max-height
         * only caps an oversized upload from Filament so it cannot blow the
         * layout out.
         */
        .ams-about-banner .aab-logo {
            display: block;
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: 56px;
            margin: 0;
        }

        /*
         * Weight 300 is explicit so the <h2> does not fall back to UA bold.
         * No max-width: the line break comes from the heading content itself,
         * so the second line must be free to stay on one line.
         */
        .ams-about-banner .aab-heading {
            margin: 0;
            padding: 0;
            color: #FFFFFF;
            font-family: var(--font-display);
            font-size: 36px;
            font-weight: 300;
            line-height: 1.3;
            text-align: left;
        }

        /* Decorative bars + button in one row, 5px apart. */
        .ams-about-banner .aab-actions {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            flex-wrap: nowrap;
            gap: 5px;
            width: 100%;
            margin: 26px 0 0;
            padding: 0;
        }

        /* No decorative bar image assets exist in the project, so these are
           CSS blocks at the reference height. */
        .ams-about-banner .aab-bar {
            flex: 0 0 auto;
            width: 15px;
            height: 46px;
            background: var(--ams-green);
        }

        /*
         * Height is padding + line box, nothing else: 15 + 16 + 15 = 46px, the
         * same height as the decorative bars. That is why line-height is 1 and
         * the font is 16px — at 18px/1.2 the line box was 21.6px and pushed the
         * button to ~52px. No border, min-height or inherited button styling is
         * involved, so those are the only two values that needed correcting.
         */
        .ams-about-banner .aab-btn {
            display: inline-flex;
            align-items: center;
            background: var(--ams-green);
            color: #FFFFFF;
            padding: 15px 30px;
            border: 0;
            border-radius: 0;
            font-family: var(--font-head);
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.5px;
            line-height: 1;
            text-decoration: none;
            transition: background-color 0.15s ease;
        }

        .ams-about-banner .aab-btn:hover {
            background: var(--ams-green-dark);
        }

        .ams-about-banner .aab-btn:focus-visible {
            outline: 3px solid #FFFFFF;
            outline-offset: 2px;
        }

        /* Below 1360px the boxed content would touch the screen edge, so a
           gutter takes over from the centring. Desktop is unaffected. */
        @media (max-width: 1419px) {
            .ams-about-banner .aab-wrap {
                padding: 0 30px;
            }
        }

        /* Tablet: shorter banner, smaller heading. Desktop background
           sizing/position is kept. */
        @media (max-width: 900px) {
            .ams-about-banner .aab-inner,
            .ams-about-banner .aab-wrap {
                min-height: 460px;
            }
            .ams-about-banner .aab-heading {
                font-size: 28px;
            }
        }

        /*
         * Mobile: `contain` would shrink the photo to a thin strip in a narrow
         * box, so it switches to `cover` with the subject pulled right — the
         * one responsive deviation from the desktop background settings. The
         * gradient is tightened so the text keeps a dark backing across the
         * narrower box, and the heading break is released so it can reflow.
         */
        @media (max-width: 540px) {
            /* Was 0 0 40px — same reason as the desktop rule above. */
            .ams-about-banner {
                padding: 0;
            }
            .ams-about-banner .aab-inner,
            .ams-about-banner .aab-wrap {
                min-height: 380px;
            }
            .ams-about-banner .aab-inner {
                background-size: cover;
                background-position: 65% center;
            }
            .ams-about-banner .aab-overlay {
                background: linear-gradient(
                    90deg,
                    #333333 45%,
                    #33333300 100%
                );
            }
            .ams-about-banner .aab-wrap {
                padding: 0 22px;
            }
            .ams-about-banner .aab-content {
                gap: 18px;
            }
            .ams-about-banner .aab-logo {
                max-height: 46px;
            }
            .ams-about-banner .aab-heading {
                font-size: 22px;
            }
            .ams-about-banner .aab-actions {
                margin-top: 18px;
            }
            .ams-about-banner .aab-bar {
                width: 10px;
                height: 40px;
            }
            .ams-about-banner .aab-btn {
                padding: 13px 24px;
            }
        }
    </style>

    {{-- The dark fill is a no-image fallback only; with a photo set the base
         must stay transparent so `contain` never paints a strip (see CSS). --}}
    <div class="aab-inner" style="@if ($backgroundUrl) background-image: url('{{ $backgroundUrl }}'); @else background-color: var(--ams-dark); @endif">
        <div class="aab-overlay" aria-hidden="true"></div>

        <div class="aab-wrap">
            <div class="aab-content">
                @if ($logoUrl)
                    <img class="aab-logo" src="{{ $logoUrl }}" alt="AMS Training">
                @endif

                {{-- $headingHtml is escaped above; only <br> is re-enabled. --}}
                <h2 class="aab-heading">{!! $headingHtml !!}</h2>

                <div class="aab-actions">
                    <span class="aab-bar" aria-hidden="true"></span>
                    <span class="aab-bar" aria-hidden="true"></span>

                    <a class="aab-btn" href="{{ $buttonUrl }}">{{ $buttonText }}</a>
                </div>
            </div>
        </div>
    </div>
</section>
