<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\XeroConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Access control on the Xero OAuth endpoints.
 *
 * The callback is what writes the credentials every invoice depends on, so
 * these two routes decide who can repoint the whole integration at a
 * different Xero organisation. They must be admin-only.
 */
class XeroConnectAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'services.xero.client_id' => 'test-client-id',
            'services.xero.client_secret' => 'test-client-secret',
            'services.xero.redirect_uri' => url('/xero/callback'),
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret',
            'is_admin' => true,
        ]);
    }

    private function customer(): User
    {
        return User::create([
            'name' => 'Customer',
            'email' => 'customer@example.test',
            'password' => 'secret',
            'is_admin' => false,
        ]);
    }

    public function test_the_routes_exist(): void
    {
        // They were deleted by accident once; this is the regression guard.
        $this->assertSame(url('/xero/connect'), route('xero.connect'));
        $this->assertSame(url('/xero/callback'), route('xero.callback'));
    }

    public function test_a_guest_cannot_start_a_xero_connection(): void
    {
        $this->get(route('xero.connect'))->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_a_guest_cannot_reach_the_callback(): void
    {
        $this->get(route('xero.callback', ['code' => 'abc', 'state' => 'xyz']))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertSame(0, XeroConnection::count());
    }

    public function test_a_non_admin_customer_cannot_start_a_xero_connection(): void
    {
        $this->actingAs($this->customer())
            ->get(route('xero.connect'))
            ->assertForbidden();
    }

    public function test_a_non_admin_customer_cannot_reach_the_callback(): void
    {
        $this->actingAs($this->customer())
            ->get(route('xero.callback', ['code' => 'abc', 'state' => 'xyz']))
            ->assertForbidden();

        $this->assertSame(0, XeroConnection::count());
    }

    public function test_an_admin_is_redirected_to_xero_with_the_scopes_we_need(): void
    {
        $response = $this->actingAs($this->admin())->get(route('xero.connect'));

        $response->assertRedirectContains('https://login.xero.com/identity/connect/authorize');

        $target = $response->headers->get('Location');

        // offline_access is what gives us a refresh token; without it the
        // connection would die in 30 minutes and could not be renewed.
        $this->assertStringContainsString('offline_access', urldecode($target));
        $this->assertStringContainsString('accounting.invoices', urldecode($target));
        $this->assertStringContainsString('accounting.contacts', urldecode($target));

        // The state must be held server-side for the callback to check.
        $this->assertNotNull(session('xero_oauth_state'));
    }

    public function test_the_callback_rejects_a_mismatched_state(): void
    {
        $this->actingAs($this->admin())
            ->withSession(['xero_oauth_state' => 'the-real-state'])
            ->get(route('xero.callback', ['code' => 'abc', 'state' => 'forged']))
            ->assertForbidden();

        $this->assertSame(0, XeroConnection::count());
    }

    public function test_a_successful_callback_stores_the_connection_and_deactivates_the_old_one(): void
    {
        $previous = XeroConnection::create([
            'tenant_id' => 'old-tenant',
            'access_token' => 'old-access',
            'refresh_token' => 'old-refresh',
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        Http::fake([
            'https://identity.xero.com/connect/token' => Http::response([
                'access_token' => 'new-access',
                'refresh_token' => 'new-refresh',
                'expires_in' => 1800,
            ], 200),
            'https://api.xero.com/connections' => Http::response([[
                'tenantId' => 'new-tenant',
                'tenantName' => 'AMS Training',
            ]], 200),
        ]);

        $this->actingAs($this->admin())
            ->withSession(['xero_oauth_state' => 'the-real-state'])
            ->get(route('xero.callback', ['code' => 'abc', 'state' => 'the-real-state']))
            ->assertRedirect('/admin');

        $this->assertFalse($previous->fresh()->is_active);

        $connection = XeroConnection::where('is_active', true)->sole();

        $this->assertSame('new-tenant', $connection->tenant_id);
        $this->assertSame('new-access', $connection->access_token);
        $this->assertSame('new-refresh', $connection->refresh_token);
        $this->assertTrue($connection->expires_at->isFuture());
    }

    public function test_a_failed_token_exchange_does_not_leak_the_response_to_the_browser(): void
    {
        Http::fake([
            'https://identity.xero.com/connect/token' => Http::response([
                'error' => 'invalid_client',
                'detail' => 'test-client-secret was rejected',
            ], 401),
        ]);

        $response = $this->actingAs($this->admin())
            ->withSession(['xero_oauth_state' => 'the-real-state'])
            ->get(route('xero.callback', ['code' => 'abc', 'state' => 'the-real-state']));

        // The old dd() dumped the raw response — including the credentials in
        // play — onto the page.
        $response->assertRedirect('/admin');
        $response->assertSessionHas('error');
        $this->assertStringNotContainsString('test-client-secret', $response->getContent());

        $this->assertSame(0, XeroConnection::count());
    }
}
