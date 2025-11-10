<?php

namespace App\Http\Requests\DigitalTwin;

use App\Models\System;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var System|null $system */
        $system = $this->route('system');

        return $system !== null && $this->user()?->can('update', $system);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'location_id' => ['sometimes', 'integer', Rule::exists(Location::class, 'id')->where('company_id', $companyId)],
        ];
    }
}
