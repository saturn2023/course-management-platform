<?php

namespace Tests\Feature;

use App\Models\CheckoutSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardPaymentPageTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLISHABLE = 'pk_test_public_key';
    private const SECRET = 'sk_test_secret_key';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.pin', [
            'publishable_key' => self::PUBLISHABLE,
            'secret_key' => self::SECRET,
            'base_url' => 'https://test-api.pinpayments.com/1',
            'sandbox' => true,
        ]);
    }

    private function sessionWithDetails(): CheckoutSession
    {
        return CheckoutSession::create([
            'course_code' => 'RIIWHS204E',
            'plan_id' => 'PLAN-1',
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
            'expires_at' => now()->addHour(),
            'student_details' => [[
                'first_name' => 'Stu', 'last_name' => 'Dent',
                'email' => 'stu@example.test', 'phone' => '0400000000',
            ]],
            'billing_details' => [
                'first_name' => 'Bill', 'last_name' => 'Payer',
                'email' => 'bill@example.test', 'address_1' => '42 Sevenoaks St', 'city' => 'Lathlain',
            ],
            'details_completed_at' => now(),
        ]);
    }

    private function poOnlyClient(): User
    {
        return User::create([
            'name' => 'PO Only', 'email' => 'po@example.test', 'password' => 'secret',
            'is_admin' => false, 'can_use_purchase_order' => true,
        ]);
    }

    public function test_card_page_renders_hosted_fields_for_a_guest(): void
    {
        $session = $this->sessionWithDetails();

        $response = $this->get(route('checkout.card-payment.show', $session));

        $response->assertOk();
        $response->assertSee('cdn.pinpayments.com/pin.hosted_fields.v1.js', false);
        $response->assertSee('js/checkout-card-payment.js', false);

        // All four Hosted Fields mount points must render (Pin requires exactly
        // name, number, cvc and expiry as Hosted Fields).
        $response->assertSee('id="pin-card-name"', false);
        $response->assertSee('id="pin-card-number"', false);
        $response->assertSee('id="pin-card-cvc"', false);
        $response->assertSee('id="pin-card-expiry"', false);

        // The old plain cardholder-name input must be gone.
        $response->assertDontSee('id="pin-cardholder-name"', false);
    }

    public function test_card_page_exposes_only_publishable_key_and_sandbox_flag(): void
    {
        $session = $this->sessionWithDetails();

        $response = $this->get(route('checkout.card-payment.show', $session));

        $response->assertOk();
        // Publishable key + sandbox flag are present...
        $response->assertSee(self::PUBLISHABLE, false);
        $response->assertSee('data-sandbox="true"', false);
        // ...the secret key must never be rendered to the browser.
        $response->assertDontSee(self::SECRET, false);
    }

    public function test_card_page_shows_the_server_side_total(): void
    {
        $session = $this->sessionWithDetails();

        $this->get(route('checkout.card-payment.show', $session))
            ->assertOk()
            ->assertSee('$100.00', false);
    }

    public function test_po_only_client_still_cannot_reach_card_page(): void
    {
        $session = $this->sessionWithDetails();

        $this->actingAs($this->poOnlyClient())
            ->get(route('checkout.card-payment.show', $session))
            ->assertForbidden();
    }

    public function test_card_page_blocked_when_details_not_saved(): void
    {
        $session = CheckoutSession::create([
            'course_code' => 'RIIWHS204E',
            'plan_id' => 'PLAN-1',
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
            'expires_at' => now()->addHour(),
        ]);

        $this->get(route('checkout.card-payment.show', $session))
            ->assertStatus(422);
    }
}
