<?php

namespace App\Http\Requests;

class RFQStoreRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('is_open_bidding')) {
            $normalized = filter_var(
                $this->input('is_open_bidding'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            $this->merge([
                'is_open_bidding' => $normalized,
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
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'item_name' => ['required', 'string'],
            'type' => ['required', 'in:ready_made,manufacture'],
            'client_company' => ['required', 'string'],
            'status' => ['required', 'in:awaiting,open,closed,awarded,cancelled'],
            'deadline_at' => ['nullable', 'date'],
            'sent_at' => ['nullable', 'date'],
            'is_open_bidding' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'cad' => ['nullable', 'file', 'mimes:step,stp,iges,igs,dwg,dxf,sldprt,3mf,stl,pdf', 'max:20480'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_name' => ['required', 'string'],
            'items.*.spec' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.uom' => ['nullable', 'string', 'max:16'],
            'items.*.target_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.method' => ['required', 'string'],
            'items.*.material' => ['required', 'string'],
            'items.*.tolerance' => ['nullable', 'string'],
            'items.*.finish' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'cad.mimes' => 'The CAD file must be a STEP, STP, IGES, IGS, DWG, DXF, SLDPRT, 3MF, STL, or PDF.',
            'cad.max' => 'The CAD file may not be greater than 20 MB.',
        ];
    }
}
