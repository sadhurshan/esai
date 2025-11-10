<?php

namespace App\Http\Requests\DigitalTwin;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Location::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:64'],
            'parent_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('company_id', $this->user()?->company_id)],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $parentId = $this->integer('parent_id');

            if ($parentId <= 0) {
                return;
            }

            $parent = Location::query()
                ->where('company_id', $this->user()?->company_id)
                ->find($parentId);

            if ($parent === null) {
                $validator->errors()->add('parent_id', 'Parent location not found.');
            }
        });
    }
}
