<?php

namespace App\Http\Controllers\Api;

use App\Actions\Company\DeleteCompanyDocumentAction;
use App\Actions\Company\StoreCompanyDocumentAction;
use App\Http\Requests\Company\StoreCompanyDocumentRequest;
use App\Http\Resources\CompanyDocumentResource;
use App\Models\Company;
use App\Models\CompanyDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyDocumentController extends ApiController
{
    public function __construct(
        private readonly StoreCompanyDocumentAction $storeCompanyDocumentAction,
        private readonly DeleteCompanyDocumentAction $deleteCompanyDocumentAction,
    ) {}

    public function index(Company $company, Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $activeCompanyId = $this->resolveUserCompanyId($user);
        if ($activeCompanyId === null || $activeCompanyId !== $company->id) {
            return $this->fail('Forbidden.', 403);
        }

        $documents = $company->documents()->latest()->get();

        $data = $documents->map(fn (CompanyDocument $document) => (new CompanyDocumentResource($document))->toArray($request))->all();

        return $this->ok(['items' => $data]);
    }

    public function store(StoreCompanyDocumentRequest $request, Company $company): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $activeCompanyId = $this->resolveUserCompanyId($user);
        if ($activeCompanyId === null || $activeCompanyId !== $company->id) {
            return $this->fail('Forbidden.', 403);
        }

        if (! in_array($user->role, ['buyer_admin', 'supplier_admin', 'platform_super'], true) && $user->id !== $company->owner_user_id) {
            return $this->fail('Forbidden.', 403);
        }

        $document = $this->storeCompanyDocumentAction->execute($company, $request->validated('type'), $request->document());

        return $this->ok((new CompanyDocumentResource($document))->toArray($request), 'Document uploaded.')
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Company $company, CompanyDocument $document): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $activeCompanyId = $this->resolveUserCompanyId($user);
        if ($activeCompanyId === null || $activeCompanyId !== $company->id) {
            return $this->fail('Forbidden.', 403);
        }

        if ($document->company_id !== $company->id) {
            return $this->fail('Document not found.', 404);
        }

        if (! in_array($user->role, ['buyer_admin', 'supplier_admin', 'platform_super'], true) && $user->id !== $company->owner_user_id) {
            return $this->fail('Forbidden.', 403);
        }

        $this->deleteCompanyDocumentAction->execute($document);

        return $this->ok(null, 'Document removed.');
    }
}
