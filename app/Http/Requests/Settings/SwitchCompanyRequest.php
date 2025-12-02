<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SwitchCompanyRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id'),
            ],
        ];
    }

    public function authorize(): bool
    {
        return $this->resolveUserId() > 0;
    }

    private function resolveUserId(): int
    {
        $user = $this->user();

        if ($user !== null) {
            return (int) $user->id;
        }

        try {
            $sanctumUser = $this->user('sanctum');
        } catch (\InvalidArgumentException) {
            $sanctumUser = null;
        }

        return $sanctumUser?->id ?? 0;
    }
}
