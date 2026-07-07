<?php

namespace Tests\Feature;

use App\Models\CheckoutSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardAccessRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function normalUser(): User
    {
        return User::create([
            'name' => 'Normal',
            'email' => 'normal@example.test',
            'password' => 'secret',
            'is_admin' => false,
            'can_use_purchase_order' => false,
        ]);
    }

    private function poOnlyClient(): User
    {
        return User::create([
            'name' => 'PO Only',
            'email' => 'po@example.test',
            'password' => 'secret',
            'is_admin' => false,
            'can_use_purchase_order' => true,
        ]);
    }

    private function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret',
            'is_admin' => true,
            'can_use_purchase_order' => false,
        ]);
    }

    private function baseSessionAttributes(): array
    {
        return [
            'course_code' => 'RIIWHS204E',
            'plan_id' => 'PLAN-1',
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
            'expires_at' => now()->addHour(),
        ];
    }

    /** Session that already has saved details (passes hasSavedDetails guards). */
    private function sessionWithDetails(): CheckoutSession
    {
        return CheckoutSession::create($this->baseSessionAttributes() + [
            'student_details' => [[
                'first_name' => 'Stu', 'last_name' => 'Dent',
                'email' => 'stu@example.test', 'phone' => '0400000000',
            ]],
            'billing_details' => [
                'first_name' => 'Bill', 'last_name' => 'Payer',
                'email' => 'bill@example.test',
            ],
            'details_completed_at' => now(),
        ]);
    }

    /** Session without details, used to exercise the saveDetails fork. */
    private function sessionWithoutDetails(): CheckoutSession
    {
        return CheckoutSession::create($this->baseSessionAttributes());
    }

    private function validDetailsPayload(): array
    {
        return [
            'students' => [[
                'first_name' => 'Stu', 'last_name' => 'Dent',
                'email' => 'stu@example.test', 'phone' => '0400000000',
            ]],
            'billing' => [
                'first_name' => 'Bill', 'last_name' => 'Payer',
                'email' => 'bill@example.test',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Guest: card allowed, PO blocked
    |--------------------------------------------------------------------------
    */
    public function test_guest_can_reach_card_and_cannot_reach_po(): void
    {
        $session = $this->sessionWithDetails();

        $this->get(route('checkout.card-payment.show', $session))
            ->assertOk();

        $this->get(route('checkout.purchase-order.show', $session))
            ->assertForbidden();
    }

    public function test_guest_saving_details_is_routed_to_card(): void
    {
        $session = $this->sessionWithoutDetails();

        $this->post(route('checkout.details.save', $session), $this->validDetailsPayload())
            ->assertRedirect(route('checkout.card-payment.show', $session));
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Normal logged-in customer: card allowed, PO blocked
    |--------------------------------------------------------------------------
    */
    public function test_normal_user_can_reach_card_and_cannot_reach_po(): void
    {
        $session = $this->sessionWithDetails();
        $user = $this->normalUser();

        $this->actingAs($user)
            ->get(route('checkout.card-payment.show', $session))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('checkout.purchase-order.show', $session))
            ->assertForbidden();
    }

    public function test_normal_user_saving_details_is_routed_to_card(): void
    {
        $session = $this->sessionWithoutDetails();

        $this->actingAs($this->normalUser())
            ->post(route('checkout.details.save', $session), $this->validDetailsPayload())
            ->assertRedirect(route('checkout.card-payment.show', $session));
    }

    /*
    |--------------------------------------------------------------------------
    | 3. PO-only client: routed to PO, card blocked
    |--------------------------------------------------------------------------
    */
    public function test_po_only_client_is_routed_to_po_and_cannot_reach_card(): void
    {
        $user = $this->poOnlyClient();

        $forkSession = $this->sessionWithoutDetails();
        $this->actingAs($user)
            ->post(route('checkout.details.save', $forkSession), $this->validDetailsPayload())
            ->assertRedirect(route('checkout.purchase-order.show', $forkSession));

        $guardSession = $this->sessionWithDetails();
        $this->actingAs($user)
            ->get(route('checkout.card-payment.show', $guardSession))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('checkout.purchase-order.show', $guardSession))
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Admin: reaches choice page and can enter both paths
    |--------------------------------------------------------------------------
    */
    public function test_admin_is_routed_to_choice_and_can_enter_both_paths(): void
    {
        $user = $this->adminUser();

        $forkSession = $this->sessionWithoutDetails();
        $this->actingAs($user)
            ->post(route('checkout.details.save', $forkSession), $this->validDetailsPayload())
            ->assertRedirect(route('checkout.payment-method.show', $forkSession));

        $session = $this->sessionWithDetails();

        $this->actingAs($user)
            ->get(route('checkout.payment-method.show', $session))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('checkout.card-payment.show', $session))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('checkout.purchase-order.show', $session))
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | 5. URL tampering cannot bypass access rules
    |--------------------------------------------------------------------------
    */
    public function test_url_changes_cannot_bypass_access_rules(): void
    {
        $session = $this->sessionWithDetails();
        $poOnly = $this->poOnlyClient();
        $normal = $this->normalUser();

        // Guest check first, before any actingAs (which persists for the test):
        // the admin-only choice page is not reachable by a guest.
        $this->get(route('checkout.payment-method.show', $session))
            ->assertForbidden();

        // Normal user cannot reach the admin-only choice page.
        $this->actingAs($normal)
            ->get(route('checkout.payment-method.show', $session))
            ->assertForbidden();

        // PO-only client cannot reach the choice page...
        $this->actingAs($poOnly)
            ->get(route('checkout.payment-method.show', $session))
            ->assertForbidden();

        // ...nor force their way onto card checkout by entering the URL.
        $this->actingAs($poOnly)
            ->get(route('checkout.card-payment.show', $session))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Existing Purchase Order behaviour is intact
    |--------------------------------------------------------------------------
    */
    public function test_existing_purchase_order_access_is_unchanged(): void
    {
        $session = $this->sessionWithDetails();
        $admin = $this->adminUser();
        $poOnly = $this->poOnlyClient();
        $normal = $this->normalUser();

        // Guest check first, before any actingAs (which persists for the test).
        $this->get(route('checkout.purchase-order.show', $session))
            ->assertForbidden();

        // Normal customer cannot.
        $this->actingAs($normal)
            ->get(route('checkout.purchase-order.show', $session))
            ->assertForbidden();

        // Admins and PO-only clients can view PO checkout.
        $this->actingAs($admin)
            ->get(route('checkout.purchase-order.show', $session))
            ->assertOk();

        $this->actingAs($poOnly)
            ->get(route('checkout.purchase-order.show', $session))
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Access-method unit coverage
    |--------------------------------------------------------------------------
    */
    public function test_user_access_methods_resolve_correctly(): void
    {
        $admin = $this->adminUser();
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isPurchaseOrderOnlyClient());
        $this->assertTrue($admin->canPayByCard());
        $this->assertTrue($admin->canPayByPurchaseOrder());

        $po = $this->poOnlyClient();
        $this->assertFalse($po->isAdmin());
        $this->assertTrue($po->isPurchaseOrderOnlyClient());
        $this->assertFalse($po->canPayByCard());
        $this->assertTrue($po->canPayByPurchaseOrder());

        $normal = $this->normalUser();
        $this->assertFalse($normal->isAdmin());
        $this->assertFalse($normal->isPurchaseOrderOnlyClient());
        $this->assertTrue($normal->canPayByCard());
        $this->assertFalse($normal->canPayByPurchaseOrder());
    }
}
