<?php

namespace App\Http\Requests\Notifications;

use App\Http\Requests\ApiFormRequest;

class MarkNotificationsReadRequest extends ApiFormRequest
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
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'ids' => collect($this->validated('ids'))
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values()
                ->all(),
        ];
    }
}
