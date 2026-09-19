<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plan-gated modules, e.g. "plan:expenses". See config/plans.php.
 */
class EnsurePlanFeature
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $business = $request->user()?->business;

        if ($business !== null && $business->hasFeature($feature)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Your plan does not include this.');
        }

        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.settings')->with('upgrade_feature', $feature);
        }

        abort(403, 'Your shop\'s plan does not include this.');
    }
}
