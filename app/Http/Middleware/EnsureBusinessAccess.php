<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shop screens need a business that is allowed to trade:
 * - no business linked → 403
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
            abort(403, 'Your account is not linked to a business. Ask the owner to invite you again.');
        }

        if ($business->isSuspended()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', "{$business->business_name} is suspended on iPOSa. Contact support to restore access.");
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
}
