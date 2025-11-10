<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExportRequestStatus;
use App\Enums\ExportRequestType;
use App\Http\Requests\Export\StoreExportRequest;
use App\Http\Resources\ExportRequestResource;
use App\Models\Company;
use App\Models\ExportRequest;
use App\Models\User;
use App\Services\ExportService;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ExportController extends ApiController
{
    public function __construct(
        private readonly ExportService $exportService,
        private readonly AuditLogger $auditLogger
    ) {
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

        $perPage = $this->perPage($request, 15, 50);

        $paginator = ExportRequest::query()
            ->forCompany($company->id)
            ->latest('created_at')
            ->with('requester')
            ->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (ExportRequest $exportRequest) => (new ExportRequestResource($exportRequest))->toArray($request))
            ->values()
            ->all();

        return $this->ok([
            'items' => $items,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Export requests retrieved.');
    }

    public function store(StoreExportRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $validated = $request->validated();
        $filters = $validated['filters'] ?? [];

        try {
            $exportRequest = $this->exportService->createRequest($user, $validated['type'], $filters);
        } catch (ValidationException $exception) {
            return $this->fail('Validation failed', 422, $exception->errors());
        }

        return $this->ok((new ExportRequestResource($exportRequest))->toArray($request), 'Export request queued.')
            ->setStatusCode(201);
    }

    public function show(Request $request, ExportRequest $exportRequest): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        if ((int) $exportRequest->company_id !== (int) $user->company_id) {
            return $this->fail('Export request not accessible.', 403);
        }

        $exportRequest->loadMissing('requester');

        return $this->ok((new ExportRequestResource($exportRequest))->toArray($request));
    }

    public function download(Request $request, ExportRequest $exportRequest)
    {
        if (! $request->hasValidSignature()) {
            return $this->fail('Invalid download signature.', 403);
        }

        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        if ((int) $exportRequest->company_id !== (int) $user->company_id) {
            return $this->fail('Export request not accessible.', 403);
        }

        if ($exportRequest->status !== ExportRequestStatus::Completed) {
            return $this->fail('Export is not ready for download.', 409);
        }

        if ($exportRequest->expires_at !== null && $exportRequest->expires_at->isPast()) {
            return $this->fail('Export file has expired. Request a new export.', 410);
        }

        if ($exportRequest->file_path === null) {
            return $this->fail('Export file is unavailable. Please request a new export.', 410);
        }

        $disk = Storage::disk('exports');

        if (! $disk->exists($exportRequest->file_path)) {
            return $this->fail('Export file is unavailable. Please request a new export.', 410);
        }

        $fullPath = $disk->path($exportRequest->file_path);

        $this->auditLogger->custom($exportRequest, 'export_downloaded', [
            'file_path' => $exportRequest->file_path,
            'downloaded_by' => $user->id,
        ]);

        $filename = sprintf('%s-%s.zip', $exportRequest->type instanceof ExportRequestType ? $exportRequest->type->value : $exportRequest->type, $exportRequest->id);

        return response()->download($fullPath, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }
}
