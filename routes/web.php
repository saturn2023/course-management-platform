<?php

use App\Http\Controllers\CardPaymentController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CheckoutLoadController;
use App\Http\Controllers\PurchaseOrderCheckoutController;
use App\Jobs\SubmitEnrolmentToApiJob;
use App\Models\CheckoutSession;
use App\Models\Course;
use App\Models\Enrolment;
use App\Models\EnrolmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Home
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Checkout routes
|--------------------------------------------------------------------------
*/

Route::get('/checkout/load', CheckoutLoadController::class)
    ->middleware('throttle:30,1')
    ->name('checkout.load');

Route::get('/checkout/{checkoutSession:uuid}', [CheckoutController::class, 'show'])
    ->name('checkout.show');

Route::patch('/checkout/{checkoutSession:uuid}/quantity', [CheckoutController::class, 'updateQuantity'])
    ->name('checkout.quantity.update');

Route::post('/checkout/{checkoutSession:uuid}/details', [CheckoutController::class, 'saveDetails'])
    ->name('checkout.details.save');

Route::get('/checkout/{checkoutSession:uuid}/purchase-order', [PurchaseOrderCheckoutController::class, 'show'])
    ->name('checkout.purchase-order.show');

Route::post('/checkout/{checkoutSession:uuid}/purchase-order', [PurchaseOrderCheckoutController::class, 'store'])
    ->name('checkout.purchase-order.store');

Route::get('/checkout/{checkoutSession:uuid}/thank-you', function (CheckoutSession $checkoutSession) {
    return view('checkout.thank-you', [
        'session' => $checkoutSession,
        'order' => $checkoutSession->order,
    ]);
})->name('checkout.thank-you');

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

/*
|--------------------------------------------------------------------------
| Mockup routes
|--------------------------------------------------------------------------
*/

Route::get('/mockups/online-refresher-courses', function () {
    return view('mockups.online-refresher-courses');
})->name('mockups.online-refresher-courses');

/*
 * DB-powered standalone mockup of the Single Online Refresher Courses block,
 * rendered via the reusable <x-home.online-refresher-courses> component.
 * Temporary — for client review only; not part of the live homepage.
 */
Route::get('/mockups/online-refresher-courses-live', function () {
    $courses = Course::query()
        ->where('status', 'active')
        ->where('show_on_homepage', true)
        ->orderBy('display_order')
        ->orderBy('id')
        ->get();

    return view('mockups.online-refresher-courses-live', [
        'courses' => $courses,
    ]);
})->name('mockups.online-refresher-courses-live');

/*
 * Standalone mockup of the reusable site header (<x-layout.header>).
 * Temporary — for review of the header block in isolation.
 */
Route::get('/mockups/header', function () {
    return view('mockups.header');
})->name('mockups.header');

/*
 * Standalone mockup of the hero banner (<x-home.hero-banner>) shown below the
 * header. Temporary — for review of the block in isolation.
 */
Route::get('/mockups/hero-banner', function () {
    return view('mockups.hero-banner');
})->name('mockups.hero-banner');

/*
 * Standalone mockup of the intro text section (<x-home.intro-text>).
 * Temporary — for review of the block in isolation.
 */
Route::get('/mockups/intro-text', function () {
    return view('mockups.intro-text');
})->name('mockups.intro-text');
/*
| Admin-only payment-method choice page. Only administrators can pay by
| either method, so only they are offered this choice.
*/
Route::get('/checkout/{checkoutSession:uuid}/payment-method', function (
    CheckoutSession $checkoutSession
) {
    abort_if($checkoutSession->isExpired(), 410);
    abort_if($checkoutSession->isCompleted(), 409);
    abort_unless($checkoutSession->hasSavedDetails(), 422);

    abort_unless(
        auth()->check() && auth()->user()->isAdmin(),
        403,
        'Only administrators can choose a payment method.'
    );

    return view('checkout.payment-method', [
        'session' => $checkoutSession,
    ]);
})->name('checkout.payment-method.show');

Route::get('/checkout/{checkoutSession:uuid}/card-payment', function (
    CheckoutSession $checkoutSession
) {
    abort_if($checkoutSession->isExpired(), 410);
    abort_if($checkoutSession->isCompleted(), 409);
    abort_unless($checkoutSession->hasSavedDetails(), 422);

    /*
     * Access guard: a logged-in PO-only client must never reach card
     * checkout, even by entering the URL directly. Guests (no user) are
     * allowed. The future card controller will repeat this same guard.
     */
    $user = auth()->user();
    abort_if(
        $user && ! $user->canPayByCard(),
        403,
        'You are not authorised to use card checkout.'
    );

    return view('checkout.card-payment', [
        'session' => $checkoutSession,
    ]);
})->name('checkout.card-payment.show');

Route::post('/checkout/{checkoutSession:uuid}/card-payment', [CardPaymentController::class, 'store'])
    ->name('checkout.card-payment.store');

Route::get('/checkout/{checkoutSession:uuid}/card-payment/callback', [CardPaymentController::class, 'callback'])
    ->name('checkout.card-payment.callback');

Route::get('/checkout/{checkoutSession:uuid}/card-payment/received', function (
    CheckoutSession $checkoutSession
) {
    return view('checkout.card-payment-received', [
        'session' => $checkoutSession,
    ]);
})->name('checkout.card-payment.received');