{{--
    Reusable site header: AMS Training

    Usage:
        <x-layout.header />

    - Sticky dark header matching the Elementor reference.
    - Shrinks on scroll: past 10px it gains .elementor-sticky--effects, reducing
      header height/padding, shrinking the logo, and adding a shadow.
    - Desktop: logo left, nav right.
    - Tablet/mobile: logo left + hamburger; nav collapses.
    - All styles scoped under .ams-site-header so admin/other pages are unaffected.
--}}

@php
    // Static nav for now. These routes may not exist yet — links still render.
    // Certificate Check is not in this list: it is the green pill button rendered
    // as the first item of the nav row, and it opens the RTODATA modal in-page
    // rather than navigating (no /certificate-check route exists).
    $navLinks = [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'About', 'url' => '/about'],
        ['label' => 'Student Info', 'url' => '/student-information'],
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

        /*
         * Horizontal padding is 7%, not 2%: in the Elementor reference the logo
         * sits ~133px in from the viewport edge (and "Contact" the same distance
         * from the right), which is ~7% of the 1900px container. 2% (~38px) is
         * what pushed the logo and nav to the extremes and opened up the dead
         * space in the middle. Vertical padding stays at 2% (~38px), which does
         * match the reference.
         */
        .ams-site-header__inner {
            max-width: 1900px;
            margin: 0 auto;
            min-height: 92px;
            padding: 2% 7%;
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

        /*
         * 112px ≈ 60% of the 225px Elementor image-widget width, and matches the
         * logo size in the reference screenshot. The header has no fixed height —
         * it is logo height + vertical padding — so this is also what brings the
         * header down from ~176px to ~155px.
         */
        .ams-site-header__logo {
            display: block;
            width: 112px;
            max-width: 112px;
            height: auto;
            transition: width 0.4s ease, max-width 0.4s ease;
            transform-origin: left center;
            margin-left: 0;
            position: relative;
            left: 0;
        }

        /*
         * Navigation takes the remaining width and right-aligns its own links,
         * rather than being shoved right by `margin-left: auto` / space-between.
         * Same visual result, but the gap between logo and nav is then governed
         * by the container inset above, which is what actually controls it.
         */
        .ams-site-header__nav {
            flex: 1 1 auto;
        }

        .ams-site-header__nav-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 34px;
        }

        /* Nav typography matches Elementor: Roboto 600. Size/line-height unchanged. */
        .ams-site-header__nav-link {
            color: var(--ash-text);
            text-decoration: none;
            font-family: "Roboto", sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            white-space: nowrap;
            transition: color 0.15s ease;
        }

        .ams-site-header__nav-link:hover,
        .ams-site-header__nav-link:focus-visible {
            color: var(--ash-green);
        }

        /*
         * Certificate Check pill — the first <li> of the same nav flex row, so it
         * is centred by the row's `align-items: center` and spaced by its 34px
         * gap. font-size is restated because buttons don't inherit it.
         */
        .ams-site-header__cert-btn {
            background-color: #45b035;
            color: #ffffff;
            border: none;
            border-radius: 999px;
            padding: 10px 22px;
            font-family: "Roboto", sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: background-color 0.15s ease;
        }

        .ams-site-header__cert-btn:hover {
            background-color: #3c9a2d;
        }

        .ams-site-header a:focus-visible,
        .ams-site-header button:focus-visible {
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

        /*
         * 95px is the full outer height of the shrunk bar (box-sizing: border-box
         * is set for everything under .ams-site-header, so the 10px vertical
         * padding is included). The 92px logo only needs ~86px, so this min-height
         * binds; `align-items: center` on __inner keeps the logo and nav centred
         * in the extra space.
         */
        .ams-site-header.shrink-header.elementor-sticky--effects > .ams-site-header__inner {
            min-height: 95px;
            padding-top: 10px !important;
            padding-bottom: 10px !important;
        }

        .ams-site-header.shrink-header.elementor-sticky--effects img,
        .ams-site-header.shrink-header.elementor-sticky--effects .ams-site-header__logo {
            width: 92px !important;
            max-width: 92px !important;
            margin-left: 0 !important;
            position: relative !important;
            left: 0 !important;
        }

        /*
         * ===== Certificate Check modal =====
         * Lives inside <header>, which is a z-index: 9999 stacking context, so a
         * z-index here only has to beat the header's own children — the whole
         * modal still paints above all page content. Fixed positioning is
         * viewport-relative regardless of the sticky ancestor.
         */
        .ams-cert-modal {
            position: fixed;
            inset: 0;
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .ams-cert-modal.is-open {
            display: flex;
        }

        .ams-cert-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
        }

        .ams-cert-modal__dialog {
            position: relative;
            width: 100%;
            max-width: 760px;
            max-height: 85vh;
            overflow-y: auto;
            background: #ffffff;
            color: #333333;
            border-radius: 10px;
            padding: 40px 32px 32px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.45);
        }

        .ams-cert-modal__close {
            position: absolute;
            top: 10px;
            right: 12px;
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 50%;
            background: transparent;
            color: #555555;
            font-size: 26px;
            line-height: 1;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
        }

        .ams-cert-modal__close:hover {
            background: #eeeeee;
            color: #111111;
        }

        /* ===== Tablet / mobile ===== */
        @media (max-width: 992px) {
            .ams-site-header__inner {
                flex-wrap: wrap;
                row-gap: 16px;
                /* Keep the pre-existing tablet/mobile inset; 7% is desktop-only. */
                padding: 2%;
            }

            /* Row 1: logo left, hamburger right. */
            .ams-site-header__logo-link {
                order: 1;
            }

            .ams-site-header__toggle {
                display: inline-flex;
                order: 2;
            }

            /* Row 2: nav collapses; shown only when toggled open. */
            .ams-site-header__nav {
                order: 3;
                flex: 1 1 100%;
                display: none;
            }

            .ams-site-header__nav.is-open {
                display: block;
            }

            .ams-site-header__nav-list {
                flex-direction: column;
                align-items: flex-start;
                justify-content: flex-start;
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

        {{-- Navigation --}}
        <nav class="ams-site-header__nav" id="ash-primary-nav" aria-label="Primary">
            <ul class="ams-site-header__nav-list">
                {{-- First item: green pill that opens the RTODATA modal below. --}}
                <li>
                    <button
                        type="button"
                        class="ams-site-header__cert-btn"
                        data-ams-cert-open
                        aria-haspopup="dialog"
                        aria-expanded="false"
                        aria-controls="ams-cert-modal"
                    >Certificate Check</button>
                </li>

                @foreach ($navLinks as $link)
                    <li><a class="ams-site-header__nav-link" href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>
    </div>

    {{--
        Certificate Check modal. The RTODATA widget div stays mounted at all times
        (only the modal wrapper is toggled) so the widget script initialises once
        on page load and is never re-injected when the popup opens.
    --}}
    <div
        class="ams-cert-modal"
        id="ams-cert-modal"
        role="dialog"
        aria-modal="true"
        aria-label="Certificate Check"
        hidden
    >
        <div class="ams-cert-modal__backdrop" data-ams-cert-close></div>

        <div class="ams-cert-modal__dialog">
            <button
                type="button"
                class="ams-cert-modal__close"
                data-ams-cert-close
                aria-label="Close Certificate Check"
            >&times;</button>

            <div
                class="rtodata-certificate-check"
                data-api-user="02bj5CZu9GcnlmYAlWZs92Y"
                data-api-key="313a5e9dddfbdec74a069f9854205b4d"
                data-subdomain="amstraining">
            </div>
        </div>
    </div>

    {{--
        This project has no shared layout with a single </body>, so @once is what
        guarantees the widget script is emitted exactly once per response even if
        the header component were ever rendered twice. `defer` keeps it off the
        critical path and runs it after the markup above is parsed.
    --}}
    @once
        <script src="https://amstraining.rtodata.com.au/api/widget/widget.js" defer></script>
    @endonce

    <script>
        // Certificate Check modal: open/close, backdrop + Escape, scroll lock.
        (function () {
            var header = document.currentScript.closest('.ams-site-header');
            if (! header) {
                return;
            }

            var modal = header.querySelector('.ams-cert-modal');
            var openBtn = header.querySelector('[data-ams-cert-open]');

            if (! modal || ! openBtn) {
                return;
            }

            // Remembered so closing restores whatever the page had, not a hard ''.
            var previousOverflow = '';

            var open = function () {
                previousOverflow = document.body.style.overflow;
                modal.hidden = false;
                modal.classList.add('is-open');
                document.body.style.overflow = 'hidden';
                openBtn.setAttribute('aria-expanded', 'true');
                var close = modal.querySelector('.ams-cert-modal__close');
                if (close) {
                    close.focus();
                }
            };

            var close = function () {
                modal.classList.remove('is-open');
                modal.hidden = true;
                document.body.style.overflow = previousOverflow;
                openBtn.setAttribute('aria-expanded', 'false');
                openBtn.focus();
            };

            openBtn.addEventListener('click', open);

            // Backdrop and × both carry data-ams-cert-close.
            modal.addEventListener('click', function (event) {
                if (event.target.closest('[data-ams-cert-close]')) {
                    close();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                    close();
                }
            });
        })();
    </script>

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
