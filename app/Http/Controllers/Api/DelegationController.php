<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Approval\StoreDelegationRequest;
use App\Http\Resources\DelegationResource;
use App\Models\Company;
use App\Models\Delegation;
use App\Models\User;
use App\Services\DelegationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DelegationController extends ApiController
{
    public function __construct(private readonly DelegationService $delegations)
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

        $delegations = Delegation::query()
            ->with(['approver', 'delegate'])
            ->where('company_id', $company->id)
            ->orderByDesc('starts_at')
            ->get();

        return $this->ok(
            DelegationResource::collection($delegations)->resolve(),
            'Delegations retrieved.'
        );
    }

    public function store(StoreDelegationRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->company instanceof Company) {
            return $this->fail('Company context required.', 403);
        }

        $delegation = $request->route('delegation');
        $data = $request->validated();

        if ($delegation instanceof Delegation) {
            if ((int) $delegation->company_id !== (int) $user->company->id) {
                return $this->fail('Delegation not accessible.', 403);
            }

            $updated = $this->delegations->update($delegation, $data, $user);
            $updated->load(['approver', 'delegate']);

            return $this->ok(
                (new DelegationResource($updated))->toArray($request),
                'Delegation updated.'
            );
        }

        $created = $this->delegations->create($user->company, $data, $user);
        $created->load(['approver', 'delegate']);

        return $this->ok(
            (new DelegationResource($created))->toArray($request),
            'Delegation created.'
        )->setStatusCode(201);
    }

    public function destroy(Request $request, Delegation $delegation): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User || ! $user->company instanceof Company) {
            return $this->fail('Company context required.', 403);
        }

        if ((int) $delegation->company_id !== (int) $user->company->id) {
            return $this->fail('Delegation not accessible.', 403);
        }

        $this->delegations->delete($delegation, $user);

        return $this->ok(null, 'Delegation removed.');
    }
}
