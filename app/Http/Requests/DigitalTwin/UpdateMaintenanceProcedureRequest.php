<?php

namespace App\Http\Requests\DigitalTwin;

use App\Models\MaintenanceProcedure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMaintenanceProcedureRequest extends FormRequest
{
    private const CATEGORIES = ['preventive', 'corrective', 'inspection', 'calibration', 'safety'];

    public function authorize(): bool
    {
        /** @var MaintenanceProcedure|null $procedure */
        $procedure = $this->route('procedure');

        return $procedure !== null && $this->user()?->can('update', $procedure);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var MaintenanceProcedure|null $procedure */
        $procedure = $this->route('procedure');
        $companyId = $this->user()?->company_id;

        return [
            'code' => ['sometimes', 'string', 'max:64', Rule::unique(MaintenanceProcedure::class, 'code')
                ->where('company_id', $companyId)
                ->ignore($procedure?->id)],
            'title' => ['sometimes', 'string', 'max:191'],
            'category' => ['sometimes', 'string', Rule::in(self::CATEGORIES)],
            'estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'instructions_md' => ['sometimes', 'string'],
            'tools' => ['sometimes', 'array'],
            'tools.*' => ['string', 'max:191'],
            'safety' => ['sometimes', 'array'],
            'safety.*' => ['string', 'max:191'],
            'meta' => ['sometimes', 'array'],
            'steps' => ['sometimes', 'array', 'min:1'],
            'steps.*.id' => ['sometimes', 'integer'],
            'steps.*.step_no' => ['required_with:steps', 'integer', 'min:1'],
            'steps.*.title' => ['required_with:steps', 'string', 'max:191'],
            'steps.*.instruction_md' => ['required_with:steps', 'string'],
            'steps.*.estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'steps.*.attachments' => ['sometimes', 'array'],
            'steps.*.attachments.*' => ['string', 'max:191'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $steps = $this->input('steps', []);
            if ($steps === []) {
                return;
            }

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
