<?php

namespace App\Actions\Invoicing;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Models\CreditNote;
use App\Models\Document;
use App\Models\User;
use App\Support\Documents\DocumentStorer;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AttachCreditNoteFileAction
{
    public function __construct(private readonly DocumentStorer $documentStorer)
    {
    }

    public function execute(User $user, CreditNote $creditNote, UploadedFile $file): Document
    {
        if ($user->company_id === null || (int) $creditNote->company_id !== (int) $user->company_id) {
            throw ValidationException::withMessages([
                'credit_note_id' => ['Credit note not found for this company.'],
            ]);
        }

        $document = $this->documentStorer->store(
            $user,
            $file,
            DocumentCategory::Financial->value,
            (int) $creditNote->company_id,
            CreditNote::class,
            (int) $creditNote->getKey(),
            [
                'kind' => DocumentKind::Other->value,
                'visibility' => 'company',
                'meta' => [
                    'context' => 'credit_note_attachment',
                    'credit_note_id' => $creditNote->getKey(),
                ],
            ],
        );

        $creditNote->documents()->syncWithoutDetaching([$document->getKey()]);

        return $document;
    }
}
