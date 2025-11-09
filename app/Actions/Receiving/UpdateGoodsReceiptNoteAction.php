<?php

namespace App\Actions\Receiving;

use App\Models\Document;
use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptNote;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Documents\DocumentStorer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class UpdateGoodsReceiptNoteAction
{
    public function __construct(
        private readonly DocumentStorer $documentStorer,
        private readonly AuditLogger $auditLogger,
        private readonly DatabaseManager $db
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @return GoodsReceiptNote
     *
     * @throws ValidationException
     */
    public function execute(User $user, GoodsReceiptNote $note, array $payload): GoodsReceiptNote
    {
        if ($note->company_id !== $user->company_id) {
            throw ValidationException::withMessages([
                'note' => ['Goods receipt note not found for this company.'],
            ]);
        }

        if ($note->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending goods receipt notes can be updated.'],
            ]);
        }

        $linesPayload = collect($payload['lines']);

        return $this->db->transaction(function () use ($linesPayload, $note, $user): GoodsReceiptNote {
            foreach ($linesPayload as $linePayload) {
                /** @var GoodsReceiptLine|null $line */
                $line = $note->lines()->whereKey($linePayload['id'])->first();

                if ($line === null) {
                    throw ValidationException::withMessages([
                        'lines' => ["Line {$linePayload['id']} is not part of this goods receipt note."],
                    ]);
                }

                $before = $line->getOriginal();

                $line->defect_notes = $linePayload['defect_notes'] ?? $line->defect_notes;

                $attachmentIds = $this->resolveUpdatedAttachments($user, $line, $linePayload);

                if ($attachmentIds !== null) {
                    $line->attachment_ids = $attachmentIds;
                }

                if ($line->isDirty(['defect_notes', 'attachment_ids'])) {
                    $line->save();
                    $changes = $line->getChanges();
                    $this->auditLogger->updated($line, $before, $changes);
                }
            }

            $note->refresh();

            return $note->load('lines');
        });
    }

    /**
     * @param array<string, mixed> $linePayload
     * @return array<int, int>|null
     */
    private function resolveUpdatedAttachments(User $user, GoodsReceiptLine $line, array $linePayload): ?array
    {
        $currentIds = collect($line->attachment_ids ?? [])
            ->map(static fn ($id) => (int) $id)
            ->values();

        $updated = false;

        $removeIds = collect($linePayload['remove_attachment_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($removeIds->isNotEmpty()) {
            if ($removeIds->diff($currentIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'remove_attachment_ids' => ['One or more attachments do not belong to this line.'],
                ]);
            }

            Document::query()
                ->whereIn('id', $removeIds)
                ->where('documentable_type', $line->getMorphClass())
                ->where('documentable_id', $line->id)
                ->delete();

            $currentIds = $currentIds->diff($removeIds)->values();
            $updated = true;
        }

        /** @var array<int, UploadedFile>|null $additions */
        $additions = $linePayload['add_attachments'] ?? null;

        if ($additions) {
            $companyId = $line->goodsReceiptNote?->company_id;

            if ($companyId === null) {
                throw ValidationException::withMessages([
                    'attachments' => ['Unable to resolve company context for attachments.'],
                ]);
            }

            foreach ($additions as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $document = $this->documentStorer->store(
                    $user,
                    $file,
                    'qa',
                    $companyId,
                    $line->getMorphClass(),
                    $line->id,
                    [
                        'kind' => 'po',
                        'visibility' => 'company',
                        'meta' => ['context' => 'grn_attachment'],
                    ]
                );

                $currentIds->push($document->id);
                $updated = true;
            }
        }

        return $updated ? $currentIds->values()->all() : null;
    }
}
