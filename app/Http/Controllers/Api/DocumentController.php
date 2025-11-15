<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\User;
use App\Support\Documents\DocumentStorer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

class DocumentController extends ApiController
{
    public function __construct(private readonly DocumentStorer $documentStorer) {}

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $documentable = $this->resolveDocumentable($request, $companyId);

        if ($documentable === null) {
            return $this->fail('Document target not found.', 404);
        }

        if ($this->authorizeDenied($user, 'create', [Document::class, [
            'company_id' => $companyId,
            'documentable' => $documentable,
        ]])) {
            return $this->fail('Forbidden.', 403);
        }

        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            return $this->fail('Invalid document upload.', 422);
        }

        $payload = $request->payload();

        $document = $this->documentStorer->store(
            $user,
            $file,
            $payload['category'],
            $companyId,
            $documentable->getMorphClass(),
            (int) $documentable->getKey(),
            [
                'kind' => $payload['kind'],
                'visibility' => $payload['visibility'] ?? null,
                'expires_at' => $payload['expires_at'] ?? null,
                'meta' => $payload['meta'] ?? [],
                'watermark' => $payload['watermark'] ?? [],
            ]
        );

        return $this->ok(
            (new DocumentResource($document))->toArray($request),
            'Document uploaded.'
        );
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'view', $document)) {
            return $this->fail('Forbidden.', 403);
        }

        return $this->ok((new DocumentResource($document))->toArray($request));
    }

    private function resolveDocumentable(StoreDocumentRequest $request, int $companyId): ?Model
    {
        $class = $request->documentableClass();

        if ($class === null) {
            return null;
        }

        /** @var Model $instance */
        $instance = new $class();

        $query = $class::query();

        if (Schema::hasColumn($instance->getTable(), 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query->whereKey($request->documentableId())->first();
    }
}
