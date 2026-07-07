<?php

namespace App\Services;

use App\Models\CheckoutSession;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use App\Models\Student;

/**
 * Creates a paid card Order from a CheckoutSession + confirmed PaymentAttempt.
 *
 * The Order/Student/pivot/OrderItem shape mirrors PurchaseOrderCheckoutController,
 * but with card payment attributes. The caller is responsible for running this
 * inside a locked transaction and for linking the session / attempt afterwards.
 */
class CheckoutOrderCreator
{
    public function createPaidCardOrder(CheckoutSession $session, PaymentAttempt $attempt): Order
    {
        $billing = $session->billing_details ?? [];
        $students = $this->normaliseStudents($session->student_details ?? []);

        abort_if(
            $students === [],
            422,
            'Student details are required before creating an order.'
        );

        abort_if(
            count($students) !== (int) $session->quantity,
            422,
            'The number of saved students does not match the checkout quantity.'
        );

        $card = $attempt->raw_response['response']['card'] ?? [];

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

            'payment_method' => 'card',
            'payment_status' => 'paid',

            'subtotal' => $session->subtotal,
            'total' => $session->subtotal,

            'status' => 'processing',
            'xero_status' => 'pending',
            'enrolment_status' => 'pending',

            'pin_charge_token' => $attempt->pin_charge_token,
            'pin_charge_amount_cents' => $attempt->amount_cents,
            'card_scheme' => $card['scheme'] ?? null,
            'card_display_number' => $card['display_number'] ?? null,
            'paid_at' => now(),
        ]);

        $firstStudentId = null;

        foreach ($students as $studentData) {
            // Email is the unique student identity (case-insensitive, trimmed).
            // Reuse and refresh an existing student rather than duplicating.
            $email = strtolower(trim((string) ($studentData['email'] ?? '')));

            $student = Student::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $studentData['first_name'] ?? null,
                    'last_name' => $studentData['last_name'] ?? null,
                    'phone' => $studentData['phone'] ?? null,
                    'date_of_birth' => $studentData['date_of_birth'] ?? null,
                ]
            );

            $order->students()->syncWithoutDetaching([$student->id]);

            $firstStudentId ??= $student->id;
        }

        if ($firstStudentId !== null) {
            $order->update(['student_id' => $firstStudentId]);
        }

        OrderItem::create([
            'order_id' => $order->id,
            'course_id' => $this->resolveCourseId($session),
            'name' => $this->buildItemName($session),
            'quantity' => $session->quantity,
            'unit_price' => $session->unit_price,
            'total' => $session->subtotal,
        ]);

        return $order;
    }

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

    private function buildItemName(CheckoutSession $session): string
    {
        $name = $session->course_title
            ?: ($session->course_code ?: 'Course');

        if (! empty($session->plan_title)) {
            $name .= ' - ' . $session->plan_title;
        }

        return $name;
    }

    private function resolveCourseId(CheckoutSession $session): ?int
    {
        $rawCourseCode = trim((string) $session->course_code);
        $planId = trim((string) $session->plan_id);

        $normalisedCourseCode = preg_replace('/-\d+$/', '', $rawCourseCode);

        $courseCodes = array_values(
            array_unique(
                array_filter([$rawCourseCode, $normalisedCourseCode])
            )
        );

        $course = Course::query()
            ->where('ams_plan_id', $planId)
            ->whereIn('code', $courseCodes)
            ->first();

        if ($course) {
            return $course->id;
        }

        $course = Course::query()
            ->whereIn('code', $courseCodes)
            ->first();

        if ($course) {
            return $course->id;
        }

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
