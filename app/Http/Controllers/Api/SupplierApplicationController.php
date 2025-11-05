<?php

namespace App\Http\Controllers\Api;

use App\Enums\SupplierApplicationStatus;
use App\Http\Requests\SupplierApplicationStoreRequest;
use App\Http\Resources\SupplierApplicationResource;
use App\Models\SupplierApplication;
use App\Models\User;
use App\Notifications\SupplierApplicationSubmitted;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;

class SupplierApplicationController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'viewAny', SupplierApplication::class)) {
            return $this->fail('Forbidden.', 403);
        }

        $companyId = $user->company_id;

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $applications = SupplierApplication::query()
            ->with(['company'])
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->get();

        return $this->ok([
            'items' => SupplierApplicationResource::collection($applications)->resolve(),
        ]);
    }

    public function store(SupplierApplicationStoreRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'create', SupplierApplication::class)) {
            return $this->fail('Forbidden.', 403);
        }

        $company = $user->company;

        if ($company === null) {
            return $this->fail('Company context required.', 403);
        }

        $pendingExists = SupplierApplication::query()
            ->where('company_id', $company->id)
            ->where('status', SupplierApplicationStatus::Pending)
            ->exists();

        if ($pendingExists) {
            return $this->fail('There is already a pending supplier application for this company.', 422);
        }

        $application = SupplierApplication::create([
            'company_id' => $company->id,
            'submitted_by' => $user->id,
            'status' => SupplierApplicationStatus::Pending,
            'form_json' => $request->payload(),
        ]);

        $this->auditLogger->created($application);

        $platformAdmins = User::query()
            ->where('role', 'platform_super')
            ->get();

        if ($platformAdmins->isNotEmpty()) {
            Notification::send($platformAdmins, new SupplierApplicationSubmitted($application->fresh(['company', 'submittedBy'])));
        }

        return $this->ok(
            (new SupplierApplicationResource($application->fresh(['company'])))->toArray($request),
            'Supplier application submitted.'
        );
    }

    public function show(Request $request, SupplierApplication $application): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'view', $application)) {
            return $this->fail('Forbidden.', 403);
        }

        return $this->ok((new SupplierApplicationResource($application->loadMissing(['company'])))->toArray($request));
    }

    public function destroy(Request $request, SupplierApplication $application): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'delete', $application)) {
            return $this->fail('Forbidden.', 403);
        }

        if ($application->status !== SupplierApplicationStatus::Pending) {
            return $this->fail('Only pending applications can be cancelled.', 422);
        }

        $before = Arr::except($application->toArray(), ['created_at', 'updated_at']);

        $application->delete();

        $this->auditLogger->deleted($application, $before);

        return $this->ok(null, 'Supplier application withdrawn.');
    }
}
