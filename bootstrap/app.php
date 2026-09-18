<?php

use App\Http\Middleware\CaptureVisitorAttribution;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        /*
         * First-touch marketing attribution. Runs on every public web request
         * so a campaign origin is captured when the visitor first lands on the
         * site, not only when they reach checkout.
         */
        $middleware->web(append: [
            CaptureVisitorAttribution::class,
        ]);

        /*
         * This app has no standalone 'login' route — sign-in lives in the
         * Filament panel. Without this, the auth middleware on any web route
         * (currently the Xero OAuth endpoints) would throw
         * RouteNotFoundException for a guest instead of redirecting them.
         */
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));

        /*
         * Behind a tunnel or load balancer (ngrok for client demos), TLS is
         * terminated upstream and the request reaches PHP as plain HTTP. Without
         * trusting the forwarded headers, every generated URL and form action
         * comes out as http:// on an https:// page, which browsers block as
         * mixed content — checkout stops working.
         */
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
