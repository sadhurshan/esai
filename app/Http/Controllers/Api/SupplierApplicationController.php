<?php

namespace App\Http\Controllers\Api;

use App\Enums\SupplierApplicationStatus;
use App\Http\Requests\SupplierApplicationStoreRequest;
use App\Http\Resources\SupplierApplicationResource;
use App\Models\Company;
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

        $payload = $request->payload();

        $application = SupplierApplication::create([
            'company_id' => $company->id,
            'submitted_by' => $user->id,
            'status' => SupplierApplicationStatus::Pending,
            'form_json' => $payload,
        ]);

        $this->markSupplierProfileCompleted($company, $payload);

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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markSupplierProfileCompleted(Company $company, array $payload): void
    {
        if ($company->supplier_profile_completed_at !== null) {
            return;
        }

        if (! $this->hasMinimumProfileData($payload)) {
            return;
        }

        $before = $company->getOriginal();

        $company->forceFill([
            'supplier_profile_completed_at' => now(),
        ]);

        $changes = $company->getDirty();

        if ($changes === []) {
            return;
        }

        $company->save();

        $this->auditLogger->updated($company, $before, $changes);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasMinimumProfileData(array $payload): bool
    {
        $capabilities = $payload['capabilities'] ?? [];
        $hasCapabilities = is_array($capabilities) && collect($capabilities)->flatten()->filter()->isNotEmpty();
        $hasLocation = filled($payload['address'] ?? null) || filled($payload['country'] ?? null) || filled($payload['city'] ?? null);
        $hasMOQ = isset($payload['moq']) && (int) $payload['moq'] > 0;
        $hasLeadTime = isset($payload['lead_time_days']) && (int) $payload['lead_time_days'] > 0;
        $contact = $payload['contact'] ?? [];
        $hasContact = is_array($contact) && (filled($contact['email'] ?? null) || filled($contact['phone'] ?? null) || filled($contact['name'] ?? null));

        return $hasCapabilities && $hasLocation && $hasMOQ && $hasLeadTime && $hasContact;
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
