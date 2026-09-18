<?php

namespace Tests\Feature;

use App\Jobs\ProcessOrderJob;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Which payment section each visitor is served is covered by
 * CardAccessRoutingTest. This covers the purchase-order POST — the endpoint
 * that actually creates the order, and so the one that has to hold when a
 * payload is replayed directly rather than sent from the checkout page.
 */
class PurchaseOrderAccessTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * The checkout page posts the student and billing details together with
     * the purchase order, so a valid submission now carries all of them.
     */
    private function validPayload(): array
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
            'purchase_order_number' => 'PO-12345',
            'purchase_order_document' => UploadedFile::fake()
                ->create('po.pdf', 120, 'application/pdf'),
        ];
    }

    public function test_guest_cannot_submit_a_purchase_order(): void
    {
        $session = $this->checkoutSession();

        $this->post(route('checkout.purchase-order.store', $session), $this->validPayload())
            ->assertForbidden();

        $this->assertSame(0, Order::count());
    }

    public function test_normal_customer_cannot_submit_a_purchase_order(): void
    {
        $session = $this->checkoutSession();

        $this->actingAs($this->normalUser())
            ->post(route('checkout.purchase-order.store', $session), $this->validPayload())
            ->assertForbidden();

        $this->assertSame(0, Order::count());
    }

    public function test_po_client_submission_still_creates_the_order_and_stores_the_document(): void
    {
        Queue::fake();
        Storage::fake('local');

        $session = $this->checkoutSession();

        $this->actingAs($this->poOnlyClient())
            ->post(route('checkout.purchase-order.store', $session), $this->validPayload())
            ->assertRedirect(route('checkout.thank-you', $session));

        $order = Order::sole();

        $this->assertSame('purchase_order', $order->payment_method);
        $this->assertSame('PO-12345', $order->purchase_order_number);
        $this->assertNotNull($order->purchase_order_document_path);

        // The uploaded PO is kept on the private disk, not the public one.
        Storage::disk('local')->assertExists($order->purchase_order_document_path);

        // Order carries the checkout's own pricing, not anything client-supplied.
        $this->assertEquals(100.00, (float) $order->total);

        // The details posted alongside the PO are what the order was built from.
        $this->assertSame('Bill', $order->billing_first_name);
        $this->assertSame('stu@example.test', $order->students->first()->email);

        $session->refresh();
        $this->assertNotNull($session->details_completed_at);
        $this->assertSame($order->id, $session->order_id);
        $this->assertNotNull($session->completed_at);

        Queue::assertPushed(ProcessOrderJob::class);
    }

    public function test_purchase_order_validation_is_preserved(): void
    {
        $session = $this->checkoutSession();

        $this->actingAs($this->poOnlyClient())
            ->post(route('checkout.purchase-order.store', $session), [])
            ->assertSessionHasErrors([
                'purchase_order_number',
                'purchase_order_document',
                'students',
                'billing',
            ]);

        $this->assertSame(0, Order::count());
    }

    public function test_a_completed_checkout_cannot_be_submitted_twice(): void
    {
        Queue::fake();
        Storage::fake('local');

        $session = $this->checkoutSession();
        $session->update(['completed_at' => now()]);

        $this->actingAs($this->poOnlyClient())
            ->post(route('checkout.purchase-order.store', $session), $this->validPayload())
            ->assertStatus(409);

        $this->assertSame(0, Order::count());
    }
}
