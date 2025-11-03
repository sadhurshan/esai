<?php

namespace App\Http\Requests;

class RFQStoreRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'item_name' => ['required', 'string'],
            'type' => ['required', 'in:ready_made,manufacture'],
            'quantity' => ['required', 'integer', 'min:1'],
            'material' => ['required', 'string'],
            'method' => ['required', 'string'],
            'tolerance' => ['nullable', 'string'],
            'finish' => ['nullable', 'string'],
            'client_company' => ['required', 'string'],
            'status' => ['required', 'in:awaiting,open,closed,awarded,cancelled'],
            'deadline_at' => ['nullable', 'date'],
            'sent_at' => ['nullable', 'date'],
            'is_open_bidding' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'cad' => ['nullable', 'file', 'mimes:step,stp,iges,igs,dwg,dxf,sldprt,3mf,stl,pdf', 'max:20480'],
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
