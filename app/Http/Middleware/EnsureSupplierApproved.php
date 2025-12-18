<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Supplier;
use App\Support\ActivePersonaContext;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupplierApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->deny($request, 'Authentication required.', 401);
        }

        if (ActivePersonaContext::isSupplier()) {
            $supplierId = ActivePersonaContext::supplierId();

            if ($supplierId === null) {
                return $this->deny($request, 'Supplier persona required.', 403);
            }

            $supplier = CompanyContext::bypass(static fn () => Supplier::query()->find($supplierId));

            if ($supplier === null || in_array($supplier->status, ['rejected', 'suspended'], true)) {
                return $this->deny($request, 'Supplier approval required.', 403);
            }

            return $next($request);
        }

        /** @var Company|null $company */
        $company = $user->company;

        if ($company === null) {
            return $this->deny($request, 'Company context required.', 403);
        }

        if (! $company->isSupplierApproved()) {
            return $this->deny($request, 'Supplier approval required.', 403);
        }

        return $next($request);
    }

    private function deny(Request $request, string $message, int $status): Response
    {
        if ($request->expectsJson()) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $message,
                'data' => null,
            ], $status);
        }

        if ($status === 401) {
            return redirect()->guest(route('login'));
        }

        return redirect()->route('dashboard');
    }
}
