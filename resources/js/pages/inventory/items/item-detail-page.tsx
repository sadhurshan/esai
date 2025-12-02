import { useCallback, useEffect, useMemo } from 'react';
import { Controller, useForm, useWatch } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Boxes, Download, FileWarning, Save } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { EmptyState } from '@/components/empty-state';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { FileDropzone } from '@/components/file-dropzone';
import { ItemStatusChip } from '@/components/inventory/item-status-chip';
import { StockBadge } from '@/components/inventory/stock-badge';
import { ReorderEditor } from '@/components/inventory/reorder-editor';
import { LocationSelect } from '@/components/inventory/location-select';
import { useItem } from '@/hooks/api/inventory/use-item';
import { useUpdateItem } from '@/hooks/api/inventory/use-update-item';
import { useLocations } from '@/hooks/api/inventory/use-locations';
import { useMovements } from '@/hooks/api/inventory/use-movements';
import { useUploadDocument } from '@/hooks/api/documents/use-upload-document';
import { queryKeys } from '@/lib/queryKeys';

const detailSchema = z.object({
    sku: z.string().min(1, 'SKU is required'),
    name: z.string().min(1, 'Name is required'),
    uom: z.string().min(1, 'Default UoM is required'),
    category: z.string().optional().nullable(),
    description: z.string().optional().nullable(),
    minStock: z.number().min(0).nullable().optional(),
    reorderQty: z.number().min(0).nullable().optional(),
    leadTimeDays: z.number().min(0).nullable().optional(),
    defaultLocationId: z.string().optional().nullable(),
    active: z.boolean(),
});

type ItemDetailFormValues = z.infer<typeof detailSchema>;

export function ItemDetailPage() {
    const { itemId } = useParams<{ itemId: string }>();
    const navigate = useNavigate();
    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const inventoryEnabled = hasFeature('inventory_enabled');
    const { formatDate } = useFormatting();

    const itemQuery = useItem(itemId ?? '', { enabled: Boolean(itemId) });
    const updateItemMutation = useUpdateItem();
    const locationsQuery = useLocations({ perPage: 200, enabled: inventoryEnabled });
    const movementQuery = useMovements({ perPage: 10, itemId: itemId ?? undefined });
    const uploadDocumentMutation = useUploadDocument();

    const form = useForm<ItemDetailFormValues>({
        resolver: zodResolver(detailSchema),
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

    useEffect(() => {
        if (!itemQuery.data) {
            return;
        }
        const detail = itemQuery.data;
        form.reset({
            sku: detail.sku,
            name: detail.name,
            uom: detail.defaultUom,
            category: detail.category ?? '',
            description: detail.description ?? '',
            minStock: detail.reorderRule.minStock,
            reorderQty: detail.reorderRule.reorderQty,
            leadTimeDays: detail.reorderRule.leadTimeDays,
            defaultLocationId: detail.stockByLocation[0]?.id ?? null,
            active: detail.active,
        });
    }, [form, itemQuery.data]);

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

    const reorderErrors = useMemo(
        () => ({
            minStock: form.formState.errors.minStock?.message,
            reorderQty: form.formState.errors.reorderQty?.message,
            leadTimeDays: form.formState.errors.leadTimeDays?.message,
        }),
        [form.formState.errors.minStock, form.formState.errors.reorderQty, form.formState.errors.leadTimeDays],
    );

    const handleAttachmentsSelected = useCallback(
        async (files: File[]) => {
            if (!itemId || files.length === 0) {
                return;
            }

            for (const file of files) {
                try {
                    await uploadDocumentMutation.mutateAsync({
                        entity: 'part',
                        entityId: itemId,
                        kind: 'part',
                        category: 'technical',
                        file,
                        invalidateKey: queryKeys.inventory.item(itemId),
                    });
                } catch {
                    // Stop on first failure; hook already surfaces a toast message.
                    break;
                }
            }
        },
        [itemId, uploadDocumentMutation],
    );

    const handleSubmit = form.handleSubmit(async (values) => {
        if (!itemId) {
            return;
        }
        await updateItemMutation.mutateAsync({
            id: itemId,
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
        });
    });

    if (featureFlagsLoaded && !inventoryEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Inventory · Item detail</title>
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

    if (itemQuery.isLoading) {
        return (
            <div className="flex flex-1 items-center justify-center">
                <Spinner className="h-10 w-10" />
            </div>
        );
    }

    if (!itemQuery.data) {
        return (
            <EmptyState
                title="Item not found"
                description="The requested SKU could not be located."
                icon={<FileWarning className="h-12 w-12 text-muted-foreground" />}
                ctaLabel="Back to items"
                ctaProps={{ onClick: () => navigate('/app/inventory/items') }}
            />
        );
    }

    const item = itemQuery.data;
    const movements = movementQuery.data?.items ?? [];
    const locations = locationsQuery.data?.items ?? [];
    const canManageAttachments = inventoryEnabled;

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>{`${item.sku} · Inventory`}</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-border/70 bg-background/80 p-4">
                <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">SKU</p>
                    <h1 className="text-2xl font-semibold text-foreground">{item.sku}</h1>
                    <p className="text-sm text-muted-foreground">{item.name}</p>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <StockBadge onHand={item.onHand} minStock={item.reorderRule.minStock ?? undefined} uom={item.defaultUom} />
                    <ItemStatusChip status={item.status} />
                </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle>Overview</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="sku">SKU</Label>
                            <Input id="sku" {...form.register('sku')} />
                            {form.formState.errors.sku ? (
                                <p className="text-xs text-destructive">{form.formState.errors.sku.message}</p>
                            ) : null}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="name">Name</Label>
                            <Input id="name" {...form.register('name')} />
                            {form.formState.errors.name ? (
                                <p className="text-xs text-destructive">{form.formState.errors.name.message}</p>
                            ) : null}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="uom">Default UoM</Label>
                            <Input id="uom" {...form.register('uom')} />
                            {form.formState.errors.uom ? (
                                <p className="text-xs text-destructive">{form.formState.errors.uom.message}</p>
                            ) : null}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="category">Category</Label>
                            <Input id="category" {...form.register('category')} />
                            {form.formState.errors.category ? (
                                <p className="text-xs text-destructive">{form.formState.errors.category.message}</p>
                            ) : null}
                        </div>
                        <div className="md:col-span-2 space-y-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea id="description" rows={4} {...form.register('description')} />
                            {form.formState.errors.description ? (
                                <p className="text-xs text-destructive">{form.formState.errors.description.message}</p>
                            ) : null}
                        </div>
                        <div className="md:col-span-2 space-y-2">
                            <Label>Primary location</Label>
                            <Controller
                                control={form.control}
                                name="defaultLocationId"
                                render={({ field }) => (
                                    <LocationSelect
                                        options={locations}
                                        loading={locationsQuery.isLoading}
                                        value={field.value ?? null}
                                        allowUnassigned
                                        onChange={(value) => field.onChange(value ?? null)}
                                    />
                                )}
                            />
                        </div>
                        <div className="col-span-full flex items-center gap-2 pt-2">
                            <Controller
                                control={form.control}
                                name="active"
                                render={({ field }) => (
                                    <Checkbox
                                        checked={field.value}
                                        onCheckedChange={(checked) => field.onChange(checked === true)}
                                    />
                                )}
                            />
                            <div>
                                <p className="text-sm font-medium">Active item</p>
                                <p className="text-xs text-muted-foreground">Inactive SKUs stay visible but cannot be used on new orders.</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Tabs defaultValue="stock" className="w-full">
                    <TabsList>
                        <TabsTrigger value="stock">Stock by location</TabsTrigger>
                        <TabsTrigger value="reorder">Reorder rules</TabsTrigger>
                        <TabsTrigger value="attachments">Attachments</TabsTrigger>
                        <TabsTrigger value="movements">Recent movements</TabsTrigger>
                    </TabsList>
                    <TabsContent value="stock">
                        <Card className="border-border/70">
                            <CardHeader>
                                <CardTitle>Per-location balances</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="text-left text-xs uppercase tracking-wide text-muted-foreground">
                                                <th className="px-2 py-2">Location</th>
                                                <th className="px-2 py-2">Site</th>
                                                <th className="px-2 py-2">On-hand</th>
                                                <th className="px-2 py-2">Reserved</th>
                                                <th className="px-2 py-2">Available</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {item.stockByLocation.length === 0 ? (
                                                <tr>
                                                    <td colSpan={5} className="px-2 py-6 text-center text-muted-foreground">
                                                        No balances yet.
                                                    </td>
                                                </tr>
                                            ) : (
                                                item.stockByLocation.map((location) => (
                                                    <tr key={location.id} className="border-t border-border/60">
                                                        <td className="px-2 py-2 font-medium">{location.name}</td>
                                                        <td className="px-2 py-2">{location.siteName ?? '—'}</td>
                                                        <td className="px-2 py-2">{location.onHand ?? 0}</td>
                                                        <td className="px-2 py-2">{location.reserved ?? 0}</td>
                                                        <td className="px-2 py-2">{location.available ?? location.onHand ?? 0}</td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="reorder">
                        <Card className="border-border/70">
                            <CardHeader>
                                <CardTitle>Safety stock</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ReorderEditor
                                    value={reorderValue}
                                    onChange={(next) => {
                                        form.setValue('minStock', next.minStock ?? null, { shouldDirty: true });
                                        form.setValue('reorderQty', next.reorderQty ?? null, { shouldDirty: true });
                                        form.setValue('leadTimeDays', next.leadTimeDays ?? null, { shouldDirty: true });
                                    }}
                                    errors={reorderErrors}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="attachments">
                        <Card className="border-border/70">
                            <CardHeader>
                                <CardTitle>Files</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {canManageAttachments ? (
                                    <FileDropzone
                                        label="Drop documents to attach"
                                        description="PDF, CAD, and images up to 50 MB each."
                                        multiple
                                        disabled={uploadDocumentMutation.isPending}
                                        onFilesSelected={handleAttachmentsSelected}
                                    />
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Attachments are read-only for your current plan.
                                        {/* TODO: clarify with spec whether inventory attachments require a dedicated feature flag. */}
                                    </p>
                                )}

                                {uploadDocumentMutation.isPending ? (
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Spinner className="h-4 w-4" />
                                        <span>Uploading…</span>
                                    </div>
                                ) : null}

                                {item.attachments.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No attachments yet.</p>
                                ) : (
                                    <div className="space-y-3">
                                        {item.attachments.map((attachment) => (
                                            <div
                                                key={attachment.id}
                                                className="flex items-center justify-between gap-3 rounded-md border border-border/60 px-3 py-2"
                                            >
                                                <div>
                                                    <p className="text-sm font-medium">{attachment.filename}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatFileSize(attachment.sizeBytes)} · {attachment.mime ?? 'application/octet-stream'}
                                                        {attachment.createdAt
                                                            ? ` · Added ${formatDate(attachment.createdAt, {
                                                                  dateStyle: 'medium',
                                                                  timeStyle: 'short',
                                                              })}`
                                                            : ''}
                                                    </p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                    disabled={!attachment.downloadUrl}
                                                >
                                                    <a
                                                        href={attachment.downloadUrl ?? '#'}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        aria-label={`Download ${attachment.filename}`}
                                                    >
                                                        <Download className="mr-2 h-4 w-4" />
                                                        Download
                                                    </a>
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="movements">
                        <Card className="border-border/70">
                            <CardHeader>
                                <CardTitle>Recent movements</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {movements.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No movements recorded for this item.</p>
                                ) : (
                                    movements.map((movement) => (
                                        <div
                                            key={movement.id}
                                            className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border/60 px-3 py-2"
                                        >
                                            <div>
                                                <p className="text-sm font-medium">{movement.movementNumber}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatMovementType(movement.type)} ·{' '}
                                                    {formatDate(movement.movedAt, {
                                                        dateStyle: 'medium',
                                                        timeStyle: 'short',
                                                    })}
                                                </p>
                                            </div>
                                            <Button asChild size="sm" variant="outline">
                                                <Link to={`/app/inventory/movements/${movement.id}`}>View</Link>
                                            </Button>
                                        </div>
                                    ))
                                )}
                                {/* TODO: add cursor pagination controls when movement API exposes cursors */}
                                <div className="text-right">
                                    <Button asChild variant="ghost" size="sm">
                                        <Link to={`/app/inventory/movements?item=${itemId}`}>Open movement log</Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>

                <div className="flex items-center justify-end gap-3">
                    <Button type="button" variant="ghost" onClick={() => navigate('/app/inventory/items')}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={updateItemMutation.isPending}>
                        <Save className="mr-2 h-4 w-4" /> Save changes
                    </Button>
                </div>
            </form>
        </div>
    );
}

function formatFileSize(bytes?: number | null): string {
    if (!bytes || Number.isNaN(bytes)) {
        return '—';
    }
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unitIndex = 0;
    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }
    return `${value.toFixed(1)} ${units[unitIndex]}`;
}

function formatMovementType(type: string): string {
    return type
        .split('_')
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1).toLowerCase())
        .join(' ');
}

