<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyRegistered
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (in_array($user->role, ['platform_super', 'platform_support'], true)) {
            return $next($request);
        }

        if ($user->company_id === null) {
            if ($request->routeIs('company.registration')) {
                return $next($request);
            }

            return redirect()->route('company.registration');
        }

        return $next($request);
    }
}
