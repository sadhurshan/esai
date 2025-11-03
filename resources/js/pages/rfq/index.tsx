import { DataTable, EmptyState, Pagination, StatusBadge, FilterBar } from '@/components/app';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { home, rfq as rfqRoutes } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';

import { useRFQs } from '@/hooks/api/useRFQs';
import type { RFQ } from '@/types/sourcing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Home', href: home().url },
    { title: 'RFQ', href: rfqRoutes.index().url },
];

export default function RfqIndex() {
    const [activeTab, setActiveTab] = useState<'sent' | 'received' | 'open'>('sent');
    const [search, setSearch] = useState('');
    const [sortKey, setSortKey] = useState<'sent_at' | 'deadline_at'>('sent_at');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');
    const [page, setPage] = useState(1);

    const { data, isLoading, isError, error, refetch } = useRFQs({
        tab: activeTab,
        q: search || undefined,
        sort: sortKey,
        sort_direction: sortDirection,
        page,
        per_page: 10,
    });

    const rfqs: RFQ[] = data?.items ?? [];
    const meta = data?.meta ?? null;

    type RfqTableRow = RFQ & { sent_at?: string | null };

    const formatDate = useCallback((value?: string) => {
        if (!value) {
            return '—';
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }
        return date.toLocaleDateString();
    }, []);

    const emptyState = useMemo(() => {
        if (isError) {
            return (
                <EmptyState
                    title="Unable to load RFQs"
                    description={error?.message ?? 'Please try again.'}
                    ctaLabel="Retry"
                    ctaProps={{ onClick: () => refetch() }}
                />
            );
        }

        return (
            <EmptyState
                title={`No RFQs found for the ${activeTab} tab.`}
                description="Adjust filters or publish new RFQs to populate this view."
            />
        );
    }, [activeTab, error?.message, isError, refetch]);

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

                <FilterBar
                    filters={[
                        {
                            id: 'sort',
                            label: 'Sort By',
                            options: [
                                { label: 'Sent Date', value: 'sent_at' },
                                { label: 'Deadline', value: 'deadline_at' },
                            ],
                            value: sortKey,
                        },
                        {
                            id: 'sort_direction',
                            label: 'Order',
                            options: [
                                { label: 'Descending', value: 'desc' },
                                { label: 'Ascending', value: 'asc' },
                            ],
                            value: sortDirection,
                        },
                    ]}
                    searchPlaceholder="Search RFQs"
                    searchValue={search}
                    onSearchChange={(value) => {
                        setSearch(value);
                        setPage(1);
                    }}
                    onFilterChange={(id, value) => {
                        if (id === 'sort' && (value === 'sent_at' || value === 'deadline_at')) {
                            setSortKey(value);
                            setSortDirection('desc');
                            setPage(1);
                            return;
                        }

                        if (id === 'sort_direction' && (value === 'asc' || value === 'desc')) {
                            setSortDirection(value);
                        }
                    }}
                    onReset={() => {
                        setSearch('');
                        setSortKey('sent_at');
                        setSortDirection('desc');
                        setPage(1);
                    }}
                    isLoading={isLoading}
                />

                <Tabs
                    defaultValue="sent"
                    value={activeTab}
                    onValueChange={(value) => {
                        const nextValue = value as 'sent' | 'received' | 'open';
                        setActiveTab(nextValue);
                        setPage(1);
                    }}
                >
                    <TabsList>
                        <TabsTrigger value="sent">Sent</TabsTrigger>
                        <TabsTrigger value="received">Received</TabsTrigger>
                        <TabsTrigger value="open">Open</TabsTrigger>
                    </TabsList>

                    <TabsContent value={activeTab} className="space-y-4">
                        <DataTable<RfqTableRow>
                            data={rfqs.map((rfq): RfqTableRow => ({
                                ...rfq,
                                dueDate: formatDate(rfq.dueDate),
                                sent_at: rfq.openBidding ? undefined : null,
                            }))}
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
                                    key: 'companyName',
                                    title: activeTab === 'received' ? 'Buyer' : 'Company',
                                },
                                {
                                    key: 'method',
                                    title: 'Method',
                                },
                                {
                                    key: 'material',
                                    title: 'Material',
                                },
                                {
                                    key: 'quantity',
                                    title: 'Qty',
                                    render: (row) => row.quantity.toLocaleString(),
                                    align: 'center',
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
                            emptyState={emptyState}
                            isLoading={isLoading}
                        />

                        <Pagination
                            meta={meta}
                            onPageChange={(nextPage) => setPage(nextPage)}
                            isLoading={isLoading}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
