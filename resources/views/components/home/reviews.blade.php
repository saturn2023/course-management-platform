{{--
    Reusable homepage section: CUSTOMER REVIEWS (below the Enrolment Process)

    Usage:
        <x-home.reviews :reviews="$reviews" />   (query done in the route)
        <x-home.reviews />                        (falls back to querying)

    - Cards come from the reviews table, managed at Admin → Reviews. Nothing is
      hard-coded: add, edit, reorder, deactivate or delete there and the
      homepage follows. There is no Google API — `source` is a label the admin
      types, so a review from anywhere is described truthfully.
    - Review text is stored as plain text and printed escaped, with newlines
      turned into <br>. No admin-supplied markup is ever rendered.
    - Fonts: normalidad-wide (heading, Adobe Fonts kit sat8ost) and Outfit
      (everything else, Google Fonts). Both already loaded in the page <head>.
    - Colours reuse the AMS palette used by the other homepage sections
      (#39B54A green).
    - Styles are scoped under .ams-reviews and the script is bound to this
      element only, so no other section is affected.
--}}

@props(['reviews' => null])

@php
    // The route supplies $reviews (same pattern as $courses / $packages /
    // $enrolmentSteps). The rescue() fallback only runs when the component is
    // used without them, and returns an empty collection rather than a 500.
    $reviewItems = $reviews ?? rescue(
        fn () => \App\Models\Review::query()->forHomepage()->get(),
        collect(),
        report: false,
    );
@endphp

{{-- No active reviews: render nothing rather than an empty row of cards. --}}
@if ($reviewItems->isNotEmpty())
<section class="ams-reviews">
    <style>
        /* Scoped to .ams-reviews — nothing here can reach another section. */
        .ams-reviews {
            --ams-green: #39B54A;
            --ams-star: #FBBC04;
            --font-display: 'normalidad-wide', 'Segoe UI', Arial, sans-serif;
            --font-body: 'Outfit', 'Segoe UI', Arial, sans-serif;

            width: 100%;
            margin: 0;
            padding: 80px 0;
            background: #FFFFFF;
        }

        .ams-reviews * {
            box-sizing: border-box;
        }

        /*
         * 1900px, the widest boxed width already used on this page (the
         * intro-text section), so five to six cards fit across a large desktop
         * as in the reference. The 1360px accordion width would only fit four.
         *
         * NB: never write a Blade component tag inside this <style> block —
         * Blade compiles component syntax anywhere in the file, including
         * inside CSS comments, and would render that whole section in here.
         */
        .ams-reviews__inner {
            width: 100%;
            max-width: 1900px;
            margin: 0 auto;
            padding: 0 2%;
        }

        .ams-reviews__heading {
            margin: 0 auto 14px;
            padding: 0;
            max-width: 1100px;
            color: var(--ams-green);
            font-family: var(--font-display);
            font-size: 34px;
            font-weight: 600;
            line-height: 1.25;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1.1px;
            word-spacing: 9px;
        }

        .ams-reviews__subtitle {
            margin: 0 auto 40px;
            padding: 0;
            max-width: 720px;
            color: #4a4a4a;
            font-family: var(--font-body);
            font-size: 18px;
            font-weight: 300;
            line-height: 1.3;
            text-align: center;
        }

        /* Carousel = the track plus its two edge buttons. */
        .ams-reviews__carousel {
            position: relative;
        }

        /*
         * Native horizontal scrolling with snap points: no carousel library,
         * and touch/trackpad swiping works for free. The buttons below just
         * drive scrollBy() on this element.
         */
        .ams-reviews__track {
            display: flex;
            flex-direction: row;
            gap: 16px;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            /* Room for the cards' shadow, which overflow would otherwise clip. */
            padding: 4px 0 12px;
            scrollbar-width: thin;
            -webkit-overflow-scrolling: touch;
        }

        .ams-reviews__track:focus-visible {
            outline: 3px solid var(--ams-green);
            outline-offset: 4px;
        }

        /*
         * align-items: stretch (the flex default) is what gives every card in a
         * row the same height regardless of review length.
         */
        .ams-reviews__card {
            flex: 0 0 300px;
            max-width: 300px;
            scroll-snap-align: start;

            display: flex;
            flex-direction: column;
            gap: 12px;

            padding: 20px;
            background: #F7F7F7;
            border: 0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        }

        .ams-reviews__header {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 12px;
        }

        /*
         * One box for both cases: an <img> fills it, and with no image the
         * flex centring draws the initial instead. Same size either way, so
         * card height never depends on whether a photo was uploaded.
         */
        .ams-reviews__avatar {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--ams-green);
            color: #FFFFFF;
            font-family: var(--font-body);
            font-size: 18px;
            font-weight: 500;
            line-height: 1;
            text-transform: uppercase;
        }

        .ams-reviews__avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* min-width:0 lets a long name ellipsis instead of stretching the card. */
        .ams-reviews__reviewer {
            min-width: 0;
            flex: 1 1 auto;
        }

        .ams-reviews__name {
            margin: 0;
            color: #1a1a1a;
            font-family: var(--font-body);
            font-size: 15px;
            font-weight: 500;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ams-reviews__date {
            margin: 2px 0 0;
            color: #8a8a8a;
            font-family: var(--font-body);
            font-size: 12px;
            font-weight: 300;
            line-height: 1.3;
        }

        /* The source label, top-right. flex-shrink:0 keeps it off the name. */
        .ams-reviews__source {
            flex: 0 0 auto;
            align-self: flex-start;
            color: #8a8a8a;
            font-family: var(--font-body);
            font-size: 11px;
            font-weight: 400;
            line-height: 1.4;
            letter-spacing: 0.3px;
        }

        .ams-reviews__stars {
            display: flex;
            flex-direction: row;
            gap: 2px;
            font-size: 15px;
            line-height: 1;
        }

        .ams-reviews__star {
            color: var(--ams-star);
        }

        .ams-reviews__star--empty {
            color: #D5D5D5;
        }

        /*
         * Clamped to five lines. The clamp is removed by .is-expanded rather
         * than by inline styles, so the script only toggles one class.
         */
        .ams-reviews__text {
            margin: 0;
            color: #4a4a4a;
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 300;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 5;
            line-clamp: 5;
            overflow: hidden;
            overflow-wrap: anywhere;
        }

        .ams-reviews__card.is-expanded .ams-reviews__text {
            -webkit-line-clamp: unset;
            line-clamp: unset;
            overflow: visible;
        }

        /*
         * Hidden by default and revealed by the script only on cards whose text
         * actually overflows, so short reviews get no pointless control.
         */
        .ams-reviews__read-more {
            display: none;
            align-self: flex-start;
            margin: 0;
            padding: 0;
            background: transparent;
            border: 0;
            cursor: pointer;
            color: #8a8a8a;
            font-family: var(--font-body);
            font-size: 13px;
            font-weight: 400;
            line-height: 1.3;
            text-decoration: underline;
            -webkit-appearance: none;
            appearance: none;
        }

        .ams-reviews__read-more.is-shown {
            display: inline-block;
        }

        .ams-reviews__read-more:hover {
            color: var(--ams-green);
        }

        .ams-reviews__read-more:focus-visible {
            outline: 2px solid var(--ams-green);
            outline-offset: 2px;
        }

        /* Prev/next, centred on the track's vertical midpoint. */
        .ams-reviews__nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            padding: 0;
            background: #FFFFFF;
            border: 1px solid #E0E0E0;
            border-radius: 50%;
            cursor: pointer;
            color: #4a4a4a;
            font-family: var(--font-body);
            font-size: 20px;
            line-height: 1;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.10);
            -webkit-appearance: none;
            appearance: none;
        }

        .ams-reviews__nav:hover {
            color: var(--ams-green);
            border-color: var(--ams-green);
        }

        .ams-reviews__nav:focus-visible {
            outline: 3px solid var(--ams-green);
            outline-offset: 2px;
        }

        /* Faded, not removed, so the row does not reflow at the ends. */
        .ams-reviews__nav[disabled] {
            opacity: 0.35;
            cursor: default;
        }

        .ams-reviews__nav--prev {
            left: -8px;
        }

        .ams-reviews__nav--next {
            right: -8px;
        }

        /* Tablet — same 900px breakpoint the other homepage sections switch at. */
        @media (max-width: 900px) {
            .ams-reviews {
                padding: 60px 0;
            }

            .ams-reviews__heading {
                font-size: 28px;
                word-spacing: 5px;
            }

            .ams-reviews__subtitle {
                margin-bottom: 32px;
            }

            .ams-reviews__card {
                flex-basis: 280px;
                max-width: 280px;
            }
        }

        @media (max-width: 540px) {
            .ams-reviews {
                padding: 44px 0;
            }

            .ams-reviews__inner {
                padding: 0 20px;
            }

            /* Tight spacing so the long heading wraps cleanly instead of
               pushing past the viewport edge. */
            .ams-reviews__heading {
                font-size: 24px;
                letter-spacing: 0.5px;
                word-spacing: 3px;
            }

            .ams-reviews__subtitle {
                font-size: 16px;
                margin-bottom: 26px;
            }

            /*
             * One card at a time: the width is the viewport minus the section's
             * 20px gutters, so a card never sits half off-screen.
             */
            .ams-reviews__card {
                flex-basis: calc(100vw - 40px);
                max-width: calc(100vw - 40px);
            }

            /* The arrows would sit over the cards at this width, so they move
               below the track instead. */
            .ams-reviews__carousel {
                padding-bottom: 52px;
            }

            .ams-reviews__nav {
                top: auto;
                bottom: 0;
                transform: none;
            }

            .ams-reviews__nav--prev {
                left: calc(50% - 48px);
            }

            .ams-reviews__nav--next {
                right: calc(50% - 48px);
            }
        }
    </style>

    <div class="ams-reviews__inner">
        <h2 class="ams-reviews__heading">25+ Years of experience, dedication and service.</h2>

        <p class="ams-reviews__subtitle">Experience the difference quality online training can provide. We&rsquo;ll help keep your qualifications up to date.</p>

        <div class="ams-reviews__carousel">
            <button type="button" class="ams-reviews__nav ams-reviews__nav--prev" aria-label="Previous reviews">&lsaquo;</button>

            <div class="ams-reviews__track" tabindex="0" role="group" aria-label="Customer reviews">
                @foreach ($reviewItems as $review)
                    @php
                        $avatarUrl = $review->avatarUrl();
                        $stars = $review->starCount();
                        $textId = 'ams-review-text-'.$review->id;
                    @endphp

                    <article class="ams-reviews__card">
                        <div class="ams-reviews__header">
                            <div class="ams-reviews__avatar">
                                @if ($avatarUrl)
                                    <img src="{{ $avatarUrl }}" alt="">
                                @else
                                    {{-- No photo uploaded: first initial instead. --}}
                                    {{ $review->initial() }}
                                @endif
                            </div>

                            <div class="ams-reviews__reviewer">
                                <p class="ams-reviews__name">{{ $review->reviewer_name }}</p>

                                @if ($review->review_date)
                                    <p class="ams-reviews__date">{{ $review->review_date->format('j M Y') }}</p>
                                @endif
                            </div>

                            @if (filled($review->source))
                                <span class="ams-reviews__source">{{ $review->source }}</span>
                            @endif
                        </div>

                        {{-- One image role carries the rating, so a screen reader
                             hears "4 out of 5 stars", not ten star characters. --}}
                        <div class="ams-reviews__stars" role="img" aria-label="{{ $stars }} out of 5 stars">
                            @for ($i = 1; $i <= 5; $i++)
                                <span class="ams-reviews__star @if ($i > $stars) ams-reviews__star--empty @endif" aria-hidden="true">&#9733;</span>
                            @endfor
                        </div>

                        {{-- Escaped first, then newlines become <br>. Any markup
                             typed in admin is shown as text. --}}
                        <p class="ams-reviews__text" id="{{ $textId }}">{!! nl2br(e($review->review_text)) !!}</p>

                        @if (filled($review->source_url))
                            {{-- A source URL was given: send people to the original
                                 instead of expanding a copy of it. --}}
                            <a
                                class="ams-reviews__read-more is-shown"
                                href="{{ $review->source_url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >Read more</a>
                        @else
                            {{-- Revealed by the script only if the text is clamped. --}}
                            <button
                                type="button"
                                class="ams-reviews__read-more"
                                aria-expanded="false"
                                aria-controls="{{ $textId }}"
                            >Read more</button>
                        @endif
                    </article>
                @endforeach
            </div>

            <button type="button" class="ams-reviews__nav ams-reviews__nav--next" aria-label="More reviews">&rsaquo;</button>
        </div>
    </div>

    <script>
        // Reviews carousel + read-more. Same pattern as the header and
        // enrolment scripts — an IIFE bound to this section only, no globals.
        (function () {
            var section = document.currentScript.closest('.ams-reviews');
            if (! section) {
                return;
            }

            var track = section.querySelector('.ams-reviews__track');
            var prev = section.querySelector('.ams-reviews__nav--prev');
            var next = section.querySelector('.ams-reviews__nav--next');

            /* ---- Read more ------------------------------------------------ */

            section.querySelectorAll('.ams-reviews__card').forEach(function (card) {
                var text = card.querySelector('.ams-reviews__text');
                var toggle = card.querySelector('button.ams-reviews__read-more');

                // Anchors (source_url) are already visible and need no toggle.
                if (! text || ! toggle) {
                    return;
                }

                // Only offer the control when the copy is actually clamped.
                if (text.scrollHeight <= text.clientHeight + 1) {
                    return;
                }

                toggle.classList.add('is-shown');

                toggle.addEventListener('click', function () {
                    var expanded = card.classList.toggle('is-expanded');
                    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                    toggle.textContent = expanded ? 'Show less' : 'Read more';
                });
            });

            /* ---- Carousel -------------------------------------------------- */

            if (! track || ! prev || ! next) {
                return;
            }

            // Scroll by whole cards, measured from the DOM so the step stays
            // correct at every breakpoint without repeating the widths here.
            var step = function () {
                var card = track.querySelector('.ams-reviews__card');
                if (! card) {
                    return track.clientWidth;
                }
                var gap = parseInt(window.getComputedStyle(track).columnGap, 10) || 0;
                return card.getBoundingClientRect().width + gap;
            };

            var syncButtons = function () {
                var max = track.scrollWidth - track.clientWidth;
                prev.disabled = track.scrollLeft <= 1;
                next.disabled = track.scrollLeft >= max - 1;
            };

            prev.addEventListener('click', function () {
                track.scrollBy({ left: -step(), behavior: 'smooth' });
            });

            next.addEventListener('click', function () {
                track.scrollBy({ left: step(), behavior: 'smooth' });
            });

            track.addEventListener('scroll', syncButtons);
            window.addEventListener('resize', syncButtons);
            syncButtons();
        })();
    </script>
</section>
@endif
