import { DataTable, EmptyState, StatusBadge } from '@/components/app';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { home, orders as orderRoutes } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { type Order } from '@/types/sourcing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Home', href: home().url },
    { title: 'Orders', href: orderRoutes.index().url },
];

const requestedOrders: Order[] = [
    {
        id: 501,
        orderNumber: 'PO-4501',
        party: 'PrecisionForge Manufacturing',
        item: 'Precision Valve Body',
        quantity: 120,
        totalUsd: 38400,
        orderDate: '2025-10-18',
        status: 'Pending',
    },
];

const receivedOrders: Order[] = [
    {
        id: 601,
        orderNumber: 'PO-4410',
        party: 'Nova Additive Labs',
        item: 'Composite Bracket Assembly',
        quantity: 60,
        totalUsd: 22800,
        orderDate: '2025-09-02',
        status: 'Delivered',
    },
];

export default function OrdersIndex() {
    const [activeTab, setActiveTab] = useState('requested');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Orders" />
            <div className="flex flex-1 flex-col gap-6 px-4 py-6">
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold text-foreground">Orders</h1>
                    <p className="text-sm text-muted-foreground">
                        Track requested and received orders alongside supplier fulfillment status and delivery timelines.
                    </p>
                </header>

                <Tabs defaultValue="requested" value={activeTab} onValueChange={setActiveTab}>
                    <TabsList>
                        <TabsTrigger value="requested">Requested</TabsTrigger>
                        <TabsTrigger value="received">Received</TabsTrigger>
                    </TabsList>

                    <TabsContent value="requested">
                        <DataTable
                            data={requestedOrders}
                            columns={[
                                { key: 'orderNumber', title: 'Number' },
                                { key: 'party', title: 'Party' },
                                { key: 'item', title: 'Item' },
                                { key: 'quantity', title: 'Qty', align: 'center' },
                                {
                                    key: 'totalUsd',
                                    title: 'Total (USD)',
                                    render: (row) => `$${row.totalUsd.toLocaleString()}`,
                                    align: 'right',
                                },
                                { key: 'orderDate', title: 'Date' },
                                {
                                    key: 'status',
                                    title: 'Status',
                                    render: (row) => <StatusBadge status={row.status} />,
                                },
                            ]}
                            emptyState={
                                <EmptyState
                                    title="No requested orders"
                                    description="RFQs that are awarded will create purchase orders. Requested orders will display here."
                                />
                            }
                        />
                    </TabsContent>

                    <TabsContent value="received">
                        <DataTable
                            data={receivedOrders}
                            columns={[
                                { key: 'orderNumber', title: 'Number' },
                                { key: 'party', title: 'Party' },
                                { key: 'item', title: 'Item' },
                                { key: 'quantity', title: 'Qty', align: 'center' },
                                {
                                    key: 'totalUsd',
                                    title: 'Total (USD)',
                                    render: (row) => `$${row.totalUsd.toLocaleString()}`,
                                    align: 'right',
                                },
                                { key: 'orderDate', title: 'Date' },
                                {
                                    key: 'status',
                                    title: 'Status',
                                    render: (row) => <StatusBadge status={row.status} />,
                                },
                            ]}
                            emptyState={
                                <EmptyState
                                    title="No received orders"
                                    description="Once goods are received and inspected, orders will transition into this view with milestone history."
                                />
                            }
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
