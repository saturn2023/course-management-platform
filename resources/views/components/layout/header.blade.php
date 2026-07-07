{{--
    Reusable site header: AMS Training

    Usage:
        <x-layout.header />

    - Sticky dark header matching the Elementor reference.
    - Shrinks on scroll: past 10px it gains .elementor-sticky--effects, reducing
      header height/padding, shrinking the logo, and adding a shadow.
    - Desktop: logo left, pill search centre, nav right.
    - Tablet/mobile: logo left + hamburger; search stacks full width; nav collapses.
    - All styles scoped under .ams-site-header so admin/other pages are unaffected.
    - Search is currently VISUAL-ONLY (no course search route exists yet).
--}}

@php
    // Static nav for now. These routes may not exist yet — links still render.
    $navLinks = [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'About', 'url' => '/about'],
        ['label' => 'Certificate Check', 'url' => '/certificate-check'],
        ['label' => 'Student Info', 'url' => '/student-info'],
        ['label' => 'Contact', 'url' => '/contact'],
    ];
@endphp

<header class="ams-site-header shrink-header">
    <style>
        /*
         * Disable scroll anchoring so the page doesn't "jump" downward when the
         * sticky header grows back to full size near the top. Scroll anchoring
         * can only be turned off on the scroll root, so this one rule
         * intentionally targets <html> rather than sitting under
         * .ams-site-header. It only affects auto scroll re-positioning, nothing
         * visual, so it is safe alongside the header wherever it is used.
         */
        html {
            overflow-anchor: none;
        }

        /* Scoped to .ams-site-header so this block never affects other pages. */
        .ams-site-header {
            --ash-green: #5bb143;
            --ash-text: #f4f4f4;
            position: sticky;
            top: 0;
            z-index: 9999;
            width: 100%;
            background: #2C2C2C;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        .ams-site-header * {
            box-sizing: border-box;
        }

        .ams-site-header.shrink-header {
            transition: background-color 0.4s ease, box-shadow 0.4s ease;
        }

        .ams-site-header__inner {
            max-width: 1900px;
            margin: 0 auto;
            min-height: 92px;
            padding: 2%;
            display: flex;
            align-items: center;
            gap: 28px;
            transition: min-height 0.4s ease, padding 0.4s ease;
        }

        /* Logo left. transform-origin/left pinning keeps it from shifting. */
        .ams-site-header__logo-link {
            flex: 0 0 auto;
            line-height: 0;
        }

        .ams-site-header__logo {
            display: block;
            width: 140px;
            max-width: 140px;
            height: auto;
            transition: width 0.4s ease, max-width 0.4s ease;
            transform-origin: left center;
            margin-left: 0;
            position: relative;
            left: 0;
        }

        /* Search pill, centre, grows to fill available space. */
        .ams-site-header__search {
            flex: 1 1 auto;
            max-width: 640px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            background: #ffffff;
            border-radius: 999px;
            padding: 4px 6px 4px 18px;
        }

        .ams-site-header__search input {
            flex: 1 1 auto;
            border: none;
            outline: none;
            background: transparent;
            font-size: 0.95rem;
            color: #333;
            height: 44px;
            padding: 0 4px;
            transition: height 0.4s ease;
        }

        .ams-site-header__search button {
            flex: 0 0 auto;
            border: none;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--ash-green);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease;
        }

        .ams-site-header__search button:hover {
            background: #4a9637;
        }

        .ams-site-header__search svg {
            width: 18px;
            height: 18px;
            display: block;
        }

        /* Right-hand navigation. */
        .ams-site-header__nav {
            flex: 0 0 auto;
        }

        .ams-site-header__nav-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            gap: 26px;
        }

        .ams-site-header__nav-link {
            color: var(--ash-text);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            white-space: nowrap;
            transition: color 0.15s ease;
        }

        .ams-site-header__nav-link:hover,
        .ams-site-header__nav-link:focus-visible {
            color: var(--ash-green);
        }

        .ams-site-header a:focus-visible,
        .ams-site-header button:focus-visible,
        .ams-site-header input:focus-visible {
            outline: 2px solid var(--ash-green);
            outline-offset: 2px;
        }

        /* Hamburger toggle — hidden on desktop. */
        .ams-site-header__toggle {
            display: none;
            flex: 0 0 auto;
            margin-left: auto;
            border: none;
            background: transparent;
            color: var(--ash-text);
            cursor: pointer;
            padding: 6px;
        }

        .ams-site-header__toggle svg {
            width: 30px;
            height: 30px;
            display: block;
        }

        /* ===== Shrink-on-scroll effects ===== */
        .ams-site-header.shrink-header.elementor-sticky--effects {
            background-color: #2C2C2C !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .ams-site-header.shrink-header.elementor-sticky--effects > .ams-site-header__inner {
            min-height: 66px;
            padding-top: 10px !important;
            padding-bottom: 10px !important;
        }

        .ams-site-header.shrink-header.elementor-sticky--effects img,
        .ams-site-header.shrink-header.elementor-sticky--effects .ams-site-header__logo {
            width: 115px !important;
            max-width: 115px !important;
            margin-left: 0 !important;
            position: relative !important;
            left: 0 !important;
        }

        .ams-site-header.shrink-header.elementor-sticky--effects .ams-site-header__search input {
            height: 38px;
        }

        /* ===== Tablet / mobile ===== */
        @media (max-width: 992px) {
            .ams-site-header__inner {
                flex-wrap: wrap;
                row-gap: 16px;
            }

            /* Row 1: logo left, hamburger right. */
            .ams-site-header__logo-link {
                order: 1;
            }

            .ams-site-header__toggle {
                display: inline-flex;
                order: 2;
            }

            /* Row 2: full-width search. */
            .ams-site-header__search {
                order: 3;
                flex: 1 1 100%;
                max-width: 100%;
                margin: 0;
            }

            /* Row 3: nav collapses; shown only when toggled open. */
            .ams-site-header__nav {
                order: 4;
                flex: 1 1 100%;
                display: none;
            }

            .ams-site-header__nav.is-open {
                display: block;
            }

            .ams-site-header__nav-list {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .ams-site-header__nav-link {
                display: block;
                width: 100%;
                padding: 12px 4px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                font-size: 1rem;
            }
        }
    </style>

    <div class="ams-site-header__inner">
        {{-- Logo --}}
        <a href="/" class="ams-site-header__logo-link" aria-label="AMS Training home">
            {{-- Root-relative src so the logo resolves against the current host
                 (localhost, ngrok, or production) instead of a fixed APP_URL. --}}
            <img
                src="/images/ams-logo.webp"
                alt="AMS Training"
                class="ams-site-header__logo"
            >
        </a>

        {{-- Hamburger (mobile only) --}}
        <button
            type="button"
            class="ams-site-header__toggle"
            aria-label="Toggle navigation menu"
            aria-expanded="false"
            aria-controls="ash-primary-nav"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        {{-- Search (visual-only for now) --}}
        <form class="ams-site-header__search" role="search" action="#" onsubmit="return false;">
            <input
                type="search"
                name="q"
                placeholder="Search Course Code, Course Name"
                aria-label="Search Course Code, Course Name"
            >
            <button type="submit" aria-label="Search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </button>
        </form>

        {{-- Navigation --}}
        <nav class="ams-site-header__nav" id="ash-primary-nav" aria-label="Primary">
            <ul class="ams-site-header__nav-list">
                @foreach ($navLinks as $link)
                    <li><a class="ams-site-header__nav-link" href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>
    </div>

    <script>
        // Minimal, self-contained mobile nav toggle scoped to this header.
        (function () {
            var header = document.currentScript.closest('.ams-site-header');
            if (! header) {
                return;
            }

            var toggle = header.querySelector('.ams-site-header__toggle');
            var nav = header.querySelector('.ams-site-header__nav');

            if (! toggle || ! nav) {
                return;
            }

            toggle.addEventListener('click', function () {
                var isOpen = nav.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        })();
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const header = document.querySelector('.ams-site-header.shrink-header');

            if (!header) {
                return;
            }

            /*
             * Small hysteresis dead-band to avoid flicker right at the
             * threshold. The scroll-anchoring bounce that used to force a large
             * gap here is handled separately by `overflow-anchor: none` on
             * <html>, so we can shrink promptly (SHRINK_AT is low) — this stops
             * the tall full-size header from covering the hero as you scroll.
             */
            const SHRINK_AT = 40;
            const GROW_AT = 10;
            let shrunk = false;
            let ticking = false;

            const apply = function () {
                ticking = false;
                const y = window.scrollY;

                if (!shrunk && y > SHRINK_AT) {
                    shrunk = true;
                    header.classList.add('elementor-sticky--effects');
                } else if (shrunk && y < GROW_AT) {
                    shrunk = false;
                    header.classList.remove('elementor-sticky--effects');
                }
            };

            const onScroll = function () {
                if (!ticking) {
                    ticking = true;
                    window.requestAnimationFrame(apply);
                }
            };

            apply();

            window.addEventListener('scroll', onScroll, {
                passive: true
            });
        });
    </script>
</header>
