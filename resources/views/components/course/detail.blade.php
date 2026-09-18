{{--
    Course detail page content — everything between the site header and the
    Reviews section.

    Usage:
        <x-course.detail :course="$course" />

    Composes:
        x-course.meta            delivery / duration / accreditation row
        x-course.pricing-card    price + ENROL NOW box
        x-course.accordion-item  one card per populated course field

    ⚠ NEVER write a Blade component tag (an x-… tag) anywhere in this file's
      <style> block — not even inside a CSS comment. Blade compiles component
      syntax everywhere in the file, so the component renders INSIDE the
      stylesheet: its own closing style tag terminates this one early, the rest
      of the CSS is printed on the page as text, and the component is duplicated.
      Refer to other components by class name or file path only.

    - Nothing about a specific course is hard-coded. The accordion is driven by
      Course::detailSections(), which returns only fields that have content, so
      an unfilled section never renders an empty card.
    - ONE layout grid and ONE sidebar span the whole page, so the price card is
      a single element that stays beside the content from the title all the way
      to the last accordion.
    - Styles scoped under .ams-course.
--}}

@props(['course'])

@php
    $sections = $course->detailSections();
@endphp

<div class="ams-course">
    <style>
        .ams-course {
            --ams-green: #39B54A;
            --ams-grey-band: #F2F2F2;
            --font-body: 'Outfit', 'Segoe UI', Arial, sans-serif;

            /*
             * Sticky offset for the sidebar. The site header is sticky at the
             * top of the viewport and settles at 95px once it shrinks on
             * scroll (its inner element carries min-height 95px in that
             * state). 115px clears it with a 20px breathing gap. Update this
             * if the header height ever changes.
             */
            --ams-header-offset: 115px;

            display: block;
            width: 100%;
            background: #FFFFFF;
        }

        .ams-course * {
            box-sizing: border-box;
        }

        /* Same boxed width as the homepage sections and the footer. */
        .ams-course__inner {
            width: 100%;
            max-width: 1360px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /*
         * ONE grid for the whole page: the left column holds both the intro and
         * the accordion, the right column holds the single sidebar. That is why
         * there is only one price card and why it keeps pace with the reader
         * from the title down to the last section.
         *
         * minmax(0, 1fr) rather than 1fr: a plain 1fr has an auto minimum, so a
         * long unbroken string in the editor content would widen the column and
         * push the sidebar off-screen.
         *
         * NOTE: no overflow property on this element or any ancestor — any
         * value other than `visible` silently disables the sticky sidebar.
         */
        .ams-course__layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 280px;
            gap: 50px;
            align-items: start;
        }

        .ams-course__main {
            min-width: 0;
        }

        /*
         * The sidebar itself is NOT sticky — it is a plain, full-height column
         * holding two independent children.
         *
         * align-self:stretch overrides the grid's align-items:start on purpose:
         * a sticky child can only travel inside its parent's box, so a
         * start-aligned (content-height) sidebar would let the price card move a
         * few pixels and immediately stop. Stretching the column to the row
         * height is what gives the card a full-page track — and because
         * .ams-course__layout ends with the course content, the card still can
         * never reach Reviews or the footer (siblings of .ams-course, not
         * descendants).
         *
         * padding-top matches .ams-course__intro's 50px top padding, so the card
         * starts level with the course code rather than hard against the header.
         */
        .ams-course__sidebar {
            align-self: stretch;
            padding-top: 50px;
        }

        /*
         * THE STICKY ELEMENT — the price card, and nothing else. The NRT logo
         * below is a sibling in normal flow, so it stays where it was rendered
         * and scrolls away while the card keeps pace with the reader.
         */
        .ams-course__price-card {
            position: sticky;
            top: var(--ams-header-offset);
        }

        /* Normal document flow — never sticky, fixed or absolute. */
        .ams-course__nrt-logo {
            position: static;
            margin-top: 18px;
            text-align: center;
        }

        .ams-course__nrt-logo img {
            display: inline-block;
            width: 150px;
            max-width: 100%;
            height: auto;
        }

        .ams-course__intro {
            padding: 50px 0 55px;
        }

        /*
         * The light-grey details band. The section itself is inside the boxed
         * left column, but the grey has to run the full viewport width and
         * behind the sidebar — so it is painted with a very wide spread shadow
         * and then clipped horizontally. clip-path stops the shadow creating
         * any horizontal overflow, which a 100vw width would (100vw includes
         * the scrollbar).
         */
        .ams-course__details {
            padding: 45px 0 60px;
            background: var(--ams-grey-band);
            box-shadow: 0 0 0 100vmax var(--ams-grey-band);
            clip-path: inset(0 -100vmax);
        }

        .ams-course__code {
            margin: 0 0 10px;
            color: var(--ams-green);
            font-family: var(--font-body);
            font-size: 20px;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .ams-course__title {
            margin: 0 0 24px;
            color: #111111;
            font-family: var(--font-body);
            font-size: 42px;
            font-weight: 500;
            line-height: 1.12;
        }

        /* Confined to the left column by living inside .ams-course__main. */
        .ams-course__divider {
            margin: 32px 0;
            border-top: 1px solid #DEDEDE;
        }

        .ams-course__subheading {
            margin: 0 0 16px;
            color: #111111;
            font-family: var(--font-body);
            font-size: 25px;
            font-weight: 600;
            line-height: 1.25;
        }

        .ams-course__overview {
            color: #4a4a4a;
            font-family: var(--font-body);
            font-size: 17px;
            font-weight: 300;
            line-height: 1.7;
        }

        /* Editor output, confined to .ams-course__overview. */
        .ams-course__overview > :first-child { margin-top: 0; }
        .ams-course__overview > :last-child { margin-bottom: 0; }
        .ams-course__overview p { margin: 0 0 16px; }
        .ams-course__overview strong,
        .ams-course__overview b { font-weight: 600; }

        .ams-course__overview h2,
        .ams-course__overview h3 {
            margin: 24px 0 10px;
            color: #1a1a1a;
            font-weight: 600;
            line-height: 1.3;
        }

        .ams-course__overview h2 { font-size: 21px; }
        .ams-course__overview h3 { font-size: 18px; }

        .ams-course__overview ul,
        .ams-course__overview ol {
            margin: 0 0 16px;
            padding-left: 22px;
        }

        .ams-course__overview li { margin: 0 0 7px; }

        .ams-course__overview a {
            color: var(--ams-green);
            text-decoration: underline;
        }

        .ams-course__overview img { max-width: 100%; height: auto; }

        /*
         * Mobile-only placement of the price card. Exactly one of this and the
         * sidebar is ever displayed, so the page never shows two price cards.
         */
        .ams-course__price-mobile {
            display: none;
        }

        /* Tablet/mobile — the same 900px breakpoint the homepage sections use. */
        @media (max-width: 900px) {
            .ams-course__layout {
                grid-template-columns: minmax(0, 1fr);
                gap: 0;
            }

            /*
             * One column: a cramped sticky rail would be worse than useless, so
             * the sidebar leaves the flow and the static copy above takes over.
             * That also puts the price and ENROL NOW directly under the meta
             * row, which is the order a reader on a phone wants.
             */
            .ams-course__sidebar {
                display: none;
                padding-top: 0;
            }

            /* Sticky off in the single-column layout. */
            .ams-course__price-card {
                position: static;
            }

            .ams-course__price-mobile {
                display: block;
                margin: 28px 0 0;
            }

            .ams-course__intro { padding: 34px 0 40px; }
            .ams-course__details { padding: 34px 0 46px; }
            .ams-course__title { font-size: 32px; }
        }

        @media (max-width: 540px) {
            .ams-course__code { font-size: 17px; }
            .ams-course__title { font-size: 26px; }
            .ams-course__subheading { font-size: 20px; }
            .ams-course__overview { font-size: 16px; }
            .ams-course__divider { margin: 26px 0; }
        }
    </style>

    <div class="ams-course__inner">
        <div class="ams-course__layout">
            <div class="ams-course__main">
                <section class="ams-course__intro">
                    @if (filled($course->code))
                        <p class="ams-course__code">{{ $course->code }}</p>
                    @endif

                    <h1 class="ams-course__title">{{ $course->title }}</h1>

                    <x-course.meta :course="$course" />

                    {{-- Shown only at <=900px, where the sidebar is hidden. --}}
                    <div class="ams-course__price-mobile">
                        <x-course.pricing-card :course="$course" />
                    </div>

                    @if (filled($course->overview))
                        <div class="ams-course__divider"></div>

                        <h2 class="ams-course__subheading">Course Overview</h2>

                        {{--
                            Rich text written by an administrator in Filament's
                            editor, so it is printed as markup by design. The
                            trust boundary is the admin panel: no public input
                            reaches this column.
                        --}}
                        <div class="ams-course__overview">{!! $course->overview !!}</div>
                    @endif
                </section>

                @if (! empty($sections))
                    <section class="ams-course__details">
                        <div class="ams-course-accordion">
                            <x-course.accordion-styles />

                            @foreach ($sections as $index => $section)
                                <x-course.accordion-item
                                    :title="$section['title']"
                                    :content="$section['content']"
                                    :warning="$section['warning']"
                                    :open="$index === 0"
                                />
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            {{--
                Two separate children: the price card sticks, the logo does not.
                The card is asked NOT to render its own accreditation logo here
                (:accreditation="false") — otherwise the logo would be inside the
                sticky box and would travel with it. The mobile copy above keeps
                the logo inside the card, where nothing is sticky.
            --}}
            <aside class="ams-course__sidebar">
                <div class="ams-course__price-card">
                    <x-course.pricing-card :course="$course" :accreditation="false" />
                </div>

                @if ($course->nationally_accredited)
                    <div class="ams-course__nrt-logo">
                        <img src="/images/nationally-recognised-training-logo.png" alt="Nationally Recognised Training">
                    </div>
                @endif
            </aside>
        </div>
    </div>
</div>
