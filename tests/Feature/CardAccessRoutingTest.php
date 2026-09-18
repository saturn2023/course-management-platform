<?php

namespace Tests\Feature;

use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Registration, billing and payment are one page. These tests cover which
 * payment section each kind of visitor is served on that page, and that the
 * submit endpoints reject a method the visitor is not entitled to — the page
 * decides what is rendered, the endpoints decide what is accepted.
 */
class CardAccessRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.pin', [
            'publishable_key' => 'pk_test',
            'secret_key' => 'sk_test',
            'base_url' => 'https://test-api.pinpayments.com/1',
            'sandbox' => true,
        ]);
    }

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

    private function checkoutSession(): CheckoutSession
    {
        return CheckoutSession::create([
            'course_code' => 'RIIWHS204E',
            'plan_id' => 'PLAN-1',
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
            'expires_at' => now()->addHour(),
        ]);
    }

    private function detailsPayload(): array
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

    private function poPayload(): array
    {
        return $this->detailsPayload() + [
            'purchase_order_number' => 'PO-12345',
            'purchase_order_document' => UploadedFile::fake()
                ->create('po.pdf', 120, 'application/pdf'),
        ];
    }

    private function cardPayload(): array
    {
        return $this->detailsPayload() + ['card_token' => 'card_x'];
    }

    /*
    |--------------------------------------------------------------------------
    | 1. The checkout page serves the right payment section
    |--------------------------------------------------------------------------
    */
    public function test_guest_sees_the_card_form_and_no_purchase_order_section(): void
    {
        $response = $this->get(route('checkout.show', $this->checkoutSession()));

        $response->assertOk();
        $response->assertSee('id="cardPane"', false);
        $response->assertSee('pin-card-number', false);
        $response->assertDontSee('id="poPane"', false);
        $response->assertDontSee('purchase_order_number', false);
        $response->assertDontSee('id="methodTabs"', false);
    }

    public function test_normal_customer_sees_the_card_form_and_no_purchase_order_section(): void
    {
        $response = $this->actingAs($this->normalUser())
            ->get(route('checkout.show', $this->checkoutSession()));

        $response->assertOk();
        $response->assertSee('id="cardPane"', false);
        $response->assertDontSee('id="poPane"', false);
        $response->assertDontSee('purchase_order_number', false);
        $response->assertDontSee('id="methodTabs"', false);
    }

    public function test_po_client_sees_the_purchase_order_form_and_no_card_section(): void
    {
        $response = $this->actingAs($this->poOnlyClient())
            ->get(route('checkout.show', $this->checkoutSession()));

        $response->assertOk();
        $response->assertSee('id="poPane"', false);
        $response->assertSee('purchase_order_number', false);
        $response->assertSee('purchase_order_document', false);
        $response->assertDontSee('id="cardPane"', false);
        $response->assertDontSee('pin-card-number', false);
        $response->assertDontSee('id="methodTabs"', false);
    }

    public function test_admin_sees_both_sections_and_the_inline_selector(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->get(route('checkout.show', $this->checkoutSession()));

        $response->assertOk();
        $response->assertSee('id="methodTabs"', false);
        $response->assertSee('id="cardPane"', false);
        $response->assertSee('id="poPane"', false);
        $response->assertSee('Pay by card', false);
        $response->assertSee('Purchase Order', false);
    }

    public function test_the_continue_to_checkout_step_is_gone(): void
    {
        // No intermediate button and no intermediate page: payment is posted
        // from the checkout page itself.
        $this->get(route('checkout.show', $this->checkoutSession()))
            ->assertOk()
            ->assertDontSee('Continue to Checkout');

        $this->assertFalse(app('router')->has('checkout.payment-method.show'));
        $this->assertFalse(app('router')->has('checkout.details.save'));
        $this->assertFalse(app('router')->has('checkout.card-payment.show'));
        $this->assertFalse(app('router')->has('checkout.purchase-order.show'));
    }

    /*
    |--------------------------------------------------------------------------
    | 2. The submit endpoints enforce the same rules, whatever is posted
    |--------------------------------------------------------------------------
    */
    public function test_po_client_cannot_post_a_card_charge(): void
    {
        $this->actingAs($this->poOnlyClient())
            ->post(route('checkout.card-payment.store', $this->checkoutSession()), $this->cardPayload())
            ->assertForbidden();
    }

    public function test_guest_cannot_post_a_purchase_order(): void
    {
        Storage::fake('local');

        $this->post(route('checkout.purchase-order.store', $this->checkoutSession()), $this->poPayload())
            ->assertForbidden();
    }

    public function test_normal_customer_cannot_post_a_purchase_order(): void
    {
        Storage::fake('local');

        $this->actingAs($this->normalUser())
            ->post(route('checkout.purchase-order.store', $this->checkoutSession()), $this->poPayload())
            ->assertForbidden();
    }

    public function test_admin_may_post_either_method(): void
    {
        Queue::fake();
        Storage::fake('local');
        Http::fake(['*' => Http::response(['response' => [
            'token' => 'ch_ok', 'success' => true, 'amount' => 10000,
            'currency' => 'AUD', 'status_message' => 'Success',
            'card' => ['display_number' => 'XXXX-XXXX-XXXX-0000', 'scheme' => 'visa'],
        ]], 201)]);

        $admin = $this->adminUser();

        // Each method needs its own checkout: submitting completes the session.
        $poSession = $this->checkoutSession();
        $this->actingAs($admin)
            ->post(route('checkout.purchase-order.store', $poSession), $this->poPayload())
            ->assertRedirect(route('checkout.thank-you', $poSession));

        $cardSession = $this->checkoutSession();
        $this->actingAs($admin)
            ->post(route('checkout.card-payment.store', $cardSession), $this->cardPayload())
            ->assertRedirect(route('checkout.thank-you', $cardSession));

        // Two orders, one by each method.
        $this->assertSame(
            ['card', 'purchase_order'],
            Order::query()->orderBy('payment_method')->pluck('payment_method')->all()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Access-method unit coverage (unchanged rules)
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
