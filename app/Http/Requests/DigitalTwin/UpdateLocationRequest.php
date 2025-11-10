<?php

namespace App\Http\Requests\DigitalTwin;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Location|null $location */
        $location = $this->route('location');

        return $location !== null && $this->user()?->can('update', $location);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('locations', 'id')->where('company_id', $this->user()?->company_id)],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Location|null $location */
            $location = $this->route('location');
            $parentId = $this->input('parent_id');

            if ($location === null || $parentId === null) {
                return;
            }

            if ((int) $parentId === (int) $location->id) {
                $validator->errors()->add('parent_id', 'Location cannot be its own parent.');
            }
        });
    }
}
