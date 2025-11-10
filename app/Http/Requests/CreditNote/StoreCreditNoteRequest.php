<?php

namespace App\Http\Requests\CreditNote;

use App\Http\Requests\ApiFormRequest;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Illuminate\Contracts\Validation\Validator;

class StoreCreditNoteRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'grn_id' => ['nullable', 'integer', 'exists:goods_receipt_notes,id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Invoice|null $invoice */
            $invoice = $this->route('invoice');

            if (! $invoice instanceof Invoice) {
                return;
            }

            $purchaseOrderId = $this->input('purchase_order_id') ?? $invoice->purchase_order_id;

            if ($purchaseOrderId === null) {
                $validator->errors()->add('purchase_order_id', 'Purchase order selection is required.');

                return;
            }

            /** @var PurchaseOrder|null $purchaseOrder */
            $purchaseOrder = PurchaseOrder::query()->find($purchaseOrderId);

            if ($purchaseOrder === null) {
                $validator->errors()->add('purchase_order_id', 'Purchase order not found.');

                return;
            }

            if ((int) $purchaseOrder->company_id !== (int) $invoice->company_id) {
                $validator->errors()->add('purchase_order_id', 'Invoice and purchase order must belong to the same company.');
            }

            if ((int) $invoice->purchase_order_id !== (int) $purchaseOrder->id) {
                $validator->errors()->add('purchase_order_id', 'Invoice must reference the selected purchase order.');
            }

            $grnId = $this->input('grn_id');

            if ($grnId !== null) {
                /** @var GoodsReceiptNote|null $grn */
                $grn = GoodsReceiptNote::query()->find($grnId);

                if ($grn === null) {
                    $validator->errors()->add('grn_id', 'Goods receipt note not found.');

                    return;
                }

                if ((int) $grn->company_id !== (int) $invoice->company_id || (int) $grn->purchase_order_id !== (int) $purchaseOrder->id) {
                    $validator->errors()->add('grn_id', 'Goods receipt note must belong to the selected purchase order.');
                }
            }
        });
    }
}
