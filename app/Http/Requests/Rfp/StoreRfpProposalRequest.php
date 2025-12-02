<?php

namespace App\Http\Requests\Rfp;

use App\Http\Requests\ApiFormRequest;

class StoreRfpProposalRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $maxKilobytes = (int) config('documents.max_size_mb', 50) * 1024;

        return [
            'supplier_company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'price_total' => ['nullable', 'numeric', 'min:0'],
            'price_total_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'lead_time_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'approach_summary' => ['required', 'string'],
            'schedule_summary' => ['required', 'string'],
            'value_add_summary' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:'.$maxKilobytes],
            'meta' => ['nullable', 'array'],
        ];
    }
}
