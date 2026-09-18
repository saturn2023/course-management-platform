<?php

namespace App\Services;

use App\Models\CheckoutSession;

/**
 * Persists the student and billing details captured on the checkout page.
 *
 * Both payment endpoints call this immediately after validation and before
 * taking payment, so the session always carries the details that the order is
 * later built from — whichever method was used.
 */
class CheckoutDetailsWriter
{
    /**
     * @param  array<int, array<string, mixed>>  $students
     * @param  array<string, mixed>  $billing
     */
    public function save(CheckoutSession $session, array $students, array $billing): void
    {
        $session->update([
            'student_details'      => array_values($students),
            'billing_details'      => $billing,
            'details_completed_at' => now(),
        ]);
    }
}
