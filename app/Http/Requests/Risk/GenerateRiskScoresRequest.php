<?php

namespace App\Http\Requests\Risk;

use App\Http\Requests\ApiFormRequest;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GenerateRiskScoresRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2000', 'max:'.now()->year],
            'month' => ['nullable', 'integer', Rule::in(range(1, 12))],
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon, key: string}
     */
    public function period(): array
    {
        $year = (int) ($this->validated('year') ?? now()->year);
        $month = (int) ($this->validated('month') ?? now()->month);

        $start = Carbon::create($year, $month, 1)->startOfDay();

        if ($start->greaterThan(now()->startOfMonth())) {
            throw ValidationException::withMessages([
                'month' => ['Risk scores cannot be generated for a future period.'],
            ]);
        }

        $end = (clone $start)->endOfMonth();

        return [
            'start' => $start,
            'end' => $end,
            'key' => $start->format('Y-m'),
        ];
    }
}
