<?php

namespace App\Http\Controllers;

use App\Models\XeroConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Xero OAuth 2.0 authorisation code flow.
 *
 * Both endpoints are admin-only (see routes/web.php). The callback writes the
 * credentials every subsequent Xero API call depends on, so it must never be
 * reachable by a non-admin. Tokens are written straight to xero_connections
 * and are never rendered back to the browser.
 */
class XeroAuthController extends Controller
{
    public function connect(Request $request)
    {
        $this->authoriseAdmin($request);

        $state = Str::random(40);

        session(['xero_oauth_state' => $state]);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.xero.client_id'),
            'redirect_uri' => config('services.xero.redirect_uri'),
            'scope' => implode(' ', [
                'offline_access',
                'accounting.contacts',
                'accounting.invoices',
            ]),
            'state' => $state,
        ]);

        return redirect('https://login.xero.com/identity/connect/authorize?' . $query);
    }

    public function callback(Request $request)
    {
        $this->authoriseAdmin($request);

        if ($request->get('state') !== session('xero_oauth_state')) {
            abort(403, 'Invalid Xero OAuth state.');
        }

        if (! $request->has('code')) {
            abort(400, 'Missing Xero authorisation code.');
        }

        $tokenResponse = Http::asForm()
            ->withBasicAuth(
                config('services.xero.client_id'),
                config('services.xero.client_secret')
            )
            ->post('https://identity.xero.com/connect/token', [
                'grant_type' => 'authorization_code',
                'code' => $request->get('code'),
                'redirect_uri' => config('services.xero.redirect_uri'),
            ]);

        if (! $tokenResponse->successful()) {
            /*
             * Never dump the response body to the browser: the previous dd()
             * echoed the raw Xero error (and the request context around it)
             * onto the page. Log the status only.
             */
            Log::error('Xero token exchange failed.', [
                'status' => $tokenResponse->status(),
            ]);

            return redirect('/admin')
                ->with('error', 'Could not complete the Xero connection (token exchange failed). Please try again.');
        }

        $tokenData = $tokenResponse->json();

        $connectionsResponse = Http::withToken($tokenData['access_token'])
            ->get('https://api.xero.com/connections');

        if (! $connectionsResponse->successful()) {
            Log::error('Xero connections lookup failed.', [
                'status' => $connectionsResponse->status(),
            ]);

            return redirect('/admin')
                ->with('error', 'Could not read the connected Xero organisations. Please try again.');
        }

        $connections = $connectionsResponse->json();

        if (empty($connections)) {
            abort(400, 'No Xero organisations connected.');
        }

        $connection = $connections[0];

        XeroConnection::query()->update(['is_active' => false]);

        XeroConnection::create([
            'tenant_id' => $connection['tenantId'],
            'tenant_name' => $connection['tenantName'] ?? null,
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'expires_at' => now()->addSeconds($tokenData['expires_in']),
            'is_active' => true,
        ]);

        session()->forget('xero_oauth_state');

        return redirect('/admin')->with('success', 'Xero connected successfully.');
    }

    /**
     * Connecting Xero rewrites the credentials used for all invoicing, so it
     * is restricted to admins rather than any authenticated customer.
     */
    private function authoriseAdmin(Request $request): void
    {
        abort_unless((bool) $request->user()?->is_admin, 403);
    }
}
