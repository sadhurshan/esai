<?php

namespace App\Http\Requests\DigitalTwin;

use App\Models\Asset;
use App\Models\Location;
use App\Models\System;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    private const STATUSES = ['active', 'standby', 'retired', 'maintenance'];

    public function authorize(): bool
    {
        return $this->user()?->can('create', Asset::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'name' => ['required', 'string', 'max:191'],
            'tag' => ['nullable', 'string', 'max:64'],
            'serial_no' => ['nullable', 'string', 'max:191'],
            'model_no' => ['nullable', 'string', 'max:191'],
            'manufacturer' => ['nullable', 'string', 'max:191'],
            'commissioned_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(self::STATUSES)],
            'meta' => ['nullable', 'array'],
            'system_id' => ['nullable', 'integer', Rule::exists(System::class, 'id')->where('company_id', $companyId)],
            'location_id' => ['required', 'integer', Rule::exists(Location::class, 'id')->where('company_id', $companyId)],
            'documents' => ['sometimes', 'array'],
            'documents.*.file' => ['required', 'file'],
            'documents.*.category' => ['sometimes', 'string', 'max:64'],
            'documents.*.kind' => ['sometimes', 'string', 'max:64'],
            'documents.*.visibility' => ['sometimes', 'nullable', 'string', 'max:32'],
            'documents.*.meta' => ['sometimes', 'array'],
        ];
    }
}
