<?php

namespace App\Http\Requests;

class RFQUpdateRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'item_name' => ['sometimes', 'string'],
            'type' => ['sometimes', 'in:ready_made,manufacture'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'material' => ['sometimes', 'string'],
            'method' => ['sometimes', 'string'],
            'tolerance' => ['sometimes', 'nullable', 'string'],
            'finish' => ['sometimes', 'nullable', 'string'],
            'client_company' => ['sometimes', 'string'],
            'status' => ['sometimes', 'in:awaiting,open,closed,awarded,cancelled'],
            'deadline_at' => ['sometimes', 'nullable', 'date'],
            'sent_at' => ['sometimes', 'nullable', 'date'],
            'is_open_bidding' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'cad' => ['sometimes', 'nullable', 'file', 'mimes:step,stp,iges,igs,dwg,dxf,sldprt,3mf,stl,pdf', 'max:20480'],
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
