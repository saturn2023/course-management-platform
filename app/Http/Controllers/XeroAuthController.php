<?php

namespace App\Http\Controllers;

use App\Models\XeroConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class XeroAuthController extends Controller
{
    public function connect()
    {
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
]) ,
            'state' => $state,
        ]);

        return redirect('https://login.xero.com/identity/connect/authorize?' . $query);
    }

    public function callback(Request $request)
    {
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
            dd([
                'message' => 'Failed to get Xero token',
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->json(),
            ]);
        }

        $tokenData = $tokenResponse->json();

        $connectionsResponse = Http::withToken($tokenData['access_token'])
            ->get('https://api.xero.com/connections');

        if (! $connectionsResponse->successful()) {
            dd([
                'message' => 'Failed to get Xero connections',
                'status' => $connectionsResponse->status(),
                'body' => $connectionsResponse->json(),
            ]);
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
}