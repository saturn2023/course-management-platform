{{--
    The v1 enrolment registration form, served from GET /enrol/{token}.

    The styles, the fields and the field toggles are partials shared with the v2
    form, so the two never drift apart. What stays here is what is specific to
    v1: the plain POST back to enrol.submit, the server-side error summary, and
    the 8MB file-size modal.
--}}
@include('enrolments.partials.registration-styles')

<x-layout.header />

<div class="ef-wrapper">

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

    @if ($errors->any())
        <div class="ef-error-summary">
            <strong>Please correct the following:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="ef-form" method="POST"
          action="{{ route('enrol.submit', $enrolment->enrolment_token) }}"
          enctype="multipart/form-data">
        @csrf

        <input type="hidden" name="enrolment_id" value="{{ $enrolment->id }}">
        <input type="hidden" name="code" value="{{ $code }}">
        <input type="hidden" name="plan" value="{{ $plan }}">

        @include('enrolments.partials.registration-fields')

        {{-- ================= SUBMIT ================= --}}
        <div class="ef-submit">
            <button type="submit" id="new-enrolment-form">Register</button>
        </div>
    </form>

    {{-- File size modal --}}
    <div id="fileSizeModal" class="tm-modal">
        <div class="tm-modal-content">
            <h3>File Too Large</h3>
            <p>Please upload a file smaller than 8MB.<br>Clear photos (JPG) usually work best.</p>
            <button type="button" id="tmModalClose">OK</button>
        </div>
    </div>
</div>

@include('enrolments.partials.registration-field-toggles')

<script>
(function () {
    // File size validation (8MB max)
    document.querySelectorAll('input[type="file"]').forEach(function (input) {
        input.addEventListener('change', function () {
            var maxBytes = 8 * 1024 * 1024;
            for (var i = 0; i < this.files.length; i++) {
                if (this.files[i].size > maxBytes) {
                    document.getElementById('fileSizeModal').style.display = 'flex';
                    this.value = '';
                    break;
                }
            }
        });
    });

    // Modal close
    var modalClose = document.getElementById('tmModalClose');
    if (modalClose) {
        modalClose.addEventListener('click', function () {
            document.getElementById('fileSizeModal').style.display = 'none';
        });
    }
})();
</script>
