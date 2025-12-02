<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\ApiFormRequest;

class ReplayDeadLettersRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return collect($this->validated('ids'))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }
}
