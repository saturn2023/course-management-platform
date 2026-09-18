{{--
    Shared CSS + behaviour for <x-course.accordion-item />.

    Usage — once, wrapping the cards:
        <div class="ams-course-accordion">
            <x-course.accordion-styles />
            <x-course.accordion-item ... />
            <x-course.accordion-item ... />
        </div>

    Separated from the item component so a page with a dozen sections ships one
    copy of the stylesheet and one event binding, not a dozen.
--}}

<style>
    .ams-course-accordion {
        --ams-green: #39B54A;
        --ams-warning: #E8483C;
        --font-body: 'Outfit', 'Segoe UI', Arial, sans-serif;
    }

    .ams-course-accordion * {
        box-sizing: border-box;
    }

    .ams-course-accordion__item {
        background: #FFFFFF;
        border: 0;
        border-radius: 8px;
        margin-bottom: 14px;
        overflow: hidden;
    }

    .ams-course-accordion__item:last-child {
        margin-bottom: 0;
    }

    /*
     * The whole header row is the button so the entire strip is clickable.
     * appearance/background/border are zeroed so no global button style can
     * leak in and change the height.
     */
    .ams-course-accordion__trigger {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        width: 100%;
        margin: 0;
        padding: 22px 25px;
        background: transparent;
        border: 0;
        border-radius: 8px;
        cursor: pointer;
        text-align: left;
        font: inherit;
        color: inherit;
        -webkit-appearance: none;
        appearance: none;
    }

    .ams-course-accordion__trigger:focus-visible {
        outline: 3px solid var(--ams-green);
        outline-offset: -3px;
    }

    /* min-width:0 lets a long heading wrap instead of pushing the icon out. */
    .ams-course-accordion__title {
        min-width: 0;
        margin: 0;
        color: var(--ams-green);
        font-family: var(--font-body);
        font-size: 19px;
        font-weight: 500;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    /* flex-shrink:0 keeps the icon hard right, never overlapping the title. */
    .ams-course-accordion__icon {
        flex: 0 0 auto;
        width: 20px;
        color: var(--ams-green);
        font-family: var(--font-body);
        font-size: 26px;
        font-weight: 400;
        line-height: 1;
        text-align: center;
    }

    /* The glyph lives in CSS so the script only toggles one class. */
    .ams-course-accordion__icon::before {
        content: '+';
    }

    .ams-course-accordion__item.is-open .ams-course-accordion__icon::before {
        content: '\2212'; /* proper minus sign, not a hyphen */
    }

    /*
     * 0fr -> 1fr animates to the panel's natural height, which a max-height
     * guess cannot do without clipping long content or feeling sluggish on
     * short content.
     */
    .ams-course-accordion__panel {
        display: grid;
        grid-template-rows: 0fr;
        transition: grid-template-rows 0.25s ease;
    }

    .ams-course-accordion__item.is-open .ams-course-accordion__panel {
        grid-template-rows: 1fr;
    }

    /*
     * overflow is what makes the 0fr row clip. visibility additionally takes
     * collapsed copy out of the accessibility tree and tab order; the delay
     * holds it visible until the close animation finishes.
     */
    .ams-course-accordion__panel-inner {
        overflow: hidden;
        visibility: hidden;
        transition: visibility 0s linear 0.25s;
    }

    .ams-course-accordion__item.is-open .ams-course-accordion__panel-inner {
        visibility: visible;
        transition-delay: 0s;
    }

    .ams-course-accordion__body {
        padding: 0 25px 25px;
        color: #4a4a4a;
        font-family: var(--font-body);
        font-size: 16px;
        font-weight: 300;
        line-height: 1.6;
    }

    /* The red outlined notice (Entry requirements). */
    .ams-course-accordion__warning {
        margin: 0 0 20px;
        padding: 16px 18px;
        background: #FFFFFF;
        border: 1px solid var(--ams-warning);
        border-radius: 4px;
        color: var(--ams-warning);
        font-size: 15px;
        line-height: 1.55;
    }

    .ams-course-accordion__warning > :first-child { margin-top: 0; }
    .ams-course-accordion__warning > :last-child { margin-bottom: 0; }

    /*
     * Editor output. These are the only rules here that style raw tags, and
     * every one is confined to .ams-course-accordion__content.
     */
    .ams-course-accordion__content > :first-child { margin-top: 0; }
    .ams-course-accordion__content > :last-child { margin-bottom: 0; }

    .ams-course-accordion__content p { margin: 0 0 14px; }

    .ams-course-accordion__content h2,
    .ams-course-accordion__content h3,
    .ams-course-accordion__content h4 {
        margin: 22px 0 10px;
        color: #1a1a1a;
        font-weight: 600;
        line-height: 1.3;
    }

    .ams-course-accordion__content h2 { font-size: 20px; }
    .ams-course-accordion__content h3 { font-size: 18px; }
    .ams-course-accordion__content h4 { font-size: 16px; }

    .ams-course-accordion__content strong,
    .ams-course-accordion__content b { font-weight: 600; }

    .ams-course-accordion__content ul,
    .ams-course-accordion__content ol {
        margin: 0 0 14px;
        padding-left: 22px;
    }

    .ams-course-accordion__content li { margin: 0 0 7px; }

    .ams-course-accordion__content a {
        color: var(--ams-green);
        text-decoration: underline;
    }

    .ams-course-accordion__content a:focus-visible {
        outline: 2px solid var(--ams-green);
        outline-offset: 2px;
    }

    /* Wide/oversized editor content must not widen the card. */
    .ams-course-accordion__content img { max-width: 100%; height: auto; }

    .ams-course-accordion__content table {
        display: block;
        max-width: 100%;
        overflow-x: auto;
    }

    @media (max-width: 540px) {
        .ams-course-accordion__trigger { padding: 18px 20px; gap: 14px; }
        .ams-course-accordion__title { font-size: 17px; }
        .ams-course-accordion__body { padding: 0 20px 20px; font-size: 15px; }
    }
</style>

<script>
    // Independent toggles — opening one section does not close the others.
    // Same IIFE pattern as the header and homepage accordion; no Alpine, which
    // is not loaded on this project's frontend.
    (function () {
        var root = document.currentScript.closest('.ams-course-accordion');
        if (! root) {
            return;
        }

        root.addEventListener('click', function (event) {
            var trigger = event.target.closest('.ams-course-accordion__trigger');
            if (! trigger || ! root.contains(trigger)) {
                return;
            }

            var item = trigger.closest('.ams-course-accordion__item');
            if (! item) {
                return;
            }

            var open = item.classList.toggle('is-open');
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    })();
</script>
