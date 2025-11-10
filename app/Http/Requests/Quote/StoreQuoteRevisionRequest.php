<?php

namespace App\Http\Requests\Quote;

use App\Http\Requests\ApiFormRequest;
use App\Models\Quote;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreQuoteRevisionRequest extends ApiFormRequest
{
    public function rules(): array
    {
        /** @var Quote|null $quote */
        $quote = $this->route('quote');
        $quoteId = $quote instanceof Quote ? $quote->id : 0;

        return [
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'min_order_qty' => ['nullable', 'integer', 'min:1'],
            'lead_time_days' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.quote_item_id' => [
                'required',
                'integer',
                Rule::exists('quote_items', 'id')->where(fn ($query) => $query->where('quote_id', $quoteId)),
            ],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.lead_time_days' => ['nullable', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }

    public function passedValidation(): void
    {
        $payload = $this->validated();

        $hasTopLevelChange = collect(['unit_price', 'min_order_qty', 'lead_time_days', 'note', 'currency'])
            ->contains(fn (string $field) => array_key_exists($field, $payload));

        $items = $payload['items'] ?? [];
        $hasItemChanges = false;

        foreach ($items as $index => $item) {
            $itemHasChange = array_key_exists('unit_price', $item)
                || array_key_exists('lead_time_days', $item)
                || array_key_exists('note', $item);

            if (! $itemHasChange) {
                throw ValidationException::withMessages([
                    "items.{$index}" => ['Provide at least one change for a revised item.'],
                ]);
            }

            $hasItemChanges = true;
        }

        if (! $hasTopLevelChange && ! $hasItemChanges) {
            throw ValidationException::withMessages([
                'revision' => ['Provide at least one field to revise.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return collect($this->validated())
            ->except('attachment')
            ->toArray();
    }
}
