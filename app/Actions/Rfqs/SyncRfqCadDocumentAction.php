<?php

namespace App\Actions\Rfqs;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Models\Document;
use App\Models\RFQ;
use App\Models\User;
use App\Services\RfqVersionService;
use App\Support\Audit\AuditLogger;
use App\Support\Documents\DocumentStorer;
use App\Support\Security\VirusScanner;
use Illuminate\Http\UploadedFile;

class SyncRfqCadDocumentAction
{
    public function __construct(
        private readonly DocumentStorer $documentStorer,
        private readonly VirusScanner $virusScanner,
        private readonly AuditLogger $auditLogger,
        private readonly RfqVersionService $rfqVersionService,
    ) {}

    public function attach(RFQ $rfq, UploadedFile $file, User $user): Document
    {
        $this->virusScanner->assertClean($file, [
            'rfq_id' => $rfq->id,
            'company_id' => $rfq->company_id,
            'user_id' => $user->id,
        ]);

        $document = $this->documentStorer->store(
            $user,
            $file,
            DocumentCategory::Technical->value,
            $rfq->company_id,
            $rfq->getMorphClass(),
            (int) $rfq->getKey(),
            [
                'kind' => DocumentKind::Cad->value,
                'visibility' => 'company',
                'meta' => [
                    'source' => 'rfq_cad',
                    'rfq_id' => $rfq->id,
                    'rfq_number' => $rfq->number,
                ],
            ]
        );

        $before = $rfq->only(['cad_document_id']);

        $rfq->forceFill([
            'cad_document_id' => $document->id,
        ])->save();

        $this->auditLogger->updated($rfq, $before, [
            'cad_document_id' => $rfq->cad_document_id,
        ]);

        $this->rfqVersionService->bump($rfq, null, 'rfq_cad_document_attached', [
            'document_id' => $document->id,
        ]);

        return $document;
    }

    public function detach(RFQ $rfq): void
    {
        $document = $rfq->cadDocument()->withTrashed()->first();
        $before = $rfq->only(['cad_document_id']);

        if ($document !== null && ! $document->trashed()) {
            $document->delete();
        }

        if (($before['cad_document_id'] ?? null) === null) {
            return;
        }

        $rfq->forceFill([
            'cad_document_id' => null,
        ])->save();

        $this->auditLogger->updated($rfq, $before, [
            'cad_document_id' => null,
        ]);

        $this->rfqVersionService->bump($rfq, null, 'rfq_cad_document_detached');
    }
}
