<?php

namespace App\Http\Controllers;

use App\Models\CheckoutSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class CheckoutController extends Controller
{
    public function show(CheckoutSession $checkoutSession): View|Response
    {
        if ($checkoutSession->isCompleted()) {
            return response()->view('checkout.load-error', [
                'message' => 'This checkout has already been completed.',
            ], 410);
        }

        if ($checkoutSession->isExpired()) {
            return response()->view('checkout.load-error', [
                'message' => 'This checkout link has expired. Please start your enrolment again.',
            ], 410);
        }

        return view('checkout.show', [
            'session' => $checkoutSession,
        ]);
    }
}