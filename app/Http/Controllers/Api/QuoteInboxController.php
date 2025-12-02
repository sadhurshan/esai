<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\CompanySupplierStatus;
use App\Http\Resources\QuoteResource;
use App\Models\Company;
use App\Models\User;
use App\Services\QuoteInboxService;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteInboxController extends ApiController
{
    public function __construct(
        private readonly QuoteInboxService $inbox,
        private readonly PermissionRegistry $permissions
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $inbox = strtolower((string) $request->query('inbox'));

        if (! in_array($inbox, ['buyer', 'supplier'], true)) {
            return $this->fail('Specify inbox=buyer or inbox=supplier.', 422, [
                'inbox' => ['Supported inbox values are buyer or supplier.'],
            ]);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Missing company assignment.', 403);
        }

        if (! $this->permissions->userHasAny($user, ['rfqs.read'], $companyId)) {
            return $this->fail('You are not authorized to view quotes for this company.', 403);
        }

        if ($inbox === 'supplier' && ! $this->supplierCompanyApproved($companyId)) {
            return $this->fail('Supplier company is not approved.', 403);
        }

        $query = $inbox === 'supplier'
            ? $this->inbox->supplierQuery($request, $companyId)
            : $this->inbox->buyerQuery($request, $companyId);

        $sortColumn = (string) $request->query('sort', 'created_at');
        $direction = $this->sortDirection($request);

        if (! in_array($sortColumn, ['created_at', 'submitted_at', 'total_minor', 'total_price_minor'], true)) {
            $sortColumn = 'created_at';
        }

        if ($sortColumn === 'total_minor') {
            $sortColumn = 'total_price_minor';
        }

        $orderColumn = "quotes.{$sortColumn}";

        $paginator = $query
            ->orderBy($orderColumn, $direction)
            ->orderBy('quotes.id', $direction)
            ->cursorPaginate($this->perPage($request));

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, QuoteResource::class);

        return $this->ok([
            'items' => $items,
        ], null, $meta);
    }

    private function supplierCompanyApproved(int $companyId): bool
    {
        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            return false;
        }

        if ($company->supplier_status === null) {
            return false;
        }

        return $company->supplier_status === CompanySupplierStatus::Approved->value;
    }
}
