<?php

use App\Http\Middleware\CaptureVisitorAttribution;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        /*
         * A stale CSRF token on the v2 enrolment endpoints has to come back in
         * the same envelope as every other refusal, with the code the browser
         * script looks for. On seeing `nonce` it fetches a fresh token and
         * retries once — the port of the reference's ams2_nonce dance — so a
         * learner who left the form open past the session lifetime does not
         * lose what they typed.
         *
         * Without this they would get Laravel's own 419 page, which the script
         * cannot read, and their answers would be gone.
         */
        /*
         * Matched on the 419 rather than on TokenMismatchException, because
         * the handler's prepareException() has already converted it by the
         * time render callbacks are consulted — a callback typed to
         * TokenMismatchException never fires. 419 is only ever CSRF.
         */
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() !== 419 || ! $request->is('enrolment-v2/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'data' => [
                    'code' => 'nonce',
                    'message' => 'Your session has expired. Refresh the page and try again.',
                ],
            ], 403);
        });
    })->create();
