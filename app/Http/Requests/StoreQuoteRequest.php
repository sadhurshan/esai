<?php

namespace App\Http\Requests;

use App\Enums\CompanySupplierStatus;
use App\Models\RFQ;
use App\Models\Supplier;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends ApiFormRequest
{
    private ?RFQ $rfq = null;

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || $user->company_id === null) {
            return false;
        }

        $rfqId = (int) $this->input('rfq_id');
        if ($rfqId <= 0) {
            return false;
        }

        $this->rfq = RFQ::with('invitations')->find($rfqId);

        if ($this->rfq === null) {
            return false;
        }

        $supplierId = (int) $this->input('supplier_id');

        if ($supplierId <= 0) {
            return false;
        }

        $supplier = Supplier::query()
            ->with('company')
            ->whereKey($supplierId)
            ->where('company_id', $user->company_id)
            ->first();

        if ($supplier === null) {
            return false;
        }

        $company = $supplier->company;

        if ($company === null || $company->supplier_status !== CompanySupplierStatus::Approved) {
            return false;
        }

        if ($this->rfq->is_open_bidding) {
            return true;
        }

        return $this->rfq->invitations
            ->contains(static fn ($invitation) => (int) $invitation->supplier_id === $supplier->id);
    }

    public function rules(): array
    {
        $rfqId = (int) $this->input('rfq_id');

        return [
            'rfq_id' => ['required', 'integer', 'exists:rfqs,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'currency' => ['required', 'string', 'size:3'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'min_order_qty' => ['nullable', 'integer', 'min:1'],
            'lead_time_days' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.rfq_item_id' => [
                'required',
                'integer',
                Rule::exists('rfq_items', 'id')->where(fn ($query) => $query->where('rfq_id', $rfqId)),
            ],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.lead_time_days' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:step,stp,iges,igs,dwg,dxf,sldprt,3mf,stl,pdf'],
        ];
    }

    public function rfq(): RFQ
    {
        if ($this->rfq === null) {
            $rfqId = (int) $this->input('rfq_id');
            $this->rfq = RFQ::with(['company', 'invitations'])->findOrFail($rfqId);
        }

        return $this->rfq;
    }
}
