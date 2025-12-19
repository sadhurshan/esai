<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Support\ActivePersonaContext;
use App\Support\Permissions\PermissionRegistry;

class InvoicePolicy
{
    private const SUPPLIER_ROLES = ['supplier_admin', 'supplier_estimator', 'owner'];

    public function __construct(private readonly PermissionRegistry $permissionRegistry)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->hasBillingPermission($user, 'billing.read');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $this->matchesCompany($user, $invoice) && ! $user->isPlatformAdmin()) {
            return false;
        }

        return $this->hasBillingPermission($user, 'billing.read', $invoice);
    }

    public function create(User $user): bool
    {
        return $this->hasBillingPermission($user, 'billing.write');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        $editableStatuses = [
            InvoiceStatus::Draft->value,
            InvoiceStatus::BuyerReview->value,
            InvoiceStatus::Rejected->value,
        ];

        if (! in_array($invoice->status, $editableStatuses, true)) {
            return false;
        }

        if (! $this->matchesCompany($user, $invoice) && ! $user->isPlatformAdmin()) {
            return false;
        }

        return $this->hasBillingPermission($user, 'billing.write', $invoice);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        $editableStatuses = [
            InvoiceStatus::Draft->value,
            InvoiceStatus::BuyerReview->value,
            InvoiceStatus::Rejected->value,
        ];

        if (! in_array($invoice->status, $editableStatuses, true)) {
            return false;
        }

        if (! $this->matchesCompany($user, $invoice) && ! $user->isPlatformAdmin()) {
            return false;
        }

        return $this->hasBillingPermission($user, 'billing.write', $invoice);
    }

    public function review(User $user, Invoice $invoice): bool
    {
        if (! $this->matchesCompany($user, $invoice) && ! $user->isPlatformAdmin()) {
            return false;
        }

        $reviewableStatuses = [InvoiceStatus::Submitted->value, InvoiceStatus::BuyerReview->value];

        if (! in_array($invoice->status, $reviewableStatuses, true)) {
            return false;
        }

        return $this->hasBillingPermission($user, 'billing.write', $invoice);
    }

    public function markPaid(User $user, Invoice $invoice): bool
    {
        if (! $this->matchesCompany($user, $invoice) && ! $user->isPlatformAdmin()) {
            return false;
        }

        if ($invoice->status !== InvoiceStatus::Approved->value) {
            return false;
        }

        return $this->hasBillingPermission($user, 'billing.write', $invoice);
    }

    public function supplierView(User $user, Invoice $invoice): bool
    {
        return $this->supplierOwnsInvoice($user, $invoice);
    }

    public function supplierUpdate(User $user, Invoice $invoice): bool
    {
        return $this->supplierOwnsInvoice($user, $invoice);
    }

    public function supplierSubmit(User $user, Invoice $invoice): bool
    {
        return $this->supplierOwnsInvoice($user, $invoice);
    }

    private function matchesCompany(User $user, Invoice $invoice): bool
    {
        return $user->company_id !== null && (int) $user->company_id === (int) $invoice->company_id;
    }

    private function hasBillingPermission(User $user, string $permission, ?Invoice $invoice = null): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $companyId = $invoice?->company_id ?? $user->company_id;

        if ($companyId === null) {
            return false;
        }

        return $this->permissionRegistry->userHasAny($user, [$permission], (int) $companyId);
    }

    private function supplierOwnsInvoice(User $user, Invoice $invoice): bool
    {
        if (! $this->isSupplierContext($user)) {
            return false;
        }

        $supplierCompanyId = $this->supplierCompanyId($user);

        if ($supplierCompanyId === null) {
            return false;
        }

        $invoiceSupplierCompanyId = $this->invoiceSupplierCompanyId($invoice);

        if ($invoiceSupplierCompanyId === null) {
            return false;
        }

        return (int) $invoiceSupplierCompanyId === (int) $supplierCompanyId;
    }

    private function supplierCompanyId(User $user): ?int
    {
        $personaCompanyId = ActivePersonaContext::supplierCompanyId();

        if ($personaCompanyId !== null) {
            return (int) $personaCompanyId;
        }

        if ($user->company_id !== null && $this->isSupplierRole($user)) {
            return (int) $user->company_id;
        }

        return null;
    }

    private function invoiceSupplierCompanyId(Invoice $invoice): ?int
    {
        if ($invoice->supplier_company_id !== null) {
            return (int) $invoice->supplier_company_id;
        }

        if (! $invoice->relationLoaded('purchaseOrder')) {
            $invoice->load('purchaseOrder.supplier', 'purchaseOrder.quote.supplier');
        }

        $purchaseOrder = $invoice->purchaseOrder;

        if ($purchaseOrder === null) {
            return null;
        }

        $supplierCompanyId = $purchaseOrder->supplier?->company_id
            ?? $purchaseOrder->quote?->supplier?->company_id;

        return $supplierCompanyId !== null ? (int) $supplierCompanyId : null;
    }

    private function isSupplierContext(User $user): bool
    {
        if (ActivePersonaContext::isSupplier()) {
            return true;
        }

        return $this->isSupplierRole($user);
    }

    private function isSupplierRole(User $user): bool
    {
        if (! is_string($user->role)) {
            return false;
        }

        if (in_array($user->role, self::SUPPLIER_ROLES, true)) {
            return true;
        }

        return str_starts_with($user->role, 'supplier_');
    }
}
