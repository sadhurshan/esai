import { Controller, useForm, useWatch } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { Helmet } from 'react-helmet-async';
import { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Boxes, Save } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useAuth } from '@/contexts/auth-context';
import { ReorderEditor } from '@/components/inventory/reorder-editor';
import { LocationSelect } from '@/components/inventory/location-select';
import { useCreateItem } from '@/hooks/api/inventory/use-create-item';
import { useLocations } from '@/hooks/api/inventory/use-locations';
import { EmptyState } from '@/components/empty-state';

const itemSchema = z.object({
    sku: z.string().min(1, 'SKU is required'),
    name: z.string().min(1, 'Name is required'),
    uom: z.string().min(1, 'Default UoM is required'),
    category: z.string().max(120).optional().nullable(),
    description: z.string().max(500).optional().nullable(),
    minStock: z.number().min(0).nullable().optional(),
    reorderQty: z.number().min(0).nullable().optional(),
    leadTimeDays: z.number().min(0).nullable().optional(),
    defaultLocationId: z.string().optional().nullable(),
    active: z.boolean().default(true),
});

type ItemFormValues = z.infer<typeof itemSchema>;

export function ItemCreatePage() {
    const navigate = useNavigate();
    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const inventoryEnabled = hasFeature('inventory_enabled');

    const form = useForm<ItemFormValues>({
        resolver: zodResolver(itemSchema),
        defaultValues: {
            sku: '',
            name: '',
            uom: '',
            category: '',
            description: '',
            minStock: null,
            reorderQty: null,
            leadTimeDays: null,
            defaultLocationId: null,
            active: true,
        },
    });
    form.register('minStock');
    form.register('reorderQty');
    form.register('leadTimeDays');

    const createItemMutation = useCreateItem();
    const locationsQuery = useLocations({ perPage: 100, enabled: inventoryEnabled });
    const locations = locationsQuery.data?.items ?? [];

    const reorderErrors = useMemo(
        () => ({
            minStock: form.formState.errors.minStock?.message,
            reorderQty: form.formState.errors.reorderQty?.message,
            leadTimeDays: form.formState.errors.leadTimeDays?.message,
        }),
        [form.formState.errors.minStock, form.formState.errors.reorderQty, form.formState.errors.leadTimeDays],
    );

    const handleSubmit = form.handleSubmit(async (values) => {
        const payload = {
            sku: values.sku.trim(),
            name: values.name.trim(),
            uom: values.uom.trim(),
            category: values.category?.trim() || undefined,
            description: values.description?.trim() || undefined,
            minStock: values.minStock ?? undefined,
            reorderQty: values.reorderQty ?? undefined,
            leadTimeDays: values.leadTimeDays ?? undefined,
            defaultLocationId: values.defaultLocationId ?? undefined,
            active: values.active,
        };

        const created = await createItemMutation.mutateAsync(payload);
        if (created.id) {
            navigate(`/app/inventory/items/${created.id}`);
        } else {
            navigate('/app/inventory/items');
        }
    });

    const watchedMinStock = useWatch({ control: form.control, name: 'minStock' });
    const watchedReorderQty = useWatch({ control: form.control, name: 'reorderQty' });
    const watchedLeadTimeDays = useWatch({ control: form.control, name: 'leadTimeDays' });

    const reorderValue = useMemo(
        () => ({
            minStock: watchedMinStock,
            reorderQty: watchedReorderQty,
            leadTimeDays: watchedLeadTimeDays,
        }),
        [watchedLeadTimeDays, watchedMinStock, watchedReorderQty],
    );

    if (featureFlagsLoaded && !inventoryEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Inventory · Create item</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Inventory unavailable"
                    description="Upgrade your plan to manage items and movements."
                    icon={<Boxes className="h-12 w-12 text-muted-foreground" />}
                    ctaLabel="View plans"
                    ctaProps={{ onClick: () => navigate('/app/settings/billing') }}
                />
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Inventory · Create item</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <form onSubmit={handleSubmit} className="space-y-6">
                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle>Item details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="sku">SKU</Label>
                            <Input id="sku" placeholder="E.g. SKU-1001" {...form.register('sku')} />
                            {form.formState.errors.sku ? (
                                <p className="text-xs text-destructive">{form.formState.errors.sku.message}</p>
                            ) : null}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="name">Name</Label>
                            <Input id="name" placeholder="Item name" {...form.register('name')} />
                            {form.formState.errors.name ? (
                                <p className="text-xs text-destructive">{form.formState.errors.name.message}</p>
                            ) : null}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="uom">Default UoM</Label>
                            <Input id="uom" placeholder="EA" {...form.register('uom')} />
                            {form.formState.errors.uom ? (
                                <p className="text-xs text-destructive">{form.formState.errors.uom.message}</p>
                            ) : null}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="category">Category</Label>
                            <Input id="category" placeholder="Optional" {...form.register('category')} />
                            {form.formState.errors.category ? (
                                <p className="text-xs text-destructive">{form.formState.errors.category.message}</p>
                            ) : null}
                        </div>
                        <div className="md:col-span-2 space-y-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea id="description" rows={4} placeholder="Optional" {...form.register('description')} />
                            {form.formState.errors.description ? (
                                <p className="text-xs text-destructive">{form.formState.errors.description.message}</p>
                            ) : null}
                        </div>
                        <div className="md:col-span-2 space-y-2">
                            <Label>Default receiving location</Label>
                            <Controller
                                control={form.control}
                                name="defaultLocationId"
                                render={({ field }) => (
                                    <LocationSelect
                                        options={locations}
                                        loading={locationsQuery.isLoading}
                                        allowUnassigned
                                        value={field.value ?? null}
                                        onChange={(value) => field.onChange(value ?? null)}
                                        placeholder="Select a site or bin"
                                    />
                                )}
                            />
                            {form.formState.errors.defaultLocationId ? (
                                <p className="text-xs text-destructive">
                                    {form.formState.errors.defaultLocationId.message}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex items-center gap-2">
                            <Controller
                                control={form.control}
                                name="active"
                                render={({ field }) => (
                                    <Checkbox checked={field.value} onCheckedChange={(checked) => field.onChange(Boolean(checked))} />
                                )}
                            />
                            <Label className="font-normal text-sm">Item is active</Label>
                        </div>
                    </CardContent>
                </Card>

                <ReorderEditor
                    value={reorderValue}
                    onChange={(next) => {
                        form.setValue('minStock', next.minStock ?? null, { shouldDirty: true });
                        form.setValue('reorderQty', next.reorderQty ?? null, { shouldDirty: true });
                        form.setValue('leadTimeDays', next.leadTimeDays ?? null, { shouldDirty: true });
                    }}
                    errors={reorderErrors}
                />

                <div className="flex items-center justify-end gap-3">
                    <Button type="button" variant="ghost" onClick={() => navigate('/app/inventory/items')}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={createItemMutation.isPending}>
                        <Save className="mr-2 h-4 w-4" /> Save item
                    </Button>
                </div>
            </form>
        </div>
    );
}
