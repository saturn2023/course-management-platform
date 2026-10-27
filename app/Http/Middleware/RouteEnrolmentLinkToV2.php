<?php

namespace App\Http\Middleware;

use App\Enrolment\V2\Ams2EnrolmentRouter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends an enrolment link to the v2 form when it belongs there.
 *
 * The analogue of the reference hooking template_redirect at priority 5: it
 * runs before the old handler, and anything it declines reaches that handler
 * completely unchanged. Keeping it out here is why the v1 route body needs no
 * edit at all.
 *
 * GET only, deliberately. A POST to the old endpoint comes from someone who
 * already has the old form open and filled in — "Started on the old form: leave
 * it with the old flow", as the reference puts it. Redirecting that request
 * would throw their answers away to no purpose: they are not yet recorded as
 * being in the old flow, so nothing is protected by moving them.
 *
 * The response is marked uncacheable. A cached redirect would pin a learner to
 * whichever side of the decision they first saw, long after a published
 * template or the rollout switch moved it.
 */
class RouteEnrolmentLinkToV2
{
    public function __construct(private Ams2EnrolmentRouter $router) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        $url = $this->router->v2UrlFor($request->route('token'));

        if ($url === null) {
            return $next($request);
        }

        return redirect($url, 302)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ]);
    }
}
