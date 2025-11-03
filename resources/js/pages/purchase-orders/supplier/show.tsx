import { EmptyState, StatusBadge } from '@/components/app';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState, type ChangeEvent, type FormEvent } from 'react';

import { useAcknowledgePo } from '@/hooks/api/useAcknowledgePo';
import { useProposeChangeOrder } from '@/hooks/api/useProposeChangeOrder';
import { usePurchaseOrder } from '@/hooks/api/usePurchaseOrder';
import { ApiError } from '@/lib/api';
import { formatCurrencyUSD, formatDate } from '@/lib/format';
import { home, purchaseOrders as purchaseOrderRoutes } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import type { PurchaseOrderChangeOrder, PurchaseOrderDetail, PurchaseOrderLine } from '@/types/sourcing';

function parsePurchaseOrderId(url: string): number | null {
    const [path] = url.split('?');
    const segments = path.split('/').filter(Boolean);
    const purchaseOrdersIndex = segments.indexOf('purchase-orders');

    if (purchaseOrdersIndex === -1) {
        return null;
    }

    const potentialId = segments[purchaseOrdersIndex + 1];
    if (!potentialId) {
        return null;
    }

    const parsed = Number.parseInt(potentialId, 10);
    return Number.isNaN(parsed) ? null : parsed;
}

function calculateTotal(lines: PurchaseOrderLine[] = []): number {
    return lines.reduce((sum, line) => sum + line.quantity * line.unitPrice, 0);
}

function calculateExpectedDelivery(lines: PurchaseOrderLine[] = []): string | null {
    const dates = lines
        .map((line) => line.deliveryDate)
        .filter((value): value is string => Boolean(value));

    if (dates.length === 0) {
        return null;
    }

    const earliest = dates.sort((a, b) => new Date(a).getTime() - new Date(b).getTime())[0];
    return earliest ?? null;
}

function normalizeDateInput(value?: string | null): string {
    if (!value) {
        return '';
    }

    return value.split('T')[0] ?? value;
}

function getErrorMessage(error: unknown): string {
    if (error instanceof ApiError) {
        return error.message;
    }

    if (error instanceof Error) {
        return error.message;
    }

    return 'Unexpected error occurred.';
}

interface LineDraft {
    quantity: string;
    unitPrice: string;
    deliveryDate: string;
}

function createLineDraftMap(lines: PurchaseOrderLine[]): Record<number, LineDraft> {
    return lines.reduce<Record<number, LineDraft>>((acc, line) => {
        acc[line.id] = {
            quantity: String(line.quantity ?? ''),
            unitPrice: String(line.unitPrice ?? ''),
            deliveryDate: normalizeDateInput(line.deliveryDate),
        };
        return acc;
    }, {});
}

export default function SupplierPurchaseOrderShow() {
    const page = usePage();
    const pageProps = page.props as Record<string, unknown>;
    const derivedId = useMemo(() => {
        const pageId = pageProps?.id;

        if (typeof pageId === 'number') {
            return pageId;
        }

        return parsePurchaseOrderId(page.url);
    }, [pageProps, page.url]);

    const purchaseOrderId = derivedId ?? 0;
    const {
        data: purchaseOrder,
        isLoading,
        isError,
        error,
        refetch,
    } = usePurchaseOrder(purchaseOrderId);

    const acknowledgeMutation = useAcknowledgePo();
    const proposeMutation = useProposeChangeOrder();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Home', href: home().url },
        { title: 'Supplier Purchase Orders', href: purchaseOrderRoutes.supplier.index().url },
        {
            title: purchaseOrder?.poNumber ?? `PO ${purchaseOrderId}`,
            href: purchaseOrderRoutes.supplier.show({ id: purchaseOrderId || 0 }).url,
        },
    ];

    if (!purchaseOrderId) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Purchase Order" />
                <div className="flex flex-1 items-center justify-center px-4 py-6">
                    <EmptyState
                        title="Purchase order not found"
                        description="A valid purchase order identifier is required to load this page."
                    />
                </div>
            </AppLayout>
        );
    }

    if (isError) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Purchase Order" />
                <div className="flex flex-1 items-center justify-center px-4 py-6">
                    <EmptyState
                        title="Unable to load purchase order"
                        description={error?.message ?? 'Please try again.'}
                        ctaLabel="Retry"
                        ctaProps={{ onClick: () => refetch() }}
                    />
                </div>
            </AppLayout>
        );
    }

    if (!isLoading && !purchaseOrder) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Purchase Order" />
                <div className="flex flex-1 items-center justify-center px-4 py-6">
                    <EmptyState
                        title="Purchase order unavailable"
                        description="This purchase order could not be located or you do not have access."
                    />
                </div>
            </AppLayout>
        );
    }

    const contentKey = purchaseOrder
        ? `${purchaseOrder.id}-${purchaseOrder.revisionNo}-${purchaseOrder.updatedAt ?? 'na'}`
        : 'loading';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={purchaseOrder ? `PO ${purchaseOrder.poNumber}` : 'Purchase Order'} />
            <SupplierPurchaseOrderContent
                key={contentKey}
                purchaseOrder={purchaseOrder}
                isLoading={isLoading}
                acknowledgeMutation={acknowledgeMutation}
                proposeMutation={proposeMutation}
            />
        </AppLayout>
    );
}

type AcknowledgeMutation = ReturnType<typeof useAcknowledgePo>;
type ProposeMutation = ReturnType<typeof useProposeChangeOrder>;

interface SupplierPurchaseOrderContentProps {
    purchaseOrder: PurchaseOrderDetail | undefined;
    isLoading: boolean;
    acknowledgeMutation: AcknowledgeMutation;
    proposeMutation: ProposeMutation;
}

function SupplierPurchaseOrderContent({
    purchaseOrder,
    isLoading,
    acknowledgeMutation,
    proposeMutation,
}: SupplierPurchaseOrderContentProps) {
    const lines = useMemo(() => purchaseOrder?.lines ?? [], [purchaseOrder?.lines]);
    const changeOrders = useMemo<PurchaseOrderChangeOrder[]>(() => {
        const items = purchaseOrder?.changeOrders ?? [];
        return [...items].sort((a, b) => {
            const aTime = a.createdAt ? new Date(a.createdAt).getTime() : 0;
            const bTime = b.createdAt ? new Date(b.createdAt).getTime() : 0;
            return bTime - aTime;
        });
    }, [purchaseOrder?.changeOrders]);

    const totalValue = useMemo(() => calculateTotal(lines), [lines]);
    const expectedDelivery = useMemo(() => calculateExpectedDelivery(lines), [lines]);

    const [reason, setReason] = useState('');
    const [lineDrafts, setLineDrafts] = useState<Record<number, LineDraft>>(() => createLineDraftMap(lines));

    if (!purchaseOrder) {
        return (
            <div className="flex flex-1 flex-col gap-6 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="space-y-1">
                        <Skeleton className="h-8 w-56" />
                        <Skeleton className="h-5 w-40" />
                    </div>
                    <Skeleton className="h-6 w-24" />
                </div>

                <section className="grid gap-4 md:grid-cols-[3fr_1fr]">
                    <Card className="border-muted/60">
                        <CardHeader>
                            <CardTitle className="text-lg">Purchase Order Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            {Array.from({ length: 6 }).map((_, index) => (
                                <div key={`po-summary-skeleton-${index}`} className="space-y-2">
                                    <Skeleton className="h-3 w-24" />
                                    <Skeleton className="h-4 w-36" />
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card className="border-muted/60">
                        <CardHeader>
                            <CardTitle className="text-lg">Supplier Actions</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Skeleton className="h-4 w-full" />
                            <Skeleton className="h-9 w-full" />
                            <Skeleton className="h-9 w-full" />
                            <Skeleton className="h-5 w-40" />
                        </CardContent>
                    </Card>
                </section>

                <Card className="border-muted/60">
                    <CardHeader>
                        <CardTitle className="text-lg">Propose Change Order</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {Array.from({ length: 4 }).map((_, index) => (
                                <Skeleton key={`change-form-skeleton-${index}`} className="h-10 w-full" />
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-muted/60">
                    <CardHeader>
                        <CardTitle className="text-lg">Change Order History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {Array.from({ length: 3 }).map((_, index) => (
                                <Skeleton key={`change-history-skeleton-${index}`} className="h-12 w-full" />
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        );
    }

    const updateLineDraft = (
        lineId: number,
        field: keyof LineDraft,
        value: string,
    ): void => {
        setLineDrafts((prev) => ({
            ...prev,
            [lineId]: {
                quantity: prev[lineId]?.quantity ?? '',
                unitPrice: prev[lineId]?.unitPrice ?? '',
                deliveryDate: prev[lineId]?.deliveryDate ?? '',
                [field]: value,
            },
        }));
    };

    const handleAcknowledge = async (action: 'accept' | 'reject') => {
        try {
            await acknowledgeMutation.mutateAsync({
                purchaseOrderId: purchaseOrder.id,
                action,
            });

            publishToast({
                title: action === 'accept' ? 'Purchase order acknowledged' : 'Purchase order rejected',
                description:
                    action === 'accept'
                        ? 'The buyer has been notified of your acknowledgement.'
                        : 'The buyer has been notified of the rejection.',
                variant: 'success',
            });
        } catch (err) {
            publishToast({
                title: 'Unable to update purchase order',
                description: getErrorMessage(err),
                variant: 'destructive',
            });
        }
    };

    const handleProposeChangeOrder = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const trimmedReason = reason.trim();
        if (trimmedReason.length === 0) {
            publishToast({
                title: 'Reason required',
                description: 'Please describe why a change order is needed.',
                variant: 'destructive',
            });
            return;
        }

        const lineChanges = lines
            .map((line) => {
                const draft = lineDrafts[line.id];
                if (!draft) {
                    return null;
                }

                const change: Record<string, unknown> = { id: line.id };
                let hasChange = false;

                if (draft.quantity !== '') {
                    const parsedQuantity = Number.parseInt(draft.quantity, 10);
                    if (!Number.isNaN(parsedQuantity) && parsedQuantity !== line.quantity) {
                        change.quantity = parsedQuantity;
                        hasChange = true;
                    }
                }

                if (draft.unitPrice !== '') {
                    const parsedPrice = Number.parseFloat(draft.unitPrice);
                    if (!Number.isNaN(parsedPrice) && Math.abs(parsedPrice - line.unitPrice) > 0.0001) {
                        change.unit_price = Number(parsedPrice.toFixed(2));
                        hasChange = true;
                    }
                }

                const normalizedExistingDate = normalizeDateInput(line.deliveryDate);
                if (draft.deliveryDate !== normalizedExistingDate) {
                    change.delivery_date = draft.deliveryDate ? draft.deliveryDate : null;
                    hasChange = true;
                }

                return hasChange ? change : null;
            })
            .filter((value): value is Record<string, unknown> => value !== null);

        if (lineChanges.length === 0) {
            publishToast({
                title: 'No changes detected',
                description: 'Update a quantity, unit price, or delivery date before submitting.',
                variant: 'destructive',
            });
            return;
        }

        try {
            await proposeMutation.mutateAsync({
                purchaseOrderId: purchaseOrder.id,
                reason: trimmedReason,
                changes: {
                    lines: lineChanges,
                },
            });

            publishToast({
                title: 'Change order proposed',
                description: 'The buyer has been notified of your requested revisions.',
                variant: 'success',
            });

            setReason('');
            setLineDrafts(createLineDraftMap(lines));
        } catch (err) {
            publishToast({
                title: 'Unable to submit change order',
                description: getErrorMessage(err),
                variant: 'destructive',
            });
        }
    };

    const acknowledgementDisabled = purchaseOrder.status !== 'sent' || acknowledgeMutation.isPending;

    return (
        <div className="flex flex-1 flex-col gap-6 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="space-y-1">
                    {isLoading ? (
                        <>
                            <Skeleton className="h-8 w-56" />
                            <Skeleton className="h-5 w-40" />
                        </>
                    ) : (
                        <>
                            <h1 className="text-2xl font-semibold text-foreground">
                                Purchase Order {purchaseOrder.poNumber}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {purchaseOrder.rfqTitle ?? purchaseOrder.rfqNumber ?? 'Buyer project reference'}
                            </p>
                        </>
                    )}
                    </div>
                {isLoading ? (
                    <Skeleton className="h-6 w-24" />
                ) : (
                    <StatusBadge status={purchaseOrder.status} />
                )}
                </div>

            <section className="grid gap-4 md:grid-cols-[3fr_1fr]">
                    <Card className="border-muted/60">
                        <CardHeader>
                            <CardTitle className="text-lg">Purchase Order Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                        {isLoading ? (
                            Array.from({ length: 6 }).map((_, index) => (
                                <div key={`supplier-po-summary-skeleton-${index}`} className="space-y-2">
                                    <Skeleton className="h-3 w-24" />
                                    <Skeleton className="h-4 w-36" />
                                </div>
                            ))
                        ) : (
                            <>
                                <div>
                                    <p className="text-xs uppercase text-muted-foreground">PO Number</p>
                                    <p className="text-sm text-foreground">{purchaseOrder.poNumber}</p>
                                </div>
                                <div>
                                    <p className="text-xs uppercase text-muted-foreground">Buyer Reference</p>
                                    <p className="text-sm text-foreground">
                                        {purchaseOrder.rfqTitle ?? purchaseOrder.rfqNumber ?? 'Not provided'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs uppercase text-muted-foreground">Expected Delivery</p>
                                    <p className="text-sm text-foreground">{formatDate(expectedDelivery)}</p>
                                </div>
                                <div>
                                    <p className="text-xs uppercase text-muted-foreground">Total Value</p>
                                    <p className="text-sm text-foreground">{formatCurrencyUSD(totalValue)}</p>
                                </div>
                                <div>
                                    <p className="text-xs uppercase text-muted-foreground">Incoterm</p>
                                    <p className="text-sm text-foreground">
                                        {purchaseOrder.incoterm ?? 'Not specified'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs uppercase text-muted-foreground">Revision</p>
                                    <p className="text-sm text-foreground">Rev {purchaseOrder.revisionNo}</p>
                                </div>
                            </>
                        )}
                        </CardContent>
                    </Card>

                    <Card className="border-muted/60">
                        <CardHeader>
                            <CardTitle className="text-lg">Supplier Actions</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                Confirm the purchase order as issued or request updates through a change order. Acceptance locks pricing and delivery commitments.
                            </p>
                            <Button
                                size="sm"
                                className="w-full"
                                disabled={acknowledgementDisabled}
                                onClick={() => handleAcknowledge('accept')}
                            >
                                {acknowledgeMutation.isPending ? 'Processing…' : 'Acknowledge (Accept)'}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                className="w-full"
                                disabled={acknowledgementDisabled}
                                onClick={() => handleAcknowledge('reject')}
                            >
                                {acknowledgeMutation.isPending ? 'Processing…' : 'Reject Purchase Order'}
                            </Button>
                            <Button variant="ghost" size="sm" asChild className="w-full">
                                <Link href={purchaseOrderRoutes.supplier.index().url}>Back to list</Link>
                            </Button>
                            {purchaseOrder.status !== 'sent' ? (
                                <p className="text-xs text-muted-foreground">
                                    Actions unlock when the purchase order status is <span className="font-medium">Sent</span>.
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>
                </section>

                <Card className="border-muted/60">
                    <CardHeader>
                        <CardTitle className="text-lg">Propose Change Order</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {isLoading ? (
                            <div className="space-y-3">
                                {Array.from({ length: 4 }).map((_, index) => (
                                    <Skeleton key={`change-form-skeleton-${index}`} className="h-10 w-full" />
                                ))}
                            </div>
                        ) : (
                            <form onSubmit={handleProposeChangeOrder} className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="change-reason">Reason for change</Label>
                                    <Textarea
                                        id="change-reason"
                                        value={reason}
                                        onChange={(event: ChangeEvent<HTMLTextAreaElement>) =>
                                            setReason(event.target.value)
                                        }
                                        placeholder="Explain why pricing, quantities, or delivery dates require adjustment."
                                        rows={3}
                                        required
                                    />
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-muted/60 text-sm">
                                        <thead className="bg-muted/40">
                                            <tr className="text-left text-xs font-semibold uppercase text-muted-foreground">
                                                <th className="px-4 py-3">Line</th>
                                                <th className="px-4 py-3">Description</th>
                                                <th className="px-4 py-3">Quantity</th>
                                                <th className="px-4 py-3">Unit Price</th>
                                                <th className="px-4 py-3">Delivery Date</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-muted/40">
                                            {lines.map((line) => {
                                                const draft = lineDrafts[line.id] ?? {
                                                    quantity: '',
                                                    unitPrice: '',
                                                    deliveryDate: '',
                                                };

                                                return (
                                                    <tr key={line.id}>
                                                        <td className="px-4 py-3 font-medium text-foreground">{line.lineNo}</td>
                                                        <td className="px-4 py-3 text-foreground">{line.description}</td>
                                                        <td className="px-4 py-3 text-foreground">
                                                            <div className="space-y-1">
                                                                <Input
                                                                    type="number"
                                                                    min={0}
                                                                    step="1"
                                                                    value={draft.quantity}
                                                                    onChange={(event: ChangeEvent<HTMLInputElement>) =>
                                                                        updateLineDraft(line.id, 'quantity', event.target.value)
                                                                    }
                                                                    className="h-9"
                                                                />
                                                                <p className="text-xs text-muted-foreground">
                                                                    Current: {line.quantity.toLocaleString()}
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3 text-foreground">
                                                            <div className="space-y-1">
                                                                <Input
                                                                    type="number"
                                                                    step="0.01"
                                                                    min={0}
                                                                    value={draft.unitPrice}
                                                                    onChange={(event: ChangeEvent<HTMLInputElement>) =>
                                                                        updateLineDraft(line.id, 'unitPrice', event.target.value)
                                                                    }
                                                                    className="h-9"
                                                                />
                                                                <p className="text-xs text-muted-foreground">
                                                                    Current: {formatCurrencyUSD(line.unitPrice)}
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3 text-foreground">
                                                            <div className="space-y-1">
                                                                <Input
                                                                    type="date"
                                                                    value={draft.deliveryDate}
                                                                    onChange={(event: ChangeEvent<HTMLInputElement>) =>
                                                                        updateLineDraft(line.id, 'deliveryDate', event.target.value)
                                                                    }
                                                                    className="h-9"
                                                                />
                                                                <p className="text-xs text-muted-foreground">
                                                                    Current: {formatDate(line.deliveryDate)}
                                                                </p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                                <div className="flex flex-wrap items-center justify-end gap-3">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={proposeMutation.isPending}
                                    >
                                        {proposeMutation.isPending ? 'Submitting…' : 'Submit change order'}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </CardContent>
                </Card>

                <Card className="border-muted/60">
                    <CardHeader>
                        <CardTitle className="text-lg">Change Order History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {isLoading ? (
                            <div className="space-y-3">
                                {Array.from({ length: 3 }).map((_, index) => (
                                    <Skeleton key={`change-history-skeleton-${index}`} className="h-12 w-full" />
                                ))}
                            </div>
                        ) : changeOrders.length === 0 ? (
                            <EmptyState
                                title="No change orders yet"
                                description="Any proposed revisions will appear here with buyer responses."
                            />
                        ) : (
                            <div className="space-y-4">
                                {changeOrders.map((changeOrder) => (
                                    <div
                                        key={changeOrder.id}
                                        className="rounded-lg border border-muted/60 p-4"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <p className="text-sm font-medium text-foreground">
                                                    Change Order #{changeOrder.id}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Proposed {formatDate(changeOrder.createdAt)}{' '}
                                                    {changeOrder.proposedBy?.name
                                                        ? `by ${changeOrder.proposedBy.name}`
                                                        : ''}
                                                </p>
                                            </div>
                                            <StatusBadge status={changeOrder.status} />
                                        </div>
                                        <div className="mt-3 space-y-2 text-sm text-foreground">
                                            <p className="font-medium">Reason</p>
                                            <p className="text-muted-foreground">{changeOrder.reason}</p>
                                        </div>
                                        <div className="mt-3 space-y-2 text-sm text-foreground">
                                            <p className="font-medium">Revision Applied</p>
                                            <p className="text-muted-foreground">
                                                {changeOrder.poRevisionNo !== undefined && changeOrder.poRevisionNo !== null
                                                    ? `Revision ${changeOrder.poRevisionNo}`
                                                    : 'Pending buyer decision'}
                                            </p>
                                        </div>
                                        <div className="mt-3 space-y-2 text-sm text-foreground">
                                            <p className="font-medium">Requested Changes</p>
                                            <pre className="max-h-64 overflow-auto whitespace-pre-wrap break-words rounded-md bg-muted/40 p-3 text-xs text-muted-foreground">
                                                {JSON.stringify(changeOrder.changes, null, 2)}
                                            </pre>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
        </div>
    );
}
