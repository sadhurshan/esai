<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\User;
use App\Support\CompanyContext;
use App\Support\Notifications\NotificationService;

class SupplierPersonaService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function ensureOwnerPersona(?Supplier $supplier): ?User
    {
        if (! $supplier instanceof Supplier) {
            return null;
        }

        $company = $supplier->company;

        if ($company === null || $company->owner === null) {
            return null;
        }

        $owner = $company->owner;
        $dirty = false;

        if (! $owner->supplier_capable) {
            $owner->supplier_capable = true;
            $dirty = true;
        }

        if ($owner->default_supplier_id === null) {
            $owner->default_supplier_id = $supplier->id;
            $dirty = true;
        }

        if ($dirty) {
            $owner->save();
        }

        return $owner;
    }

    public function ensureBuyerContact(
        ?Supplier $supplier,
        int $buyerCompanyId,
        ?Company $buyerCompany = null,
        bool $notify = true,
    ): void {
        $owner = $this->ensureOwnerPersona($supplier);

        if ($owner === null) {
            return;
        }

        $buyerCompany ??= Company::query()->find($buyerCompanyId);

        $contact = CompanyContext::forCompany($buyerCompanyId, static function () use ($supplier, $owner, $buyerCompanyId) {
            return SupplierContact::firstOrCreate([
                'company_id' => $buyerCompanyId,
                'supplier_id' => $supplier?->id,
                'user_id' => $owner->id,
            ]);
        });

        if (! $contact instanceof SupplierContact) {
            return;
        }

        if ($contact->wasRecentlyCreated && $notify && $supplier instanceof Supplier) {
            $this->notifyOwnerOfPersona($owner, $supplier, $contact, $buyerCompanyId, $buyerCompany);
        }
    }

    private function notifyOwnerOfPersona(
        User $owner,
        Supplier $supplier,
        SupplierContact $contact,
        int $buyerCompanyId,
        ?Company $buyerCompany = null,
    ): void {
        $buyerName = $buyerCompany?->name ?? 'the buyer';
        $supplierName = $supplier->name ?? 'your supplier organization';

        $title = sprintf('You can now act as %s for %s', $supplierName, $buyerName);
        $body = sprintf(
            'You were invited to respond to %s RFQs as %s. Use the persona switcher in the top navigation to review their requests.',
            $buyerName,
            $supplierName,
        );

        $this->notifications->send(
            [$owner],
            'persona.supplier.invited',
            $title,
            $body,
            SupplierContact::class,
            $contact->id,
            [
                'buyer_company_id' => $buyerCompanyId,
                'buyer_company_name' => $buyerCompany?->name,
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplierName,
                'cta_url' => '/app/rfqs',
                'cta_label' => 'Open supplier RFQs',
            ],
        );
    }
}
