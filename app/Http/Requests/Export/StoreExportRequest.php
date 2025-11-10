<?php

namespace App\Http\Requests\Export;

use App\Enums\ExportRequestType;
use App\Http\Requests\ApiFormRequest;
use App\Services\ExportService;
use Illuminate\Validation\Rule;

class StoreExportRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $supportedTables = ExportService::supportedTables();

        return [
            'type' => ['required', 'string', Rule::in(ExportRequestType::values())],
            'filters' => ['nullable', 'array'],
            'filters.from' => ['nullable', 'date'],
            'filters.to' => ['nullable', 'date', 'after_or_equal:filters.from'],
            'filters.tables' => [
                Rule::requiredIf(fn () => $this->input('type') === ExportRequestType::Custom->value),
                'array',
                'min:1',
            ],
            'filters.tables.*' => ['string', Rule::in($supportedTables)],
        ];
    }
}
