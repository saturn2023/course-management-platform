<?php

namespace Tests\Feature;

use App\Models\CheckoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The site header on the checkout pages.
 *
 * Both of them, which is the point. The header was added to the checkout page
 * itself, but every expired or completed checkout link renders
 * checkout/load-error instead — and that page had no header, so a learner
 * returning to an old link landed on a bare card with no way back into the
 * site. It is also the page you see if you assume the header is missing from
 * checkout when it is not.
 */
class CheckoutHeaderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The columns checkout_sessions will not accept a row without.
     */
    private function checkoutSession(array $attributes = []): CheckoutSession
    {
        return CheckoutSession::create(array_merge([
            'course_code' => 'RIIWHS204E',
            'plan_id' => '38',
            'course_title' => 'Work Safely At Heights',
            'quantity' => 1,
            'unit_price' => 110,
            'subtotal' => 110,
            'expires_at' => now()->addDay(),
        ], $attributes));
    }

    /**
     * Markers from the header component itself, so the assertion fails if the
     * component stops being rendered rather than merely being restyled.
     */
    private function assertHeaderRendered(\Illuminate\Testing\TestResponse $response): void
    {
        $response->assertSee('ams-site-header', false);
        $response->assertSee('ams-site-header__inner', false);
        $response->assertSee('ash-primary-nav', false);
    }

    public function test_the_checkout_page_renders_the_site_header(): void
    {
        $session = $this->checkoutSession();

        $response = $this->get(route('checkout.show', $session));

        $response->assertOk();
        $response->assertViewIs('checkout.show');
        $this->assertHeaderRendered($response);
    }

    public function test_the_header_comes_before_the_page_content(): void
    {
        $session = $this->checkoutSession();

        $html = $this->get(route('checkout.show', $session))->getContent();

        $headerAt = strpos($html, 'ams-site-header__inner');
        $contentAt = strpos($html, 'Registration Page');

        $this->assertNotFalse($headerAt);
        $this->assertNotFalse($contentAt);
        $this->assertLessThan($contentAt, $headerAt);
    }

    public function test_the_body_carries_no_padding_that_would_inset_the_header(): void
    {
        $session = $this->checkoutSession();

        $html = $this->get(route('checkout.show', $session))->getContent();

        /*
         * The header is full-bleed and sticky, so the gutter belongs on the
         * container. Body padding would inset the header and offset where it
         * sticks.
         */
        $this->assertStringContainsString('.container {', $html);
        $this->assertDoesNotMatchRegularExpression('/body\s*\{[^}]*padding:\s*2rem 1rem/', $html);
    }

    /*
    |--------------------------------------------------------------------------
    | The page an old checkout link actually lands on
    |--------------------------------------------------------------------------
    */

    public function test_an_expired_checkout_still_renders_the_header(): void
    {
        $session = $this->checkoutSession(['expires_at' => now()->subDay()]);

        $response = $this->get(route('checkout.show', $session));

        $response->assertStatus(410);
        $response->assertViewIs('checkout.load-error');
        $response->assertSee('This checkout link has expired.');
        $this->assertHeaderRendered($response);
    }

    public function test_a_completed_checkout_still_renders_the_header(): void
    {
        $session = $this->checkoutSession(['completed_at' => now()->subHour()]);

        $response = $this->get(route('checkout.show', $session));

        $response->assertStatus(410);
        $response->assertViewIs('checkout.load-error');
        $response->assertSee('This checkout has already been completed.');
        $this->assertHeaderRendered($response);
    }

    public function test_the_expired_page_still_offers_a_way_back(): void
    {
        $session = $this->checkoutSession(['expires_at' => now()->subDay()]);

        $this->get(route('checkout.show', $session))
            ->assertSee(url('/courses'), false)
            ->assertSee('Return to courses');
    }
}
