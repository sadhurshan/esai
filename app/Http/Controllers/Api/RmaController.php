<?php

namespace App\Http\Controllers\Api;

use App\Enums\RmaStatus;
use App\Http\Requests\Rma\ReviewRmaRequest;
use App\Http\Requests\Rma\StoreRmaRequest;
use App\Http\Resources\RmaResource;
use App\Models\Company;
use App\Models\GoodsReceiptNote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Rma;
use App\Models\User;
use App\Services\RmaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RmaController extends ApiController
{
    public function __construct(private readonly RmaService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $company = $user->company;

        if (! $company instanceof Company) {
            return $this->fail('Company context required.', 403);
        }

        $query = Rma::query()
            ->where('company_id', $company->id)
            ->with(['purchaseOrder', 'purchaseOrderLine', 'goodsReceiptNote', 'documents'])
            ->orderByDesc('created_at');

        $status = $request->query('status');
        if ($status !== null) {
            if (! in_array($status, RmaStatus::values(), true)) {
                return $this->fail('Invalid status filter supplied.', 422, [
                    'status' => ['Status must be one of: '.implode(', ', RmaStatus::values()).'.'],
                ]);
            }

            $query->where('status', $status);
        }

        $poNumber = $request->query('po_number');
        if ($poNumber !== null) {
            $query->whereHas('purchaseOrder', function (Builder $builder) use ($poNumber): void {
                $builder->where('po_number', 'like', '%'.$poNumber.'%');
            });
        }

        $submittedFrom = $request->query('submitted_from');
        if ($submittedFrom !== null) {
            try {
                $fromDate = Carbon::parse($submittedFrom)->startOfDay();
                $query->whereDate('created_at', '>=', $fromDate);
            } catch (\Throwable) {
                // Ignore invalid date input; validation handled at UI level.
            }
        }

        $submittedTo = $request->query('submitted_to');
        if ($submittedTo !== null) {
            try {
                $toDate = Carbon::parse($submittedTo)->endOfDay();
                $query->whereDate('created_at', '<=', $toDate);
            } catch (\Throwable) {
                // Ignore invalid date input; validation handled at UI level.
            }
        }

        // TODO: add supplier filter once purchase orders expose supplier references.

        $perPage = $this->perPage($request, 15, 50);
        $paginator = $query->paginate($perPage);

        $result = $this->paginate($paginator, $request, RmaResource::class);

        return $this->ok($result, 'RMAs retrieved.');
    }

    public function store(StoreRmaRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $company = $user->company;

        if (! $company instanceof Company) {
            return $this->fail('Company context required.', 403);
        }

        if ((int) $purchaseOrder->company_id !== (int) $company->id) {
            return $this->fail('Purchase order not accessible.', 403);
        }

        $line = null;
        $lineId = $request->input('purchase_order_line_id');
        if ($lineId !== null) {
            $line = PurchaseOrderLine::query()
                ->whereKey($lineId)
                ->where('purchase_order_id', $purchaseOrder->id)
                ->first();
        }

        $grn = null;
        $grnId = $request->input('grn_id');
        if ($grnId !== null) {
            $grn = GoodsReceiptNote::query()
                ->whereKey($grnId)
                ->where('purchase_order_id', $purchaseOrder->id)
                ->first();
        }

        $attachments = collect($request->file('attachments', []))
            ->filter(fn ($file) => $file !== null)
            ->values()
            ->all();

        try {
            $rma = $this->service->createRma(
                $company,
                $purchaseOrder,
                $line,
                $grn,
                $user,
                $request->validated(),
                $attachments
            );
        } catch (ValidationException $exception) {
            return $this->fail('Validation failed', 422, $exception->errors());
        }

        return $this->ok(
            (new RmaResource($rma->load(['purchaseOrder', 'purchaseOrderLine', 'goodsReceiptNote', 'documents'])))->toArray($request),
            'RMA created successfully.'
        )->setStatusCode(201);
    }

    public function show(Request $request, Rma $rma): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        if ((int) $rma->company_id !== (int) $user->company_id) {
            return $this->fail('RMA not accessible.', 403);
        }

        $rma->load(['purchaseOrder', 'purchaseOrderLine', 'goodsReceiptNote', 'documents']);

        return $this->ok((new RmaResource($rma))->toArray($request));
    }

    public function review(ReviewRmaRequest $request, Rma $rma): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        if ((int) $rma->company_id !== (int) $user->company_id) {
            return $this->fail('RMA not accessible.', 403);
        }

        $this->authorize('review', $rma);

        try {
            $updated = $this->service->reviewRma(
                $rma,
                $request->input('decision'),
                $request->input('comment'),
                $user
            );
        } catch (ValidationException $exception) {
            return $this->fail('Validation failed', 422, $exception->errors());
        }

        return $this->ok(
            (new RmaResource($updated->load(['purchaseOrder', 'purchaseOrderLine', 'goodsReceiptNote', 'documents'])))->toArray($request),
            'RMA review recorded.'
        );
    }
}
