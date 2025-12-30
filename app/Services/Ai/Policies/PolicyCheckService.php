<?php

namespace App\Services\Ai\Policies;

use App\Enums\RiskGrade;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PolicyCheckService
{
    private const CATEGORY_PERMISSION_MAP = [
        'purchase_order' => ['orders.write'],
        'invoice' => ['billing.write'],
        'payment' => ['finance.write'],
        'supplier' => ['suppliers.write'],
        'item' => ['inventory.write'],
        'rfq' => ['rfqs.write'],
    ];

    public function __construct(private readonly PermissionRegistry $permissions)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function evaluate(int $companyId, User $user, string $actionType, array $payload): PolicyDecision
    {
        $category = $this->detectCategory($actionType);
        $decision = new PolicyDecision($actionType, $category);
        $sanitizedPayload = $this->sanitizePayload($payload);

        $this->enforceCategoryPermission($decision, $companyId, $user, $category);
        $this->enforceHighValueThreshold($decision, $companyId, $user, $category, $sanitizedPayload);
        $this->enforceSupplierRisk($decision, $companyId, $category, $sanitizedPayload);

        return $decision;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sanitizePayload(array $payload): array
    {
        return array_filter($payload, static fn ($value) => $value !== null && $value !== '');
    }

    private function detectCategory(string $actionType): string
    {
        $normalized = Str::of($actionType)->lower()->value();

        if (str_contains($normalized, 'invoice')) {
            return 'invoice';
        }

        if (str_contains($normalized, 'payment')) {
            return 'payment';
        }

        if (str_contains($normalized, 'purchase_order') || str_contains($normalized, 'po')) {
            return 'purchase_order';
        }

        if (str_contains($normalized, 'supplier')) {
            return 'supplier';
        }

        if (str_contains($normalized, 'item')) {
            return 'item';
        }

        if (str_contains($normalized, 'rfq')) {
            return 'rfq';
        }

        return 'general';
    }

    private function enforceCategoryPermission(PolicyDecision $decision, int $companyId, User $user, string $category): void
    {
        $permissions = self::CATEGORY_PERMISSION_MAP[$category] ?? null;

        if ($permissions === null || $permissions === []) {
            return;
        }

        if ($this->permissions->userHasAny($user, $permissions, $companyId)) {
            return;
        }

        foreach ($permissions as $permission) {
            $decision->deny(
                sprintf('%s permission is required to run this action.', $permission),
                $this->approvalFromPermission($permission)
            );
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function enforceHighValueThreshold(PolicyDecision $decision, int $companyId, User $user, string $category, array $payload): void
    {
        $threshold = match ($category) {
            'purchase_order' => (float) config('policy.thresholds.purchase_order_high_value', 50000),
            'invoice' => (float) config('policy.thresholds.invoice_high_value', 25000),
            'payment' => (float) config('policy.thresholds.payment_high_value', 25000),
            default => null,
        };

        if ($threshold === null) {
            return;
        }

        $amount = $this->extractAmount($payload);

        if ($amount === null || $amount < $threshold) {
            return;
        }

        $financePermission = 'finance.write';
        $approval = $this->approvalFromPermission($financePermission, 'Finance approval');

        if (! $this->permissions->userHasAny($user, [$financePermission], $companyId)) {
            $decision->deny(
                sprintf('Finance approval required for totals above %s.', $this->formatAmount($threshold)),
                $approval,
                'Escalate to a finance approver or split the transaction below the policy limit.'
            );

            return;
        }

        $decision->requireApproval($approval);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function enforceSupplierRisk(PolicyDecision $decision, int $companyId, string $category, array $payload): void
    {
        if ($category !== 'supplier' && $this->extractSupplierId($payload) === null) {
            return;
        }

        $supplierId = $this->extractSupplierId($payload);
        $supplier = null;

        if ($supplierId !== null) {
            $supplier = Supplier::query()
                ->forCompany($companyId)
                ->with(['riskScore'])
                ->find($supplierId);
        }

        $supplierName = $supplier?->name ?? Arr::get($payload, 'supplier.name');
        $riskGrade = $supplier?->risk_grade?->value ?? strtolower((string) Arr::get($payload, 'supplier.risk_grade')) ?: null;

        $maxRiskGrade = strtolower((string) config('policy.supplier.max_risk_grade', RiskGrade::Medium->value));
        $gradeOrder = ['low' => 1, 'medium' => 2, 'high' => 3];

        $gradeBreached = $riskGrade !== null
            && isset($gradeOrder[$riskGrade], $gradeOrder[$maxRiskGrade])
            && $gradeOrder[$riskGrade] > $gradeOrder[$maxRiskGrade];

        $riskScore = $supplier?->riskScore?->overall_score;

        if ($riskScore === null) {
            $riskScore = $this->normalizeNumeric(Arr::get($payload, 'supplier.risk_score'));
        }

        $maxRiskIndex = (float) config('policy.supplier.max_risk_index', 0.25);
        $scoreBreached = $riskScore !== null && (1 - (float) $riskScore) > $maxRiskIndex;

        if (! $gradeBreached && ! $scoreBreached) {
            return;
        }

        $name = $supplierName ?? 'Supplier';

        $decision->deny(
            sprintf('%s is flagged as high risk and needs additional quality approval.', $name),
            [
                'type' => 'policy',
                'value' => 'quality.high_risk_supplier',
                'label' => 'Quality & compliance review',
            ],
            'Attach the latest audit or mitigation plan before moving forward.'
        );
    }

    private function extractSupplierId(array $payload): ?int
    {
        $candidate = Arr::get($payload, 'supplier_id')
            ?? Arr::get($payload, 'supplier.supplier_id')
            ?? Arr::get($payload, 'supplier.id');

        if ($candidate === null) {
            return null;
        }

        return is_numeric($candidate) ? max(1, (int) $candidate) : null;
    }

    private function approvalFromPermission(string $permission, ?string $label = null): array
    {
        return [
            'type' => 'permission',
            'value' => $permission,
            'label' => $label ?? Str::headline($permission),
        ];
    }

    private function extractAmount(array $payload): ?float
    {
        $keys = [
            'total',
            'total_value',
            'grand_total',
            'amount',
            'totals.total',
            'totals.grand_total',
            'summary.total',
        ];

        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            $numeric = $this->normalizeNumeric($value);

            if ($numeric !== null) {
                return $numeric;
            }
        }

        return null;
    }

    private function normalizeNumeric(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $clean = preg_replace('/[^0-9.\-]/', '', $value);

            if ($clean === '' || ! is_numeric($clean)) {
                return null;
            }

            return (float) $clean;
        }

        if (is_array($value)) {
            if (isset($value['amount']) && is_numeric($value['amount'])) {
                return (float) $value['amount'];
            }

            if (isset($value['value']) && is_numeric($value['value'])) {
                return (float) $value['value'];
            }
        }

        return null;
    }

    private function formatAmount(float $amount): string
    {
        return '$' . number_format($amount, 2, '.', ',');
    }
}
