<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        if(!auth()->check()){
            return redirect('/login');
        }

        $allowedRoutes = explode('|', $roles);
        $userRole = auth()->user()->role;

        if (in_array($userRole, $allowedRoutes)) {
            return $next($request);
        }

        return match($userRole){
            'admin' => redirect()->route('admin.dashboard'),
            'super_admin' => redirect()->route('super_admin.dashbord'),
            'staff' => redirect()->route('staff.dashbord'),
            default => redirect('/'),
        };
    }
}
