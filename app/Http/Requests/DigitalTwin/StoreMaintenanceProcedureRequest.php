<?php

namespace App\Http\Requests\DigitalTwin;

use App\Models\MaintenanceProcedure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMaintenanceProcedureRequest extends FormRequest
{
    private const CATEGORIES = ['preventive', 'corrective', 'inspection', 'calibration', 'safety'];

    public function authorize(): bool
    {
        return $this->user()?->can('create', MaintenanceProcedure::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'code' => ['required', 'string', 'max:64', Rule::unique(MaintenanceProcedure::class, 'code')->where('company_id', $companyId)],
            'title' => ['required', 'string', 'max:191'],
            'category' => ['required', 'string', Rule::in(self::CATEGORIES)],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
            'instructions_md' => ['required', 'string'],
            'tools' => ['sometimes', 'array'],
            'tools.*' => ['string', 'max:191'],
            'safety' => ['sometimes', 'array'],
            'safety.*' => ['string', 'max:191'],
            'meta' => ['sometimes', 'array'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.step_no' => ['required', 'integer', 'min:1'],
            'steps.*.title' => ['required', 'string', 'max:191'],
            'steps.*.instruction_md' => ['required', 'string'],
            'steps.*.estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'steps.*.attachments' => ['sometimes', 'array'],
            'steps.*.attachments.*' => ['string', 'max:191'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $steps = $this->input('steps', []);
            $stepNumbers = [];

            foreach ($steps as $index => $step) {
                $number = (int) ($step['step_no'] ?? 0);
                if ($number <= 0) {
                    continue;
                }

                if (in_array($number, $stepNumbers, true)) {
                    $validator->errors()->add("steps.$index.step_no", 'Step number must be unique.');
                }

                $stepNumbers[] = $number;
            }
        });
    }
}
