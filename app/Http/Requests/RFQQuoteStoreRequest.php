<?php

namespace App\Http\Requests;

class RFQQuoteStoreRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'unit_price_usd' => ['required', 'numeric', 'min:0'],
            'lead_time_days' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:step,stp,iges,igs,dwg,dxf,sldprt,3mf,stl,pdf', 'max:20480'],
            'via' => ['required', 'in:direct,bidding'],
        ];
    }

    public function messages(): array
    {
        return [
            'attachment.mimes' => 'Attachments must be a STEP, STP, IGES, IGS, DWG, DXF, SLDPRT, 3MF, STL, or PDF file.',
            'attachment.max' => 'Attachments may not exceed 20 MB.',
        ];
    }
}
