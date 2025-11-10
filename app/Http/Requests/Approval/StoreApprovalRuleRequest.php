<?php

namespace App\Http\Requests\Approval;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreApprovalRuleRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $levels = $this->input('levels_json');

        if (is_string($levels)) {
            $decoded = json_decode($levels, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['levels_json' => $decoded]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::in(['rfq', 'purchase_order', 'change_order', 'invoice', 'ncr'])],
            'threshold_min' => ['required', 'numeric', 'min:0'],
            'threshold_max' => ['nullable', 'numeric', 'gte:threshold_min'],
            'levels_json' => ['required', 'array', 'min:1', 'max:5'],
            'levels_json.*.level_no' => ['required', 'integer', 'between:1,5'],
            'levels_json.*.approver_role' => ['nullable', 'string', 'max:50'],
            'levels_json.*.approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'levels_json.*.max_amount' => ['nullable', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $levels = $this->input('levels_json');

            if (! is_array($levels)) {
                return;
            }

            $levelNumbers = [];

            foreach ($levels as $index => $level) {
                $role = $level['approver_role'] ?? null;
                $userId = $level['approver_user_id'] ?? null;

                if (empty($role) && empty($userId)) {
                    $validator->errors()->add("levels_json.$index", 'Each level must define either an approver role or a specific user.');
                }

                if (! empty($role) && ! empty($userId)) {
                    $validator->errors()->add("levels_json.$index", 'Specify either an approver role or a user, not both.');
                }

                $no = (int) ($level['level_no'] ?? 0);

                if (in_array($no, $levelNumbers, true)) {
                    $validator->errors()->add('levels_json', 'Level numbers must be unique.');
                } else {
                    $levelNumbers[] = $no;
                }
            }

            sort($levelNumbers);

            if ($levelNumbers !== range(1, count($levelNumbers))) {
                $validator->errors()->add('levels_json', 'Levels must be sequential starting from 1.');
            }
        });
    }
}
