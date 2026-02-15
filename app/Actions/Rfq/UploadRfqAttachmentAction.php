<?php

namespace App\Actions\Rfq;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Models\Document;
use App\Models\RFQ;
use App\Models\User;
use App\Services\DigitalTwin\DigitalTwinLinkService;
use App\Services\RfqVersionService;
use App\Support\Documents\DocumentStorer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class UploadRfqAttachmentAction
{
    public function __construct(
        private readonly DocumentStorer $documentStorer,
        private readonly RfqVersionService $rfqVersionService,
        private readonly DigitalTwinLinkService $digitalTwinLinkService,
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function execute(User $user, RFQ $rfq, UploadedFile $file, array $meta = []): Document
    {
        if ($user->company_id === null || (int) $rfq->company_id !== (int) $user->company_id) {
            throw ValidationException::withMessages([
                'rfq_id' => ['RFQ not found for this company.'],
            ]);
        }

        $metaPayload = array_filter([
            'context' => 'rfq_attachment',
            'rfq_id' => $rfq->getKey(),
            'title' => Arr::get($meta, 'title'),
            'description' => Arr::get($meta, 'description'),
            'uploaded_by_name' => $user->name,
        ], static fn ($value) => $value !== null && $value !== '');

        $document = $this->documentStorer->store(
            $user,
            $file,
            DocumentCategory::Technical->value,
            (int) $rfq->company_id,
            $rfq->getMorphClass(),
            $rfq->getKey(),
            [
                'kind' => DocumentKind::Rfq->value,
                'visibility' => 'company',
                'meta' => $metaPayload,
            ],
        );

        $this->rfqVersionService->bump($rfq, null, 'rfq_attachment_uploaded', [
            'document_id' => $document->id,
        ]);

        $this->digitalTwinLinkService->linkRfqDocument($rfq, $document, $user, 'rfq_attachment');

        return $document;
    }
}
