import { useMemo, useState } from 'react';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { useFormatting } from '@/contexts/formatting-context';
import type { AdminPlansUpdateRequest, Plan } from '@/sdk';

const EMPTY_PLANS: Plan[] = [];

const featureCatalog = [
    {
        key: 'priceUsd',
        label: 'Monthly price',
        type: 'currency',
        description: 'Amount billed per tenant per month (USD).',
        min: 0,
        step: 10,
    },
    {
        key: 'rfqsPerMonth',
        label: 'RFQs per month',
        type: 'number',
        description: 'Monthly RFQ allotment. Leave blank for unlimited.',
        min: 0,
        step: 10,
    },
    {
        key: 'usersMax',
        label: 'Seats included',
        type: 'number',
        description: 'Maximum active user seats.',
        min: 0,
        step: 1,
    },
    {
        key: 'analyticsEnabled',
        label: 'Analytics workspace',
        type: 'boolean',
        description: 'Access to dashboards and exports.',
    },
    {
        key: 'inventoryEnabled',
        label: 'Inventory module',
        type: 'boolean',
        description: 'Warehouse and stock tracking.',
    },
    {
        key: 'multiCurrencyEnabled',
        label: 'Multi-currency',
        type: 'boolean',
        description: 'Allow RFQs, POs, and invoices in multiple currencies.',
    },
    {
        key: 'taxEngineEnabled',
        label: 'Tax engine',
        type: 'boolean',
        description: 'Automated indirect tax handling.',
    },
    {
        key: 'localizationEnabled',
        label: 'Localization',
        type: 'boolean',
        description: 'Localized UI + units of measure.',
    },
    {
        key: 'exportsEnabled',
        label: 'Bulk exports',
        type: 'boolean',
        description: 'CSV exports for RFQs, suppliers, and orders.',
    },
] as const;

type FeatureKey = (typeof featureCatalog)[number]['key'];

type DraftState = Record<
    number,
    Partial<Record<FeatureKey, number | boolean | undefined>>
>;

export interface FeatureMatrixEditorProps {
    plans?: Plan[];
    isLoading?: boolean;
    savingPlanId?: number | null;
    onSavePlan: (
        planId: number,
        payload: AdminPlansUpdateRequest,
    ) => Promise<void> | void;
}

export function FeatureMatrixEditor({
    plans,
    isLoading = false,
    savingPlanId,
    onSavePlan,
}: FeatureMatrixEditorProps) {
    const [drafts, setDrafts] = useState<DraftState>({});
    const { formatDate } = useFormatting();

    const planList = plans ?? EMPTY_PLANS;

    const dirtyMap = useMemo(() => {
        return planList.reduce<Record<number, boolean>>((acc, plan) => {
            acc[plan.id] = featureCatalog.some((feature) => {
                const current = getDraftValue(plan, drafts, feature.key);
                const original = plan[feature.key as keyof Plan];
                return normalizeValue(current) !== normalizeValue(original);
            });
            return acc;
        }, {});
    }, [planList, drafts]);

    if (isLoading) {
        return <LoadingMatrix />;
    }

    if (!planList.length) {
        return (
            <Alert>
                <AlertDescription>
                    No plans available. Sync billing plans to continue.
                </AlertDescription>
            </Alert>
        );
    }

    const handleValueChange = (
        planId: number,
        key: FeatureKey,
        value: number | boolean | undefined,
    ) => {
        setDrafts((prev) => ({
            ...prev,
            [planId]: {
                ...(prev[planId] ?? {}),
                [key]: value,
            },
        }));
    };

    const handleSave = async (plan: Plan) => {
        const payload: AdminPlansUpdateRequest = {};
        featureCatalog.forEach((feature) => {
            const nextValue = getDraftValue(plan, drafts, feature.key);
            const originalValue = plan[feature.key as keyof Plan];
            if (normalizeValue(nextValue) !== normalizeValue(originalValue)) {
                (payload as Record<string, unknown>)[feature.key] = nextValue;
            }
        });

        if (Object.keys(payload).length === 0) {
            return;
        }

        await onSavePlan(plan.id, payload);
    };

    return (
        <div className="space-y-4">
            <div className="overflow-x-auto rounded-xl border">
                <table className="min-w-full divide-y divide-muted/60 text-sm">
                    <thead className="bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-3">Feature</th>
                            {planList.map((plan) => (
                                <th key={plan.id} className="px-4 py-3">
                                    <div className="font-semibold text-foreground">
                                        {plan.name}
                                    </div>
                                    <CardDescription>
                                        Code: {plan.code}
                                    </CardDescription>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-muted">
                        {featureCatalog.map((feature) => (
                            <tr key={feature.key}>
                                <td className="px-4 py-4 align-top">
                                    <div className="font-medium text-foreground">
                                        {feature.label}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {feature.description}
                                    </p>
                                </td>
                                {planList.map((plan) => {
                                    const value = getDraftValue(
                                        plan,
                                        drafts,
                                        feature.key,
                                    );
                                    const saving = savingPlanId === plan.id;
                                    const numericValue =
                                        typeof value === 'number' &&
                                        !Number.isNaN(value)
                                            ? value
                                            : '';

                                    return (
                                        <td
                                            key={`${feature.key}-${plan.id}`}
                                            className="px-4 py-4"
                                        >
                                            {feature.type === 'boolean' ? (
                                                <label className="inline-flex items-center gap-2 text-sm">
                                                    <Checkbox
                                                        checked={!!value}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            handleValueChange(
                                                                plan.id,
                                                                feature.key,
                                                                Boolean(
                                                                    checked,
                                                                ),
                                                            )
                                                        }
                                                        disabled={saving}
                                                    />
                                                    <span>
                                                        {value
                                                            ? 'Enabled'
                                                            : 'Disabled'}
                                                    </span>
                                                </label>
                                            ) : (
                                                <Input
                                                    type="number"
                                                    min={feature.min}
                                                    step={feature.step}
                                                    value={numericValue}
                                                    onChange={(event) => {
                                                        const next =
                                                            event.target.value;
                                                        handleValueChange(
                                                            plan.id,
                                                            feature.key,
                                                            next === ''
                                                                ? undefined
                                                                : Number(next),
                                                        );
                                                    }}
                                                    disabled={saving}
                                                    placeholder={
                                                        feature.type ===
                                                        'currency'
                                                            ? '0.00'
                                                            : 'Unlimited'
                                                    }
                                                />
                                            )}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
                {planList.map((plan) => {
                    const dirty = dirtyMap[plan.id];
                    const saving = savingPlanId === plan.id;
                    return (
                        <Card key={`plan-actions-${plan.id}`}>
                            <CardHeader>
                                <CardTitle>{plan.name}</CardTitle>
                                <CardDescription>
                                    Last updated{' '}
                                    {plan.updatedAt
                                        ? formatDate(plan.updatedAt, {
                                              dateStyle: 'medium',
                                              timeStyle: 'short',
                                          })
                                        : 'recently'}
                                    .
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div className="text-sm text-muted-foreground">
                                    {dirty
                                        ? 'Unsaved changes'
                                        : 'All changes synced'}
                                </div>
                                <Button
                                    type="button"
                                    disabled={!dirty || saving}
                                    onClick={() => handleSave(plan)}
                                >
                                    {saving ? 'Saving...' : 'Save plan'}
                                </Button>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </div>
    );
}

function getDraftValue(plan: Plan, drafts: DraftState, key: FeatureKey) {
    const draft = drafts[plan.id]?.[key];
    if (draft !== undefined) {
        return draft;
    }
    return plan[key as keyof Plan] as number | boolean | undefined;
}

function normalizeValue(
    value: number | boolean | undefined | Plan[keyof Plan],
) {
    if (typeof value === 'number' && Number.isNaN(value)) {
        return undefined;
    }
    return value;
}

function LoadingMatrix() {
    return (
        <div className="space-y-3 rounded-xl border p-4">
            <Skeleton className="h-6 w-48" />
            <div className="space-y-2">
                {Array.from({ length: 3 }).map((_, index) => (
                    <div key={index} className="grid gap-3 md:grid-cols-3">
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-10 w-full" />
                    </div>
                ))}
            </div>
        </div>
    );
}
