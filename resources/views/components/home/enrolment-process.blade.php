{{--
    Reusable homepage section: WHAT IS THE ENROLMENT PROCESS?

    Usage:
        <x-home.enrolment-process :steps="$enrolmentSteps" />  (query done in the route)
        <x-home.enrolment-process />                           (falls back to querying)

    - The accordion rows come from the enrolment_steps table, managed at
      Admin → Enrolment Steps. Nothing about the steps is hard-coded: add,
      edit, reorder, deactivate or delete them there and the homepage follows.
      The step count in the subtitle is derived, and the whole section is
      omitted when no step is active.
    - Content is stored as plain text and printed escaped, with newlines turned
      into <br>. No admin-supplied markup is ever rendered.
    - Fonts: Outfit (Google Fonts), loaded in the page <head>. Weights used
      here are 400, 500 and 700 — see resources/views/mockups/hero-banner.blade.php.
    - Colours reuse the AMS palette already used by the other homepage sections
      (#39B54A green, #2C2C2C dark).
    - All styles are scoped under .ams-enrolment and the script is bound to this
      element only, so no other section is affected.
--}}

@props(['steps' => null])

@php
    // The route supplies $steps (same pattern as $courses / $packages). The
    // rescue() fallback only runs when the component is used without them, and
    // returns an empty collection rather than a 500 if the table is missing.
    $enrolmentSteps = $steps ?? rescue(
        fn () => \App\Models\EnrolmentStep::query()->forHomepage()->get(),
        collect(),
        report: false,
    );
@endphp

{{-- Nothing active: render nothing at all, rather than "Our 0 Step Process". --}}
@if ($enrolmentSteps->isNotEmpty())
<section class="ams-enrolment">
    <style>
        /* Scoped to .ams-enrolment — nothing here can reach another section. */
        .ams-enrolment {
            /* Same AMS palette as the sections above. */
            --ams-green: #39B54A;
            --ams-dark: #2C2C2C;
            --font-body: 'Outfit', 'Segoe UI', Arial, sans-serif;

            /*
             * Full-bleed dark band. No top margin: the section above already
             * carries its own 60px bottom margin, and adding more here is what
             * would open a gap between the two.
             */
            width: 100%;
            margin: 0;
            padding: 0;
            background: var(--ams-dark);
        }

        .ams-enrolment * {
            box-sizing: border-box;
        }

        /* Boxed content, same 1360px as the other homepage sections. */
        .ams-enrolment__inner {
            width: 100%;
            max-width: 1360px;
            margin: 0 auto;
            padding: 55px 10px 60px;
        }

        /*
         * The 16px below the heading and the 16px below the subtitle are a
         * matched pair: they put the subtitle midway between the heading and
         * the first accordion card. They were 6px and 26px, which pinned the
         * subtitle up against the heading. The total heading-to-card distance
         * is unchanged at 32px — only the split moved. Keep them equal.
         */
        .ams-enrolment__heading {
            margin: 0 0 16px;
            padding: 0;
            color: var(--ams-green);
            font-family: var(--font-body);
            font-size: 26px;
            font-weight: 700;
            line-height: 1.2;
            text-transform: uppercase;
        }

        /* Matches the heading's 16px above — see the note there. */
        .ams-enrolment__subtitle {
            margin: 0 0 16px;
            padding: 0;
            color: #FFFFFF;
            font-family: var(--font-body);
            font-size: 18px;
            font-weight: 400;
            line-height: 1.4;
        }

        .ams-enrolment__accordion {
            margin: 0;
            padding: 0;
        }

        .ams-enrolment__item {
            background: #FFFFFF;
            border: 0;
            border-radius: 10px;
            margin-bottom: 10px;
            overflow: hidden;
        }

        .ams-enrolment__item:last-child {
            margin-bottom: 0;
        }

        /*
         * The whole header row is the button, so the entire strip is clickable.
         * appearance/background/border are zeroed so no global button style can
         * leak in — this project has none, but the section must not depend on
         * that staying true.
         */
        .ams-enrolment__trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            width: 100%;
            margin: 0;
            padding: 25px;
            background: transparent;
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            text-align: left;
            font: inherit;
            color: inherit;
            -webkit-appearance: none;
            appearance: none;
        }

        .ams-enrolment__trigger:focus-visible {
            outline: 3px solid var(--ams-green);
            outline-offset: -3px;
        }

        /* min-width:0 lets a long title wrap instead of forcing the row wider
           than the card and pushing the icon out. */
        .ams-enrolment__title {
            min-width: 0;
            margin: 0;
            color: #000000;
            font-family: var(--font-body);
            font-size: 20px;
            font-weight: 500;
            line-height: 1.35;
            text-align: left;
            overflow-wrap: anywhere;
        }

        /* flex-shrink:0 keeps the icon its own size and hard right, so it can
           never overlap the title however long the title runs. */
        .ams-enrolment__icon {
            flex: 0 0 auto;
            width: 20px;
            color: var(--ams-green);
            font-family: var(--font-body);
            font-size: 24px;
            font-weight: 400;
            line-height: 1;
            text-align: center;
        }

        /* The glyph lives in CSS so the script only has to toggle one class. */
        .ams-enrolment__icon::before {
            content: '+';
        }

        .ams-enrolment__item.is-open .ams-enrolment__icon::before {
            content: '\2212'; /* proper minus sign, not a hyphen */
        }

        /*
         * 0fr → 1fr animates to the panel's natural height, which a max-height
         * guess cannot do without either clipping long copy or making short
         * copy sluggish.
         */
        .ams-enrolment__content {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows 0.25s ease;
        }

        .ams-enrolment__item.is-open .ams-enrolment__content {
            grid-template-rows: 1fr;
        }

        /*
         * overflow:hidden is what makes the 0fr row actually clip. visibility
         * additionally takes the collapsed copy out of the accessibility tree
         * and tab order; the delay holds it visible until the close finishes.
         */
        .ams-enrolment__content-inner {
            overflow: hidden;
            visibility: hidden;
            transition: visibility 0s linear 0.25s;
        }

        .ams-enrolment__item.is-open .ams-enrolment__content-inner {
            visibility: visible;
            transition-delay: 0s;
        }

        .ams-enrolment__body {
            margin: 0;
            padding: 0 25px 25px;
            color: #4a4a4a;
            font-family: var(--font-body);
            font-size: 17px;
            font-weight: 300;
            line-height: 1.5;
        }

        /* Tablet — same 900px breakpoint the other homepage sections switch at. */
        @media (max-width: 900px) {
            .ams-enrolment__inner {
                padding: 45px 24px 50px;
            }

            .ams-enrolment__title {
                font-size: 18px;
            }
        }

        @media (max-width: 540px) {
            .ams-enrolment__inner {
                padding: 36px 20px 40px;
            }

            .ams-enrolment__heading {
                font-size: 22px;
            }

            /* Size only. The 16px bottom margin is inherited from the base rule
               so the subtitle stays centred between heading and card here too —
               a 20px override here would tip it off-centre again. */
            .ams-enrolment__subtitle {
                font-size: 16px;
            }

            .ams-enrolment__trigger {
                padding: 18px 20px;
                gap: 14px;
            }

            .ams-enrolment__title {
                font-size: 17px;
            }

            .ams-enrolment__body {
                padding: 0 20px 20px;
                font-size: 16px;
            }
        }
    </style>

    <div class="ams-enrolment__inner">
        <h2 class="ams-enrolment__heading">What is the enrolment process?</h2>

        {{-- Derived from the active rows, so adding a sixth step says "6". --}}
        <p class="ams-enrolment__subtitle">
            Our {{ $enrolmentSteps->count() }} Step Process To Enrolling With AMS Training
        </p>

        <div class="ams-enrolment__accordion">
            @foreach ($enrolmentSteps as $step)
                {{-- Panel ids are built from the record id, so they stay stable
                     when steps are reordered, added or removed. --}}
                @php
                    $panelId = 'ams-enrolment-panel-'.$step->id;
                    $triggerId = 'ams-enrolment-trigger-'.$step->id;
                @endphp

                <div class="ams-enrolment__item">
                    <button
                        type="button"
                        class="ams-enrolment__trigger"
                        id="{{ $triggerId }}"
                        aria-expanded="false"
                        aria-controls="{{ $panelId }}"
                    >
                        <span class="ams-enrolment__title">{{ $step->title }}</span>
                        <span class="ams-enrolment__icon" aria-hidden="true"></span>
                    </button>

                    <div
                        class="ams-enrolment__content"
                        id="{{ $panelId }}"
                        role="region"
                        aria-labelledby="{{ $triggerId }}"
                    >
                        <div class="ams-enrolment__content-inner">
                            {{-- Escaped first, then newlines become <br>. Any
                                 markup typed in admin is shown as text. --}}
                            <p class="ams-enrolment__body">{!! nl2br(e($step->content)) !!}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        // Accordion: one panel open at a time. Same pattern as the header's
        // modal script — an IIFE bound to this section only, no globals.
        (function () {
            var section = document.currentScript.closest('.ams-enrolment');
            if (! section) {
                return;
            }

            var items = section.querySelectorAll('.ams-enrolment__item');

            items.forEach(function (item) {
                var trigger = item.querySelector('.ams-enrolment__trigger');
                if (! trigger) {
                    return;
                }

                trigger.addEventListener('click', function () {
                    var willOpen = ! item.classList.contains('is-open');

                    // Close every item first, so opening one closes the rest
                    // and clicking an open one simply leaves it closed.
                    items.forEach(function (other) {
                        other.classList.remove('is-open');
                        var otherTrigger = other.querySelector('.ams-enrolment__trigger');
                        if (otherTrigger) {
                            otherTrigger.setAttribute('aria-expanded', 'false');
                        }
                    });

                    if (willOpen) {
                        item.classList.add('is-open');
                        trigger.setAttribute('aria-expanded', 'true');
                    }
                });
            });
        })();
    </script>
</section>
@endif
