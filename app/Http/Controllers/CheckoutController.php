<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCheckoutQuantityRequest;
use App\Models\CheckoutSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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

    public function updateQuantity(
        UpdateCheckoutQuantityRequest $request,
        CheckoutSession $checkoutSession
    ): JsonResponse {
        if ($checkoutSession->isCompleted()) {
            return response()->json([
                'message' => 'This checkout has already been completed.',
            ], 410);
        }

        if ($checkoutSession->isExpired()) {
            return response()->json([
                'message' => 'This checkout link has expired. Please start your enrolment again.',
            ], 410);
        }

        $quantity = (int) $request->integer('quantity');

        /*
        |--------------------------------------------------------------------------
        | Capacity check using stored RTO snapshot
        |--------------------------------------------------------------------------
        |
        | We do not re-call RTO Data in this phase. We use the stock/enrolment
        | snapshot that was saved when the checkout session was created.
        |
        */

        if ($this->requiresCapacityCheck($checkoutSession)) {
            $remaining = (int) $checkoutSession->stock_quantity
                - (int) ($checkoutSession->enrolments ?? 0);

            if ($remaining < $quantity) {
                return response()->json([
                    'message' => $remaining > 0
                        ? "Only {$remaining} place(s) remaining on this schedule."
                        : 'This schedule is fully booked.',
                    'remaining' => max($remaining, 0),
                ], 422);
            }
        }

        $unitPrice = (float) $checkoutSession->unit_price;
        $subtotal = round($unitPrice * $quantity, 2);

        $checkoutSession->update([
            'quantity' => $quantity,
            'subtotal' => $subtotal,
        ]);

        return response()->json([
            'quantity' => $quantity,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'formatted_subtotal' => '$' . number_format($subtotal, 2),
        ]);
    }

    private function requiresCapacityCheck(CheckoutSession $session): bool
    {
        $mode = strtolower((string) $session->delivery_mode);

        if ($mode === '' || $mode === 'online') {
            return false;
        }

        return $session->stock_quantity !== null;
    }
}