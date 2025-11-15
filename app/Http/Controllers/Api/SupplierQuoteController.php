<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierQuoteController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Missing company assignment.', 403);
        }

        $query = Quote::query()
            ->whereHas('supplier', static function (Builder $builder) use ($companyId): void {
                $builder->where('company_id', $companyId);
            })
            ->with([
                'supplier',
                'rfq',
                'items.taxes.taxCode',
                'items.rfqItem',
                'documents',
            ]);

        if ($request->filled('rfq_id')) {
            $query->where('rfq_id', (int) $request->query('rfq_id'));
        }

        if ($request->filled('status')) {
            $statuses = collect(explode(',', (string) $request->query('status')))
                ->map(static fn (string $value): string => strtolower(trim($value)))
                ->filter()
                ->unique()
                ->all();

            if ($statuses !== []) {
                $query->whereIn('status', $statuses);
            }
        }

        if ($request->filled('rfq_number')) {
            $rfqNumber = trim((string) $request->query('rfq_number'));
            $query->whereHas('rfq', static function (Builder $builder) use ($rfqNumber): void {
                $builder->where('rfq_number', 'like', "%{$rfqNumber}%");
            });
        }

        $sortColumn = (string) $request->query('sort', 'created_at');

        $paginator = $query
            ->orderBy($sortColumn, $this->sortDirection($request))
            ->paginate($this->perPage($request))
            ->withQueryString();

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, QuoteResource::class);

        return $this->ok([
            'items' => $items,
            'meta' => $meta,
        ]);
    }
}
