<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BypassCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        return CompanyContext::bypass(static fn () => $next($request));
    }
}
