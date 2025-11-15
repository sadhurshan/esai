<?php

namespace App\Actions\Receiving;

use App\Models\Document;
use App\Models\GoodsReceiptNote;
use App\Models\User;
use App\Support\Documents\DocumentStorer;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AttachGoodsReceiptFileAction
{
    public function __construct(private readonly DocumentStorer $documentStorer)
    {
    }

    public function execute(User $user, GoodsReceiptNote $note, UploadedFile $file): Document
    {
        if ($user->company_id === null || (int) $note->company_id !== (int) $user->company_id) {
            throw ValidationException::withMessages([
                'goods_receipt_note_id' => ['Goods receipt note not found for this company.'],
            ]);
        }

        return $this->documentStorer->store(
            $user,
            $file,
            'qa',
            (int) $note->company_id,
            $note->getMorphClass(),
            $note->getKey(),
            [
                'kind' => 'grn_attachment',
                'visibility' => 'company',
                'meta' => [
                    'context' => 'grn_note_attachment',
                    'grn_id' => $note->getKey(),
                    'purchase_order_id' => $note->purchase_order_id,
                ],
            ],
        );
    }
}
