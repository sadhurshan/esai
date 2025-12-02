<?php

namespace App\Http\Requests;

class RfqLineStoreRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $payload = [];

        $partName = $this->input('partName') ?? $this->input('part_name') ?? $this->input('partNumber');
        if ($partName && ! $this->has('part_number')) {
            $payload['part_number'] = $partName;
        }

        if ($this->has('spec') && ! $this->has('description')) {
            $payload['description'] = $this->input('spec');
        }

        if ($this->has('quantity') && ! $this->has('qty')) {
            $payload['qty'] = $this->input('quantity');
        }

        if ($this->has('targetPrice') && ! $this->has('target_price')) {
            $payload['target_price'] = $this->input('targetPrice');
        }

        if ($this->has('cadDocumentId') && ! $this->has('cad_doc_id')) {
            $payload['cad_doc_id'] = $this->input('cadDocumentId');
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'part_number' => ['required', 'string', 'max:120'],
            'spec' => ['nullable', 'string'],
            'method' => ['required', 'string', 'max:120'],
            'material' => ['required', 'string', 'max:120'],
            'tolerance' => ['nullable', 'string', 'max:120'],
            'finish' => ['nullable', 'string', 'max:120'],
            'qty' => ['required', 'integer', 'min:1'],
            'uom' => ['nullable', 'string', 'max:16'],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'cad_doc_id' => ['nullable', 'integer', 'exists:documents,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
