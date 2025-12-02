<?php

namespace App\Http\Controllers\Api;

use App\Actions\Supplier\StoreSupplierDocumentAction;
use App\Http\Requests\Supplier\StoreSupplierDocumentRequest;
use App\Http\Resources\SupplierDocumentResource;
use App\Models\Company;
use App\Models\Document;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class SupplierDocumentController extends ApiController
{
    public function __construct(
        private readonly StoreSupplierDocumentAction $storeSupplierDocumentAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'viewAny', SupplierDocument::class)) {
            return $this->fail('Forbidden.', 403);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $documents = SupplierDocument::query()
            ->with('document')
            ->select('supplier_documents.*')
            ->selectRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END AS expires_null_flag')
            ->where('company_id', $companyId)
            ->orderBy('expires_null_flag')
            ->orderBy('expires_at')
            ->orderBy('type')
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request, 15, 50));

        ['items' => $items, 'meta' => $meta] = $this->paginate($documents, $request, SupplierDocumentResource::class);

        return $this->ok([
            'items' => $items,
        ], 'Supplier documents retrieved.', $meta);
    }

    public function store(StoreSupplierDocumentRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'create', SupplierDocument::class)) {
            return $this->fail('Forbidden.', 403);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $supplier = Supplier::query()->where('company_id', $companyId)->first();

        if ($supplier === null) {
            $company = $user->company;

            if ($company === null || $company->id !== $companyId) {
                $company = Company::find($companyId);
            }

            $supplier = new Supplier([
                'company_id' => $companyId,
                'name' => $company?->name ?? 'Supplier '.$companyId,
                'status' => 'pending',
                'email' => $company?->primary_contact_email,
                'phone' => $company?->primary_contact_phone,
                'address' => $company?->address,
                'country' => $company?->country,
                'website' => $company?->website,
                'capabilities' => [],
            ]);

            $supplier->save();
        }

        $document = $this->storeSupplierDocumentAction->execute(
            $supplier,
            $user,
            $request->document(),
            $request->type(),
            $request->issuedAt(),
            $request->expiresAt(),
        )->load('document');

        return $this->ok((new SupplierDocumentResource($document))->toArray($request), 'Supplier document uploaded.');
    }

    public function destroy(Request $request, SupplierDocument $document): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'delete', $document)) {
            return $this->fail('Forbidden.', 403);
        }

        $before = Arr::except($document->toArray(), ['created_at', 'updated_at', 'deleted_at']);
        $linkedDocument = $document->document;

        $document->delete();

        if ($linkedDocument instanceof Document) {
            $linkedDocument->delete();
        } elseif ($document->document_id !== null) {
            Document::query()->whereKey($document->document_id)->delete();
        }

        $this->auditLogger->deleted($document, $before);

        return $this->ok(null, 'Supplier document removed.');
    }
}
