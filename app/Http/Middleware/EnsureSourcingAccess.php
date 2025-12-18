<?php

namespace App\Http\Middleware;

use App\Models\RFQ;
use App\Support\ActivePersonaContext;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use App\Support\Permissions\PermissionRegistry;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSourcingAccess
{
    public function __construct(private readonly PermissionRegistry $permissionRegistry)
    {
    }

    public function handle(Request $request, Closure $next, string $level = 'read'): JsonResponse|Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->errorResponse('Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        if (ActivePersonaContext::isSupplier()) {
            if ($this->supplierCanAccessRfq($request)) {
                return $next($request);
            }

            return $this->errorResponse('Sourcing access required.', Response::HTTP_FORBIDDEN);
        }

        $permission = $level === 'write' ? 'rfqs.write' : 'rfqs.read';
        $companyId = CompanyContext::get();

        if ($companyId === null && $user->company_id !== null) {
            $companyId = (int) $user->company_id;
        }

        if (! $this->permissionRegistry->userHasAny($user, [$permission], $companyId)) {
            $message = $level === 'write'
                ? 'Sourcing write access required.'
                : 'Sourcing access required.';

            return $this->errorResponse($message, Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return ApiResponse::error($message, $status);
    }

    private function supplierCanAccessRfq(Request $request): bool
    {
        $rfqParam = $request->route('rfq');

        if ($rfqParam === null) {
            return false;
        }

        $rfq = $rfqParam instanceof RFQ
            ? $rfqParam
            : RFQ::query()->with('invitations')->find((int) $rfqParam);

        if (! $rfq instanceof RFQ) {
            return false;
        }

        if ($rfq->is_open_bidding) {
            return true;
        }

        $supplierId = ActivePersonaContext::supplierId();

        if ($supplierId === null) {
            return false;
        }

        return $rfq->invitations()
            ->where('supplier_id', $supplierId)
            ->exists();
    }
}
