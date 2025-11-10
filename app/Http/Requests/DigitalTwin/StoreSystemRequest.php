<?php

namespace App\Http\Requests\DigitalTwin;

use App\Models\System;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSystemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', System::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
            'location_id' => ['required', 'integer', Rule::exists(Location::class, 'id')->where('company_id', $companyId)],
        ];
    }
}
