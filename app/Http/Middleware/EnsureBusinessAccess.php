<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shop screens need a business that is allowed to trade:
 * - no business linked → 403
 * - moved to the trash by the operator → logged out with a message
 * - suspended → logged out with a message
 * - trial ended / payment overdue → only billing, settings and exports stay open
 */
class EnsureBusinessAccess
{
    /**
     * Route names that stay reachable while payment is required.
     *
     * @var list<string>
     */
    private const OPEN_WHILE_UNPAID = [
        'admin.settings',
        'admin.settings.*',
        'admin.billing.*',
        'admin.exports.*',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $business = $user?->business;

        if ($business === null) {
            // A trashed shop is invisible to the relation, so check before giving up.
            $trashed = $user?->business_id === null
                ? null
                : Business::onlyTrashed()->find($user->business_id);

            if ($trashed !== null) {
                return $this->logOut($request, "{$trashed->business_name} has been removed from iPOSa. Contact support if that is a mistake.");
            }

            abort(403, 'Your account is not linked to a business. Ask the owner to invite you again.');
        }

        if ($business->isSuspended()) {
            return $this->logOut($request, "{$business->business_name} is suspended on iPOSa. Contact support to restore access.");
        }

        if ($business->requiresPayment() && ! $request->routeIs(...self::OPEN_WHILE_UNPAID)) {
            if ($request->expectsJson()) {
                abort(402, 'The subscription for this shop needs payment.');
            }

            if ($user->isAdmin()) {
                return redirect()->route('admin.settings')->with('billing_required', true);
            }

            return response()->view('errors.subscription', ['business' => $business], 402);
        }

        return $next($request);
    }

    /**
     * End the session and send them to the login page with an explanation.
     */
    private function logOut(Request $request, string $message): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', $message);
    }
}
