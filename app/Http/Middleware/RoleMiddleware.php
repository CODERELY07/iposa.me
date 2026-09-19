<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Only let the listed roles through (e.g. "role:staff|admin"). Everyone else goes back to their own home screen.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (in_array($user->role, explode('|', $roles), true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Your role cannot do this.');
        }

        return redirect()->route($user->homeRoute());
    }
}
