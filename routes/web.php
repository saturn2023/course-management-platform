<?php

use App\Models\Enrolment;
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
| Example:
| /enrol/PbJjVMj9S9ckXQdx44dmDkEM8rMYS2tm8pEV09FnQiwu1jVVtbXR891R0sCz3PZF
|
| This route shows the Laravel registration form.
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

        'first_name' => ['nullable', 'string', 'max:255'],
        'middle_name' => ['nullable', 'string', 'max:255'],
        'last_name' => ['nullable', 'string', 'max:255'],
        'date_of_birth' => ['nullable', 'string', 'max:50'],
        'gender' => ['nullable', 'string', 'max:100'],
        'country_of_birth' => ['nullable', 'string', 'max:255'],
        'main_language_home' => ['nullable', 'string', 'max:255'],
        'usi_number' => ['nullable', 'string', 'max:100'],

        'email' => ['nullable', 'email', 'max:255'],
        'phone' => ['nullable', 'string', 'max:50'],
        'address_line_1' => ['nullable', 'string', 'max:255'],
        'address_line_2' => ['nullable', 'string', 'max:255'],
        'suburb' => ['nullable', 'string', 'max:255'],
        'state' => ['nullable', 'string', 'max:100'],
        'postcode' => ['nullable', 'string', 'max:20'],

        'emergency_contact_name' => ['nullable', 'string', 'max:255'],
        'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
        'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],

        'employment_status' => ['nullable', 'string', 'max:255'],
        'highest_school_level' => ['nullable', 'string', 'max:255'],
        'previous_qualification' => ['nullable', 'string', 'max:255'],
        'support_needs' => ['nullable', 'string', 'max:2000'],
        'notes' => ['nullable', 'string', 'max:2000'],

        'student_declaration' => ['nullable'],
        'privacy_declaration' => ['nullable'],

        // File validation can be improved later.
        'id_document' => ['nullable', 'file', 'max:8192'],
        'vet_transcript' => ['nullable', 'file', 'max:8192'],
    ]);

    $enrolment->update([
        'status' => 'completed',
        'enrolment_completed_at' => now(),
        'response_payload' => json_encode([
            'message' => 'Student completed Laravel registration form.',
            'submitted_data' => $validated,
        ]),
    ]);

    return redirect('/enrolment-completed');
})->name('enrol.submit');

Route::get('/enrolment-completed', function () {
    return response(
        'Your registration has been completed successfully. This enrolment link can no longer be used.',
        200
    );
})->name('enrolment.completed');

Route::get('/enrolment-not-successful', function () {
    return response(
        'Enrolment link is invalid, expired, or already completed. Please contact AMS Training.',
        404
    );
})->name('enrolment.failed');