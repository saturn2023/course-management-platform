<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOrderJob;
use App\Models\CheckoutSession;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurchaseOrderCheckoutController extends Controller
{
    /**
     * Show the Purchase Order checkout page.
     */
    public function show(CheckoutSession $checkoutSession): View
    {
        $this->guardSession($checkoutSession);

        return view('checkout.purchase-order', [
            'session' => $checkoutSession,
            'billing' => $checkoutSession->billing_details ?? [],
        ]);
    }

    /**
     * Create and automatically process a Purchase Order checkout.
     *
     * The order is created as processing, then ProcessOrderJob is dispatched.
     * That existing job creates:
     * - the unpaid/draft Xero invoice
     * - enrolment records and secure enrolment links
     * - student emails
     * - purchaser confirmation email
     */
    public function store(
        Request $request,
        CheckoutSession $checkoutSession
    ): RedirectResponse {
        $this->guardSession($checkoutSession);

        $validated = $request->validate([
            'purchase_order_number' => [
                'required',
                'string',
                'max:100',
            ],
            'purchase_order_document' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:25600',
            ],
        ]);

        $billing = $checkoutSession->billing_details ?? [];
        $students = $this->normaliseStudents(
            $checkoutSession->student_details ?? []
        );

        abort_if(
            $students === [],
            422,
            'Student details are required before submitting a purchase order.'
        );

        abort_if(
            count($students) !== (int) $checkoutSession->quantity,
            422,
            'The number of saved students does not match the checkout quantity.'
        );

        /*
        |--------------------------------------------------------------------------
        | Store the Purchase Order document
        |--------------------------------------------------------------------------
        |
        | The local disk is private by default, so the uploaded document is not
        | publicly accessible through a direct URL.
        |
        */

        $documentPath = $request
            ->file('purchase_order_document')
            ->store(
                "purchase-orders/{$checkoutSession->uuid}",
                'local'
            );

        try {
            $order = DB::transaction(function () use (
                $checkoutSession,
                $validated,
                $billing,
                $students,
                $documentPath
            ) {
                /*
                |--------------------------------------------------------------------------
                | Create the Order
                |--------------------------------------------------------------------------
                */

                $order = Order::create([
                    'billing_first_name' => $billing['first_name'] ?? null,
                    'billing_last_name' => $billing['last_name'] ?? null,
                    'billing_company' => $billing['company'] ?? null,
                    'billing_email' => $billing['email'] ?? null,
                    'billing_phone' => $billing['phone'] ?? null,
                    'billing_address_1' => $billing['address_1'] ?? null,
                    'billing_address_2' => $billing['address_2'] ?? null,
                    'billing_city' => $billing['city'] ?? null,
                    'billing_postcode' => $billing['postcode'] ?? null,
                    'billing_abn' => $billing['abn'] ?? null,

                    'purchase_order_number' =>
                        $validated['purchase_order_number'],

                    'purchase_order_document_path' => $documentPath,

                    'payment_method' => 'purchase_order',
                    'payment_status' => 'pending',

                    'subtotal' => $checkoutSession->subtotal,
                    'total' => $checkoutSession->subtotal,

                    /*
                     * Processing begins automatically after checkout.
                     */
                    'status' => 'processing',

                    /*
                     * The Xero invoice will be created as DRAFT/unpaid.
                     */
                    'xero_status' => 'pending',

                    /*
                     * Enrolment links have not been created yet.
                     */
                    'enrolment_status' => 'pending',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Create and attach Students
                |--------------------------------------------------------------------------
                */

                $firstStudentId = null;

                foreach ($students as $studentData) {
                    $student = Student::create([
                        'first_name' => $studentData['first_name'] ?? null,
                        'last_name' => $studentData['last_name'] ?? null,
                        'email' => $studentData['email'] ?? null,
                        'phone' => $studentData['phone'] ?? null,
                        'date_of_birth' =>
                            $studentData['date_of_birth'] ?? null,
                    ]);

                    $order->students()->attach($student->id);

                    $firstStudentId ??= $student->id;
                }

                /*
                 * Keep student_id populated for older code that still expects
                 * one primary student on the order.
                 */
                if ($firstStudentId !== null) {
                    $order->update([
                        'student_id' => $firstStudentId,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Create the Order Item
                |--------------------------------------------------------------------------
                */

                OrderItem::create([
                    'order_id' => $order->id,
                    'course_id' => $this->resolveCourseId($checkoutSession),
                    'name' => $this->buildItemName($checkoutSession),
                    'quantity' => $checkoutSession->quantity,
                    'unit_price' => $checkoutSession->unit_price,
                    'total' => $checkoutSession->subtotal,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Complete the Checkout Session
                |--------------------------------------------------------------------------
                */

                $checkoutSession->update([
                    'order_id' => $order->id,
                    'completed_at' => now(),
                ]);

                return $order;
            });
        } catch (Throwable $exception) {
            /*
             * If database creation fails, remove the uploaded document so an
             * unused/orphaned PO file is not left in storage.
             */
            Storage::disk('local')->delete($documentPath);

            throw $exception;
        }

        /*
        |--------------------------------------------------------------------------
        | Automatically process the PO order
        |--------------------------------------------------------------------------
        |
        | ProcessOrderJob dispatches the Xero and enrolment jobs.
        | This happens only after the database transaction succeeds.
        |
        */

        ProcessOrderJob::dispatch($order->id);

        return redirect()
            ->route('checkout.thank-you', $checkoutSession)
            ->with('order_id', $order->id);
    }

    /**
     * Protect the Purchase Order checkout.
     */
    private function guardSession(CheckoutSession $checkoutSession): void
    {
        abort_unless(
            auth()->check(),
            403,
            'You must be logged in to use purchase order checkout.'
        );

        abort_unless(
            auth()->user()->canPayByPurchaseOrder(),
            403,
            'You are not authorised to use purchase order checkout.'
        );

        abort_if(
            $checkoutSession->isExpired(),
            410,
            'This checkout session has expired.'
        );

        abort_if(
            $checkoutSession->isCompleted(),
            409,
            'This checkout session has already been completed.'
        );

        abort_unless(
            $checkoutSession->hasSavedDetails(),
            422,
            'Student and billing details must be saved first.'
        );
    }

    /**
     * Ensure student details always use a list structure.
     */
    private function normaliseStudents(array $studentDetails): array
    {
        if ($studentDetails === []) {
            return [];
        }

        if (
            array_is_list($studentDetails)
            && isset($studentDetails[0])
            && is_array($studentDetails[0])
        ) {
            return $studentDetails;
        }

        return [$studentDetails];
    }

    /**
     * Build the Xero/order-item description.
     */
    private function buildItemName(CheckoutSession $session): string
    {
        $name = $session->course_title
            ?: ($session->course_code ?: 'Course');

        if (! empty($session->plan_title)) {
            $name .= ' - ' . $session->plan_title;
        }

        return $name;
    }

    /**
     * Resolve the checkout session to the correct local Course record.
     */
    private function resolveCourseId(CheckoutSession $session): ?int
    {
        $rawCourseCode = trim((string) $session->course_code);
        $planId = trim((string) $session->plan_id);

        /*
         * RTO Data may return RIIWHS204E-2, while the local course stores
         * RIIWHS204E.
         */
        $normalisedCourseCode = preg_replace(
            '/-\d+$/',
            '',
            $rawCourseCode
        );

        $courseCodes = array_values(
            array_unique(
                array_filter([
                    $rawCourseCode,
                    $normalisedCourseCode,
                ])
            )
        );

        /*
         * Preferred lookup: course code and AMS plan ID.
         */
        $course = Course::query()
            ->where('ams_plan_id', $planId)
            ->whereIn('code', $courseCodes)
            ->first();

        if ($course) {
            return $course->id;
        }

        /*
         * Fallback: match only by exact or normalised course code.
         */
        $course = Course::query()
            ->whereIn('code', $courseCodes)
            ->first();

        if ($course) {
            return $course->id;
        }

        /*
         * Final fallback: use plan ID only when exactly one course matches.
         */
        if ($planId !== '') {
            $planMatches = Course::query()
                ->where('ams_plan_id', $planId)
                ->limit(2)
                ->get();

            if ($planMatches->count() === 1) {
                return $planMatches->first()->id;
            }
        }

        return null;
    }
}

