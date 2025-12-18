<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\InteractsWithDocumentRules;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ActivePersonaContext;
use App\Support\CompanyContext;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Support\Str;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends ApiFormRequest
{
    use InteractsWithDocumentRules;

    private ?RFQ $rfq = null;

    protected function prepareForValidation(): void
    {
        if (! ActivePersonaContext::isSupplier()) {
            return;
        }

        $supplierId = ActivePersonaContext::supplierId();

        if ($supplierId !== null) {
            $this->merge([
                'supplier_id' => $supplierId,
            ]);
        }
    }

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $companyId = $this->resolveActingCompanyId($user);

        if ($companyId === null) {
            return false;
        }

        $routeRfq = $this->route('rfq');
        if ($routeRfq instanceof RFQ) {
            $this->rfq = $routeRfq->loadMissing('invitations');
        }

        if ($this->rfq === null) {
            $rfqId = (int) $this->input('rfq_id');
            if ($rfqId <= 0) {
                return false;
            }

            $this->rfq = RFQ::with('invitations')->find($rfqId);
        }

        if ($this->rfq === null) {
            return false;
        }

        $supplierId = $this->resolveSupplierId();

        if ($supplierId === null) {
            return false;
        }

        $supplier = CompanyContext::bypass(static fn () => Supplier::query()
            ->with('company')
            ->whereKey($supplierId)
            ->first());

        if ($supplier === null) {
            return false;
        }

        if (! $this->supplierMatchesActivePersona($supplier)) {
            return false;
        }

        if (! $this->supplierIsActive($supplier)) {
            return false;
        }

        $permissionCompanyId = ActivePersonaContext::isSupplier()
            ? (int) ($supplier->company_id ?? 0)
            : $companyId;

        if ($permissionCompanyId <= 0) {
            return false;
        }

        $permissionRegistry = app(PermissionRegistry::class);

        if (! $permissionRegistry->userHasAny($user, ['rfqs.read'], $permissionCompanyId)) {
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
        $rfqId = $this->rfq()?->id ?? 0;
        $extensions = $this->documentAllowedExtensions();
        $maxKilobytes = $this->documentMaxKilobytes();
        $leadTimeRules = $this->isSupplierActor()
            ? ['nullable', 'integer', 'min:0']
            : ['required', 'integer', 'min:0'];

        return [
            'rfq_id' => ['nullable', 'integer', 'exists:rfqs,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'currency' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'incoterm' => ['nullable', 'string', 'max:8'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'min_order_qty' => ['nullable', 'integer', 'min:1'],
            'lead_time_days' => $leadTimeRules,
            'notes' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.rfq_item_id' => [
                'required',
                'integer',
                Rule::exists('rfq_items', 'id')->where(fn ($query) => $query->where('rfq_id', $rfqId)),
            ],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price_minor' => ['nullable', 'integer', 'min:0'],
            'items.*.currency' => ['nullable', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'items.*.lead_time_days' => ['required', 'integer', 'min:0'],
            'items.*.note' => ['nullable', 'string'],
            'items.*.tax_code_ids' => ['nullable', 'array'],
            'items.*.tax_code_ids.*' => ['integer', 'min:1'],
            'attachment' => ['nullable', 'file', 'max:'.$maxKilobytes, 'mimes:'.implode(',', $extensions)],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['integer', 'exists:documents,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $items = $this->input('items', []);

            foreach ($items as $index => $item) {
                $hasUnit = isset($item['unit_price']) && $item['unit_price'] !== null;
                $hasMinor = isset($item['unit_price_minor']) && $item['unit_price_minor'] !== null;

                if (! $hasUnit && ! $hasMinor) {
                    $validator->errors()->add("items.{$index}.unit_price", 'Provide either unit_price or unit_price_minor for each item.');
                }
            }
        });
    }

    public function rfq(): RFQ
    {
        if ($this->rfq === null) {
            $routeRfq = $this->route('rfq');

            if ($routeRfq instanceof RFQ) {
                $this->rfq = $routeRfq->loadMissing(['company', 'invitations']);
            } else {
                $rfqId = (int) $this->input('rfq_id');
                $this->rfq = RFQ::with(['company', 'invitations'])->findOrFail($rfqId);
            }
        }

        return $this->rfq;
    }

    private function isSupplierActor(): bool
    {
        if (ActivePersonaContext::isSupplier()) {
            return true;
        }

        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $role = (string) ($user->role ?? '');

        return Str::startsWith($role, 'supplier_');
    }

    private function supplierMatchesActivePersona(Supplier $supplier): bool
    {
        $personaSupplierId = ActivePersonaContext::supplierId();

        if ($personaSupplierId === null) {
            return true;
        }

        return $personaSupplierId === (int) $supplier->id;
    }

    private function resolveActingCompanyId(User $user): ?int
    {
        $personaCompanyId = ActivePersonaContext::companyId();

        if ($personaCompanyId !== null) {
            return $personaCompanyId;
        }

        if ($user->company_id !== null) {
            return (int) $user->company_id;
        }

        return null;
    }

    private function supplierIsActive(Supplier $supplier): bool
    {
        if ($supplier->status === null) {
            return true;
        }

        return ! in_array($supplier->status, ['rejected', 'suspended'], true);
    }

    private function resolveSupplierId(): ?int
    {
        $value = $this->input('supplier_id');

        if ($value === null || $value === '') {
            return null;
        }

        $supplierId = (int) $value;

        return $supplierId > 0 ? $supplierId : null;
    }
}
