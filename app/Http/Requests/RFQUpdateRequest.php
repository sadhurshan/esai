<?php

namespace App\Http\Requests;

use App\Models\RFQ;
use Illuminate\Validation\Rule;

class RFQUpdateRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('is_open_bidding') && ! $this->has('open_bidding')) {
            $this->merge([
                'open_bidding' => $this->input('is_open_bidding'),
            ]);
        }

        if ($this->has('open_bidding')) {
            $this->merge([
                'open_bidding' => filter_var(
                    $this->input('open_bidding'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }

        if ($this->has('items') && is_string($this->input('items'))) {
            $decoded = json_decode($this->input('items'), true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->merge([
                    'items' => $decoded,
                ]);
            }
        }

        if (! $this->filled('title') && $this->filled('item_name')) {
            $this->merge([
                'title' => $this->input('item_name'),
            ]);
        }

        if (! $this->filled('delivery_location') && $this->filled('client_company')) {
            $this->merge([
                'delivery_location' => $this->input('client_company'),
            ]);
        }

        if ($this->has('taxPercent') && ! $this->has('tax_percent')) {
            $this->merge([
                'tax_percent' => $this->input('taxPercent'),
            ]);
        }

        if ($this->has('paymentTerms') && ! $this->has('payment_terms')) {
            $this->merge([
                'payment_terms' => $this->input('paymentTerms'),
            ]);
        }

        if ($this->has('items') && is_array($this->input('items'))) {
            $this->merge([
                'items' => $this->normalizeItems($this->input('items')),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $extensions = config('documents.allowed_extensions', []);

        if (! is_array($extensions) || $extensions === []) {
            $extensions = [
                'step', 'stp', 'iges', 'igs', 'dwg', 'dxf', 'sldprt', 'stl', '3mf',
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'png', 'jpg', 'jpeg', 'tif', 'tiff',
            ];
        }

        $maxKilobytes = max(1, (int) config('documents.max_size_mb', 50)) * 1024;

        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'method' => ['sometimes', Rule::in(RFQ::METHODS)],
            'type' => ['prohibited'],
            'material' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tolerance' => ['sometimes', 'nullable', 'string', 'max:120'],
            'finish' => ['sometimes', 'nullable', 'string', 'max:120'],
            'quantity_total' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'delivery_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'incoterm' => ['sometimes', 'nullable', 'string', 'max:32'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'tax_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'payment_terms' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['prohibited'],
            'publish_at' => ['sometimes', 'nullable', 'date'],
            'due_at' => ['sometimes', 'nullable', 'date', 'after:publish_at'],
            'close_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:due_at'],
            'open_bidding' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'cad' => ['sometimes', 'nullable', 'file', 'mimes:'.implode(',', $extensions), 'max:'.$maxKilobytes],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.part_number' => ['required_with:items', 'string', 'max:120'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.qty' => ['required_with:items', 'integer', 'min:1'],
            'items.*.uom' => ['nullable', 'string', 'max:16'],
            'items.*.target_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.method' => ['nullable', 'string', 'max:120'],
            'items.*.material' => ['nullable', 'string', 'max:120'],
            'items.*.tolerance' => ['nullable', 'string', 'max:120'],
            'items.*.finish' => ['nullable', 'string', 'max:120'],
            'items.*.cad_doc_id' => ['nullable', 'integer', 'exists:documents,id'],
            'items.*.specs_json' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        $maxMb = max(1, (int) config('documents.max_size_mb', 50));

        return [
            'cad.mimes' => 'The CAD file must be a STEP, STP, IGES, IGS, DWG, DXF, SLDPRT, 3MF, STL, or PDF.',
            'cad.max' => 'The CAD file may not be greater than '.$maxMb.' MB.',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $items): array
    {
        return array_map(function (array $item): array {
            if (! isset($item['part_number']) && isset($item['part_name'])) {
                $item['part_number'] = $item['part_name'];
            }

            if (! isset($item['description']) && isset($item['spec'])) {
                $item['description'] = $item['spec'];
            }

            if (! isset($item['qty']) && isset($item['quantity'])) {
                $item['qty'] = $item['quantity'];
            }

            return $item;
        }, $items);
    }

}
