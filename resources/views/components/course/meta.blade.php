{{--
    Course detail: META ROW (delivery type, duration, accreditation)

    Usage:
        <x-course.meta :course="$course" />

    - Each item renders only when the course has that value, so a course with no
      duration simply shows one fewer item rather than an empty slot.
    - Icons are inline SVG, not emoji and not an icon font: no new dependency,
      they inherit currentColor, and they stay crisp at any size.
    - Styles scoped under .ams-course-meta.
--}}

@props(['course'])

<div class="ams-course-meta">
    <style>
        .ams-course-meta {
            --ams-green: #39B54A;
            --font-body: 'Outfit', 'Segoe UI', Arial, sans-serif;

            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            align-items: center;
            gap: 14px 34px;
        }

        .ams-course-meta * {
            box-sizing: border-box;
        }

        .ams-course-meta__item {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: #3a3a3a;
            font-family: var(--font-body);
            font-size: 16px;
            font-weight: 400;
            line-height: 1.3;
        }

        /* flex-shrink:0 so the icon keeps its size when the label wraps. */
        .ams-course-meta__icon {
            flex: 0 0 auto;
            width: 19px;
            height: 19px;
            color: var(--ams-green);
        }

        .ams-course-meta__logo {
            flex: 0 0 auto;
            width: 26px;
            height: auto;
        }

        @media (max-width: 540px) {
            .ams-course-meta {
                gap: 12px 22px;
            }

            .ams-course-meta__item {
                font-size: 15px;
            }
        }
    </style>

    @if (filled($course->course_type))
        <span class="ams-course-meta__item">
            {{-- Map pin --}}
            <svg class="ams-course-meta__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/>
                <circle cx="12" cy="10" r="3"/>
            </svg>
            {{ $course->course_type }}
        </span>
    @endif

    @if (filled($course->duration))
        <span class="ams-course-meta__item">
            {{-- Clock --}}
            <svg class="ams-course-meta__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 7v5l3 2"/>
            </svg>
            {{ $course->duration }}
        </span>
    @endif

    @if ($course->nationally_accredited)
        <span class="ams-course-meta__item">
            <img class="ams-course-meta__logo" src="/images/nationally-recognised-training-logo.png" alt="">
            Nationally Accredited
        </span>
    @endif
</div>
