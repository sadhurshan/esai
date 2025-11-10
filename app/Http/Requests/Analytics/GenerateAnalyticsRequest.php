<?php

namespace App\Http\Requests\Analytics;

use App\Http\Requests\ApiFormRequest;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GenerateAnalyticsRequest extends ApiFormRequest
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
     * @return array{start: Carbon, end: Carbon}
     */
    public function period(): array
    {
        $year = (int) ($this->validated('year') ?? now()->year);
        $month = (int) ($this->validated('month') ?? now()->month);

        $start = Carbon::create($year, $month, 1)->startOfDay();

        if ($start->greaterThan(now()->startOfMonth())) {
            throw ValidationException::withMessages([
                'month' => ['Analytics cannot be generated for a future period.'],
            ]);
        }

        $end = (clone $start)->endOfMonth();

        return ['start' => $start, 'end' => $end];
    }
}
