<?php

namespace App\Http\Requests;

use App\Models\RFQ;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class StoreInvitationRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $supplierIds = [];

        if ($this->has('supplier_ids') && is_array($this->input('supplier_ids'))) {
            $supplierIds = $this->input('supplier_ids');
        }

        if ($this->filled('supplier_id')) {
            $supplierIds = array_merge($supplierIds, Arr::wrap($this->input('supplier_id')));
        }

        if ($supplierIds === []) {
            return;
        }

        $normalized = collect($supplierIds)
            ->filter(static fn ($value) => $value !== null && $value !== '')
            ->map(static fn ($value) => filter_var($value, FILTER_VALIDATE_INT))
            ->filter(static fn ($value) => $value !== false)
            ->unique()
            ->values()
            ->all();

        if ($normalized !== []) {
            $this->merge([
                'supplier_ids' => $normalized,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'supplier_ids' => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['integer', 'distinct', 'exists:suppliers,id'],
        ];
    }

    public function authorize(): bool
    {
        $user = $this->user();
        $rfq = $this->route('rfq');

        if (! $user instanceof User || ! $rfq instanceof RFQ) {
            return false;
        }

        return Gate::forUser($user)->allows('manageInvitations', $rfq);
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            $this->fail(
                'RFQ invitations require sourcing write access.',
                403,
                ['code' => 'rfqs_write_required']
            )
        );
    }
}
