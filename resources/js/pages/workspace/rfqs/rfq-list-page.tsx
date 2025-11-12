import { startTransition, useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import {
    ListRfqsSortDirectionEnum,
    ListRfqsSortEnum,
    ListRfqsTabEnum,
    RFQsApi,
    RfqStatusEnum,
    type Rfq,
    type ListRfqs200Response,
} from '@/sdk';
import { useSdkClient } from '@/contexts/api-client-context';
import { usePageTitle } from '@/hooks/use-page-title';
import { useAuth } from '@/contexts/auth-context';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { ArrowRight, Plus, Search } from 'lucide-react';

const PAGE_SIZE = 20;
const DEFAULT_TAB: ListRfqsTabEnum = ListRfqsTabEnum.All;
const DEFAULT_SORT: ListRfqsSortEnum = ListRfqsSortEnum.SentAt;
const DEFAULT_DIRECTION: ListRfqsSortDirectionEnum = ListRfqsSortDirectionEnum.Desc;
const dateFormatter = new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' });

const tabOptions: Array<{ value: ListRfqsTabEnum; label: string; description: string }> = [
    {
        value: ListRfqsTabEnum.All,
        label: 'All RFQs',
        description: 'Everything in-flight with suppliers.',
    },
    {
        value: ListRfqsTabEnum.Open,
        label: 'Open',
        description: 'Active RFQs awaiting supplier quotes.',
    },
    {
        value: ListRfqsTabEnum.Received,
        label: 'Received',
        description: 'RFQs you have received from buyers.',
    },
    {
        value: ListRfqsTabEnum.Sent,
        label: 'Sent',
        description: 'RFQs distributed to suppliers.',
    },
];

const sortOptions = [
    { value: `${ListRfqsSortEnum.SentAt}:${ListRfqsSortDirectionEnum.Desc}`, label: 'Sent date · Newest' },
    { value: `${ListRfqsSortEnum.SentAt}:${ListRfqsSortDirectionEnum.Asc}`, label: 'Sent date · Oldest' },
    { value: `${ListRfqsSortEnum.DeadlineAt}:${ListRfqsSortDirectionEnum.Asc}`, label: 'Due date · Soonest' },
    { value: `${ListRfqsSortEnum.DeadlineAt}:${ListRfqsSortDirectionEnum.Desc}`, label: 'Due date · Latest' },
];

const statusStyles: Record<
    RfqStatusEnum,
    {
        label: string;
        className: string;
    }
> = {
    [RfqStatusEnum.Awaiting]: {
        label: 'Awaiting',
        className: 'border-amber-200 bg-amber-100/80 text-amber-900',
    },
    [RfqStatusEnum.Open]: {
        label: 'Open',
        className: 'border-emerald-200 bg-emerald-100/80 text-emerald-900',
    },
    [RfqStatusEnum.Closed]: {
        label: 'Closed',
        className: 'border-muted bg-muted/70 text-muted-foreground',
    },
    [RfqStatusEnum.Awarded]: {
        label: 'Awarded',
        className: 'border-brand-accent/30 bg-brand-background text-brand-primary',
    },
    [RfqStatusEnum.Cancelled]: {
        label: 'Cancelled',
        className: 'border-destructive/30 bg-destructive/15 text-destructive',
    },
};

export function RfqListPage() {
    usePageTitle('RFQs');
    const navigate = useNavigate();
    const { hasFeature } = useAuth();
    const rfqsApi = useSdkClient(RFQsApi);
    const [searchParams, setSearchParams] = useSearchParams();
    const [searchTerm, setSearchTerm] = useState(searchParams.get('q') ?? '');
    const debouncedQuery = useDebouncedValue(searchTerm, 350);

    const activeTab = useMemo(() => {
        const raw = (searchParams.get('tab') as ListRfqsTabEnum | null) ?? DEFAULT_TAB;
        if (Object.values(ListRfqsTabEnum).includes(raw)) {
            return raw;
        }
        return DEFAULT_TAB;
    }, [searchParams]);

    const sort = useMemo(() => {
        const raw = (searchParams.get('sort') as ListRfqsSortEnum | null) ?? DEFAULT_SORT;
        return Object.values(ListRfqsSortEnum).includes(raw) ? raw : DEFAULT_SORT;
    }, [searchParams]);

    const sortDirection = useMemo(() => {
        const raw = (searchParams.get('direction') as ListRfqsSortDirectionEnum | null) ?? DEFAULT_DIRECTION;
        return Object.values(ListRfqsSortDirectionEnum).includes(raw) ? raw : DEFAULT_DIRECTION;
    }, [searchParams]);

    const currentPage = useMemo(() => {
        const raw = Number.parseInt(searchParams.get('page') ?? '1', 10);
        if (Number.isNaN(raw) || raw < 1) {
            return 1;
        }
        return raw;
    }, [searchParams]);

    useEffect(() => {
        const current = searchParams.get('q') ?? '';
        startTransition(() => {
            setSearchTerm((prev) => {
                if (prev === current) {
                    return prev;
                }
                return current;
            });
        });
    }, [searchParams]);

    const applyParams = useCallback(
        (updates: Record<string, string | null>, options?: { resetPage?: boolean }) => {
            const next = new URLSearchParams(searchParams);

            Object.entries(updates).forEach(([key, value]) => {
                if (value == null || value.trim() === '') {
                    next.delete(key);
                } else {
                    next.set(key, value);
                }
            });

            if (options?.resetPage) {
                next.set('page', '1');
            }

            const nextString = next.toString();
            if (nextString !== searchParams.toString()) {
                setSearchParams(next, { replace: true });
            }
        },
        [searchParams, setSearchParams],
    );

    useEffect(() => {
        const normalized = debouncedQuery.trim();
        const nextValue = normalized.length > 0 ? normalized : null;
        const currentValue = searchParams.get('q');
        if ((nextValue ?? null) === (currentValue ?? null)) {
            return;
        }
        applyParams({ q: nextValue }, { resetPage: true });
    }, [applyParams, debouncedQuery, searchParams]);

    const canCreateRfq = useMemo(() => {
        // TODO: clarify with spec which feature flag gates RFQ creation (e.g. rfqs.create vs rfqs.manage).
        return hasFeature('rfqs.create') || hasFeature('rfqs.manage');
    }, [hasFeature]);

    const trimmedQuery = debouncedQuery.trim();

    const rfqsQuery = useQuery<ListRfqs200Response>({
        queryKey: [
            'rfqs',
            {
                tab: activeTab,
                page: currentPage,
                sort,
                direction: sortDirection,
                q: trimmedQuery,
            },
        ],
        queryFn: async () => {
            return await rfqsApi.listRfqs({
                tab: activeTab,
                page: currentPage,
                perPage: PAGE_SIZE,
                sort,
                sortDirection,
                q: trimmedQuery.length > 0 ? trimmedQuery : undefined,
            });
        },
        placeholderData: keepPreviousData,
    });

    const items: Rfq[] = rfqsQuery.data?.data.items ?? [];
    const meta = rfqsQuery.data?.data.meta;
    const isLoading = rfqsQuery.isLoading;
    const isFetching = rfqsQuery.isFetching;
    const hasError = Boolean(rfqsQuery.error);

    return (
        <section className="space-y-6">
            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">Requests for Quotation</h1>
                    <p className="text-sm text-muted-foreground">
                        Monitor sourcing activity, due dates, and supplier responses in one workspace.
                    </p>
                </div>
                <Button
                    onClick={() => navigate('/app/rfqs/new')}
                    className="w-full md:w-auto"
                    disabled={!canCreateRfq}
                >
                    <Plus className="mr-2 h-4 w-4" />
                    New RFQ
                </Button>
            </div>

            <Card className="gap-0">
                <CardHeader className="gap-4">
                    <Tabs
                        defaultValue={DEFAULT_TAB}
                        value={activeTab}
                        onValueChange={(value) =>
                            applyParams({ tab: value }, { resetPage: true })
                        }
                    >
                        <TabsList>
                            {tabOptions.map((tab) => (
                                <TabsTrigger key={tab.value} value={tab.value} className="flex flex-col">
                                    <span className="text-sm font-medium">{tab.label}</span>
                                    <span className="text-xs font-normal text-muted-foreground">
                                        {tab.description}
                                    </span>
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </Tabs>
                </CardHeader>
                <CardContent className="flex flex-col gap-4 pb-6">
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div className="flex w-full flex-col gap-3 md:flex-row md:items-center md:gap-4">
                            <div className="relative w-full md:max-w-xs">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={searchTerm}
                                    onChange={(event) => setSearchTerm(event.target.value)}
                                    placeholder="Search RFQs by title, material, or supplier"
                                    className="pl-9"
                                />
                            </div>
                        </div>
                        <div className="flex items-center gap-2 md:justify-end">
                            <Select
                                value={`${sort}:${sortDirection}`}
                                onValueChange={(value) => {
                                    const [nextSort, nextDirection] = value.split(':') as [
                                        ListRfqsSortEnum,
                                        ListRfqsSortDirectionEnum,
                                    ];
                                    applyParams({ sort: nextSort, direction: nextDirection });
                                }}
                            >
                                <SelectTrigger className="w-[220px]">
                                    <SelectValue placeholder="Sort RFQs" />
                                </SelectTrigger>
                                <SelectContent>
                                    {sortOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {isFetching && !isLoading ? <Spinner aria-hidden className="text-muted-foreground" /> : null}
                        </div>
                    </div>

                    {hasError ? (
                        <Alert variant="destructive">
                            <AlertTitle>Unable to load RFQs</AlertTitle>
                            <AlertDescription>
                                Something went wrong while fetching RFQs. Please retry or adjust your filters.
                            </AlertDescription>
                        </Alert>
                    ) : null}
                </CardContent>
            </Card>

            {isLoading ? (
                <div className="grid gap-4">
                    {Array.from({ length: 4 }).map((_, index) => (
                        <RfqCardSkeleton key={index} />
                    ))}
                </div>
            ) : null}

            {!isLoading && items.length === 0 && !hasError ? (
                <Card>
                    <CardHeader>
                        <CardTitle>No RFQs yet</CardTitle>
                        <CardDescription>
                            Create your first RFQ to request quotes, share drawings, and invite suppliers.
                        </CardDescription>
                    </CardHeader>
                    {canCreateRfq ? (
                        <CardFooter className="justify-start">
                            <Button onClick={() => navigate('/app/rfqs/new')}>
                                <Plus className="mr-2 h-4 w-4" />
                                Start an RFQ
                            </Button>
                        </CardFooter>
                    ) : null}
                </Card>
            ) : null}

            {!isLoading && items.length > 0 ? (
                <div className="grid gap-4">
                    {items.map((rfq) => (
                        <RfqListCard key={rfq.id} rfq={rfq} />
                    ))}
                </div>
            ) : null}

            {meta && meta.lastPage > 1 ? (
                <div className="flex items-center justify-between gap-3 rounded-lg border bg-card px-4 py-3 text-sm">
                    <span className="text-muted-foreground">
                        Page {meta.currentPage} of {meta.lastPage}
                    </span>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={meta.currentPage <= 1}
                            onClick={() => applyParams({ page: String(meta.currentPage - 1) })}
                        >
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={meta.currentPage >= meta.lastPage}
                            onClick={() => applyParams({ page: String(meta.currentPage + 1) })}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            ) : null}
        </section>
    );
}

function RfqListCard({ rfq }: { rfq: Rfq }) {
    const status = statusStyles[rfq.status] ?? statusStyles[RfqStatusEnum.Awaiting];
    const dueLabel = rfq.deadlineAt ? dateFormatter.format(rfq.deadlineAt) : 'No due date';
    const sentLabel = rfq.sentAt ? dateFormatter.format(rfq.sentAt) : 'Not yet sent';
    const itemCount = rfq.items?.length ?? 0;
    const quoteCount = rfq.quotes?.length ?? 0;

    return (
        <Card className="transition hover:border-brand-accent/50">
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1">
                    <CardTitle className="text-lg">{rfq.number ?? `RFQ ${rfq.id.slice(0, 8)}`}</CardTitle>
                    <CardDescription className="line-clamp-2 text-sm">
                        {rfq.itemName || 'Untitled RFQ'}
                    </CardDescription>
                    <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                        <span className="font-medium text-foreground">Method:</span>
                        <span>{toTitleCase(rfq.method)}</span>
                        <span className="font-medium text-foreground">Material:</span>
                        <span>{toTitleCase(rfq.material)}</span>
                    </div>
                </div>
                <div className="flex flex-col items-end gap-2">
                    <Badge variant="secondary" className={cn('uppercase', status.className)}>
                        {status.label}
                    </Badge>
                    <Badge variant="outline" className="border-brand-accent/40 text-xs text-brand-primary">
                        {rfq.isOpenBidding ? 'Open bidding' : 'Direct invite'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-3 text-sm text-muted-foreground md:grid-cols-2">
                    <div className="flex flex-col gap-1 rounded-lg border bg-muted/40 px-3 py-2">
                        <span className="text-xs uppercase tracking-wide text-muted-foreground/80">Due</span>
                        <span className="font-medium text-foreground">{dueLabel}</span>
                    </div>
                    <div className="flex flex-col gap-1 rounded-lg border bg-muted/40 px-3 py-2">
                        <span className="text-xs uppercase tracking-wide text-muted-foreground/80">Sent</span>
                        <span className="font-medium text-foreground">{sentLabel}</span>
                    </div>
                    <div className="flex flex-col gap-1 rounded-lg border bg-muted/40 px-3 py-2">
                        <span className="text-xs uppercase tracking-wide text-muted-foreground/80">Line items</span>
                        <span className="font-medium text-foreground">{itemCount}</span>
                    </div>
                    <div className="flex flex-col gap-1 rounded-lg border bg-muted/40 px-3 py-2">
                        <span className="text-xs uppercase tracking-wide text-muted-foreground/80">Quotes</span>
                        <span className="font-medium text-foreground">{quoteCount}</span>
                    </div>
                </div>
                <div className="flex items-center justify-between gap-3">
                    <div className="text-sm text-muted-foreground">
                        Last updated {rfq.updatedAt ? dateFormatter.format(rfq.updatedAt) : 'not available'}
                    </div>
                    <Button variant="ghost" size="sm" asChild>
                        <Link to={`/app/rfqs/${rfq.id}`} className="inline-flex items-center gap-1">
                            View RFQ
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function RfqCardSkeleton() {
    return (
        <Card>
            <CardHeader className="gap-3">
                <Skeleton className="h-5 w-40" />
                <Skeleton className="h-4 w-56" />
                <div className="flex gap-2">
                    <Skeleton className="h-5 w-24" />
                    <Skeleton className="h-5 w-24" />
                </div>
            </CardHeader>
            <CardContent className="space-y-3">
                <div className="grid gap-3 md:grid-cols-2">
                    <Skeleton className="h-16 rounded-lg" />
                    <Skeleton className="h-16 rounded-lg" />
                    <Skeleton className="h-16 rounded-lg" />
                    <Skeleton className="h-16 rounded-lg" />
                </div>
                <div className="flex items-center justify-between">
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="h-9 w-24" />
                </div>
            </CardContent>
        </Card>
    );
}

function toTitleCase(value?: string | null) {
    if (!value) {
        return '—';
    }

    return value
        .split(/[_\s]+/)
        .filter(Boolean)
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
}

function useDebouncedValue<T>(value: T, delay: number) {
    const [debouncedValue, setDebouncedValue] = useState(value);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            setDebouncedValue(value);
        }, delay);

        return () => window.clearTimeout(handle);
    }, [value, delay]);

    return debouncedValue;
}
