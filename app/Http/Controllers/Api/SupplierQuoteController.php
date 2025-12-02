<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\QuoteResource;
use App\Services\QuoteInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierQuoteController extends ApiController
{
    public function __construct(private readonly QuoteInboxService $inbox)
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
            return $this->fail('Missing company assignment.', 403);
        }

        $direction = $this->sortDirection($request);
        $sortColumn = (string) $request->query('sort', 'created_at');

        if (! in_array($sortColumn, ['created_at', 'submitted_at', 'total_minor', 'total_price_minor'], true)) {
            $sortColumn = 'created_at';
        }

        if ($sortColumn === 'total_minor') {
            $sortColumn = 'total_price_minor';
        }

        $paginator = $this->inbox
            ->supplierQuery($request, $companyId)
            ->orderBy("quotes.{$sortColumn}", $direction)
            ->orderBy('quotes.id', $direction)
            ->cursorPaginate($this->perPage($request));

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, QuoteResource::class);

        return $this->ok([
            'items' => $items,
        ], null, $meta);
    }
}
