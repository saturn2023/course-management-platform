<?php

namespace App\Http\Middleware;

use App\Support\OrderAttribution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records the visitor's first-touch marketing origin in the session on every
 * web request, before they ever reach checkout.
 *
 * Read-only with respect to the request: it only inspects query parameters.
 */
class CaptureVisitorAttribution
{
    public function handle(Request $request, Closure $next): Response
    {
        OrderAttribution::capture($request);

        return $next($request);
    }
}
