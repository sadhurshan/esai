<?php

namespace App\Http\Requests\DigitalTwin;

use App\Models\Asset;
use App\Models\Location;
use App\Models\System;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
{
    private const STATUSES = ['active', 'standby', 'retired', 'maintenance'];

    public function authorize(): bool
    {
        /** @var Asset|null $asset */
        $asset = $this->route('asset');

        return $asset !== null && $this->user()?->can('update', $asset);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'tag' => ['sometimes', 'nullable', 'string', 'max:64'],
            'serial_no' => ['sometimes', 'nullable', 'string', 'max:191'],
            'model_no' => ['sometimes', 'nullable', 'string', 'max:191'],
            'manufacturer' => ['sometimes', 'nullable', 'string', 'max:191'],
            'commissioned_at' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(self::STATUSES)],
            'meta' => ['sometimes', 'array'],
            'system_id' => ['sometimes', 'nullable', 'integer', Rule::exists(System::class, 'id')->where('company_id', $companyId)],
            'location_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Location::class, 'id')->where('company_id', $companyId)],
            'documents' => ['sometimes', 'array'],
            'documents.*.file' => ['required', 'file'],
            'documents.*.category' => ['sometimes', 'string', 'max:64'],
            'documents.*.kind' => ['sometimes', 'string', 'max:64'],
            'documents.*.visibility' => ['sometimes', 'nullable', 'string', 'max:32'],
            'documents.*.meta' => ['sometimes', 'array'],
        ];
    }
}
