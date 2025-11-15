import { useEffect, useMemo } from 'react';
import { Helmet } from 'react-helmet-async';
import { useNavigate } from 'react-router-dom';
import { Controller, useForm, useWatch } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { CalendarClock, Loader2, ShieldAlert } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useAuth } from '@/contexts/auth-context';
import { MovementLineEditor } from '@/components/inventory/movement-line-editor';
import { useItems } from '@/hooks/api/inventory/use-items';
import { useLocations } from '@/hooks/api/inventory/use-locations';
import { useCreateMovement } from '@/hooks/api/inventory/use-create-movement';
import type { MovementItemOption } from '@/components/inventory/movement-line-editor';
import type { MovementType } from '@/sdk';
import { EmptyState } from '@/components/empty-state';
import { Boxes } from 'lucide-react';
import { publishToast } from '@/components/ui/use-toast';
import { validateMovementStock } from '@/lib/inventory-stock-validations';
import type { InventoryItemSummary } from '@/types/inventory';

const movementSchema = z.object({
    type: z.enum(['RECEIPT', 'ISSUE', 'TRANSFER', 'ADJUST']),
    movedAt: z.string().min(1, 'Movement date is required'),
    referenceSource: z.enum(['PO', 'SO', 'MANUAL']).optional().or(z.literal('')).nullable(),
    referenceId: z.string().optional().nullable(),
    notes: z.string().max(500, 'Notes must be 500 characters or fewer').optional().nullable(),
    lines: z
        .array(
            z.object({
                itemId: z.string().min(1, 'Item is required'),
                qty: z.number().positive('Quantity must be positive'),
                uom: z.string().optional().nullable(),
                fromLocationId: z.string().optional().nullable(),
                toLocationId: z.string().optional().nullable(),
                reason: z.string().optional().nullable(),
            }),
        )
        .min(1, 'Add at least one line'),
});

type MovementFormValues = z.infer<typeof movementSchema>;

const REFERENCES = [
    { value: 'PO', label: 'Purchase order' },
    { value: 'SO', label: 'Sales order' },
    { value: 'MANUAL', label: 'Manual' },
];

function defaultDateTimeLocal(): string {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    return now.toISOString().slice(0, 16);
}

export function MovementCreatePage() {
    const navigate = useNavigate();
    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const inventoryEnabled = hasFeature('inventory_enabled');

    const form = useForm<MovementFormValues>({
        resolver: zodResolver(movementSchema),
        defaultValues: {
            type: 'RECEIPT',
            movedAt: defaultDateTimeLocal(),
            referenceSource: null,
            referenceId: null,
            notes: null,
            lines: [
                {
                    itemId: '',
                    qty: 1,
                    uom: '',
                    fromLocationId: null,
                    toLocationId: null,
                    reason: null,
                },
            ],
        },
    });

    const movementType = useWatch({ control: form.control, name: 'type' });

    const itemsQuery = useItems({ perPage: 100, status: 'active' });
    const locationsQuery = useLocations({ perPage: 200 });
    const createMovement = useCreateMovement();

    const itemOptions: MovementItemOption[] = useMemo(() => {
        return (itemsQuery.data?.items ?? []).map((item) => ({
            id: item.id,
            label: item.name,
            sku: item.sku,
            defaultUom: item.defaultUom,
        }));
    }, [itemsQuery.data?.items]);

    const itemSummaryMap = useMemo(() => {
        const entries = new Map<string, InventoryItemSummary>();
        (itemsQuery.data?.items ?? []).forEach((item) => {
            entries.set(item.id, item);
        });
        return entries;
    }, [itemsQuery.data?.items]);

    const locationOptions = useMemo(() => locationsQuery.data?.items ?? [], [locationsQuery.data?.items]);
    const locationMap = useMemo(() => new Map(locationOptions.map((location) => [location.id, location])), [locationOptions]);
    const defaultReceivingLocationId = useMemo(
        () => locationOptions.find((location) => location.isDefaultReceiving)?.id ?? null,
        [locationOptions],
    );
    const itemSummaryLookup = useMemo<Record<string, InventoryItemSummary>>(
        () => Object.fromEntries(itemSummaryMap),
        [itemSummaryMap],
    );

    useEffect(() => {
        if (!defaultReceivingLocationId) {
            return;
        }

        if (movementType !== 'RECEIPT' && movementType !== 'TRANSFER') {
            return;
        }

        const currentLines = form.getValues('lines');
        if (!Array.isArray(currentLines)) {
            return;
        }

        currentLines.forEach((line, index) => {
            if (!line?.toLocationId) {
                form.setValue(`lines.${index}.toLocationId`, defaultReceivingLocationId, { shouldDirty: false });
            }
        });
    }, [defaultReceivingLocationId, movementType, form]);

    const handleSubmit = form.handleSubmit(async (values) => {
        form.clearErrors('lines');

        const stockViolations = validateMovementStock({
            lines: values.lines,
            movementType,
            itemsById: itemSummaryMap,
            locationsById: locationMap,
        });

        if (stockViolations.length > 0) {
            stockViolations.forEach((violation) => {
                form.setError(`lines.${violation.index}.qty`, {
                    type: 'manual',
                    message: violation.message,
                });
            });

            publishToast({
                variant: 'destructive',
                title: 'Insufficient stock',
                description: 'One or more lines exceed the available on-hand quantity.',
            });
            return;
        }

        const movedAtIso = new Date(values.movedAt).toISOString();
        const payload = await createMovement.mutateAsync({
            type: values.type,
            movedAt: movedAtIso,
            lines: values.lines.map((line) => ({
                itemId: line.itemId,
                qty: line.qty,
                uom: line.uom ?? undefined,
                fromLocationId: line.fromLocationId ?? undefined,
                toLocationId: line.toLocationId ?? undefined,
                reason: line.reason ?? undefined,
            })),
            reference: values.referenceSource
                ? {
                      source: values.referenceSource,
                      id: values.referenceId || undefined,
                  }
                : undefined,
            notes: values.notes ?? undefined,
        });

        if (payload.id) {
            navigate(`/app/inventory/movements/${payload.id}`);
        } else {
            navigate('/app/inventory/movements');
        }
    });

    if (featureFlagsLoaded && !inventoryEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Inventory · Post movement</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Inventory unavailable"
                    description="Upgrade your plan to post stock receipts, issues, transfers, and adjustments."
                    icon={<Boxes className="h-12 w-12 text-muted-foreground" />}
                    ctaLabel="View plans"
                    ctaProps={{ onClick: () => navigate('/app/settings?tab=billing') }}
                />
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Inventory · Post movement</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <form onSubmit={handleSubmit} className="space-y-6">
                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle>Movement header</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Movement type</Label>
                            <Controller
                                name="type"
                                control={form.control}
                                render={({ field }) => (
                                    <Select value={field.value} onValueChange={field.onChange}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="RECEIPT">Receipt</SelectItem>
                                            <SelectItem value="ISSUE">Issue</SelectItem>
                                            <SelectItem value="TRANSFER">Transfer</SelectItem>
                                            <SelectItem value="ADJUST">Adjust</SelectItem>
                                        </SelectContent>
                                    </Select>
                                )}
                            />
                            {form.formState.errors.type ? (
                                <p className="text-xs text-destructive">{form.formState.errors.type.message}</p>
                            ) : null}
                        </div>
                        <div className="space-y-2">
                            <Label>Movement date</Label>
                            <Input type="datetime-local" {...form.register('movedAt')} />
                            {form.formState.errors.movedAt ? (
                                <p className="text-xs text-destructive">{form.formState.errors.movedAt.message}</p>
                            ) : (
                                <p className="text-xs text-muted-foreground">Local timezone. Converted to UTC on submission.</p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label>Reference type</Label>
                            <Controller
                                name="referenceSource"
                                control={form.control}
                                render={({ field }) => (
                                    <Select
                                        value={field.value ?? ''}
                                        onValueChange={(next) => field.onChange(next === '' ? null : next)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Optional" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">None</SelectItem>
                                            {REFERENCES.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Reference ID</Label>
                            <Input placeholder="PO-1001" {...form.register('referenceId')} />
                        </div>
                        <div className="md:col-span-2 space-y-2">
                            <Label>Notes</Label>
                            <Textarea rows={3} placeholder="Optional context" {...form.register('notes')} />
                            {form.formState.errors.notes ? (
                                <p className="text-xs text-destructive">{form.formState.errors.notes.message}</p>
                            ) : null}
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle>Movement lines</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <MovementLineEditor
                            form={form}
                            type={movementType as MovementType}
                            itemOptions={itemOptions}
                            locations={locationOptions}
                            disabled={createMovement.isPending}
                            itemSummaries={itemSummaryLookup}
                            defaultDestinationId={defaultReceivingLocationId}
                        />
                        {form.formState.errors.lines ? (
                            <p className="text-sm text-destructive">{form.formState.errors.lines.message as string}</p>
                        ) : null}
                        <div className="rounded-md border border-border/60 bg-muted/30 p-3 text-xs text-muted-foreground">
                            <div className="flex items-center gap-2 font-medium">
                                <ShieldAlert className="h-4 w-4" /> Posting adjustments may bypass negative-stock protections.
                            </div>
                            <p>Verify all quantities before submitting.</p>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex flex-wrap items-center justify-end gap-3">
                    <Button type="button" variant="ghost" onClick={() => navigate('/app/inventory/movements')}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={createMovement.isPending}>
                        {createMovement.isPending ? (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                            <CalendarClock className="mr-2 h-4 w-4" />
                        )}
                        {createMovement.isPending ? 'Posting…' : 'Post movement'}
                    </Button>
                </div>
            </form>
        </div>
    );
}
