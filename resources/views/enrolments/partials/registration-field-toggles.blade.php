{{--
    Show-and-hide behaviour for the conditional sections of the registration
    form fields.

    Shared by the v1 form (enrolments.registration-form) and the v2 form. Only
    field toggles belong here. The v1 form's 8MB file-size check and its modal
    stay with v1: the v2 form reads each file into memory, checks its magic
    bytes and allows 10MB, so running both would fight over the same inputs.
--}}
<script>
(function () {
    // Postal address toggle
    var postalType = document.getElementById('ef_postal_address_type');
    if (postalType) {
        postalType.addEventListener('change', function () {
            document.getElementById('ef_postal_fields').style.display =
                this.value === 'different' ? 'block' : 'none';
        });
    }

    // Qualification checkboxes toggle
    var otherQual = document.getElementById('ef_other_qualifications');
    if (otherQual) {
        otherQual.addEventListener('change', function () {
            document.getElementById('ef_qualification_details').style.display =
                this.value === 'Yes' ? 'block' : 'none';
        });
    }

    // Disability checkboxes toggle
    var disability = document.getElementById('ef_disability');
    if (disability) {
        disability.addEventListener('change', function () {
            document.getElementById('ef_disability_details').style.display =
                this.value === 'Y' ? 'block' : 'none';
        });
    }

    // Individual needs textarea toggle
    var indNeeds = document.getElementById('ef_individual_needs');
    if (indNeeds) {
        indNeeds.addEventListener('change', function () {
            document.getElementById('ef_individual_needs_details').style.display =
                this.value === 'Yes' ? 'block' : 'none';
        });
    }
})();
</script>
