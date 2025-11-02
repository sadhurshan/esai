import { CADPreview, DataTable, EmptyState, StatusBadge } from '@/components/app';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { home, rfq as rfqRoutes } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { type RFQ, type RFQQuote } from '@/types/sourcing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Home', href: home().url },
    { title: 'RFQ', href: rfqRoutes.index().url },
    { title: 'RFQ Detail', href: rfqRoutes.show({ id: 101 }).url },
];

const mockRfq: RFQ = {
    id: 101,
    rfqNumber: 'RFQ-24001',
    title: 'Precision Valve Body',
    method: '5-Axis CNC',
    material: 'Stainless Steel 17-4PH',
    quantity: 120,
    dueDate: '2025-12-05',
    status: 'Open',
    companyName: 'Elements Supply AI',
    openBidding: false,
};

const mockQuotes: RFQQuote[] = [
    {
        id: 4101,
        supplierName: 'PrecisionForge Manufacturing',
        revision: 1,
        totalPriceUsd: 38400,
        unitPriceUsd: 320,
        leadTimeDays: 35,
        status: 'Submitted',
        submittedAt: '2025-10-14',
    },
    {
        id: 4102,
        supplierName: 'Nova Additive Labs',
        revision: 2,
        totalPriceUsd: 40200,
        unitPriceUsd: 335,
        leadTimeDays: 32,
        status: 'Revised',
        submittedAt: '2025-10-16',
    },
];

export default function RfqShow() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="RFQ Detail" />
            <div className="flex flex-1 flex-col gap-6 px-4 py-6">
                <header className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p className="text-sm font-semibold text-muted-foreground">
                            {mockRfq.rfqNumber}
                        </p>
                        <h1 className="text-2xl font-semibold text-foreground">
                            {mockRfq.title}
                        </h1>
                    </div>
                    <StatusBadge status={mockRfq.status} />
                </header>

                <section className="grid gap-4 md:grid-cols-[1.2fr_1fr]">
                    <Card className="border-muted/60">
                        <CardHeader>
                            <CardTitle className="text-lg">Part Specifications</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 md:grid-cols-2">
                            <div>
                                <p className="text-xs uppercase text-muted-foreground">Material</p>
                                <p className="text-sm text-foreground">{mockRfq.material}</p>
                            </div>
                            <div>
                                <p className="text-xs uppercase text-muted-foreground">Manufacturing Method</p>
                                <p className="text-sm text-foreground">{mockRfq.method}</p>
                            </div>
                            <div>
                                <p className="text-xs uppercase text-muted-foreground">Tolerance</p>
                                <p className="text-sm text-foreground">±0.003 in (mock)</p>
                            </div>
                            <div>
                                <p className="text-xs uppercase text-muted-foreground">Finish</p>
                                <p className="text-sm text-foreground">Passivated per ASTM A967 (mock)</p>
                            </div>
                            <div>
                                <p className="text-xs uppercase text-muted-foreground">Quantity</p>
                                <p className="text-sm text-foreground">{mockRfq.quantity} units</p>
                            </div>
                            <div>
                                <p className="text-xs uppercase text-muted-foreground">Deadline</p>
                                <p className="text-sm text-foreground">Due {mockRfq.dueDate}</p>
                            </div>
                            <div>
                                <p className="text-xs uppercase text-muted-foreground">Client</p>
                                <p className="text-sm text-foreground">{mockRfq.companyName}</p>
                            </div>
                            <div>
                                <p className="text-xs uppercase text-muted-foreground">Open Bidding</p>
                                <p className="text-sm text-foreground">
                                    {mockRfq.openBidding ? 'Enabled' : 'Direct RFQ'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-muted/60">
                        <CardHeader>
                            <CardTitle className="text-lg">CAD & Attachments</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <CADPreview fileName="ValveBody-V1.step" message="Preview unavailable in mock mode." />
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-4 md:grid-cols-[1.2fr_1fr]">
                    <Card className="border-muted/60 md:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-lg">Supplier Responses</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <DataTable
                                data={mockQuotes}
                                columns={[
                                    { key: 'supplierName', title: 'Supplier' },
                                    {
                                        key: 'revision',
                                        title: 'Revision',
                                    },
                                    {
                                        key: 'totalPriceUsd',
                                        title: 'Total (USD)',
                                        render: (row) => `$${row.totalPriceUsd.toLocaleString()}`,
                                        align: 'right',
                                    },
                                    {
                                        key: 'unitPriceUsd',
                                        title: 'Unit Price (USD)',
                                        render: (row) => `$${row.unitPriceUsd.toLocaleString()}`,
                                        align: 'right',
                                    },
                                    {
                                        key: 'leadTimeDays',
                                        title: 'Lead Time (days)',
                                        align: 'center',
                                    },
                                    {
                                        key: 'status',
                                        title: 'Status',
                                        render: (row) => <StatusBadge status={row.status} />,
                                    },
                                    {
                                        key: 'submittedAt',
                                        title: 'Submitted',
                                    },
                                ]}
                                emptyState={
                                    <EmptyState
                                        title="No supplier responses yet"
                                        description="Invited suppliers and open bidders will appear here once quotes are submitted."
                                    />
                                }
                            />
                        </CardContent>
                    </Card>
                </section>
            </div>
        </AppLayout>
    );
}
