<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SupplierApplicationStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\SupplierApplication\SupplierApplicationRejectRequest;
use App\Http\Resources\Admin\AuditLogResource;
use App\Http\Resources\SupplierApplicationResource;
use App\Models\AuditLog;
use App\Models\SupplierApplication;
use App\Services\CompanyLifecycleService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierApplicationReviewController extends ApiController
{
    public function __construct(private readonly CompanyLifecycleService $companyLifecycleService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if (! in_array($user->role, ['platform_super', 'platform_support'], true)) {
            return $this->fail('Forbidden.', 403);
        }

        $status = (string) $request->query('status', SupplierApplicationStatus::Pending->value);
        $statusValues = array_map(static fn (SupplierApplicationStatus $case) => $case->value, SupplierApplicationStatus::cases());

        if ($status !== 'all' && ! in_array($status, $statusValues, true)) {
            return $this->fail('Invalid status filter.', 422);
        }

        ['items' => $items, 'meta' => $meta] = CompanyContext::bypass(function () use ($request, $status): array {
            $query = SupplierApplication::query()
                ->with(['company', 'submittedBy', 'reviewedBy', 'documents.document'])
                ->orderByDesc('created_at')
                ->orderByDesc('id');

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            $paginator = $query->cursorPaginate($this->perPage($request, 25, 100));

            return $this->paginate($paginator, $request, SupplierApplicationResource::class);
        });

        return $this->ok([
            'items' => $items,
        ], null, $meta);
    }

    public function approve(Request $request, SupplierApplication $application): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'approve', $application)) {
            return $this->fail('Forbidden.', 403);
        }

        if ($application->status !== SupplierApplicationStatus::Pending) {
            return $this->fail('Only pending applications can be approved.', 422);
        }

        $notes = $request->input('notes');
        if (is_string($notes)) {
            $notes = trim($notes);
            $notes = $notes === '' ? null : $notes;
        } else {
            $notes = null;
        }

        $application = $this->companyLifecycleService
            ->approveSupplier($application, $user, $notes)
            ->loadMissing(['company', 'reviewedBy', 'documents.document']);

        return $this->ok((new SupplierApplicationResource($application))->toArray($request), 'Supplier application approved.');
    }

    public function reject(SupplierApplicationRejectRequest $request, SupplierApplication $application): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'reject', $application)) {
            return $this->fail('Forbidden.', 403);
        }

        if ($application->status !== SupplierApplicationStatus::Pending) {
            return $this->fail('Only pending applications can be rejected.', 422);
        }

        $notes = $request->validated('notes');

        $application = $this->companyLifecycleService
            ->rejectSupplier($application, $user, $notes)
            ->loadMissing(['company', 'reviewedBy', 'documents.document']);

        return $this->ok((new SupplierApplicationResource($application))->toArray($request), 'Supplier application rejected.');
    }

    public function auditLogs(Request $request, SupplierApplication $application): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if (! in_array($user->role, ['platform_super', 'platform_support'], true)) {
            return $this->fail('Forbidden.', 403);
        }

        $limit = (int) $request->integer('limit', 25);
        $limit = $limit > 0 ? min($limit, 100) : 25;

        $logs = AuditLog::query()
            ->with(['user:id,name,email'])
            ->where('entity_type', $application->getMorphClass())
            ->where('entity_id', $application->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $this->ok([
            'items' => AuditLogResource::collection($logs)->resolve($request),
        ]);
    }
}
