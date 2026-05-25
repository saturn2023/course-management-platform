
<style>
    .ef-wrapper {
        max-width: 760px;
        margin: 0 auto;
        padding: 30px 20px 60px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #333;
        background: #fff;
    }

    .ef-top-heading {
        color: #1a3d6d;
        font-size: 28px;
        font-weight: 700;
        margin: 25px 0 10px;
    }

    .ef-top-text {
        font-size: 15px;
        line-height: 1.6;
        color: #444;
        margin: 0 0 20px;
    }

    .ef-important-warning {
        color: #d9534f;
        font-size: 15px;
        line-height: 1.5;
        margin: 20px 0 30px;
    }

    .ef-title {
        display: none;
    }

    .ef-form {
        margin-top: 30px;
    }

    .ef-section {
        border: 1px solid #ddd;
        margin-bottom: 25px;
        background: #fff;
    }

    .ef-legend {
        background: #f5f5f5;
        padding: 12px 18px;
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #555;
        border-bottom: 1px solid #e3e3e3;
    }

    .ef-row {
        display: flex;
        border-bottom: 1px solid #e3e3e3;
        align-items: stretch;
    }

    .ef-row:last-child {
        border-bottom: none;
    }

    .ef-label {
        flex: 0 0 35%;
        background: #f7f7f7;
        padding: 14px 18px;
        font-size: 14px;
        color: #555;
        display: flex;
        align-items: center;
        border-right: 1px solid #e3e3e3;
    }

    .ef-field {
        flex: 1;
        padding: 10px 14px;
        display: flex;
        align-items: center;
        background: #fff;
    }

    .ef-field input[type="text"],
    .ef-field input[type="email"],
    .ef-field input[type="tel"],
    .ef-field input[type="date"],
    .ef-field input[type="number"],
    .ef-field select,
    .ef-field textarea {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 3px;
        background: #fff;
        font-size: 14px;
        color: #333;
        font-family: inherit;
        box-sizing: border-box;
    }

    .ef-field input:focus,
    .ef-field select:focus,
    .ef-field textarea:focus {
        outline: none;
        border-color: #999;
    }

    .ef-req {
        color: #d9534f;
        margin-left: 2px;
    }

    .ef-row--full {
        display: block;
    }

    .ef-field--full {
        padding: 14px 18px;
        background: #fff;
    }

    .ef-sublabel {
        font-size: 14px;
        color: #555;
        margin: 0 0 10px;
        font-weight: 600;
    }

    .ef-cb-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .ef-cb-group label {
        font-size: 14px;
        color: #444;
        font-weight: normal;
        display: flex;
        align-items: flex-start;
        gap: 8px;
        cursor: pointer;
    }

    .ef-cb-group input[type="checkbox"] {
        margin-top: 3px;
    }

    .ef-upload-row {
        padding: 16px 18px;
        border-bottom: 1px solid #e3e3e3;
        background: #fff;
    }

    .ef-upload-row:last-child {
        border-bottom: none;
    }

    .ef-upload-row label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: #333;
        margin-bottom: 4px;
    }

    .ef-upload-row span {
        display: block;
        font-size: 13px;
        color: #777;
        margin-bottom: 10px;
    }

    .ef-upload-row input[type="file"] {
        font-size: 13px;
    }

    .ef-privacy-notice {
        padding: 18px;
        font-size: 14px;
        line-height: 1.6;
        color: #444;
        border-bottom: 1px solid #e3e3e3;
        background: #fff;
    }

    .ef-privacy-notice strong {
        display: block;
        font-size: 15px;
        margin-bottom: 8px;
        color: #333;
    }

    .ef-privacy-notice ul {
        margin: 8px 0;
        padding-left: 22px;
    }

    .ef-privacy-notice li {
        margin: 3px 0;
    }

    .ef-privacy-notice a {
        color: #2a7ca8;
        text-decoration: underline;
    }

    .ef-check-row {
        padding: 14px 18px;
        border-bottom: 1px solid #e3e3e3;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: #fff;
    }

    .ef-check-row:last-child {
        border-bottom: none;
    }

    .ef-check-row input[type="checkbox"] {
        margin-top: 3px;
        flex: 0 0 auto;
    }

    .ef-check-row label {
        font-size: 13px;
        line-height: 1.5;
        color: #444;
        font-weight: 600;
        cursor: pointer;
    }

    .ef-submit {
        margin-top: 20px;
    }

    .ef-submit button {
        width: 100%;
        padding: 16px 20px;
        background: #87c65a;
        color: #fff;
        border: none;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        border-radius: 3px;
        transition: background 0.15s;
    }

    .ef-submit button:hover {
        background: #79b84d;
    }

    .field-error {
        color: #d9534f;
        font-size: 13px;
        margin-top: 4px;
        width: 100%;
    }

    .ef-error-summary {
        background: #fdecea;
        border: 1px solid #d9534f;
        color: #a94442;
        padding: 12px 16px;
        margin-bottom: 20px;
        font-size: 14px;
        border-radius: 3px;
    }

    .ef-error-summary ul {
        margin: 6px 0 0;
        padding-left: 20px;
    }

    /* File size modal */
    .tm-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        align-items: center;
        justify-content: center;
    }

    .tm-modal-content {
        background: #fff;
        padding: 30px;
        border-radius: 4px;
        max-width: 400px;
        text-align: center;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }

    .tm-modal-content h3 {
        margin: 0 0 12px;
        color: #d9534f;
    }

    .tm-modal-content p {
        margin: 0 0 18px;
        color: #444;
        font-size: 14px;
        line-height: 1.5;
    }

    .tm-modal-content button {
        background: #87c65a;
        color: #fff;
        border: none;
        padding: 10px 30px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        border-radius: 3px;
    }

    @media (max-width: 600px) {
        .ef-row {
            flex-direction: column;
        }
        .ef-label {
            flex: 1 1 auto;
            border-right: none;
            border-bottom: 1px solid #e3e3e3;
        }
    }
</style>

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

        {{-- ================= PERSONAL ================= --}}
        <div class="ef-section">
            <div class="ef-legend">Personal</div>

            <div class="ef-row">
                <div class="ef-label">Title</div>
                <div class="ef-field">
                    <select name="title" id="title">
                        <option value="">Select…</option>
                        @foreach (['Mr', 'Ms', 'Mrs', 'Miss'] as $t)
                            <option value="{{ $t }}" @selected(old('title', $student?->title) === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">First Name <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <input type="text" name="first_name" id="first_name"
                           value="{{ old('first_name', $student?->first_name) }}" readonly>
                    @error('first_name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Last Name <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <input type="text" name="last_name" id="last_name"
                           value="{{ old('last_name', $student?->last_name) }}" readonly>
                    @error('last_name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Date of Birth <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <input type="date" name="date_of_birth" id="date_of_birth"
                           value="{{ old('date_of_birth', $student?->date_of_birth) }}" required>
                    @error('date_of_birth')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Gender <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <select name="gender" id="gender" required>
                        <option value="">Select…</option>
                        <option value="F" @selected(old('gender', $student?->gender) === 'F')>Female</option>
                        <option value="M" @selected(old('gender', $student?->gender) === 'M')>Male</option>
                        <option value="X" @selected(old('gender', $student?->gender) === 'X')>Other</option>
                        <option value="@" @selected(old('gender', $student?->gender) === '@')>Not Specified</option>
                    </select>
                    @error('gender')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Town / City of Birth</div>
                <div class="ef-field">
                    <input type="text" name="city_of_birth" id="city_of_birth"
                           value="{{ old('city_of_birth', $student?->city_of_birth) }}">
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Country of Birth <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <select name="country_of_birth" id="country_of_birth" required>
                        <option value="">Select…</option>
                        @foreach ($countries as $cCode => $country)
                            <option value="{{ $cCode }}"
                                @selected(old('country_of_birth', $student?->country_of_birth ?? '1101') == $cCode)>
                                {{ $country }}
                            </option>
                        @endforeach
                    </select>
                    @error('country_of_birth')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- ================= USI ================= --}}
        <div class="ef-section">
            <div class="ef-legend">USI</div>

            <div class="ef-row">
                <div class="ef-label">USI Number</div>
                <div class="ef-field">
                    <input type="text" name="usi" id="usi" maxlength="10"
                           placeholder="10-character USI"
                           value="{{ old('usi', $student?->usi) }}">
                    @error('usi')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- ================= ADDRESS ================= --}}
        <div class="ef-section">
            <div class="ef-legend">Address</div>

            <div class="ef-row">
                <div class="ef-label">Building / Property Name</div>
                <div class="ef-field">
                    <input type="text" name="building_name" id="building_name"
                           value="{{ old('building_name', $student?->building_name) }}">
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Unit / Flat Number</div>
                <div class="ef-field">
                    <input type="text" name="unit_number" id="unit_number"
                           value="{{ old('unit_number', $student?->unit_number) }}">
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Street Number <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <input type="text" name="street_number" id="street_number"
                           value="{{ old('street_number', $student?->street_number) }}" required>
                    @error('street_number')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Street Name <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <input type="text" name="street_name" id="street_name"
                           value="{{ old('street_name', $student?->street_name) }}" required>
                    @error('street_name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Suburb <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <input type="text" name="suburb" id="suburb"
                           value="{{ old('suburb', $student?->suburb) }}" required>
                    @error('suburb')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">State <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <select name="state" id="state" required>
                        <option value="">Select…</option>
                        @foreach (['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'ACT', 'NT'] as $s)
                            <option value="{{ $s }}" @selected(old('state', $student?->state) === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                    @error('state')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Postcode <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <input type="text" name="postcode" id="postcode"
                           inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                           placeholder="e.g. 6000"
                           value="{{ old('postcode', $student?->postcode) }}" required>
                    @error('postcode')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Country <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <input type="text" name="address_country" id="address_country"
                           value="{{ old('address_country', $student?->address_country ?? 'Australia') }}" required>
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Postal address</div>
                <div class="ef-field">
                    <select name="postal_address_type" id="ef_postal_address_type">
                        <option value="same" @selected(old('postal_address_type', 'same') === 'same')>Same as above</option>
                        <option value="different" @selected(old('postal_address_type') === 'different')>Different postal address</option>
                    </select>
                </div>
            </div>

            <div id="ef_postal_fields" style="display: {{ old('postal_address_type') === 'different' ? 'block' : 'none' }};">
                <div class="ef-row">
                    <div class="ef-label">PO Box</div>
                    <div class="ef-field">
                        <input type="text" name="postal_box" maxlength="22"
                               value="{{ old('postal_box') }}">
                    </div>
                </div>
                <div class="ef-row">
                    <div class="ef-label">Postal Building / Property</div>
                    <div class="ef-field">
                        <input type="text" name="postal_building_property" maxlength="50"
                               value="{{ old('postal_building_property') }}">
                    </div>
                </div>
                <div class="ef-row">
                    <div class="ef-label">Postal Unit / Flat</div>
                    <div class="ef-field">
                        <input type="text" name="postal_unit_flat" maxlength="30"
                               value="{{ old('postal_unit_flat') }}">
                    </div>
                </div>
                <div class="ef-row">
                    <div class="ef-label">Postal Street Number</div>
                    <div class="ef-field">
                        <input type="text" name="postal_street_number" maxlength="15"
                               value="{{ old('postal_street_number') }}">
                    </div>
                </div>
                <div class="ef-row">
                    <div class="ef-label">Postal Street Name</div>
                    <div class="ef-field">
                        <input type="text" name="postal_street_name" maxlength="70"
                               value="{{ old('postal_street_name') }}">
                    </div>
                </div>
                <div class="ef-row">
                    <div class="ef-label">Postal Suburb</div>
                    <div class="ef-field">
                        <input type="text" name="postal_suburb" maxlength="50"
                               value="{{ old('postal_suburb') }}">
                    </div>
                </div>
                <div class="ef-row">
                    <div class="ef-label">Postal State</div>
                    <div class="ef-field">
                        <select name="postal_state">
                            <option value="">Select…</option>
                            @php
                                $postalStates = [
                                    '01' => 'NSW', '02' => 'VIC', '03' => 'QLD', '04' => 'SA',
                                    '05' => 'WA', '06' => 'TAS', '07' => 'NT', '08' => 'ACT',
                                    '09' => 'Other Australian territories', '99' => 'Other (Overseas)',
                                ];
                            @endphp
                            @foreach ($postalStates as $ps => $psLabel)
                                <option value="{{ $ps }}" @selected(old('postal_state') === $ps)>{{ $psLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="ef-row">
                    <div class="ef-label">Postal Postcode</div>
                    <div class="ef-field">
                        <input type="text" name="postal_postcode" maxlength="4"
                               value="{{ old('postal_postcode') }}">
                    </div>
                </div>
                <div class="ef-row">
                    <div class="ef-label">Postal Country</div>
                    <div class="ef-field">
                        <select name="postal_country">
                            <option value="">Select…</option>
                            @foreach ($countries as $cCode => $country)
                                <option value="{{ $cCode }}"
                                    @selected(old('postal_country', '1101') == $cCode)>
                                    {{ $country }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= CONTACT ================= --}}
        <div class="ef-section">
            <div class="ef-legend">Contact</div>

            <div class="ef-row">
                <div class="ef-label">Email Address <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <input type="email" name="email" id="email"
                           value="{{ old('email', $student?->email) }}" readonly>
                    @error('email')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Mobile Phone <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <input type="tel" name="mobile_phone" id="mobile_phone"
                           pattern="^04\d{8}$" inputmode="numeric" maxlength="10"
                           placeholder="04xxxxxxxx"
                           value="{{ old('mobile_phone', $student?->phone) }}"
                           oninput="this.value=this.value.replace(/[^0-9]/g,'')" required>
                    @error('mobile_phone')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- ================= EDUCATION ================= --}}
        <div class="ef-section">
            <div class="ef-legend">Education</div>

            <div class="ef-row">
                <div class="ef-label">Highest completed school level <span class="ef-req">*</span></div>
                <div class="ef-field">
                    @php
                        $schoolLevels = [
                            '02' => 'Did not go to school',
                            '08' => 'Completed Year 8',
                            '09' => 'Completed Year 9',
                            '10' => 'Completed Year 10',
                            '11' => 'Completed Year 11',
                            '12' => 'Completed Year 12',
                            '@@' => 'Not Specified',
                        ];
                    @endphp
                    <select name="school_level" id="school_level" required>
                        <option value="">Select…</option>
                        @foreach ($schoolLevels as $slCode => $slLabel)
                            <option value="{{ $slCode }}" @selected(old('school_level') === $slCode)>{{ $slLabel }}</option>
                        @endforeach
                    </select>
                    @error('school_level')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Currently at school? <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <select name="currently_at_school" id="currently_at_school" required>
                        <option value="">Select…</option>
                        <option value="Y" @selected(old('currently_at_school') === 'Y')>Yes</option>
                        <option value="N" @selected(old('currently_at_school') === 'N')>No</option>
                        <option value="@" @selected(old('currently_at_school') === '@')>Not Specified</option>
                    </select>
                    @error('currently_at_school')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Other qualifications completed? <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <select name="other_qualifications" id="ef_other_qualifications" required>
                        <option value="">Select…</option>
                        <option value="Yes" @selected(old('other_qualifications') === 'Yes')>Yes</option>
                        <option value="No" @selected(old('other_qualifications') === 'No')>No</option>
                    </select>
                    @error('other_qualifications')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div id="ef_qualification_details" style="display: {{ old('other_qualifications') === 'Yes' ? 'block' : 'none' }};">
                <div class="ef-row ef-row--full">
                    <div class="ef-field ef-field--full">
                        <p class="ef-sublabel">Select all that apply <span class="ef-req">*</span></p>
                        @php
                            $qualLevels = [
                                'prior_achievement[all][008]' => 'Bachelor degree or higher degree level',
                                'prior_achievement[all][410]' => 'Advanced diploma or associate degree level',
                                'prior_achievement[all][420]' => 'Diploma level',
                                'prior_achievement[all][511]' => 'Certificate IV',
                                'prior_achievement[all][514]' => 'Certificate III',
                                'prior_achievement[all][521]' => 'Certificate II',
                                'prior_achievement[all][524]' => 'Certificate I',
                                'prior_achievement[all][990]' => 'Miscellaneous education',
                            ];
                            $oldQuals = old('qualification_level', []);
                        @endphp
                        <div class="ef-cb-group">
                            @foreach ($qualLevels as $qlVal => $qlLabel)
                                <label>
                                    <input type="checkbox" name="qualification_level[]" value="{{ $qlVal }}"
                                        @checked(in_array($qlVal, $oldQuals))>
                                    {{ $qlLabel }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Reason for study <span class="ef-req">*</span></div>
                <div class="ef-field">
                    @php
                        $studyReasons = [
                            '01' => 'To get a job',
                            '02' => 'To develop my existing business',
                            '03' => 'To start my own business',
                            '04' => 'To try for a different career',
                            '05' => 'To get a better job or promotion',
                            '06' => 'It was a requirement of my job',
                            '07' => 'I wanted extra skills for my job',
                            '08' => 'To get into another course of study',
                            '11' => 'Other reasons',
                            '12' => 'For personal interest or self-development',
                            '13' => 'To get skills for community/voluntary work',
                        ];
                    @endphp
                    <select name="study_reason" id="study_reason" required>
                        <option value="">Select…</option>
                        @foreach ($studyReasons as $srCode => $srLabel)
                            <option value="{{ $srCode }}" @selected(old('study_reason') === $srCode)>{{ $srLabel }}</option>
                        @endforeach
                    </select>
                    @error('study_reason')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- ================= DEMOGRAPHY ================= --}}
        <div class="ef-section">
            <div class="ef-legend">Demography</div>

            <div class="ef-row">
                <div class="ef-label">Employment status <span class="ef-req">*</span></div>
                <div class="ef-field">
                    @php
                        $employmentOptions = [
                            '01' => 'Full-time employee',
                            '02' => 'Part-time employee',
                            '03' => 'Self-employed, not employing others',
                            '04' => 'Employer',
                            '05' => 'Unpaid family worker',
                            '06' => 'Unemployed – seeking work',
                            '07' => 'Not employed – not seeking employment',
                            '@@' => 'Not Specified',
                        ];
                    @endphp
                    <select name="labour_force" id="labour_force" required>
                        <option value="">Select…</option>
                        @foreach ($employmentOptions as $eCode => $eLabel)
                            <option value="{{ $eCode }}" @selected(old('labour_force') === $eCode)>{{ $eLabel }}</option>
                        @endforeach
                    </select>
                    @error('labour_force')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Aboriginal or Torres Strait Islander <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <select name="atsi_status" id="atsi_status" required>
                        <option value="">Select…</option>
                        @foreach (['No', 'Yes, Aboriginal', 'Yes, Torres Strait Islander'] as $atsi)
                            <option value="{{ $atsi }}" @selected(old('atsi_status') === $atsi)>{{ $atsi }}</option>
                        @endforeach
                    </select>
                    @error('atsi_status')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Main language spoken at home <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <select name="main_language_home" id="main_language_home" required>
                        <option value="">Select…</option>
                        @foreach ($languages as $lCode => $language)
                            <option value="{{ $lCode }}"
                                @selected(old('main_language_home', '1201') == $lCode)>
                                {{ $language }}
                            </option>
                        @endforeach
                    </select>
                    @error('main_language_home')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- ================= NEEDS ================= --}}
        <div class="ef-section">
            <div class="ef-legend">Needs</div>

            <div class="ef-row">
                <div class="ef-label">Disability or impairment <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <select name="disability" id="ef_disability" required>
                        <option value="">Select…</option>
                        <option value="N" @selected(old('disability') === 'N')>No</option>
                        <option value="Y" @selected(old('disability') === 'Y')>Yes</option>
                    </select>
                    @error('disability')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div id="ef_disability_details" style="display: {{ old('disability') === 'Y' ? 'block' : 'none' }};">
                <div class="ef-row ef-row--full">
                    <div class="ef-field ef-field--full">
                        <p class="ef-sublabel">Select all that apply <span class="ef-req">*</span></p>
                        @php
                            $disabilityTypes = [
                                '11' => 'Hearing/deaf',
                                '12' => 'Physical',
                                '13' => 'Intellectual',
                                '14' => 'Learning',
                                '15' => 'Mental illness',
                                '16' => 'Acquired brain impairment',
                                '17' => 'Vision',
                                '18' => 'Medical condition',
                                '19' => 'Other',
                                '99' => 'Not Specified',
                            ];
                            $oldDisability = old('disability_type', []);
                        @endphp
                        <div class="ef-cb-group">
                            @foreach ($disabilityTypes as $dVal => $dLabel)
                                <label>
                                    <input type="checkbox" name="disability_type[]" value="{{ $dVal }}"
                                        @checked(in_array($dVal, $oldDisability))>
                                    {{ $dLabel }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="ef-row">
                <div class="ef-label">Any individual needs? <span class="ef-req">*</span></div>
                <div class="ef-field">
                    <select name="individual_needs" id="ef_individual_needs" required>
                        <option value="">Select…</option>
                        <option value="No" @selected(old('individual_needs') === 'No')>No</option>
                        <option value="Yes" @selected(old('individual_needs') === 'Yes')>Yes</option>
                    </select>
                    @error('individual_needs')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div id="ef_individual_needs_details" style="display: {{ old('individual_needs') === 'Yes' ? 'block' : 'none' }};">
                <div class="ef-row ef-row--full">
                    <div class="ef-field ef-field--full">
                        <label class="ef-sublabel" for="individual_needs_specify">Please specify <span class="ef-req">*</span></label>
                        <textarea name="individual_needs_specify" id="individual_needs_specify"
                                  rows="4">{{ old('individual_needs_specify') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= UPLOADS ================= --}}
        <div class="ef-section">
            <div class="ef-legend">Uploads</div>

            <div class="ef-upload-row">
                <label for="id_document">ID Document (PDF / Image)</label>
                <span>Upload a copy of your ID — Drivers Licence, Passport, or Proof of Age card. Max 8MB.</span>
                <input type="file" id="id_document" name="id_document" accept=".pdf,.jpg,.jpeg,.png">
                @error('id_document')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="ef-upload-row">
                <label for="vet_transcript">VET Transcript (PDF)</label>
                <span>Upload your VET transcript listing your previous courses. Max 8MB.</span>
                <input type="file" id="vet_transcript" name="vet_transcript" accept=".pdf">
                @error('vet_transcript')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- ================= DECLARATIONS ================= --}}
        <div class="ef-section">
            <div class="ef-legend">Declarations</div>

            <div class="ef-privacy-notice">
                <strong>Privacy Notice</strong>
                <p>My personal information (including the personal information contained in this enrolment form and my training activity data) may be used or disclosed by the RTO for statistical, regulatory and research purposes. The RTO may disclose my personal information to:</p>
                <ul>
                    <li>My employer (if training is paid for by my employer)</li>
                    <li>Commonwealth, State or Territory government departments and authorised agencies</li>
                    <li>NCVER</li>
                    <li>Organisations conducting student surveys</li>
                    <li>Researchers</li>
                </ul>
                <p>NCVER will collect, hold, use and disclose my personal information in accordance with the Privacy Act 1988 (Cth), the VET Data Policy and all NCVER policies and protocols (including those published at <a href="https://www.ncver.edu.au" target="_blank" rel="noopener noreferrer">www.ncver.edu.au</a>).</p>
            </div>

            <div class="ef-check-row">
                <input type="checkbox" id="agree_handbook" name="agree_handbook" value="1"
                    @checked(old('agree_handbook')) required>
                <label for="agree_handbook">I have reviewed the student handbook, fee schedule, and course description available from this website and am informed about my rights and obligations, payment obligations and the services to be provided.</label>
            </div>

            <div class="ef-check-row">
                <input type="checkbox" id="agree_terms" name="agree_terms" value="1"
                    @checked(old('agree_terms')) required>
                <label for="agree_terms">I agree to the terms and conditions applicable to this enrolment and confirm that the information I have provided in this enrolment form is true and correct.</label>
            </div>

            <div class="ef-check-row">
                <input type="checkbox" id="agree_privacy" name="agree_privacy" value="1"
                    @checked(old('agree_privacy')) required>
                <label for="agree_privacy">I agree that under the Data Provision Requirements 2012, the RTO is required to collect personal information about me and to disclose that personal information to the National Centre for Vocational Education Research Ltd (NCVER). I give permission to AMS Training PTY LTD to have access to my previous training records in order to issue a new Statement of Attainment or Statement of Attendance. This permission also includes AMS Training PTY LTD contacting RTOs to verify the information I have provided.</label>
            </div>

            <div class="ef-check-row">
                <input type="checkbox" id="agree_data_provision" name="agree_data_provision" value="1"
                    @checked(old('agree_data_provision')) required>
                <label for="agree_data_provision">I acknowledge that under the Data Provision Requirements 2012, the RTO is required to collect personal information about me and disclose that information to the National Centre for Vocational Education Research Ltd (NCVER).</label>
            </div>

            <div class="ef-check-row">
                <input type="checkbox" id="agree_record_access" name="agree_record_access" value="1"
                    @checked(old('agree_record_access')) required>
                <label for="agree_record_access">I give permission to AMS Training PTY LTD to have access to my previous training records in order to issue a new Statement of Attainment or Statement of Attendance. This permission also includes AMS Training PTY LTD contacting RTOs to verify the information I have provided.</label>
            </div>

            <div class="ef-check-row">
                <input type="checkbox" id="agree_declaration" name="agree_declaration" value="1"
                    @checked(old('agree_declaration')) required>
                <label for="agree_declaration">I declare that I have maintained my skills, experience, and knowledge for this course since completing the course within the past 5 years.</label>
            </div>
        </div>

        <div id="form-global-error" style="display:none; color:#d9534f; margin-bottom:1rem;"></div>

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

