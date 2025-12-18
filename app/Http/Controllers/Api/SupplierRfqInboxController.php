<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\RFQResource;
use App\Services\SupplierRfqInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\CompanyContext;

class SupplierRfqInboxController extends ApiController
{
    public function __construct(private readonly SupplierRfqInboxService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $actingSupplierId = $request->attributes->get('acting_supplier_id');

        if ($actingSupplierId === null) {
            return $this->fail('Supplier persona required.', 403, [
                'code' => 'supplier_persona_required',
            ]);
        }

        $sort = (string) $request->query('sort', 'due_at');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, ['due_at', 'created_at', 'close_at'], true)) {
            $sort = 'due_at';
        }

        $query = $this->service
            ->query($request, $companyId, (int) $actingSupplierId)
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction);

        $paginator = CompanyContext::bypass(fn () => $query->cursorPaginate($this->perPage($request)));

        ['items' => $items, 'meta' => $meta] = CompanyContext::bypass(fn () => $this->paginate($paginator, $request, RFQResource::class));

        return $this->ok([
            'items' => $items,
        ], null, $meta);
    }
}
