<?php

namespace App\Http\Controllers;

use App\Models\CheckoutSession;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     * Create the Purchase Order order from the checkout session.
     *
     * This intentionally does NOT dispatch ProcessOrderJob. The order is created
     * in a po_pending state and waits for an admin to review and process it.
     */
    public function store(Request $request, CheckoutSession $checkoutSession): RedirectResponse
    {
        $this->guardSession($checkoutSession);

        $validated = $request->validate([
            'purchase_order_number' => ['required', 'string', 'max:100'],
            'purchase_order_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:25600'], // 25 MB
        ]);

        $billing = $checkoutSession->billing_details ?? [];
        $students = $this->normaliseStudents($checkoutSession->student_details ?? []);

        $order = DB::transaction(function () use ($checkoutSession, $validated, $request, $billing, $students) {
            // Store the PO document on the default (local/private) disk.
            $documentPath = $request->file('purchase_order_document')->store(
                "purchase-orders/{$checkoutSession->uuid}"
            );

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

                'purchase_order_number' => $validated['purchase_order_number'],
                'purchase_order_document_path' => $documentPath,
                'payment_method' => 'purchase_order',
                'payment_status' => 'pending',

                'subtotal' => $checkoutSession->subtotal,
                'total' => $checkoutSession->subtotal,

                'status' => 'po_pending',
                'xero_status' => 'pending',
                'enrolment_status' => 'pending',
            ]);

            // Create Student records from the saved details and attach via the pivot.
            $firstStudentId = null;

            foreach ($students as $studentData) {
                $student = Student::create([
                    'first_name' => $studentData['first_name'] ?? null,
                    'last_name' => $studentData['last_name'] ?? null,
                    'email' => $studentData['email'] ?? null,
                    'phone' => $studentData['phone'] ?? null,
                    'date_of_birth' => $studentData['date_of_birth'] ?? null,
                ]);

                $order->students()->attach($student->id);

                $firstStudentId ??= $student->id;
            }

            // Backward-compatible primary student.
            if ($firstStudentId !== null) {
                $order->update(['student_id' => $firstStudentId]);
            }

            // Single order item representing the course/plan.
            OrderItem::create([
                'order_id' => $order->id,
                'course_id' => $this->resolveCourseId($checkoutSession->course_code),
                'name' => $this->buildItemName($checkoutSession),
                'quantity' => $checkoutSession->quantity,
                'unit_price' => $checkoutSession->unit_price,
                'total' => $checkoutSession->subtotal,
            ]);

            // Link the session to the order and mark it completed.
            $checkoutSession->update([
                'order_id' => $order->id,
                'completed_at' => now(),
            ]);

            return $order;
        });

        return redirect()
            ->route('checkout.thank-you', $checkoutSession->uuid)
            ->with('order_id', $order->id);
    }

    /**
     * Shared guard rails for both show and store.
     */
    private function guardSession(CheckoutSession $checkoutSession): void
    {
        abort_if($checkoutSession->isExpired(), 410, 'This checkout session has expired.');
        abort_if($checkoutSession->isCompleted(), 409, 'This checkout session is already completed.');
        abort_unless($checkoutSession->hasSavedDetails(), 422, 'Student and billing details must be saved first.');
    }

    /**
     * student_details may be a list of students, or a single student object.
     * Normalise to a list so the loop is uniform.
     *
     * NOTE: adjust the key names below if your student_details JSON uses
     * different keys than first_name/last_name/email/phone/date_of_birth.
     */
    private function normaliseStudents(array $studentDetails): array
    {
        if ($studentDetails === []) {
            return [];
        }

        // Already a list of students.
        if (array_is_list($studentDetails) && isset($studentDetails[0]) && is_array($studentDetails[0])) {
            return $studentDetails;
        }

        // A single student keyed object.
        return [$studentDetails];
    }

    private function buildItemName(CheckoutSession $session): string
    {
        $name = $session->course_title ?: ($session->course_code ?: 'Course');

        if (! empty($session->plan_title)) {
            $name .= ' - ' . $session->plan_title;
        }

        return $name;
    }

    /**
     * Best-effort resolution of a Course id from the session's course_code.
     *
     * The exact lookup column isn't known here, so we only query columns that
     * actually exist on the courses table and fall back to null. Confirm that
     * order_items.course_id is nullable, or wire this to your real Course lookup.
     */
    private function resolveCourseId(?string $courseCode): ?int
    {
        if (empty($courseCode) || ! class_exists(Course::class)) {
            return null;
        }

        $candidateColumns = ['ams_enrolment_code', 'code', 'course_code'];

        $course = Course::query()
            ->where(function (Builder $query) use ($candidateColumns, $courseCode) {
                foreach ($candidateColumns as $column) {
                    if (Schema::hasColumn((new Course())->getTable(), $column)) {
                        $query->orWhere($column, $courseCode);
                    }
                }
            })
            ->first();

        return $course?->id;
    }
}