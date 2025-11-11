<?php

namespace App\Http\Middleware;

use App\Services\LocaleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyCompanyLocale
{
    public function __construct(private readonly LocaleService $localeService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->company_id === null) {
            return $next($request);
        }

        $user->loadMissing('company');

        if ($user->company === null) {
            return $next($request);
        }

        $this->localeService->apply($request, $user->company);

        return $next($request);
    }
}
