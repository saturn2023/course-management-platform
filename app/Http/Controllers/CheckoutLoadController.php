<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutLoadRequest;
use App\Models\CheckoutSession;
use App\Services\RtoDataCourseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckoutLoadController extends Controller
{
    public function __construct(private RtoDataCourseService $rtoData)
    {
    }

    public function __invoke(CheckoutLoadRequest $request): RedirectResponse|Response
    {
        $code = $request->string('code')->toString();
        $plan = $request->string('plan')->toString();
        $schedule = $request->input('schedule');
        $quantity = (int) $request->input('quantity', 1);

        try {
            $detail = $this->rtoData->getCourseDetail($code, $plan, $schedule);
        } catch (Throwable $e) {
            Log::warning('Checkout load failed: RTO Data lookup', [
                'message' => $e->getMessage(),
                'course_code' => $code,
                'plan_id' => $plan,
                'schedule_id' => $schedule,
            ]);

            return $this->errorView($e->getMessage() ?: 'Could not load course details.');
        }

        /*
        |--------------------------------------------------------------------------
        | Safety: block missing, zero, or $1.00 sentinel prices
        |--------------------------------------------------------------------------
        |
        | The old WooCommerce system blocked $1.00 prices because the real price
        | was supposed to come from RTO Data, not the placeholder Woo product.
        |
        */

        $unitPrice = (float) $detail['unit_price'];

        if ($unitPrice <= 0.0 || abs($unitPrice - 1.00) < 0.005) {
            Log::warning('Checkout load blocked: invalid price', [
                'course_code' => $code,
                'plan_id' => $plan,
                'schedule_id' => $schedule,
                'unit_price' => $unitPrice,
            ]);

            return $this->errorView(
                'This course is not available for online enrolment at the moment. Please contact us to enrol.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Safety: capacity check for non-online courses
        |--------------------------------------------------------------------------
        */

        if ($this->requiresCapacityCheck($detail)) {
            $remaining = (int) $detail['stock_quantity'] - (int) ($detail['enrolments'] ?? 0);

            if ($remaining < $quantity) {
                return $this->errorView(
                    $remaining > 0
                        ? "Only {$remaining} place(s) remaining on this schedule."
                        : 'This schedule is fully booked.'
                );
            }
        }

        $subtotal = round($unitPrice * $quantity, 2);

        $session = CheckoutSession::create([
            'course_code' => $detail['course_code'],
            'plan_id' => $detail['plan_id'],
            'schedule_id' => $detail['schedule_id'],
            'quantity' => $quantity,
            'course_title' => $detail['course_title'],
            'plan_title' => $detail['plan_title'],
            'start_date' => $detail['start_date'],
            'end_date' => $detail['end_date'],
            'dates' => $detail['dates'],
            'delivery_mode' => $detail['delivery_mode'],
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'stock_quantity' => $detail['stock_quantity'],
            'enrolments' => $detail['enrolments'],
            'rto_payload' => $detail['raw'],
            'expires_at' => now()->addMinutes(60),
        ]);

        return redirect()->route('checkout.show', $session);
    }

    private function requiresCapacityCheck(array $detail): bool
    {
        $mode = $detail['delivery_mode'] ?? null;

        if ($mode === 'online') {
            return false;
        }

        return $detail['stock_quantity'] !== null;
    }

    private function errorView(string $message): Response
    {
        return response()->view('checkout.load-error', [
            'message' => $message,
        ], 422);
    }
}