<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\PersonaResolver;
use App\Support\ActivePersonaContext;
use App\Support\CompanyContext;
use App\Support\RequestCompanyContextResolver;
use App\Support\RequestPersonaResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompanyContext
{
    public function __construct(private readonly PersonaResolver $personaResolver)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->applyPersonaFromHeader($request);

        $context = RequestCompanyContextResolver::resolve($request);

        if ($context === null) {
            CompanyContext::clear();
            ActivePersonaContext::clear();
            $request->attributes->set('company_id', null);
            $request->attributes->set('active_persona', null);
            $request->attributes->set('acting_supplier_id', null);

            return $next($request);
        }

        $persona = $context['persona'] ?? null;

        if ($persona !== null) {
            ActivePersonaContext::set($persona);
            $request->attributes->set('active_persona', $persona->toArray());
            $request->attributes->set('acting_supplier_id', $persona->supplierId());
        } else {
            ActivePersonaContext::clear();
            $request->attributes->set('active_persona', null);
            $request->attributes->set('acting_supplier_id', null);
        }

        CompanyContext::set($context['companyId']);
        $request->attributes->set('company_id', $context['companyId']);

        try {
            return $next($request);
        } finally {
            CompanyContext::clear();
            ActivePersonaContext::clear();
            $request->attributes->set('company_id', null);
            $request->attributes->set('active_persona', null);
            $request->attributes->set('acting_supplier_id', null);
        }
    }

    private function applyPersonaFromHeader(Request $request): void
    {
        $personaKey = $request->headers->get('X-Active-Persona');

        if (! is_string($personaKey) || $personaKey === '') {
            return;
        }

        $user = $request->user();

        if (! $user instanceof User) {
            $user = RequestCompanyContextResolver::resolveRequestUser($request);
        }

        if (! $user instanceof User) {
            return;
        }

        $personas = $this->personaResolver->resolve($user);
        $persona = collect($personas)->firstWhere('key', $personaKey);

        if ($persona === null) {
            return;
        }

        RequestPersonaResolver::remember($request, $persona);
    }
}
