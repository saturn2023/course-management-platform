{{--
    Course detail: ONE ACCORDION CARD

    Usage:
        <x-course.accordion-item title="Assessment" :content="$course->assessment" />
        <x-course.accordion-item title="Entry requirements"
                                 :content="..." :warning="..." :open="true" />

    - Renders nothing at all when there is no content, so a course that has not
      filled in a section never shows an empty card (see $content guard below).
    - Sections toggle independently; opening one does not close another, which
      is what the reference screenshots show.
    - `warning` renders the red outlined notice above the body — used by Entry
      requirements, but available to any section.
    - Styles/behaviour live in <x-course.accordion-styles /> so N cards share one
      copy rather than repeating the CSS and script per item.
--}}

@props([
    'title',
    'content' => null,
    'warning' => null,
    'open' => false,
])

@if (filled($content) || filled($warning))
    @php
        // Stable per-card ids from the title, so reordering or removing a
        // section never repoints another card's aria-controls.
        $uid = 'ams-course-section-'.\Illuminate\Support\Str::slug($title);
    @endphp

    <div class="ams-course-accordion__item @if ($open) is-open @endif">
        <button
            type="button"
            class="ams-course-accordion__trigger"
            id="{{ $uid }}-trigger"
            aria-expanded="{{ $open ? 'true' : 'false' }}"
            aria-controls="{{ $uid }}-panel"
        >
            <span class="ams-course-accordion__title">{{ $title }}</span>
            <span class="ams-course-accordion__icon" aria-hidden="true"></span>
        </button>

        <div
            class="ams-course-accordion__panel"
            id="{{ $uid }}-panel"
            role="region"
            aria-labelledby="{{ $uid }}-trigger"
        >
            <div class="ams-course-accordion__panel-inner">
                <div class="ams-course-accordion__body">
                    {{--
                        warning/content are rich text written by an administrator
                        in Filament's editor, so they are printed as markup by
                        design — escaping them would show the tags. The trust
                        boundary is the admin panel: no public input reaches
                        these columns.
                    --}}
                    @if (filled($warning))
                        <div class="ams-course-accordion__warning">{!! $warning !!}</div>
                    @endif

                    @if (filled($content))
                        <div class="ams-course-accordion__content">{!! $content !!}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
