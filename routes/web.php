<?php

use App\Jobs\SubmitEnrolmentToApiJob;
use App\Models\Enrolment;
use App\Models\EnrolmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Secure enrolment registration link
|--------------------------------------------------------------------------
|
| GET  /enrol/{token}  = show Laravel enrolment registration form
| POST /enrol/{token}  = save submitted form and mark link as completed
|
| The enrolment link can only be used once.
|
*/

Route::get('/enrol/{token}', function (string $token) {
    $enrolment = Enrolment::with(['student', 'course', 'order'])
        ->where('enrolment_token', $token)
        ->first();

    if (! $enrolment) {
        return redirect('/enrolment-not-successful');
    }

    if ($enrolment->enrolment_completed_at) {
        return redirect('/enrolment-not-successful');
    }

    if (
        $enrolment->enrolment_token_expires_at
        && now()->greaterThan($enrolment->enrolment_token_expires_at)
    ) {
        return redirect('/enrolment-not-successful');
    }

    $queryParams = [];

    if ($enrolment->secret_base_url) {
        $queryString = parse_url($enrolment->secret_base_url, PHP_URL_QUERY);

        if ($queryString) {
            parse_str($queryString, $queryParams);
        }
    }

    $languages = config('enrolment_languages', []);
    $countries = config('enrolment_countries', []);

    return view('enrolments.registration-form', [
        'enrolment' => $enrolment,
        'student' => $enrolment->student,
        'course' => $enrolment->course,
        'order' => $enrolment->order,

        // Only these two query parameters are used for now.
        'code' => $queryParams['code'] ?? null,
        'plan' => $queryParams['plan'] ?? null,

        // Lists used by the Blade form.
        'languages' => $languages,
        'countries' => $countries,
    ]);
})->name('enrol.show');

Route::post('/enrol/{token}', function (Request $request, string $token) {
    $enrolment = Enrolment::with(['student', 'course', 'order'])
        ->where('enrolment_token', $token)
        ->first();

    if (! $enrolment) {
        return redirect('/enrolment-not-successful');
    }

    if ($enrolment->enrolment_completed_at) {
        return redirect('/enrolment-not-successful');
    }

    if (
        $enrolment->enrolment_token_expires_at
        && now()->greaterThan($enrolment->enrolment_token_expires_at)
    ) {
        return redirect('/enrolment-not-successful');
    }

    $validated = $request->validate([
        'enrolment_id' => ['nullable', 'integer'],
        'code' => ['nullable', 'string', 'max:100'],
        'plan' => ['nullable', 'string', 'max:100'],

        // Personal
        'title' => ['nullable', 'string', 'max:50'],
        'first_name' => ['nullable', 'string', 'max:255'],
        'middle_name' => ['nullable', 'string', 'max:255'],
        'last_name' => ['nullable', 'string', 'max:255'],
        'date_of_birth' => ['nullable', 'string', 'max:50'],
        'gender' => ['nullable', 'string', 'max:100'],
        'city_of_birth' => ['nullable', 'string', 'max:255'],
        'country_of_birth' => ['nullable', 'string', 'max:255'],
        'main_language_home' => ['nullable', 'string', 'max:255'],
        'main_language' => ['nullable', 'string', 'max:255'],
        'usi_number' => ['nullable', 'string', 'max:100'],
        'usi' => ['nullable', 'string', 'max:100'],
        'atsi_status' => ['nullable', 'string', 'max:255'],

        // Address
        'building_name' => ['nullable', 'string', 'max:255'],
        'unit_number' => ['nullable', 'string', 'max:100'],
        'street_number' => ['nullable', 'string', 'max:100'],
        'street_name' => ['nullable', 'string', 'max:255'],
        'suburb' => ['nullable', 'string', 'max:255'],
        'state' => ['nullable', 'string', 'max:100'],
        'postcode' => ['nullable', 'string', 'max:20'],
        'address_country' => ['nullable', 'string', 'max:255'],

        // Postal address
        'postal_address_type' => ['nullable', 'string', 'max:100'],
        'postal_box' => ['nullable', 'string', 'max:255'],
        'postal_building_property' => ['nullable', 'string', 'max:255'],
        'postal_unit_flat' => ['nullable', 'string', 'max:255'],
        'postal_street_number' => ['nullable', 'string', 'max:100'],
        'postal_street_name' => ['nullable', 'string', 'max:255'],
        'postal_suburb' => ['nullable', 'string', 'max:255'],
        'postal_state' => ['nullable', 'string', 'max:100'],
        'postal_postcode' => ['nullable', 'string', 'max:20'],
        'postal_country' => ['nullable', 'string', 'max:255'],

        // Contact
        'email' => ['nullable', 'email', 'max:255'],
        'email_address' => ['nullable', 'email', 'max:255'],
        'mobile_phone' => ['nullable', 'string', 'max:50'],
        'phone' => ['nullable', 'string', 'max:50'],

        // Education
        'school_level' => ['nullable', 'string', 'max:255'],
        'highest_school_level' => ['nullable', 'string', 'max:255'],
        'currently_at_school' => ['nullable', 'string', 'max:50'],
        'other_qualifications' => ['nullable', 'string', 'max:50'],
        'qualification_level' => ['nullable', 'array'],
        'qualification_level.*' => ['nullable', 'string', 'max:255'],
        'study_reason' => ['nullable', 'string', 'max:255'],

        // Demography
        'labour_force' => ['nullable', 'string', 'max:255'],
        'employment_status' => ['nullable', 'string', 'max:255'],

        // Needs
        'disability' => ['nullable', 'string', 'max:50'],
        'disability_type' => ['nullable', 'array'],
        'disability_type.*' => ['nullable', 'string', 'max:255'],
        'individual_needs' => ['nullable', 'string', 'max:50'],
        'individual_needs_specify' => ['nullable', 'string', 'max:2000'],
        'support_needs' => ['nullable', 'string', 'max:2000'],
        'notes' => ['nullable', 'string', 'max:2000'],

        // Declarations
        'agree_handbook' => ['nullable'],
        'agree_terms' => ['nullable'],
        'agree_privacy' => ['nullable'],
        'agree_data_provision' => ['nullable'],
        'agree_record_access' => ['nullable'],
        'agree_declaration' => ['nullable'],
        'student_declaration' => ['nullable'],
        'privacy_declaration' => ['nullable'],

        // Uploads
        'id_document' => ['nullable', 'file', 'max:8192'],
        'vet_transcript' => ['nullable', 'file', 'max:8192'],
        'documents' => ['nullable', 'array'],
        'documents.*' => ['nullable', 'file', 'max:8192'],
    ]);

    /*
    |--------------------------------------------------------------------------
    | Locked identity fields
    |--------------------------------------------------------------------------
    |
    | First name, last name, and email may be readonly in the frontend, but
    | frontend fields can still be edited using browser dev tools.
    | So we keep the linked Student record as the source of truth.
    |
    */

    $validated['first_name'] = $enrolment->student?->first_name;
    $validated['last_name'] = $enrolment->student?->last_name;
    $validated['email'] = $enrolment->student?->email;

    /*
    |--------------------------------------------------------------------------
    | File uploads
    |--------------------------------------------------------------------------
    |
    | The form may use either named upload fields:
    | id_document / vet_transcript
    |
    | Or old WordPress style:
    | documents[]
    |
    | This handles both safely for now.
    |
    */

    $idDocumentPath = null;
    $vetTranscriptPath = null;
    $additionalDocumentPaths = [];

    if ($request->hasFile('id_document')) {
        $idDocumentPath = $request->file('id_document')->store('enrolment-documents', 'local');
    }

    if ($request->hasFile('vet_transcript')) {
        $vetTranscriptPath = $request->file('vet_transcript')->store('enrolment-documents', 'local');
    }

    if ($request->hasFile('documents')) {
        foreach ($request->file('documents') as $document) {
            if (! $document) {
                continue;
            }

            $additionalDocumentPaths[] = $document->store('enrolment-documents', 'local');
        }

        if (! $idDocumentPath && isset($additionalDocumentPaths[0])) {
            $idDocumentPath = $additionalDocumentPaths[0];
        }

        if (! $vetTranscriptPath && isset($additionalDocumentPaths[1])) {
            $vetTranscriptPath = $additionalDocumentPaths[1];
        }

        $validated['uploaded_documents'] = $additionalDocumentPaths;
    }

    /*
    |--------------------------------------------------------------------------
    | Save submission
    |--------------------------------------------------------------------------
    |
    | The enrolment form data is saved first. Then a queued job handles
    | submission to the external enrolment API.
    |
    */

    $submission = EnrolmentSubmission::create([
        'enrolment_id' => $enrolment->id,
        'order_id' => $enrolment->order_id,
        'student_id' => $enrolment->student_id,
        'course_id' => $enrolment->course_id,
        'code' => $request->input('code'),
        'plan' => $request->input('plan'),
        'form_data' => $validated,
        'id_document_path' => $idDocumentPath,
        'vet_transcript_path' => $vetTranscriptPath,
        'submitted_at' => now(),
        'api_status' => 'pending',
    ]);

    SubmitEnrolmentToApiJob::dispatch($submission->id);

    $enrolment->update([
        'status' => 'completed',
        'enrolment_completed_at' => now(),
        'response_payload' => json_encode([
            'message' => 'Student completed Laravel registration form.',
            'submission_saved' => true,
            'api_job_queued' => true,
            'submission_id' => $submission->id,
            'submitted_at' => now()->toDateTimeString(),
        ]),
    ]);

    return redirect('/enrolment-completed');
})->name('enrol.submit');

Route::get('/enrolment-completed', function () {
    return view('enrolments.completed');
})->name('enrolment.completed');

Route::get('/enrolment-not-successful', function () {
    return response()->view('enrolments.failed', [], 404);
})->name('enrolment.failed');