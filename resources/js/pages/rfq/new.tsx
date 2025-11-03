import { CADPreview, FileDropzone } from '@/components/app';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';
import { useCreateRFQ, type CreateRFQItemInput } from '@/hooks/api/useCreateRFQ';
import { useSuppliers } from '@/hooks/api/useSuppliers';
import AppLayout from '@/layouts/app-layout';
import { ApiError, api } from '@/lib/api';
import { home, rfq as rfqRoutes } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Loader2, Plus, Trash2 } from 'lucide-react';
import { useMemo, useRef, useState, type ChangeEvent, type FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Home', href: home().url },
    { title: 'RFQ', href: rfqRoutes.index().url },
    { title: 'New RFQ', href: rfqRoutes.new().url },
];

const cadAcceptTypes = [
    '.STEP',
    '.STP',
    '.IGES',
    '.IGS',
    '.DWG',
    '.DXF',
    '.SLDPRT',
    '.3MF',
    '.STL',
    '.PDF',
];

interface LineItemDraft {
    id: number;
    partName: string;
    spec: string;
    quantity: string;
    uom: string;
    targetPrice: string;
}

const MIN_SUPPLIERS = 0;

export default function RfqNew() {
    const [openBidding, setOpenBidding] = useState(false);
    const [selectedCad, setSelectedCad] = useState<File | null>(null);
    const [selectedSupplierIds, setSelectedSupplierIds] = useState<number[]>([]);
    const [form, setForm] = useState({
        itemName: '',
        quantity: '1',
        material: '',
        method: '',
        tolerance: '',
        finish: '',
        clientCompany: '',
        deadlineAt: '',
        sentAt: '',
        notes: '',
    });
    const makeLineItem = (id: number): LineItemDraft => ({
        id,
        partName: '',
        spec: '',
        quantity: '1',
        uom: 'pcs',
        targetPrice: '',
    });
    const lineIdRef = useRef(2);
    const [items, setItems] = useState<LineItemDraft[]>(() => [makeLineItem(1)]);
    const [isInviting, setIsInviting] = useState(false);

    const { data: supplierData, isLoading: suppliersLoading } = useSuppliers({ per_page: 50 });
    const { mutateAsync: createRfq, isPending } = useCreateRFQ();

    const cadMessage = useMemo(() => {
        if (!selectedCad) {
            return 'CAD preview coming soon. Upload a file to enable the viewer.';
        }
        return `${selectedCad.name} uploaded. Preview rendering is mocked for now.`;
    }, [selectedCad]);

    const handleFormChange = (field: keyof typeof form) => (event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        setForm((previous) => ({
            ...previous,
            [field]: event.target.value,
        }));
    };

    const handleItemChange = (
        id: number,
        field: keyof Omit<LineItemDraft, 'id'>,
        value: string,
    ) => {
        setItems((previous) =>
            previous.map((item) =>
                item.id === id
                    ? {
                          ...item,
                          [field]: value,
                      }
                    : item,
            ),
        );
    };

    const addLineItem = () => {
    const nextId = lineIdRef.current++;
    setItems((previous) => [...previous, makeLineItem(nextId)]);
    };

    const removeLineItem = (id: number) => {
        setItems((previous) => (previous.length > 1 ? previous.filter((item) => item.id !== id) : previous));
    };

    const toggleSupplierSelection = (supplierId: number, nextValue: boolean) => {
        setSelectedSupplierIds((previous) => {
            if (nextValue) {
                if (previous.includes(supplierId)) {
                    return previous;
                }
                return [...previous, supplierId];
            }

            return previous.filter((id) => id !== supplierId);
        });
    };

    const sanitizeItems = (): CreateRFQItemInput[] | null => {
        const sanitized: CreateRFQItemInput[] = [];

        for (const item of items) {
            const partName = item.partName.trim();
            if (!partName) {
                continue;
            }

            const quantityValue = Number.parseInt(item.quantity, 10);
            if (!Number.isFinite(quantityValue) || quantityValue <= 0) {
                publishToast({
                    title: 'Invalid line quantity',
                    description: 'Each RFQ line must include a quantity greater than zero.',
                    variant: 'destructive',
                });
                return null;
            }

            const targetPriceValue = item.targetPrice.trim();
            let parsedTargetPrice: number | undefined;

            if (targetPriceValue) {
                const candidate = Number.parseFloat(targetPriceValue);
                if (Number.isNaN(candidate) || candidate < 0) {
                    publishToast({
                        title: 'Invalid target price',
                        description: 'Target price must be a positive number when provided.',
                        variant: 'destructive',
                    });
                    return null;
                }
                parsedTargetPrice = candidate;
            }

            sanitized.push({
                partName,
                spec: item.spec.trim() || undefined,
                quantity: quantityValue,
                uom: item.uom.trim() || 'pcs',
                targetPrice: parsedTargetPrice,
            });
        }

        return sanitized;
    };

    const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const headerQuantity = Number.parseInt(form.quantity, 10);
        if (!Number.isFinite(headerQuantity) || headerQuantity <= 0) {
            publishToast({
                title: 'Quantity required',
                description: 'Provide a total requested quantity greater than zero.',
                variant: 'destructive',
            });
            return;
        }

        if (!form.itemName.trim()) {
            publishToast({
                title: 'Title required',
                description: 'Add a descriptive RFQ title before publishing.',
                variant: 'destructive',
            });
            return;
        }

        if (!form.clientCompany.trim()) {
            publishToast({
                title: 'Client company required',
                description: 'Specify the buyer or requesting company.',
                variant: 'destructive',
            });
            return;
        }

        const preparedItems = sanitizeItems();
        if (preparedItems === null) {
            return;
        }

        if (preparedItems.length === 0) {
            publishToast({
                title: 'Line items required',
                description: 'Add at least one RFQ line item with part information.',
                variant: 'destructive',
            });
            return;
        }

        if (!openBidding && selectedSupplierIds.length <= MIN_SUPPLIERS) {
            publishToast({
                title: 'Invite suppliers',
                description: 'Select at least one supplier or enable open bidding.',
                variant: 'destructive',
            });
            return;
        }

        try {
            const rfq = await createRfq({
                type: 'manufacture',
                itemName: form.itemName.trim(),
                quantity: headerQuantity,
                material: form.material.trim(),
                method: form.method.trim(),
                tolerance: form.tolerance.trim() || undefined,
                finish: form.finish.trim() || undefined,
                clientCompany: form.clientCompany.trim(),
                deadlineAt: form.deadlineAt || undefined,
                sentAt: form.sentAt || undefined,
                notes: form.notes.trim() || undefined,
                isOpenBidding: openBidding,
                status: openBidding ? 'open' : 'awaiting',
                cad: selectedCad,
                items: preparedItems,
            });

            if (selectedSupplierIds.length > 0) {
                setIsInviting(true);
                await api.post(`/rfqs/${rfq.id}/invitations`, {
                    supplier_ids: selectedSupplierIds,
                });
                setIsInviting(false);
            }

            publishToast({
                title: 'RFQ published',
                description: openBidding
                    ? 'Open bidding enabled and preferred suppliers invited.'
                    : 'Invitations sent to selected suppliers.',
                variant: 'success',
            });

            router.visit(rfqRoutes.show({ id: rfq.id }).url);
        } catch (error) {
            setIsInviting(false);
            const message = error instanceof ApiError ? error.message : 'Unable to publish the RFQ. Please try again.';
            publishToast({
                title: 'Publish failed',
                description: message,
                variant: 'destructive',
            });
        }
    };

    const isSubmitting = isPending || isInviting;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New RFQ" />
            <form className="flex flex-1 flex-col gap-6 px-4 py-6" onSubmit={handleSubmit}>
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold text-foreground">New RFQ</h1>
                    <p className="text-sm text-muted-foreground">
                        Capture manufacturing requirements, attach CAD, invite preferred suppliers, and optionally enable open bidding.
                    </p>
                </header>

                <Card className="border-muted/60">
                    <CardHeader>
                        <CardTitle className="text-lg">RFQ Overview</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="rfq-title">RFQ Title</Label>
                            <Input
                                id="rfq-title"
                                value={form.itemName}
                                onChange={handleFormChange('itemName')}
                                placeholder="Precision Valve Assembly"
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="client-company">Client Company</Label>
                            <Input
                                id="client-company"
                                value={form.clientCompany}
                                onChange={handleFormChange('clientCompany')}
                                placeholder="Elements Supply AI"
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="method">Manufacturing Method</Label>
                            <Input
                                id="method"
                                value={form.method}
                                onChange={handleFormChange('method')}
                                placeholder="5-axis CNC"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="material">Material</Label>
                            <Input
                                id="material"
                                value={form.material}
                                onChange={handleFormChange('material')}
                                placeholder="Aluminum 7075-T6"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="tolerance">Tolerance</Label>
                            <Input
                                id="tolerance"
                                value={form.tolerance}
                                onChange={handleFormChange('tolerance')}
                                placeholder="±0.005 in"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="finish">Finish</Label>
                            <Input
                                id="finish"
                                value={form.finish}
                                onChange={handleFormChange('finish')}
                                placeholder="Anodized Type II"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="total-quantity">Total Quantity</Label>
                            <Input
                                id="total-quantity"
                                type="number"
                                min={1}
                                value={form.quantity}
                                onChange={handleFormChange('quantity')}
                                required
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="deadline">Bid Deadline</Label>
                                <Input
                                    id="deadline"
                                    type="date"
                                    value={form.deadlineAt}
                                    onChange={handleFormChange('deadlineAt')}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="sent-at">Sent Date</Label>
                                <Input
                                    id="sent-at"
                                    type="date"
                                    value={form.sentAt}
                                    onChange={handleFormChange('sentAt')}
                                />
                            </div>
                        </div>
                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea
                                id="notes"
                                rows={4}
                                value={form.notes}
                                onChange={handleFormChange('notes')}
                                placeholder="Add inspection requirements, certifications, packaging or Incoterm expectations."
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-muted/60">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-lg">RFQ Line Items</CardTitle>
                        <Button type="button" variant="outline" size="sm" onClick={addLineItem}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Line
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {items.map((item, index) => (
                            <div key={item.id} className="grid gap-4 rounded-lg border border-muted/60 p-4 md:grid-cols-[1.2fr_1fr_1fr_1fr_0.2fr]">
                                <div className="space-y-2">
                                    <Label htmlFor={`part-name-${item.id}`}>Part Name</Label>
                                    <Input
                                        id={`part-name-${item.id}`}
                                        value={item.partName}
                                        onChange={(event) =>
                                            handleItemChange(item.id, 'partName', event.target.value)
                                        }
                                        placeholder="Valve Body"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor={`spec-${item.id}`}>Specification</Label>
                                    <Input
                                        id={`spec-${item.id}`}
                                        value={item.spec}
                                        onChange={(event) =>
                                            handleItemChange(item.id, 'spec', event.target.value)
                                        }
                                        placeholder="CNC machined, tolerance ±0.01mm"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor={`quantity-${item.id}`}>Quantity</Label>
                                    <Input
                                        id={`quantity-${item.id}`}
                                        type="number"
                                        min={1}
                                        value={item.quantity}
                                        onChange={(event) =>
                                            handleItemChange(item.id, 'quantity', event.target.value)
                                        }
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor={`uom-${item.id}`}>UOM</Label>
                                    <Input
                                        id={`uom-${item.id}`}
                                        value={item.uom}
                                        onChange={(event) =>
                                            handleItemChange(item.id, 'uom', event.target.value)
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor={`target-price-${item.id}`}>Target Price</Label>
                                    <Input
                                        id={`target-price-${item.id}`}
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={item.targetPrice}
                                        onChange={(event) =>
                                            handleItemChange(item.id, 'targetPrice', event.target.value)
                                        }
                                    />
                                </div>
                                <div className="flex items-start justify-end pt-6">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="text-muted-foreground hover:text-destructive"
                                        onClick={() => removeLineItem(item.id)}
                                        disabled={items.length === 1}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        <span className="sr-only">Remove line item {index + 1}</span>
                                    </Button>
                                </div>
                            </div>
                        ))}
                        <p className="text-xs text-muted-foreground">
                            Ensure each RFQ line describes a discrete part or service scope. Pricing and lead time comparisons use these references.
                        </p>
                    </CardContent>
                </Card>

                <Card className="border-muted/60">
                    <CardHeader>
                        <CardTitle className="text-lg">CAD & Supporting Documents</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-[1.2fr_1fr]">
                        <div className="space-y-3">
                            <Label>Upload CAD</Label>
                            <FileDropzone
                                accept={cadAcceptTypes}
                                onFilesSelected={(files) => setSelectedCad(files[0] ?? null)}
                                description="Drag and drop or browse STEP, IGES, DWG, STL, PDF, and similar formats."
                            />
                        </div>
                        <CADPreview fileName={selectedCad?.name} message={cadMessage} />
                    </CardContent>
                </Card>

                <Card className="border-muted/60">
                    <CardHeader>
                        <CardTitle className="text-lg">Supplier Invitations</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-start gap-3 rounded-lg border border-muted/60 bg-muted/20 p-4">
                            <Checkbox
                                id="open-bidding"
                                checked={openBidding}
                                onCheckedChange={(checked) => setOpenBidding(Boolean(checked))}
                            />
                            <div className="space-y-1">
                                <Label htmlFor="open-bidding" className="text-sm font-medium">
                                    Enable Open Bidding
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Allow verified suppliers on the platform to submit quotes. You can still invite strategic partners directly below.
                                </p>
                            </div>
                        </div>

                        <div className="space-y-3">
                            <Label>Invite Preferred Suppliers</Label>
                            {suppliersLoading ? (
                                <div className="grid gap-3 md:grid-cols-2">
                                    {Array.from({ length: 4 }).map((_, index) => (
                                        <Skeleton key={`supplier-skeleton-${index}`} className="h-12 w-full" />
                                    ))}
                                </div>
                            ) : supplierData && supplierData.items.length > 0 ? (
                                <div className="grid gap-3 md:grid-cols-2">
                                    {supplierData.items.map((supplier) => {
                                        const isSelected = selectedSupplierIds.includes(supplier.id);

                                        return (
                                            <label
                                                key={supplier.id}
                                                className="flex cursor-pointer items-start gap-3 rounded-lg border border-muted/60 p-3 transition hover:border-muted"
                                            >
                                                <Checkbox
                                                    checked={isSelected}
                                                    onCheckedChange={(checked) =>
                                                        toggleSupplierSelection(
                                                            supplier.id,
                                                            Boolean(checked),
                                                        )
                                                    }
                                                />
                                                <div className="space-y-1">
                                                    <p className="font-medium text-sm text-foreground">{supplier.name}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Rating {supplier.rating.toFixed(1)} • MOQ {supplier.minimumOrderQuantity}
                                                    </p>
                                                </div>
                                            </label>
                                        );
                                    })}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No suppliers found yet. Seed suppliers or enable open bidding so the RFQ is still published.
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <div className="flex justify-end gap-2">
                    <Button
                        type="submit"
                        disabled={isSubmitting}
                    >
                        {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                        Publish RFQ
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
