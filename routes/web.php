<?php

use App\Models\Enrolment;
use Illuminate\Support\Facades\Route;
Route::get('/', function () {
    return view('welcome');
});
use App\Http\Controllers\XeroAuthController;

Route::get('/xero/connect', [XeroAuthController::class, 'connect'])
    ->name('xero.connect');

Route::get('/xero/callback', [XeroAuthController::class, 'callback'])
    ->name('xero.callback');
Route::get('/enrol/{token}', function (string $token) {
    $enrolment = Enrolment::with(['student', 'course', 'order'])
        ->where('enrolment_token', $token)
        ->first();

    if (! $enrolment) {
        return redirect('/enrolment-not-successful');
    }

    if ($enrolment->enrolment_token_expires_at && now()->greaterThan($enrolment->enrolment_token_expires_at)) {
        return redirect('/enrolment-not-successful');
    }

    if (! $enrolment->secret_base_url || ! $enrolment->secret_key) {
        return redirect('/enrolment-not-successful');
    }

    $separator = str_contains($enrolment->secret_base_url, '?') ? '&' : '?';

    $redirectUrl = $enrolment->secret_base_url
        . $separator
        . 'secretKey='
        . urlencode($enrolment->secret_key);

    return redirect()->away($redirectUrl);
})->name('enrol.redirect');
Route::get('/enrolment-not-successful', function () {
    return response('Enrolment link is invalid, expired, or no longer available. Please contact AMS Training.', 404);
})->name('enrolment.failed');