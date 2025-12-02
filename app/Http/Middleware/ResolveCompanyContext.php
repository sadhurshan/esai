<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use App\Support\RequestCompanyContextResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = RequestCompanyContextResolver::resolve($request);

        if ($context === null) {
            CompanyContext::clear();

            return $next($request);
        }

        CompanyContext::set($context['companyId']);

        try {
            return $next($request);
        } finally {
            CompanyContext::clear();
        }
    }
}
