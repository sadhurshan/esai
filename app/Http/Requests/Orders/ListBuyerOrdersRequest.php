<?php

namespace App\Http\Requests\Orders;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ListBuyerOrdersRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $status = $this->query('status');

        if (is_string($status)) {
            $this->merge([
                'status' => [$status],
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in($this->allowedStatuses())],
            'supplier_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function allowedStatuses(): array
    {
        return ['draft', 'pending_ack', 'accepted', 'partially_fulfilled', 'fulfilled', 'cancelled'];
    }

    /**
     * @return list<string>
     */
    public function statuses(): array
    {
        $statuses = $this->input('status');

        if ($statuses === null) {
            return [];
        }

        if (is_array($statuses)) {
            return array_values(array_filter($statuses, fn ($status) => is_string($status) && $status !== ''));
        }

        return [];
    }
}
