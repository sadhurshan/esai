<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\SupplierVisibilityUpdateRequest;
use App\Http\Resources\SupplierDocumentResource;
use App\Models\SupplierApplication;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupplierSelfServiceController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function status(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $company = $user->company;

        if ($company === null) {
            return $this->fail('Company context required.', 403);
        }

        $latestApplication = $company->supplierApplications()
            ->with('documents.document')
            ->latest('created_at')
            ->first();

        return $this->ok([
            'supplier_status' => $company->supplier_status instanceof \BackedEnum ? $company->supplier_status->value : $company->supplier_status,
            'directory_visibility' => $company->directory_visibility,
            'supplier_profile_completed_at' => optional($company->supplier_profile_completed_at)?->toIso8601String(),
            'is_listed' => $company->isSupplierListed(),
            'current_application' => $this->formatApplication($latestApplication, $request),
        ]);
    }

    public function updateVisibility(SupplierVisibilityUpdateRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $company = $user->company;

        if ($company === null) {
            return $this->fail('Company context required.', 403);
        }

        if (! $company->isSupplierApproved()) {
            return $this->fail('Supplier approval required.', 403);
        }

        if (! in_array($user->role, ['owner', 'buyer_admin'], true) && $user->id !== $company->owner_user_id) {
            return $this->fail('Forbidden.', 403);
        }

        $payload = $request->validated();
        $visibility = $payload['visibility'];

        if ($visibility === 'public' && $company->supplier_profile_completed_at === null) {
            return $this->fail('Complete your supplier profile before listing publicly.', 422);
        }

        $before = $company->getOriginal();
        $company->directory_visibility = $visibility;
        $changes = $company->getDirty();

        if ($changes !== []) {
            $company->save();
            $this->auditLogger->updated($company, $before, $changes);
        }

        $latestApplication = $company->supplierApplications()
            ->with('documents.document')
            ->latest('created_at')
            ->first();

        return $this->ok([
            'supplier_status' => $company->supplier_status instanceof \BackedEnum ? $company->supplier_status->value : $company->supplier_status,
            'directory_visibility' => $company->directory_visibility,
            'supplier_profile_completed_at' => optional($company->supplier_profile_completed_at)?->toIso8601String(),
            'is_listed' => $company->isSupplierListed(),
            'current_application' => $this->formatApplication($latestApplication, $request),
        ], 'Supplier directory visibility updated.');
    }

    private function formatApplication(?SupplierApplication $application, ?Request $request = null): ?array
    {
        if ($application === null) {
            return null;
        }

        $status = $application->status instanceof \BackedEnum ? $application->status->value : $application->status;
        $notes = $application->notes;

        $documents = $application->relationLoaded('documents') ? $application->documents : collect();

        if (method_exists($documents, 'load')) {
            $documents->load('document');
        }

        return [
            'id' => $application->id,
            'status' => $status,
            'notes' => $notes,
            'submitted_at' => optional($application->created_at)?->toIso8601String(),
            'auto_reverification' => $status === 'pending' && is_string($notes)
                && Str::startsWith(Str::lower($notes), 'auto re-verification triggered'),
            'documents' => $application->relationLoaded('documents')
                ? SupplierDocumentResource::collection($documents)->toArray($request ?: request())
                : [],
        ];
    }
}
