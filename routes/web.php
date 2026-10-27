<?php

use App\Http\Controllers\Ams2EnrolmentController;
use App\Http\Controllers\CardPaymentController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CheckoutLoadController;
use App\Http\Controllers\PurchaseOrderCheckoutController;
use App\Http\Controllers\XeroAuthController;
use App\Http\Middleware\RouteEnrolmentLinkToV2;
use App\Jobs\SubmitEnrolmentToApiJob;
use App\Models\CheckoutSession;
use App\Models\Course;
use App\Models\CoursePackage;
use App\Models\Enrolment;
use App\Models\EnrolmentStep;
use App\Models\EnrolmentSubmission;
use App\Models\Review;
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
| Xero OAuth connection
|--------------------------------------------------------------------------
|
| Admin-only. /xero/connect starts the authorisation code flow and
| /xero/callback exchanges the code for the token pair stored in
| xero_connections. Both must stay behind auth: the callback is what writes
| the credentials used for every subsequent Xero API call, and the state
| parameter it validates lives in the authenticated session.
|
| The redirect URI registered in the Xero app must match xero.callback
| exactly (see XERO_REDIRECT_URI).
*/

Route::middleware(['auth'])->group(function () {
    Route::get('/xero/connect', [XeroAuthController::class, 'connect'])
        ->name('xero.connect');

    Route::get('/xero/callback', [XeroAuthController::class, 'callback'])
        ->name('xero.callback');
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
})->middleware(RouteEnrolmentLinkToV2::class)->name('enrol.show');

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
     * form_data is a JSON column, and $validated still holds the UploadedFile
     * objects under these keys, which cannot be encoded — any submission with a
     * file attached failed with "Type is not supported" before the row was
     * written. Replace them with the stored paths, which is what
     * 'uploaded_documents' above already does for the documents[] array.
     */
    $validated['id_document'] = $idDocumentPath;
    $validated['vet_transcript'] = $vetTranscriptPath;
    unset($validated['documents']);

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
    /*
     * An expired link gets its own wording. It is a genuine link, so "contact
     * us for a new one" is the useful thing to say; "not valid" would send the
     * learner looking for a mistake they did not make.
     */
    return response()->view('enrolments.failed', [
        'reason' => session('enrolment_failure_reason'),
    ], 404);
})->name('enrolment.failed');

/*
|--------------------------------------------------------------------------
| Enrolment v2
|--------------------------------------------------------------------------
|
| Links reach this page only via RouteEnrolmentLinkToV2 on enrol.show, which
| is the port of the reference hooking template_redirect ahead of the old
| handler. The page re-checks everything that middleware checked: a learner can
| sit here for an hour, and the secret key in the URL is shareable.
|
*/

Route::get('/enrolment-v2', [Ams2EnrolmentController::class, 'show'])
    ->name('enrol.v2.show');

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
 * DB-powered standalone mockup of the Package Online Refresher Courses block.
 * Temporary — for review of the block in isolation.
 */
Route::get('/mockups/package-refresher-courses-live', function () {
    $packages = CoursePackage::query()
        ->where('status', 'active')
        ->where('show_on_homepage', true)
        ->orderBy('display_order')
        ->orderBy('id')
        ->get();

    return view('mockups.package-refresher-courses-live', [
        'packages' => $packages,
    ]);
})->name('mockups.package-refresher-courses-live');

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
    // Same query as /mockups/online-refresher-courses-live: live records from
    // /admin/courses, so image, code, card title, order and the two toggles all
    // take effect on the homepage as soon as they are saved in admin.
    $courses = Course::query()
        ->where('status', 'active')
        ->where('show_on_homepage', true)
        ->orderBy('display_order')
        ->orderBy('id')
        ->get();

    // Same live-from-admin approach for the package cards (/admin/course-packages).
    $packages = CoursePackage::query()
        ->where('status', 'active')
        ->where('show_on_homepage', true)
        ->orderBy('display_order')
        ->orderBy('id')
        ->get();

    // Accordion rows for the enrolment section, live from /admin/enrolment-steps:
    // active only, in the admin's order (see EnrolmentStep::scopeForHomepage).
    $enrolmentSteps = EnrolmentStep::query()->forHomepage()->get();

    // Review cards, live from /admin/reviews: active only, in the admin's
    // order (see Review::scopeForHomepage).
    $reviews = Review::query()->forHomepage()->get();

    return view('mockups.hero-banner', [
        'courses' => $courses,
        'packages' => $packages,
        'enrolmentSteps' => $enrolmentSteps,
        'reviews' => $reviews,
    ]);
})->name('mockups.hero-banner');

/*
 * Public course detail page.
 *
 * Route model binding on the slug, scoped to active courses only, so an
 * inactive record 404s rather than being reachable by guessing its URL. The
 * reviews query is the same one the homepage uses, so the shared
 * <x-home.reviews /> component gets the data it expects.
 */
Route::get('/courses/{course:slug}', function (Course $course) {
    abort_unless($course->status === 'active', 404);

    return view('courses.show', [
        'course' => $course,
        'reviews' => Review::query()->forHomepage()->get(),
    ]);
})->name('courses.show');

/*
 * Standalone mockup of the intro text section (<x-home.intro-text>).
 * Temporary — for review of the block in isolation.
 */
Route::get('/mockups/intro-text', function () {
    return view('mockups.intro-text');
})->name('mockups.intro-text');

/*
 * Standalone page for rebuilding the AMS Training homepage/header design.
 * Temporary — isolated from the live homepage; starts as a single empty
 * Flexbox container with content to be added later.
 */
Route::get('/mockups/ams-homepage-rebuild', function () {
    return view('mockups.ams-homepage-rebuild');
})->name('mockups.ams-homepage-rebuild');
/*
| Payment is submitted from the checkout page itself. Both endpoints below
| enforce their own authorization — see CardChargeRequest::authorize() and
| SubmitPurchaseOrderRequest::authorize() — so neither can be reached by a
| direct POST from a user who is not entitled to that method.
*/
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