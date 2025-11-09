<?php

namespace App\Http\Requests;

class UpdateGoodsReceiptRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer', 'exists:goods_receipt_lines,id'],
            'lines.*.defect_notes' => ['nullable', 'string', 'max:1000'],
            'lines.*.add_attachments' => ['nullable', 'array', 'max:5'],
            'lines.*.add_attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'lines.*.remove_attachment_ids' => ['nullable', 'array'],
            'lines.*.remove_attachment_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->validated();
    }
}
