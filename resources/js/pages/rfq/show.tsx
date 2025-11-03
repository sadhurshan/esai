import { CADPreview, DataTable, EmptyState, StatusBadge } from '@/components/app';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { home, rfq as rfqRoutes } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { useMemo } from 'react';

import { useRFQ } from '@/hooks/api/useRFQ';
import type { QuoteSummary } from '@/types/sourcing';

function parseRfqId(url: string): number | null {
    const [path] = url.split('?');
    const segments = path.split('/').filter(Boolean);
    const lastSegment = segments.pop();

    if (!lastSegment) {
        return null;
    }

    const parsed = Number.parseInt(lastSegment, 10);
    return Number.isNaN(parsed) ? null : parsed;
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString();
}

const quoteColumns = [
    { key: 'supplierName', title: 'Supplier' },
    { key: 'revision', title: 'Revision', align: 'center' as const },
    {
        key: 'unitPrice',
        title: 'Unit Price',
        render: (row: QuoteSummary) =>
            `${row.currency.toUpperCase()} ${row.unitPrice.toLocaleString()}`,
        align: 'right' as const,
    },
    {
        key: 'minOrderQty',
        title: 'MOQ',
        render: (row: QuoteSummary) => row.minOrderQty?.toLocaleString() ?? '—',
        align: 'center' as const,
    },
    {
        key: 'leadTimeDays',
        title: 'Lead Time (days)',
        align: 'center' as const,
    },
    {
        key: 'status',
        title: 'Status',
        render: (row: QuoteSummary) => <StatusBadge status={row.status} />,
    },
    {
        key: 'submittedAt',
        title: 'Submitted',
        render: (row: QuoteSummary) => formatDate(row.submittedAt),
    },
];

export default function RfqShow() {
    const page = usePage();
    const rfqId = useMemo(() => parseRfqId(page.url), [page.url]);

    const { data, isLoading, isError, error, refetch } = useRFQ(rfqId ?? 0);
    const rfq = data?.rfq;
    const detail = data?.detail;
    const quotes = data?.quotes ?? [];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Home', href: home().url },
        { title: 'RFQ', href: rfqRoutes.index().url },
        {
            title: rfq?.rfqNumber ?? 'RFQ Detail',
            href: rfqId ? rfqRoutes.show({ id: rfqId }).url : rfqRoutes.index().url,
        },
    ];

    if (rfqId === null) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="RFQ Detail" />
                <div className="flex flex-1 items-center justify-center px-4 py-6">
                    <EmptyState
                        title="RFQ not found"
                        description="The requested RFQ identifier is missing from the URL."
                    />
                </div>
            </AppLayout>
        );
    }

    if (isError) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="RFQ Detail" />
                <div className="flex flex-1 items-center justify-center px-4 py-6">
                    <EmptyState
                        title="Unable to load RFQ"
                        description={error?.message ?? 'An unexpected error occurred.'}
                        ctaLabel="Retry"
                        ctaProps={{ onClick: () => refetch() }}
                    />
                </div>
            </AppLayout>
        );
    }

    if (!isLoading && !rfq) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="RFQ Detail" />
                <div className="flex flex-1 items-center justify-center px-4 py-6">
                    <EmptyState
                        title="RFQ unavailable"
                        description="This RFQ could not be located. It may have been removed or you may not have access."
                    />
                </div>
            </AppLayout>
        );
    }

    const showSkeleton = isLoading || !rfq;
    const cadFileName = detail?.cad_path
        ? detail.cad_path.split('/').pop() ?? detail.cad_path
        : undefined;

    const summaryItems = rfq
        ? [
              { label: 'RFQ Number', value: rfq.rfqNumber },
              { label: 'Company', value: rfq.companyName },
              { label: 'Quantity', value: rfq.quantity.toLocaleString() },
              { label: 'Method', value: rfq.method },
              { label: 'Material', value: rfq.material },
              { label: 'Deadline', value: formatDate(detail?.deadline_at ?? rfq.dueDate) },
              { label: 'Sent', value: formatDate(detail?.sent_at) },
              { label: 'Open Bidding', value: detail ? (detail.is_open_bidding ? 'Enabled' : 'Disabled') : '—' },
              { label: 'Tolerance', value: detail?.tolerance ?? 'Not specified' },
              { label: 'Finish', value: detail?.finish ?? 'Not specified' },
              { label: 'Type', value: detail?.type ?? '—' },
          ]
        : [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={rfq ? `${rfq.rfqNumber} · ${rfq.title}` : 'RFQ Detail'} />
            <div className="flex flex-1 flex-col gap-6 px-4 py-6">
                <header className="space-y-2">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="space-y-1">
                            {showSkeleton ? (
                                <>
                                    <Skeleton className="h-8 w-56" />
                                    <Skeleton className="h-5 w-72" />
                                </>
                            ) : (
                                <>
                                    <h1 className="text-2xl font-semibold text-foreground">
                                        {rfq.title}
                                    </h1>
                                    <p className="text-sm text-muted-foreground">
                                        RFQ {rfq.rfqNumber} • {rfq.companyName}
                                    </p>
                                </>
                            )}
                        </div>
                        {showSkeleton ? (
                            <Skeleton className="h-6 w-24" />
                        ) : (
                            <StatusBadge status={rfq.status} />
                        )}
                    </div>
                    {!showSkeleton && detail?.notes && (
                        <p className="text-sm text-muted-foreground">{detail.notes}</p>
                    )}
                </header>

                <section className="grid gap-4 md:grid-cols-[2fr_1fr]">
                    <Card className="border-muted/60">
                        <CardHeader>
                            <CardTitle className="text-lg">RFQ Overview</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            {showSkeleton
                                ? Array.from({ length: 8 }).map((_, index) => (
                                      <div key={`rfq-skeleton-${index}`} className="space-y-2">
                                          <Skeleton className="h-3 w-20" />
                                          <Skeleton className="h-4 w-32" />
                                      </div>
                                  ))
                                : summaryItems.map((item) => (
                                      <div key={item.label}>
                                          <p className="text-xs uppercase text-muted-foreground">
                                              {item.label}
                                          </p>
                                          <p className="text-sm text-foreground">{item.value}</p>
                                      </div>
                                  ))}
                        </CardContent>
                    </Card>

                    <Card className="border-muted/60">
                        <CardHeader>
                            <CardTitle className="text-lg">CAD & Attachments</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {showSkeleton ? (
                                <Skeleton className="h-[180px] w-full rounded-xl" />
                            ) : (
                                <CADPreview fileName={cadFileName} />
                            )}
                        </CardContent>
                    </Card>
                </section>

                <Card className="border-muted/60">
                    <CardHeader>
                        <CardTitle className="text-lg">Supplier Responses</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            data={quotes}
                            columns={quoteColumns}
                            isLoading={isLoading}
                            emptyState={
                                <EmptyState
                                    title="No supplier responses yet"
                                    description="Invited suppliers and open bidders will appear here once quotes are submitted."
                                />
                            }
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
