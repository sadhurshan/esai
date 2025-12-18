<?php

namespace App\Http\Requests\Rma;

use App\Http\Requests\ApiFormRequest;
use App\Models\GoodsReceiptNote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreRmaRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'resolution_requested' => ['required', Rule::in(['repair', 'replacement', 'credit', 'refund', 'other'])],
            'defect_qty' => ['nullable', 'integer', 'min:1'],
            'purchase_order_line_id' => ['nullable', 'integer', 'exists:po_lines,id'],
            'grn_id' => ['nullable', 'integer', 'exists:goods_receipt_notes,id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var PurchaseOrder|null $purchaseOrder */
            $purchaseOrder = $this->route('purchaseOrder');

            if (! $purchaseOrder instanceof PurchaseOrder) {
                return;
            }

            $lineId = $this->input('purchase_order_line_id');
            if ($lineId !== null) {
                $line = PurchaseOrderLine::query()
                    ->whereKey($lineId)
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->first();

                if ($line === null) {
                    $validator->errors()->add('purchase_order_line_id', 'PO line does not belong to the selected purchase order.');
                } else {
                    $defectQty = (int) $this->input('defect_qty');

                    if ($defectQty > 0 && $defectQty > (int) $line->quantity) {
                        $validator->errors()->add('defect_qty', 'Defect quantity cannot exceed the ordered quantity.');
                    }
                }
            }

            $grnId = $this->input('grn_id');
            if ($grnId !== null) {
                $grn = GoodsReceiptNote::query()
                    ->whereKey($grnId)
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->first();

                if ($grn === null) {
                    $validator->errors()->add('grn_id', 'Goods receipt note does not belong to the selected purchase order.');
                }
            }
        });
    }
}
