{{--
    Course detail: PRICE / ENROLMENT CARD (right column)

    Usage:
        <x-course.pricing-card :course="$course" />

    - Every value comes from the Course record. The discount percentage is
      derived from normal_price vs price, so it can never disagree with them.
    - Nothing here is sticky. The desktop sidebar makes its own wrapper sticky,
      which keeps this component droppable anywhere (e.g. the mobile position)
      without side effects.
    - :accreditation="false" suppresses the Nationally Recognised Training logo
      under the box. The desktop sidebar uses that so it can render the logo as
      a sibling of the sticky card — inside the card it would travel with it.

    ⚠ Do not write a Blade component tag (an x-… tag) anywhere in this file's
      <style> block, not even in a comment: Blade compiles it there and the
      component's own closing style tag ends this one early.

    - Styles scoped under .ams-course-price.
--}}

@props(['course', 'accreditation' => true])

@php
    $hasDiscount = $course->hasDiscount();
    $discount = $course->discountPercentage();
    $enrolLink = $course->enrolmentLink();
@endphp

<div class="ams-course-price">
    <style>
        .ams-course-price {
            --ams-green: #39B54A;
            --ams-green-dark: #2f9e3f;
            --ams-sale: #E8483C;
            --font-body: 'Outfit', 'Segoe UI', Arial, sans-serif;
            --font-btn: 'azo-sans-web', 'Segoe UI', Arial, sans-serif;
        }

        .ams-course-price * {
            box-sizing: border-box;
        }

        .ams-course-price__box {
            background: #FFFFFF;
            border: 1px solid #E2E2E2;
            border-radius: 8px;
            overflow: hidden;
        }

        .ams-course-price__label {
            margin: 0;
            padding: 18px 20px 14px;
            color: #1a1a1a;
            font-family: var(--font-body);
            font-size: 17px;
            font-weight: 600;
            line-height: 1.3;
            text-align: center;
        }

        .ams-course-price__rule {
            margin: 0 20px;
            border-top: 1px solid #E2E2E2;
        }

        /* Wraps rather than overflowing when all three parts are present. */
        .ams-course-price__amounts {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 16px 20px 20px;
        }

        .ams-course-price__was {
            color: #9a9a9a;
            font-family: var(--font-body);
            font-size: 16px;
            font-weight: 300;
            text-decoration: line-through;
        }

        .ams-course-price__now {
            color: var(--ams-sale);
            font-family: var(--font-body);
            font-size: 22px;
            font-weight: 600;
            line-height: 1.1;
        }

        /* Outline only — transparent centre, as in the reference. */
        .ams-course-price__save {
            padding: 3px 8px;
            border: 1px solid var(--ams-sale);
            border-radius: 3px;
            background: transparent;
            color: var(--ams-sale);
            font-family: var(--font-body);
            font-size: 12px;
            font-weight: 500;
            line-height: 1.3;
            white-space: nowrap;
        }

        .ams-course-price__cta {
            display: block;
            width: 100%;
            padding: 16px 20px;
            background: var(--ams-green);
            border: 0;
            border-radius: 0;
            color: #FFFFFF;
            font-family: var(--font-btn);
            font-size: 16px;
            font-weight: 300;
            line-height: 1;
            letter-spacing: 0.5px;
            text-align: center;
            text-decoration: none;
            text-transform: uppercase;
            transition: background-color 0.15s ease;
        }

        .ams-course-price__cta:hover {
            background: var(--ams-green-dark);
        }

        .ams-course-price__cta:focus-visible {
            outline: 3px solid var(--ams-green);
            outline-offset: 2px;
        }

        /* Rendered when the course has no enrolment destination yet. */
        .ams-course-price__cta.is-disabled {
            background: #B9B9B9;
            cursor: default;
        }

        /* Sits below the box, not inside it. */
        .ams-course-price__accreditation {
            margin: 18px 0 0;
            text-align: center;
        }

        .ams-course-price__accreditation img {
            display: inline-block;
            width: 150px;
            max-width: 100%;
            height: auto;
        }
    </style>

    <div class="ams-course-price__box">
        @if ($hasDiscount)
            <p class="ams-course-price__label">Don&rsquo;t Pay Full Price</p>
            <div class="ams-course-price__rule"></div>
        @endif

        <div class="ams-course-price__amounts">
            @if ($hasDiscount)
                <span class="ams-course-price__was">${{ number_format((float) $course->regular_price, 2) }}</span>
            @endif

            <span class="ams-course-price__now">${{ number_format((float) $course->price, 2) }}</span>

            {{-- Percentage is calculated from the two prices, never typed. --}}
            @if ($discount)
                <span class="ams-course-price__save">Save {{ $discount }}%</span>
            @endif
        </div>

        {{--
            No AMS mapping and no override URL means there is nowhere valid to
            send the user, so the button renders disabled rather than linking to
            a checkout that would fail — the same convention the package cards
            use for a missing URL.
        --}}
        @if ($enrolLink)
            <a class="ams-course-price__cta" href="{{ $enrolLink }}">Enrol now</a>
        @else
            <span class="ams-course-price__cta is-disabled" role="link" aria-disabled="true">Enrol now</span>
        @endif
    </div>

    @if ($accreditation && $course->nationally_accredited)
        <p class="ams-course-price__accreditation">
            <img src="/images/nationally-recognised-training-logo.png" alt="Nationally Recognised Training">
        </p>
    @endif
</div>
