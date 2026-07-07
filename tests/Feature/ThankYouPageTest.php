<?php

namespace Tests\Feature;

use App\Models\CheckoutSession;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThankYouPageTest extends TestCase
{
    use RefreshDatabase;

    private function sessionForOrder(Order $order): CheckoutSession
    {
        return CheckoutSession::create([
            'course_code' => 'RIIWHS204E',
            'plan_id' => 'PLAN-1',
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
            'expires_at' => now()->addHour(),
            'completed_at' => now(),
            'order_id' => $order->id,
        ]);
    }

    public function test_card_order_shows_payment_successful_messaging(): void
    {
        $order = Order::create([
            'subtotal' => 100, 'total' => 100,
            'payment_method' => 'card', 'payment_status' => 'paid',
        ]);
        $session = $this->sessionForOrder($order);

        $response = $this->get(route('checkout.thank-you', $session));

        $response->assertOk();
        $response->assertSee('Payment successful');
        $response->assertSee('Status: Payment received');
        $response->assertSee('card payment was received successfully');
        $response->assertSee('#' . $order->id, false);

        // Must NOT show Purchase Order wording for a card order.
        $response->assertDontSee('Purchase order received');
        $response->assertDontSee('review it shortly');
    }

    public function test_purchase_order_shows_existing_purchase_order_messaging(): void
    {
        $order = Order::create([
            'subtotal' => 100, 'total' => 100,
            'payment_method' => 'purchase_order', 'payment_status' => 'pending',
        ]);
        $session = $this->sessionForOrder($order);

        $response = $this->get(route('checkout.thank-you', $session));

        $response->assertOk();
        $response->assertSee('Purchase order received');
        $response->assertSee('Status: Purchase order received');
        $response->assertSee('review it shortly');
        $response->assertSee('#' . $order->id, false);

        // Must NOT show card wording for a PO order.
        $response->assertDontSee('Payment successful');
        $response->assertDontSee('card payment was received successfully');
    }

    public function test_thank_you_without_linked_order_preserves_purchase_order_default(): void
    {
        $session = CheckoutSession::create([
            'course_code' => 'RIIWHS204E',
            'plan_id' => 'PLAN-1',
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
            'expires_at' => now()->addHour(),
        ]);

        $this->get(route('checkout.thank-you', $session))
            ->assertOk()
            ->assertSee('Purchase order received')
            ->assertDontSee('Payment successful');
    }
}
