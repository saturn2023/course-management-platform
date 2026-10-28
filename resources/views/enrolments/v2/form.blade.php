{{--
    The v2 enrolment form. Port of the ams2_enrolment_form() shortcode's output.

    The fields are the same partial the v1 form uses, so the two cannot drift.
    What differs is everything around them: the form does not POST to a route,
    it is driven by JavaScript that saves the enrolment first and then uploads
    each document in its own request. That is why there is a status region, a
    <noscript> warning, and no form action.

    The reference reaches the same place by string-replacing two attributes in
    the shared shortcode output, including renaming the old handler's
    `ams_action` field so a no-JavaScript submit cannot reach it. Nothing to
    rename here: this form has no action to fall back to.

    $student   the learner, for the prefill the partial renders
    $languages / $countries  the option lists the partial needs
    $config    the JSON blob the browser script reads
    $limit     Ams2DocumentLimit, for the size the form promises
--}}
@include('enrolments.partials.registration-styles')

<style>
    .ams2-enrol .ams-status { margin: 1rem 0; }

    .ams2-enrol .ams-box {
        padding: 12px 14px;
        border: 1px solid;
        border-radius: 6px;
        margin-bottom: 10px;
        font-size: 14px;
        line-height: 1.5;
    }

    .ams2-enrol .ams-box--ok { background: #edf7ed; border-color: #b7dfb9; color: #1e4620; }
    .ams2-enrol .ams-box--info { background: #f4f6f8; border-color: #dde2e7; color: #223a74; }
    .ams2-enrol .ams-box--bad { background: #fdecea; border-color: #f5c2c0; color: #8a1c14; }

    .ams2-enrol .upload-error {
        display: block;
        color: #d63638;
        font-size: .9em;
        margin-top: .4em;
    }

    .ams2-enrol .upload-error-field { border: 1px solid #d63638 !important; }

    .ams2-enrol .ams-retry {
        margin-right: 8px;
        padding: 10px 18px;
        border: 0;
        border-radius: 4px;
        background: #223a74;
        color: #fff;
        font-size: 15px;
        cursor: pointer;
    }

    .ams2-enrol .ams-continue {
        padding: 10px 18px;
        border: 1px solid #223a74;
        border-radius: 4px;
        background: #fff;
        color: #223a74;
        font-size: 15px;
        cursor: pointer;
    }
</style>

<x-layout.header />

<div class="ef-wrapper ams2-enrol" id="ams2-enrol">

    <h1 class="ef-top-heading">Important!</h1>
    <p class="ef-top-text">
        Please fill in all of the respective fields below using the exact same details you used in the previous payment page.
    </p>

    <h2 class="ef-top-heading">Online Student</h2>
    <p class="ef-top-text">
        Please complete the below registration form.<br>
        If you are completing an online refresher course be sure to include your VET transcript with QR code, USI Number and copy of valid ID.
    </p>

    <p class="ef-important-warning">
        IMPORTANT! If you are experiencing issues with submitting this form please check that the information you have provided matches the information in the email sent to you.
    </p>

    <noscript>
        <div class="ams-box ams-box--bad">
            This form needs JavaScript. Turn it on in your browser, or contact AMS Training.
        </div>
    </noscript>

    {{--
        No action and no method: every request this form makes is built by
        ams2-enrolment.js. @csrf is still here because those requests send the
        token with them.
    --}}
    <form class="ef-form" id="ams2-form" novalidate>
        @csrf

        @include('enrolments.partials.registration-fields')

        {{-- The script moves this directly above the button before first use. --}}
        <div id="ams2-status" class="ams-status" aria-live="polite"></div>

        <div class="ef-submit">
            <button type="submit" id="ams2-submit">Register</button>
        </div>
    </form>
</div>

@include('enrolments.partials.registration-field-toggles')

<script>
    window.AMS2 = @json($config);
</script>

<script>
(function () {
    /*
     * The upload size the form promises has to match what this server will
     * accept, which is not always the configured ceiling. Rewriting the text
     * here keeps the number in one place instead of duplicating the limit into
     * the shared field markup, which the v1 form also renders.
     */
    var limit = {{ Illuminate\Support\Js::from($limit->label()) }};

    document.querySelectorAll('#ams2-enrol .ef-upload-row span, #ams2-enrol .ef-upload-row label')
        .forEach(function (el) {
            el.textContent = el.textContent.replace(/\d+(\.\d+)?\s?(MB|KB)/gi, limit);
        });
})();
</script>

{{--
    Last, because it reads window.AMS2 and expects the field toggles above to
    have wired themselves up already.
--}}
<script src="{{ asset('js/ams2-enrolment.js') }}"></script>
