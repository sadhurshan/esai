import { FormEvent, useEffect, useMemo, useState } from 'react';
import { BadgeCheck, Filter, Loader2, MapPin, RefreshCw, Search, Sparkles, Trash2 } from 'lucide-react';

import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';
import { EmptyState } from '@/components/empty-state';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useSupplierScrapeJobs } from '@/hooks/api/admin/use-supplier-scrape-jobs';
import { useStartSupplierScrape } from '@/hooks/api/admin/use-start-supplier-scrape';
import { useScrapedSuppliers } from '@/hooks/api/admin/use-scraped-suppliers';
import { useApproveScrapedSupplier } from '@/hooks/api/admin/use-approve-scraped-supplier';
import { useDiscardScrapedSupplier } from '@/hooks/api/admin/use-discard-scraped-supplier';
import type {
    ApproveScrapedSupplierPayload,
    ScrapedSupplier,
    ScrapedSupplierFilters,
    ScrapedSupplierStatus,
    SupplierScrapeJob,
    SupplierScrapeJobFilters,
    StartSupplierScrapePayload,
} from '@/types/admin';
import type { SupplierDocumentType } from '@/types/sourcing';
import { cn } from '@/lib/utils';

const JOBS_PAGE_SIZE = 25;
const RESULTS_PAGE_SIZE = 25;
const SUPER_ADMIN_ROLE = 'platform_super';
const SCRAPED_STATUS_OPTIONS: Array<{ label: string; value: ScrapedSupplierStatus | 'all' }> = [
    { label: 'All', value: 'all' },
    { label: 'Pending', value: 'pending' },
    { label: 'Approved', value: 'approved' },
    { label: 'Discarded', value: 'discarded' },
];
const JOB_STATUS_OPTIONS = [
    { label: 'All', value: 'all' },
    { label: 'Pending', value: 'pending' },
    { label: 'Running', value: 'running' },
    { label: 'Completed', value: 'completed' },
    { label: 'Failed', value: 'failed' },
];
const STATUS_VARIANTS: Record<string, string> = {
    pending: 'border-amber-200 bg-amber-50 text-amber-800',
    running: 'border-sky-200 bg-sky-50 text-sky-800',
    completed: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    failed: 'border-rose-200 bg-rose-50 text-rose-800',
};
const SCRAPED_STATUS_VARIANTS: Record<string, string> = {
    pending: 'bg-slate-100 text-slate-700',
    approved: 'bg-emerald-100 text-emerald-800',
    discarded: 'bg-rose-100 text-rose-800',
};
const ATTACHMENT_TYPES: Array<{ label: string; value: SupplierDocumentType }> = [
    { label: 'ISO 9001', value: 'iso9001' },
    { label: 'ISO 14001', value: 'iso14001' },
    { label: 'AS9100', value: 'as9100' },
    { label: 'ITAR', value: 'itar' },
    { label: 'REACH', value: 'reach' },
    { label: 'ROHS', value: 'rohs' },
    { label: 'Insurance', value: 'insurance' },
    { label: 'NDA', value: 'nda' },
    { label: 'Other', value: 'other' },
];

interface JobFilterFormState {
    status: string;
    query: string;
    region: string;
    createdFrom: string;
    createdTo: string;
}

interface StartFormState {
    query: string;
    region: string;
    maxResults: string;
}

interface ResultFilterFormState {
    search: string;
    status: string;
    minConfidence: string;
    maxConfidence: string;
}

interface ReviewFormState {
    name: string;
    website: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    state: string;
    country: string;
    productSummary: string;
    certifications: string;
    notes: string;
    leadTimeDays: string;
    moq: string;
    attachment: File | null;
    attachmentType: SupplierDocumentType;
}

const DEFAULT_FILTER_FORM: JobFilterFormState = {
    status: 'all',
    query: '',
    region: '',
    createdFrom: '',
    createdTo: '',
};

const BASE_JOB_FILTERS: SupplierScrapeJobFilters = {
    perPage: JOBS_PAGE_SIZE,
};

const DEFAULT_START_FORM: StartFormState = {
    query: '',
    region: '',
    maxResults: '10',
};

const DEFAULT_RESULT_FILTER_FORM: ResultFilterFormState = {
    search: '',
    status: 'all',
    minConfidence: '',
    maxConfidence: '',
};

const EMPTY_REVIEW_FORM: ReviewFormState = {
    name: '',
    website: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    state: '',
    country: '',
    productSummary: '',
    certifications: '',
    notes: '',
    leadTimeDays: '',
    moq: '',
    attachment: null,
    attachmentType: 'iso9001',
};

export function AdminSupplierScrapePage() {
    const { state } = useAuth();
    const { formatDate, formatNumber } = useFormatting();
    const isSuperAdmin = state.user?.role === SUPER_ADMIN_ROLE;

    const [autoRefresh, setAutoRefresh] = useState(true);
    const [filterForm, setFilterForm] = useState<JobFilterFormState>(DEFAULT_FILTER_FORM);
    const [jobFilters, setJobFilters] = useState<SupplierScrapeJobFilters>(() => ({ ...BASE_JOB_FILTERS }));
    const [startForm, setStartForm] = useState<StartFormState>(DEFAULT_START_FORM);
    const [selectedJob, setSelectedJob] = useState<SupplierScrapeJob | null>(null);
    const [resultFilterForm, setResultFilterForm] = useState<ResultFilterFormState>(DEFAULT_RESULT_FILTER_FORM);
    const [resultFilters, setResultFilters] = useState<ScrapedSupplierFilters>({ perPage: RESULTS_PAGE_SIZE });
    const [reviewForm, setReviewForm] = useState<ReviewFormState>(EMPTY_REVIEW_FORM);
    const [reviewSupplier, setReviewSupplier] = useState<ScrapedSupplier | null>(null);

    const jobsQuery = useSupplierScrapeJobs(jobFilters, {
        enabled: isSuperAdmin,
        refetchInterval: isSuperAdmin && autoRefresh ? 15_000 : false,
    });
    const startMutation = useStartSupplierScrape();
    const resultsQuery = useScrapedSuppliers(selectedJob?.id ?? null, resultFilters, {
        enabled: Boolean(selectedJob),
        refetchInterval: selectedJob && autoRefresh ? 20_000 : false,
    });
    const approveMutation = useApproveScrapedSupplier();
    const discardMutation = useDiscardScrapedSupplier();

    const jobs = jobsQuery.data?.items ?? [];
    const jobsMeta = jobsQuery.data?.meta;
    const results = resultsQuery.data?.items ?? [];
    const resultsMeta = resultsQuery.data?.meta;

    const selectedJobStatus = selectedJob?.status ?? null;
    const selectedJobScope = selectedJob?.company_id ? `Queued via company #${selectedJob.company_id}` : 'Queued without tenant scope';
    const emptyStateIcon = <Search className="h-10 w-10" aria-hidden />;
    const resultContext = useMemo(
        () => ({
            pending: results.filter((supplier) => supplier.status === 'pending').length,
            approved: results.filter((supplier) => supplier.status === 'approved').length,
            discarded: results.filter((supplier) => supplier.status === 'discarded').length,
        }),
        [results],
    );

    useEffect(() => {
        if (!selectedJob) {
            setResultFilterForm(DEFAULT_RESULT_FILTER_FORM);
            setResultFilters({ perPage: RESULTS_PAGE_SIZE });
        }
    }, [selectedJob]);

    useEffect(() => {
        if (!reviewSupplier) {
            setReviewForm(EMPTY_REVIEW_FORM);
            return;
        }

        setReviewForm({
            name: reviewSupplier.name ?? '',
            website: reviewSupplier.website ?? '',
            email: reviewSupplier.email ?? '',
            phone: reviewSupplier.phone ?? '',
            address: reviewSupplier.address ?? '',
            city: reviewSupplier.city ?? '',
            state: reviewSupplier.state ?? '',
            country: reviewSupplier.country ?? '',
            productSummary: reviewSupplier.product_summary ?? reviewSupplier.description ?? '',
            certifications: (reviewSupplier.certifications ?? []).join('\n'),
            notes: reviewSupplier.review_notes ?? '',
            leadTimeDays: '',
            moq: '',
            attachment: null,
            attachmentType: 'iso9001',
        });
    }, [reviewSupplier]);

    if (!isSuperAdmin) {
        return <AccessDeniedPage />;
    }

    const applyJobFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const createdFrom = filterForm.createdFrom.trim();
        const createdTo = filterForm.createdTo.trim();

        setJobFilters({
            ...BASE_JOB_FILTERS,
            cursor: null,
            status: filterForm.status !== 'all' ? filterForm.status : undefined,
            query: filterForm.query.trim() || undefined,
            region: filterForm.region.trim() || undefined,
            createdFrom: createdFrom || undefined,
            createdTo: createdTo || undefined,
        });
    };

    const resetJobFilters = () => {
        setFilterForm(DEFAULT_FILTER_FORM);
        setJobFilters({ ...BASE_JOB_FILTERS });
    };

    const handleStartSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const maxResults = normalizeNumber(startForm.maxResults);

        if (!startForm.query.trim()) {
            publishToast({ title: 'Search keywords required', description: 'Provide a query.', variant: 'destructive' });
            return;
        }

        if (!maxResults || maxResults < 1 || maxResults > 25) {
            publishToast({
                title: 'Max results out of range',
                description: 'Pick a value between 1 and 25.',
                variant: 'destructive',
            });
            return;
        }

        const payload: StartSupplierScrapePayload = {
            query: startForm.query.trim(),
            region: startForm.region.trim() || undefined,
            maxResults,
        };

        startMutation.mutate(payload);
    };

    const openJobDetails = (job: SupplierScrapeJob) => {
        setSelectedJob(job);
        setResultFilters({ perPage: RESULTS_PAGE_SIZE });
    };

    const closeJobDetails = () => {
        setSelectedJob(null);
    };

    const applyResultFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const minValue = normalizeConfidence(resultFilterForm.minConfidence);
        const maxValue = normalizeConfidence(resultFilterForm.maxConfidence);

        if (minValue !== null && (minValue < 0 || minValue > 1)) {
            publishToast({ title: 'Min confidence invalid', description: 'Use a value between 0 and 1.', variant: 'destructive' });
            return;
        }

        if (maxValue !== null && (maxValue < 0 || maxValue > 1)) {
            publishToast({ title: 'Max confidence invalid', description: 'Use a value between 0 and 1.', variant: 'destructive' });
            return;
        }

        if (minValue !== null && maxValue !== null && maxValue < minValue) {
            publishToast({ title: 'Confidence range invalid', description: 'Max should be greater than min.', variant: 'destructive' });
            return;
        }

        setResultFilters({
            perPage: RESULTS_PAGE_SIZE,
            cursor: null,
            search: resultFilterForm.search.trim() || undefined,
            status: resultFilterForm.status !== 'all' ? resultFilterForm.status : undefined,
            minConfidence: minValue ?? undefined,
            maxConfidence: maxValue ?? undefined,
        });
    };

    const resetResultFilters = () => {
        setResultFilterForm(DEFAULT_RESULT_FILTER_FORM);
        setResultFilters({ perPage: RESULTS_PAGE_SIZE });
    };

    const openReviewModal = (supplier: ScrapedSupplier) => {
        setReviewSupplier(supplier);
    };

    const closeReviewModal = () => {
        setReviewSupplier(null);
        setReviewForm(EMPTY_REVIEW_FORM);
    };

    const handleApproveSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!selectedJob || !reviewSupplier) {
            return;
        }

        if (!reviewForm.name.trim()) {
            publishToast({ title: 'Name required', description: 'Provide a legal name.', variant: 'destructive' });
            return;
        }

        const payload: ApproveScrapedSupplierPayload = {
            name: reviewForm.name.trim(),
            website: reviewForm.website.trim() || undefined,
            email: reviewForm.email.trim() || undefined,
            phone: reviewForm.phone.trim() || undefined,
            address: reviewForm.address.trim() || undefined,
            city: reviewForm.city.trim() || undefined,
            state: reviewForm.state.trim() || undefined,
            country: reviewForm.country.trim() || undefined,
            productSummary: reviewForm.productSummary.trim() || undefined,
            certifications: parseMultiline(reviewForm.certifications),
            notes: reviewForm.notes.trim() || undefined,
            leadTimeDays: normalizeNumber(reviewForm.leadTimeDays) ?? undefined,
            moq: normalizeNumber(reviewForm.moq) ?? undefined,
            attachment: reviewForm.attachment,
            attachmentType: reviewForm.attachment ? reviewForm.attachmentType : undefined,
        };

        approveMutation.mutate(
            {
                jobId: selectedJob.id,
                scrapedSupplierId: reviewSupplier.id,
                payload,
            },
            {
                onSuccess: () => {
                    closeReviewModal();
                },
            },
        );
    };

    const handleDiscard = (supplier: ScrapedSupplier) => {
        if (!selectedJob) {
            return;
        }
        const confirmed = window.confirm(`Discard ${supplier.name ?? 'this supplier'}?`);
        if (!confirmed) {
            return;
        }

        discardMutation.mutate({ jobId: selectedJob.id, scrapedSupplierId: supplier.id });
    };

    const reviewing = Boolean(reviewSupplier);
    const disableActions = approveMutation.isPending || discardMutation.isPending;

    return (
        <div className="space-y-8">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <Heading
                    title="Supplier discovery"
                    description="Launch scrape jobs across tenants or globally, then triage AI-enriched supplier leads."
                />
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline" className="uppercase tracking-wide">
                        Super admin
                    </Badge>
                    <div className="flex items-center gap-2 rounded-md border px-3 py-1 text-sm">
                        <Checkbox id="auto-refresh" checked={autoRefresh} onCheckedChange={(value) => setAutoRefresh(Boolean(value))} />
                        <Label htmlFor="auto-refresh" className="cursor-pointer select-none text-xs font-semibold uppercase tracking-wide">
                            Auto-refresh 15s
                        </Label>
                    </div>
                    <Button type="button" variant="outline" size="sm" onClick={() => jobsQuery.refetch()} disabled={jobsQuery.isFetching}>
                        <RefreshCw className="mr-2 h-4 w-4" aria-hidden /> Refresh
                    </Button>
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Start supplier scrape</CardTitle>
                        <CardDescription>Provide the keywords to queue a new discovery job.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="grid gap-4" onSubmit={handleStartSubmit}>
                            <div className="space-y-2">
                                <Label htmlFor="start-region">Region</Label>
                                <Input
                                    id="start-region"
                                    placeholder="North America"
                                    value={startForm.region}
                                    onChange={(event) => setStartForm((prev) => ({ ...prev, region: event.target.value }))}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="start-query">Search keywords</Label>
                                <Input
                                    id="start-query"
                                    placeholder="precision cnc machining aerospace"
                                    value={startForm.query}
                                    onChange={(event) => setStartForm((prev) => ({ ...prev, query: event.target.value }))}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="start-max">Max results</Label>
                                <Input
                                    id="start-max"
                                    type="number"
                                    min={1}
                                    max={25}
                                    value={startForm.maxResults}
                                    onChange={(event) => setStartForm((prev) => ({ ...prev, maxResults: event.target.value }))}
                                />
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button type="reset" variant="ghost" onClick={() => setStartForm(DEFAULT_START_FORM)}>
                                    Clear
                                </Button>
                                <Button type="submit" disabled={startMutation.isPending}>
                                    {startMutation.isPending ? (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden />
                                    ) : (
                                        <Sparkles className="mr-2 h-4 w-4" aria-hidden />
                                    )}
                                    Start scrape
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Job filters</CardTitle>
                        <CardDescription>Scope job history by keyword, region, or date range.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="grid gap-4" onSubmit={applyJobFilters}>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="filter-status">Status</Label>
                                    <Select
                                        value={filterForm.status}
                                        onValueChange={(value) => setFilterForm((prev) => ({ ...prev, status: value }))}
                                    >
                                        <SelectTrigger id="filter-status">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {JOB_STATUS_OPTIONS.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="filter-query">Keywords</Label>
                                    <Input
                                        id="filter-query"
                                        placeholder="Additive manufacturing"
                                        value={filterForm.query}
                                        onChange={(event) => setFilterForm((prev) => ({ ...prev, query: event.target.value }))}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="filter-region">Region</Label>
                                    <Input
                                        id="filter-region"
                                        placeholder="EMEA"
                                        value={filterForm.region}
                                        onChange={(event) => setFilterForm((prev) => ({ ...prev, region: event.target.value }))}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="filter-created-from">Created from</Label>
                                    <Input
                                        id="filter-created-from"
                                        type="date"
                                        value={filterForm.createdFrom}
                                        onChange={(event) => setFilterForm((prev) => ({ ...prev, createdFrom: event.target.value }))}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="filter-created-to">Created to</Label>
                                    <Input
                                        id="filter-created-to"
                                        type="date"
                                        value={filterForm.createdTo}
                                        onChange={(event) => setFilterForm((prev) => ({ ...prev, createdTo: event.target.value }))}
                                    />
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button type="button" variant="ghost" onClick={resetJobFilters}>
                                    Clear
                                </Button>
                                <Button type="submit">
                                    <Filter className="mr-2 h-4 w-4" aria-hidden /> Apply filters
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Scrape jobs</CardTitle>
                    <CardDescription>Monitor pending jobs and review completion metrics.</CardDescription>
                </CardHeader>
                <CardContent>
                    {jobsQuery.isLoading ? (
                        <div className="flex justify-center py-12">
                            <Spinner />
                        </div>
                    ) : jobFilters ? (
                        jobs.length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Query</TableHead>
                                            <TableHead>Region</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Started</TableHead>
                                            <TableHead>Finished</TableHead>
                                            <TableHead className="text-right">Results</TableHead>
                                            <TableHead>
                                                <span className="sr-only">Actions</span>
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {jobs.map((job) => (
                                            <TableRow key={job.id} className={cn(selectedJob?.id === job.id ? 'bg-muted/30' : undefined)}>
                                                <TableCell>
                                                    <div className="font-medium">{job.query}</div>
                                                    <div className="text-xs text-muted-foreground">#{job.id}</div>
                                                </TableCell>
                                                <TableCell>
                                                    {job.region ? (
                                                        <div className="flex items-center gap-1 text-sm">
                                                            <MapPin className="h-4 w-4 text-muted-foreground" aria-hidden />
                                                            {job.region}
                                                        </div>
                                                    ) : (
                                                        <span className="text-sm text-muted-foreground">—</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline" className={cn('capitalize', STATUS_VARIANTS[job.status ?? ''] ?? 'bg-muted')}>
                                                        {job.status ?? 'unknown'}
                                                    </Badge>
                                                    {job.error_message ? (
                                                        <p className="text-xs text-red-600">{job.error_message}</p>
                                                    ) : null}
                                                </TableCell>
                                                <TableCell>{formatDate(job.started_at, { dateStyle: 'medium', timeStyle: 'short' }) ?? '—'}</TableCell>
                                                <TableCell>{formatDate(job.finished_at, { dateStyle: 'medium', timeStyle: 'short' }) ?? '—'}</TableCell>
                                                <TableCell className="text-right text-sm font-medium">
                                                    {job.result_count != null ? formatNumber(job.result_count) : '—'}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button type="button" variant="outline" size="sm" onClick={() => openJobDetails(job)}>
                                                        View results
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <EmptyState
                                icon={emptyStateIcon}
                                title="No jobs found"
                                description="Adjust your filters or start a new scrape."
                            />
                        )
                    ) : (
                        <EmptyState
                            icon={emptyStateIcon}
                            title="Apply filters"
                            description="Set optional filters above to load scrape jobs."
                        />
                    )}
                </CardContent>
                {jobsMeta && (jobsMeta.nextCursor || jobsMeta.prevCursor) ? (
                    <CardFooter className="flex justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setJobFilters((prev) => ({ ...prev, cursor: jobsMeta.prevCursor ?? null }))
                            }
                            disabled={!jobsMeta.prevCursor || jobsQuery.isFetching}
                        >
                            Previous
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setJobFilters((prev) => ({ ...prev, cursor: jobsMeta.nextCursor ?? null }))
                            }
                            disabled={!jobsMeta.nextCursor || jobsQuery.isFetching}
                        >
                            Next
                        </Button>
                    </CardFooter>
                ) : null}
            </Card>

            <Sheet open={Boolean(selectedJob)} onOpenChange={(open) => (open ? null : closeJobDetails())}>
                <SheetContent side="right" className="w-full max-w-4xl">
                    <SheetHeader>
                        <SheetTitle>Scraped suppliers</SheetTitle>
                        <SheetDescription>
                            {selectedJob ? `Job #${selectedJob.id} • ${selectedJob.query}` : 'Select a job to view details.'}
                        </SheetDescription>
                    </SheetHeader>
                    {selectedJob ? (
                        <div className="mt-4 flex h-[calc(100vh-6rem)] flex-col gap-4">
                            <Card>
                                <CardHeader className="space-y-1">
                                    <CardTitle>Job summary</CardTitle>
                                    <CardDescription>{selectedJobScope}</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-2 text-sm">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="outline" className={cn('capitalize', STATUS_VARIANTS[selectedJobStatus ?? ''] ?? 'bg-muted')}>
                                            {selectedJobStatus ?? 'unknown'}
                                        </Badge>
                                        <Badge variant="secondary">{selectedJob.result_count ?? 0} candidates</Badge>
                                    </div>
                                    <div className="text-muted-foreground">
                                        Started {formatDate(selectedJob.started_at, { dateStyle: 'medium', timeStyle: 'short' }) ?? '—'} | Finished{' '}
                                        {formatDate(selectedJob.finished_at, { dateStyle: 'medium', timeStyle: 'short' }) ?? '—'}
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Result filters</CardTitle>
                                    <CardDescription>Search scraped candidates and filter by review status.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form className="grid gap-4" onSubmit={applyResultFilters}>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="result-search">Search</Label>
                                                <Input
                                                    id="result-search"
                                                    placeholder="supplier or website"
                                                    value={resultFilterForm.search}
                                                    onChange={(event) => setResultFilterForm((prev) => ({ ...prev, search: event.target.value }))}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="result-status">Status</Label>
                                                <Select
                                                    value={resultFilterForm.status}
                                                    onValueChange={(value) => setResultFilterForm((prev) => ({ ...prev, status: value }))}
                                                >
                                                    <SelectTrigger id="result-status">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {SCRAPED_STATUS_OPTIONS.map((option) => (
                                                            <SelectItem key={option.value} value={option.value}>
                                                                {option.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="result-min-confidence">Min confidence</Label>
                                                <Input
                                                    id="result-min-confidence"
                                                    type="number"
                                                    step="0.1"
                                                    min={0}
                                                    max={1}
                                                    value={resultFilterForm.minConfidence}
                                                    onChange={(event) =>
                                                        setResultFilterForm((prev) => ({ ...prev, minConfidence: event.target.value }))
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="result-max-confidence">Max confidence</Label>
                                                <Input
                                                    id="result-max-confidence"
                                                    type="number"
                                                    step="0.1"
                                                    min={0}
                                                    max={1}
                                                    value={resultFilterForm.maxConfidence}
                                                    onChange={(event) =>
                                                        setResultFilterForm((prev) => ({ ...prev, maxConfidence: event.target.value }))
                                                    }
                                                />
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Button type="button" variant="ghost" onClick={resetResultFilters}>
                                                Clear
                                            </Button>
                                            <Button type="submit" size="sm">
                                                Apply
                                            </Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>
                            <div className="flex items-center gap-3 text-sm text-muted-foreground">
                                <Badge>Pending {resultContext.pending}</Badge>
                                <Badge variant="secondary">Approved {resultContext.approved}</Badge>
                                <Badge variant="outline">Discarded {resultContext.discarded}</Badge>
                            </div>
                            <ScrollArea className="flex-1 rounded-md border p-4">
                                {resultsQuery.isLoading ? (
                                    <div className="flex justify-center py-8">
                                        <Spinner />
                                    </div>
                                ) : results.length > 0 ? (
                                    <div className="space-y-4">
                                        {results.map((supplier) => (
                                            <div key={supplier.id} className="rounded-lg border p-4 shadow-sm">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-lg font-semibold">{supplier.name ?? 'Untitled candidate'}</span>
                                                            <Badge
                                                                className={cn(
                                                                    'capitalize',
                                                                    SCRAPED_STATUS_VARIANTS[supplier.status ?? 'pending'] ?? 'bg-muted',
                                                                )}
                                                            >
                                                                {supplier.status ?? 'pending'}
                                                            </Badge>
                                                        </div>
                                                        {supplier.website ? (
                                                            <a
                                                                href={supplier.website}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="text-sm text-primary underline"
                                                            >
                                                                {supplier.website}
                                                            </a>
                                                        ) : null}
                                                    </div>
                                                    <div className="text-right text-sm">
                                                        <div className="font-semibold">
                                                            Confidence {supplier.confidence != null ? `${Math.round(supplier.confidence * 100)}%` : '—'}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            Source {supplier.source_url ?? 'N/A'}
                                                        </div>
                                                    </div>
                                                </div>
                                                {supplier.product_summary || supplier.description ? (
                                                    <p className="mt-2 text-sm text-muted-foreground">
                                                        {supplier.product_summary ?? supplier.description}
                                                    </p>
                                                ) : null}
                                                {supplier.industry_tags && supplier.industry_tags.length > 0 ? (
                                                    <div className="mt-3 flex flex-wrap gap-2">
                                                        {supplier.industry_tags.map((tag) => (
                                                            <Badge key={tag} variant="outline">
                                                                {tag}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                ) : null}
                                                <div className="mt-4 flex flex-wrap gap-2">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() => openReviewModal(supplier)}
                                                        disabled={supplier.status !== 'pending'}
                                                    >
                                                        <BadgeCheck className="mr-2 h-4 w-4" aria-hidden /> Review & onboard
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() => handleDiscard(supplier)}
                                                        disabled={supplier.status !== 'pending' || discardMutation.isPending}
                                                    >
                                                        <Trash2 className="mr-2 h-4 w-4" aria-hidden />
                                                        Discard
                                                    </Button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <EmptyState
                                        icon={emptyStateIcon}
                                        title="No scraped suppliers"
                                        description="Rerun the scrape or adjust filters."
                                    />
                                )}
                            </ScrollArea>
                            {resultsMeta && (resultsMeta.nextCursor || resultsMeta.prevCursor) ? (
                                <div className="flex justify-between">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setResultFilters((prev) => ({ ...prev, cursor: resultsMeta.prevCursor ?? null }))}
                                        disabled={!resultsMeta.prevCursor || resultsQuery.isFetching}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setResultFilters((prev) => ({ ...prev, cursor: resultsMeta.nextCursor ?? null }))}
                                        disabled={!resultsMeta.nextCursor || resultsQuery.isFetching}
                                    >
                                        Next
                                    </Button>
                                </div>
                            ) : null}
                        </div>
                    ) : (
                        <div className="flex flex-1 items-center justify-center">
                            <EmptyState icon={emptyStateIcon} title="Select a job" description="Choose a job to inspect the scraped suppliers." />
                        </div>
                    )}
                </SheetContent>
            </Sheet>

            <Dialog open={reviewing} onOpenChange={(open) => (open ? null : closeReviewModal())}>
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Review scraped supplier</DialogTitle>
                        <DialogDescription>Verify the extracted fields before onboarding.</DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={handleApproveSubmit}>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="review-name">Legal name</Label>
                                <Input
                                    id="review-name"
                                    required
                                    value={reviewForm.name}
                                    onChange={(event) => setReviewForm((prev) => ({ ...prev, name: event.target.value }))}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="review-website">Website</Label>
                                <Input
                                    id="review-website"
                                    value={reviewForm.website}
                                    onChange={(event) => setReviewForm((prev) => ({ ...prev, website: event.target.value }))}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="review-email">Email</Label>
                                <Input
                                    id="review-email"
                                    value={reviewForm.email}
                                    onChange={(event) => setReviewForm((prev) => ({ ...prev, email: event.target.value }))}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="review-phone">Phone</Label>
                                <Input
                                    id="review-phone"
                                    value={reviewForm.phone}
                                    onChange={(event) => setReviewForm((prev) => ({ ...prev, phone: event.target.value }))}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="review-address">Address</Label>
                                <Input
                                    id="review-address"
                                    value={reviewForm.address}
                                    onChange={(event) => setReviewForm((prev) => ({ ...prev, address: event.target.value }))}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="review-city">City</Label>
                                <Input
                                    id="review-city"
                                    value={reviewForm.city}
                                    onChange={(event) => setReviewForm((prev) => ({ ...prev, city: event.target.value }))}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="review-state">State / Province</Label>
                                <Input
                                    id="review-state"
                                    value={reviewForm.state}
                                    onChange={(event) => setReviewForm((prev) => ({ ...prev, state: event.target.value }))}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="review-country">Country (ISO)</Label>
                                <Input
                                    id="review-country"
                                    maxLength={2}
                                    value={reviewForm.country}
                                    onChange={(event) => setReviewForm((prev) => ({ ...prev, country: event.target.value.toUpperCase() }))}
                                />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="review-summary">Product summary</Label>
                            <Textarea
                                id="review-summary"
                                rows={3}
                                value={reviewForm.productSummary}
                                onChange={(event) => setReviewForm((prev) => ({ ...prev, productSummary: event.target.value }))}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="review-certifications">Certifications (one per line)</Label>
                            <Textarea
                                id="review-certifications"
                                rows={3}
                                value={reviewForm.certifications}
                                onChange={(event) => setReviewForm((prev) => ({ ...prev, certifications: event.target.value }))}
                            />
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="review-lead-time">Lead time (days)</Label>
                                <Input
                                    id="review-lead-time"
                                    type="number"
                                    value={reviewForm.leadTimeDays}
                                    onChange={(event) => setReviewForm((prev) => ({ ...prev, leadTimeDays: event.target.value }))}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="review-moq">MOQ</Label>
                                <Input
                                    id="review-moq"
                                    type="number"
                                    value={reviewForm.moq}
                                    onChange={(event) => setReviewForm((prev) => ({ ...prev, moq: event.target.value }))}
                                />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="review-notes">Internal notes</Label>
                            <Textarea
                                id="review-notes"
                                rows={3}
                                value={reviewForm.notes}
                                onChange={(event) => setReviewForm((prev) => ({ ...prev, notes: event.target.value }))}
                            />
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="review-attachment">Attachment</Label>
                                <Input
                                    id="review-attachment"
                                    type="file"
                                    onChange={(event) =>
                                        setReviewForm((prev) => ({
                                            ...prev,
                                            attachment: event.target.files?.[0] ?? null,
                                        }))
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="review-attachment-type">Attachment type</Label>
                                <Select
                                    value={reviewForm.attachmentType}
                                    onValueChange={(value) => setReviewForm((prev) => ({ ...prev, attachmentType: value as SupplierDocumentType }))}
                                    disabled={!reviewForm.attachment}
                                >
                                    <SelectTrigger id="review-attachment-type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {ATTACHMENT_TYPES.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={closeReviewModal}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={disableActions}>
                                {approveMutation.isPending ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden />
                                ) : (
                                    <BadgeCheck className="mr-2 h-4 w-4" aria-hidden />
                                )}
                                Approve & onboard
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function normalizeNumber(value: string): number | null {
    if (!value) {
        return null;
    }
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}

function normalizeConfidence(value: string): number | null {
    if (!value) {
        return null;
    }
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}

function parseMultiline(value: string): string[] | undefined {
    if (!value.trim()) {
        return undefined;
    }
    const lines = value
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);
    return lines.length > 0 ? lines : undefined;
}
