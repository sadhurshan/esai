<?php

namespace App\Actions\Supplier;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Models\Document;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Documents\DocumentStorer;
use App\Support\Security\VirusScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StoreSupplierDocumentAction
{
    public function __construct(
        private readonly DocumentStorer $documentStorer,
        private readonly AuditLogger $auditLogger,
        private readonly VirusScanner $virusScanner,
    ) {}

    public function execute(
        Supplier $supplier,
        User $uploader,
        UploadedFile $file,
        string $type,
        ?Carbon $issuedAt = null,
        ?Carbon $expiresAt = null,
    ): SupplierDocument {
        $this->virusScanner->assertClean($file, [
            'context' => 'supplier_document_upload',
            'supplier_id' => $supplier->id,
            'company_id' => $supplier->company_id,
            'user_id' => $uploader->id,
            'document_type' => $type,
        ]);

        return DB::transaction(function () use ($supplier, $uploader, $file, $type, $issuedAt, $expiresAt): SupplierDocument {
            $document = $this->documentStorer->store(
                $uploader,
                $file,
                DocumentCategory::Qa->value,
                $supplier->company_id,
                Supplier::class,
                $supplier->getKey(),
                [
                    'kind' => DocumentKind::Certificate->value,
                    'visibility' => 'company',
                    'expires_at' => $expiresAt,
                    'meta' => [
                        'supplier_document_type' => $type,
                    ],
                ]
            );

            /** @var Document $document */
            $supplierDocument = SupplierDocument::create([
                'supplier_id' => $supplier->id,
                'company_id' => $supplier->company_id,
                'document_id' => $document->id,
                'type' => $type,
                'path' => $document->path,
                'mime' => $document->mime,
                'size_bytes' => $document->size_bytes,
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
                'status' => $this->resolveStatus($expiresAt),
            ]);

            $supplierDocument->setRelation('document', $document);

            $this->auditLogger->created($supplierDocument);

            return $supplierDocument;
        });
    }

    private function resolveStatus(?Carbon $expiresAt): string
    {
        if ($expiresAt === null) {
            return 'valid';
        }

        if ($expiresAt->isPast()) {
            return 'expired';
        }

        if ($expiresAt->lessThanOrEqualTo(now()->addDays(30))) {
            return 'expiring';
        }

        return 'valid';
    }
}
