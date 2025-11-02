import { DataTable, EmptyState, StatusBadge } from '@/components/app';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { home, rfq as rfqRoutes } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { type RFQ } from '@/types/sourcing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Home', href: home().url },
    { title: 'RFQ', href: rfqRoutes.index().url },
];

const sentRfqs: RFQ[] = [
    {
        id: 101,
        rfqNumber: 'RFQ-24001',
        title: 'Gearbox Housing Revision A',
        method: 'CNC Machining',
        material: 'Aluminum 7075-T6',
        quantity: 150,
        dueDate: '2025-12-05',
        status: 'Open',
        companyName: 'Elements Supply AI',
        openBidding: false,
    },
];

const receivedRfqs: RFQ[] = [
    {
        id: 202,
        rfqNumber: 'RFQ-24005',
        title: 'Composite Bracket Assembly',
        method: 'Composite Layup',
        material: 'Carbon Fiber Prepreg',
        quantity: 60,
        dueDate: '2025-11-21',
        status: 'Received',
        companyName: 'Axiom Aero Systems',
        openBidding: false,
    },
];

const openRfqs: RFQ[] = [
    {
        id: 303,
        rfqNumber: 'RFQ-24012',
        title: 'Open Bidding: Hydraulic Manifold',
        method: 'Additive Manufacturing',
        material: 'Inconel 718',
        quantity: 40,
        dueDate: '2025-12-18',
        status: 'Open',
        companyName: 'Elements Supply AI',
        openBidding: true,
    },
];

export default function RfqIndex() {
    const [activeTab, setActiveTab] = useState('sent');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="RFQ" />
            <div className="flex flex-1 flex-col gap-6 px-4 py-6">
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold text-foreground">
                        RFQs
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Monitor sent RFQs, supplier responses, and open bidding activity across your sourcing pipeline.
                    </p>
                </header>

                <Tabs defaultValue="sent" value={activeTab} onValueChange={setActiveTab}>
                    <TabsList>
                        <TabsTrigger value="sent">Sent</TabsTrigger>
                        <TabsTrigger value="received">Received</TabsTrigger>
                        <TabsTrigger value="open">Open</TabsTrigger>
                    </TabsList>

                    <TabsContent value="sent" className="space-y-4">
                        <DataTable
                            data={sentRfqs}
                            columns={[
                                {
                                    key: 'rfqNumber',
                                    title: 'RFQ Number',
                                },
                                {
                                    key: 'title',
                                    title: 'Title',
                                },
                                {
                                    key: 'method',
                                    title: 'Manufacturing Method',
                                },
                                {
                                    key: 'material',
                                    title: 'Material',
                                },
                                {
                                    key: 'quantity',
                                    title: 'Quantity',
                                },
                                {
                                    key: 'dueDate',
                                    title: 'Due Date',
                                },
                                {
                                    key: 'status',
                                    title: 'Status',
                                    render: (row) => <StatusBadge status={row.status} />,
                                },
                            ]}
                            emptyState={
                                <EmptyState
                                    title="No sent RFQs yet"
                                    description="Create your first RFQ to invite suppliers or publish an open bidding opportunity."
                                />
                            }
                        />
                    </TabsContent>

                    <TabsContent value="received" className="space-y-4">
                        <DataTable
                            data={receivedRfqs}
                            columns={[
                                {
                                    key: 'rfqNumber',
                                    title: 'RFQ Number',
                                },
                                {
                                    key: 'companyName',
                                    title: 'Buyer',
                                },
                                {
                                    key: 'title',
                                    title: 'Title',
                                },
                                {
                                    key: 'method',
                                    title: 'Manufacturing Method',
                                },
                                {
                                    key: 'material',
                                    title: 'Material',
                                },
                                {
                                    key: 'dueDate',
                                    title: 'Due Date',
                                },
                                {
                                    key: 'status',
                                    title: 'Status',
                                    render: (row) => <StatusBadge status={row.status} />,
                                },
                            ]}
                            emptyState={
                                <EmptyState
                                    title="No received RFQs"
                                    description="Suppliers will see invited RFQs and open bidding opportunities here."
                                />
                            }
                        />
                    </TabsContent>

                    <TabsContent value="open" className="space-y-4">
                        <DataTable
                            data={openRfqs}
                            columns={[
                                {
                                    key: 'rfqNumber',
                                    title: 'RFQ Number',
                                },
                                {
                                    key: 'title',
                                    title: 'Title',
                                },
                                {
                                    key: 'method',
                                    title: 'Manufacturing Method',
                                },
                                {
                                    key: 'material',
                                    title: 'Material',
                                },
                                {
                                    key: 'dueDate',
                                    title: 'Due Date',
                                },
                                {
                                    key: 'companyName',
                                    title: 'Company',
                                },
                                {
                                    key: 'status',
                                    title: 'Status',
                                    render: (row) => <StatusBadge status={row.status} />,
                                },
                            ]}
                            emptyState={
                                <EmptyState
                                    title="No open RFQs"
                                    description="Publish an RFQ as open bidding to allow approved suppliers to submit proposals."
                                />
                            }
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
