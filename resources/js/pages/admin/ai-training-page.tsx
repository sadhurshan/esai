import { useMutation } from '@tanstack/react-query';
import {
    Activity,
    Brain,
    CalendarClock,
    Database,
    Gauge,
    RefreshCcw,
    ShieldCheck,
    Sparkles,
    UploadCloud,
    Workflow,
} from 'lucide-react';
import type { Dispatch, SetStateAction } from 'react';
import { FormEvent, useMemo, useState } from 'react';

import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useAiTrainingJobs } from '@/hooks/api/admin/use-ai-training-jobs';
import { cn } from '@/lib/utils';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import { AdminConsoleApi } from '@/sdk';
import type {
    ModelTrainingJob,
    ModelTrainingJobFilters,
    StartAiTrainingPayload,
    UploadAiTrainingDatasetResponse,
} from '@/types/admin';

const FEATURE_CARDS = [
    {
        key: 'forecast',
        label: 'Forecasting',
        description: 'Retrains demand + supply time-series models.',
        icon: Activity,
        metricKeys: ['mape', 'mae', 'rmse'],
    },
    {
        key: 'risk',
        label: 'Supplier risk',
        description: 'Updates credit + risk calibration weights.',
        icon: ShieldCheck,
        metricKeys: ['accuracy', 'f1', 'auc'],
    },
    {
        key: 'rag',
        label: 'RAG / Search',
        description: 'Refreshes embeddings + document clusters.',
        icon: Database,
        metricKeys: ['documents_indexed', 'duration_seconds'],
    },
    {
        key: 'actions',
        label: 'Deterministic actions',
        description: 'Recomputes heuristic playbooks for copilots.',
        icon: Sparkles,
        metricKeys: ['actions_refreshed', 'latency_ms'],
    },
    {
        key: 'workflows',
        label: 'Workflow copilots',
        description: 'Regenerates reviewer routing + guardrails.',
        icon: Workflow,
        metricKeys: ['templates_updated', 'latency_ms'],
    },
    {
        key: 'chat',
        label: 'Conversational AI',
        description: 'Fine-tunes assistants (chat feature gated).',
        icon: Brain,
        metricKeys: ['sessions_sampled'],
    },
] as const;

const STATUS_VARIANTS: Record<string, string> = {
    pending: 'border-amber-500 text-amber-600',
    running: 'border-sky-500 text-sky-600',
    completed: 'border-emerald-500 text-emerald-600',
    failed: 'border-rose-500 text-rose-600',
};

const FILTER_ANY_VALUE = '__any';

const DEFAULT_FILTER_FORM = {
    feature: FILTER_ANY_VALUE,
    status: FILTER_ANY_VALUE,
    companyId: '',
    startedFrom: '',
    startedTo: '',
    createdFrom: '',
    createdTo: '',
    jobId: '',
};

const DEFAULT_FORM_VALUES: TrainingFormState = {
    feature: 'forecast',
    companyId: '',
    startDate: '',
    endDate: '',
    horizon: '',
    reindexAll: false,
    datasetUploadId: '',
    additionalParams: '',
    datasetFile: null,
};

const DEFAULT_SCHEDULE_FORM: ScheduleFormState = {
    feature: 'forecast',
    companyId: '',
    cadence: 'weekly',
    dayOfWeek: 'monday',
    time: '02:00',
};

type FeatureKey = (typeof FEATURE_CARDS)[number]['key'];

type TrainingFormState = {
    feature: FeatureKey;
    companyId: string;
    startDate: string;
    endDate: string;
    horizon: string;
    reindexAll: boolean;
    datasetUploadId: string;
    additionalParams: string;
    datasetFile: File | null;
};

type ScheduleFormState = {
    feature: FeatureKey;
    companyId: string;
    cadence: 'daily' | 'weekly' | 'monthly';
    dayOfWeek?: string;
    time: string;
};

export function AdminAiTrainingPage() {
    const { canTrainAi } = useAuth();
    const { formatDate, formatNumber } = useFormatting();
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const [autoRefresh, setAutoRefresh] = useState(true);
    const [filters, setFilters] = useState<ModelTrainingJobFilters>({
        perPage: 50,
    });
    const [filterForm, setFilterForm] = useState(DEFAULT_FILTER_FORM);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [trainingForm, setTrainingForm] =
        useState<TrainingFormState>(DEFAULT_FORM_VALUES);
    const [scheduleForm, setScheduleForm] = useState<ScheduleFormState>(
        DEFAULT_SCHEDULE_FORM,
    );
    const [savedSchedules, setSavedSchedules] = useState<ScheduleFormState[]>(
        [],
    );

    const { data, isLoading, isFetching, refetch } = useAiTrainingJobs(
        filters,
        {
            enabled: canTrainAi,
            refetchInterval: canTrainAi && autoRefresh ? 10_000 : false,
        },
    );

    const jobs = useMemo(() => data?.items ?? [], [data?.items]);
    const latestByFeature = useMemo(() => deriveLatestByFeature(jobs), [jobs]);
    const runningJobIds = useMemo(
        () =>
            new Set(
                jobs
                    .filter((job) => job.status === 'running')
                    .map((job) => job.id),
            ),
        [jobs],
    );

    const startTraining = useMutation({
        mutationFn: (payload: StartAiTrainingPayload) =>
            adminConsoleApi.startAiTraining(payload),
        onSuccess: (job) => {
            publishToast({
                title: 'Training started',
                description: `Job #${job.id} queued (${job.feature}).`,
                variant: 'success',
            });
            setDialogOpen(false);
            setTrainingForm((prev) => ({ ...prev, datasetFile: null }));
            void refetch();
        },
        onError: (error) => {
            publishToast({
                title: 'Unable to start training',
                description:
                    error instanceof Error ? error.message : 'Unexpected error',
                variant: 'destructive',
            });
        },
    });

    const uploadDataset = useMutation({
        mutationFn: (payload: { companyId: number; dataset: File }) =>
            adminConsoleApi.uploadAiTrainingDataset(payload),
        onSuccess: (data) => {
            setTrainingForm((prev) => ({
                ...prev,
                datasetUploadId: data.datasetUploadId,
            }));
            publishToast({
                title: 'Dataset uploaded',
                description: `Stored ${data.filename}.`,
                variant: 'success',
            });
        },
        onError: (error) => {
            publishToast({
                title: 'Dataset upload failed',
                description:
                    error instanceof Error ? error.message : 'Unexpected error',
                variant: 'destructive',
            });
        },
    });

    const refreshJob = useMutation({
        mutationFn: (jobId: string) =>
            adminConsoleApi.refreshAiTrainingJob(jobId),
        onSuccess: () => {
            publishToast({
                title: 'Status updated',
                description: 'Remote snapshot synced.',
                variant: 'success',
            });
            void refetch();
        },
        onError: (error) => {
            publishToast({
                title: 'Refresh failed',
                description:
                    error instanceof Error ? error.message : 'Unexpected error',
                variant: 'destructive',
            });
        },
    });

    if (!canTrainAi) {
        return <AccessDeniedPage />;
    }

    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const normalizedFeature =
            filterForm.feature === FILTER_ANY_VALUE ? '' : filterForm.feature;
        const normalizedStatus =
            filterForm.status === FILTER_ANY_VALUE ? '' : filterForm.status;

        setFilters({
            perPage: filters.perPage ?? 50,
            cursor: null,
            feature: normalizeFilter(normalizedFeature),
            status: normalizeFilter(normalizedStatus),
            companyId: normalizeNumber(filterForm.companyId),
            startedFrom: normalizeFilter(filterForm.startedFrom),
            startedTo: normalizeFilter(filterForm.startedTo),
            createdFrom: normalizeFilter(filterForm.createdFrom),
            createdTo: normalizeFilter(filterForm.createdTo),
            microserviceJobId: normalizeFilter(filterForm.jobId),
        });
    };

    const clearFilters = () => {
        setFilterForm({ ...DEFAULT_FILTER_FORM });
        setFilters({ perPage: filters.perPage ?? 50 });
    };

    const openDialogForFeature = (feature: FeatureKey) => {
        setTrainingForm({ ...DEFAULT_FORM_VALUES, feature });
        setDialogOpen(true);
    };

    const handleTrainingSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const companyId = normalizeNumber(trainingForm.companyId);

        if (!companyId) {
            publishToast({
                title: 'Company required',
                description: 'Enter a valid company ID.',
                variant: 'destructive',
            });
            return;
        }

        const parameterPayload = buildParameterPayload(trainingForm);
        if (parameterPayload === null) {
            return;
        }

        let datasetUploadId = normalizeFilter(trainingForm.datasetUploadId);
        if (trainingForm.datasetFile) {
            try {
                const uploadResponse: UploadAiTrainingDatasetResponse =
                    await uploadDataset.mutateAsync({
                        companyId,
                        dataset: trainingForm.datasetFile,
                    });
                datasetUploadId = uploadResponse.datasetUploadId;
            } catch {
                return;
            }
        }

        const payload: StartAiTrainingPayload = {
            feature: trainingForm.feature,
            companyId,
            startDate: normalizeFilter(trainingForm.startDate),
            endDate: normalizeFilter(trainingForm.endDate),
            horizon: trainingForm.horizon
                ? Number(trainingForm.horizon)
                : undefined,
            reindexAll: trainingForm.reindexAll,
            datasetUploadId,
            parameters: parameterPayload,
        };

        startTraining.mutate(payload);
    };

    const handleScheduleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setSavedSchedules((prev) => [...prev, scheduleForm]);
        publishToast({
            title: 'Schedule staged',
            description: 'Preferences captured locally. Backend hook pending.',
            variant: 'default',
        });
        // TODO: wire to AiTrainingService::scheduleTraining once scheduling endpoint ships (Stage 14).
    };

    const meta = data?.meta;
    const nextCursor = meta?.nextCursor ?? null;
    const prevCursor = meta?.prevCursor ?? null;

    return (
        <div className="space-y-8">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <Heading
                    title="AI training control room"
                    description="Orchestrate retraining runs, inspect telemetry, and keep copilots healthy."
                />
                <div className="flex flex-wrap gap-2">
                    <Badge
                        variant="outline"
                        className="tracking-wide uppercase"
                    >
                        Super admin
                    </Badge>
                    <div className="flex items-center gap-2 rounded-md border px-3 py-1 text-sm">
                        <Checkbox
                            id="auto-refresh"
                            checked={autoRefresh}
                            onCheckedChange={(value) =>
                                setAutoRefresh(Boolean(value))
                            }
                        />
                        <Label
                            htmlFor="auto-refresh"
                            className="cursor-pointer text-xs font-medium tracking-wide uppercase select-none"
                        >
                            Auto-refresh 10s
                        </Label>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => refetch()}
                        disabled={isFetching}
                    >
                        <RefreshCcw className="h-4 w-4" aria-hidden />
                        <span className="sr-only">Refresh</span>
                    </Button>
                </div>
            </div>

            <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {FEATURE_CARDS.map((card) => (
                    <Card
                        key={card.key}
                        className="flex flex-col justify-between"
                    >
                        <CardHeader className="space-y-1">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <card.icon className="h-4 w-4" aria-hidden />
                                {card.label}
                            </div>
                            <CardTitle className="text-xl text-foreground">
                                {summarizeStatus(
                                    latestByFeature.get(card.key)?.status,
                                )}
                            </CardTitle>
                            <CardDescription>
                                {card.description}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <MetricList
                                job={latestByFeature.get(card.key)}
                                metricKeys={card.metricKeys}
                                formatNumber={formatNumber}
                            />
                            <div className="text-sm text-muted-foreground">
                                Last trained:{' '}
                                {formatDate(
                                    latestByFeature.get(card.key)
                                        ?.finished_at ??
                                        latestByFeature.get(card.key)
                                            ?.started_at,
                                    {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    },
                                ) ?? 'Never'}
                            </div>
                        </CardContent>
                        <CardFooter className="flex items-center justify-between">
                            <StatusBadge
                                status={latestByFeature.get(card.key)?.status}
                                running={runningJobIds.has(
                                    latestByFeature.get(card.key)?.id ?? '',
                                )}
                            />
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => openDialogForFeature(card.key)}
                            >
                                Train now
                            </Button>
                        </CardFooter>
                    </Card>
                ))}
            </section>

            <section className="grid gap-4 lg:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Manual filters</CardTitle>
                        <CardDescription>
                            Scope job history by feature, tenant, and time
                            window.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="grid gap-4" onSubmit={applyFilters}>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="filter-feature">
                                        Feature
                                    </Label>
                                    <Select
                                        value={filterForm.feature}
                                        onValueChange={(value) =>
                                            setFilterForm((prev) => ({
                                                ...prev,
                                                feature: value,
                                            }))
                                        }
                                    >
                                        <SelectTrigger id="filter-feature">
                                            <SelectValue placeholder="Any" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                value={FILTER_ANY_VALUE}
                                            >
                                                Any
                                            </SelectItem>
                                            {FEATURE_CARDS.map((card) => (
                                                <SelectItem
                                                    key={card.key}
                                                    value={card.key}
                                                >
                                                    {card.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="filter-status">
                                        Status
                                    </Label>
                                    <Select
                                        value={filterForm.status}
                                        onValueChange={(value) =>
                                            setFilterForm((prev) => ({
                                                ...prev,
                                                status: value,
                                            }))
                                        }
                                    >
                                        <SelectTrigger id="filter-status">
                                            <SelectValue placeholder="Any" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                value={FILTER_ANY_VALUE}
                                            >
                                                Any
                                            </SelectItem>
                                            <SelectItem value="pending">
                                                Pending
                                            </SelectItem>
                                            <SelectItem value="running">
                                                Running
                                            </SelectItem>
                                            <SelectItem value="completed">
                                                Completed
                                            </SelectItem>
                                            <SelectItem value="failed">
                                                Failed
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="filter-company">
                                        Company ID
                                    </Label>
                                    <Input
                                        id="filter-company"
                                        type="number"
                                        placeholder="123"
                                        value={filterForm.companyId}
                                        onChange={(event) =>
                                            setFilterForm((prev) => ({
                                                ...prev,
                                                companyId: event.target.value,
                                            }))
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="filter-job">
                                        Microservice job ID
                                    </Label>
                                    <Input
                                        id="filter-job"
                                        placeholder="job_abc123"
                                        value={filterForm.jobId}
                                        onChange={(event) =>
                                            setFilterForm((prev) => ({
                                                ...prev,
                                                jobId: event.target.value,
                                            }))
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="filter-start-from">
                                        Started from
                                    </Label>
                                    <Input
                                        id="filter-start-from"
                                        type="date"
                                        value={filterForm.startedFrom}
                                        onChange={(event) =>
                                            setFilterForm((prev) => ({
                                                ...prev,
                                                startedFrom: event.target.value,
                                            }))
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="filter-start-to">
                                        Started to
                                    </Label>
                                    <Input
                                        id="filter-start-to"
                                        type="date"
                                        value={filterForm.startedTo}
                                        onChange={(event) =>
                                            setFilterForm((prev) => ({
                                                ...prev,
                                                startedTo: event.target.value,
                                            }))
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="filter-created-from">
                                        Created from
                                    </Label>
                                    <Input
                                        id="filter-created-from"
                                        type="date"
                                        value={filterForm.createdFrom}
                                        onChange={(event) =>
                                            setFilterForm((prev) => ({
                                                ...prev,
                                                createdFrom: event.target.value,
                                            }))
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="filter-created-to">
                                        Created to
                                    </Label>
                                    <Input
                                        id="filter-created-to"
                                        type="date"
                                        value={filterForm.createdTo}
                                        onChange={(event) =>
                                            setFilterForm((prev) => ({
                                                ...prev,
                                                createdTo: event.target.value,
                                            }))
                                        }
                                    />
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={clearFilters}
                                >
                                    Reset
                                </Button>
                                <Button type="submit">Apply</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Recurring schedule</CardTitle>
                        <CardDescription>
                            Queue future re-trains on a cadence.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-3"
                            onSubmit={handleScheduleSubmit}
                        >
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="schedule-feature">
                                        Feature
                                    </Label>
                                    <Select
                                        value={scheduleForm.feature}
                                        onValueChange={(value) =>
                                            setScheduleForm((prev) => ({
                                                ...prev,
                                                feature: value as FeatureKey,
                                            }))
                                        }
                                    >
                                        <SelectTrigger id="schedule-feature">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {FEATURE_CARDS.map((card) => (
                                                <SelectItem
                                                    key={card.key}
                                                    value={card.key}
                                                >
                                                    {card.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="schedule-company">
                                        Company ID
                                    </Label>
                                    <Input
                                        id="schedule-company"
                                        placeholder="Tenant ID"
                                        value={scheduleForm.companyId}
                                        onChange={(event) =>
                                            setScheduleForm((prev) => ({
                                                ...prev,
                                                companyId: event.target.value,
                                            }))
                                        }
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="schedule-cadence">
                                    Cadence
                                </Label>
                                <Select
                                    value={scheduleForm.cadence}
                                    onValueChange={(value) =>
                                        setScheduleForm((prev) => ({
                                            ...prev,
                                            cadence:
                                                value as ScheduleFormState['cadence'],
                                        }))
                                    }
                                >
                                    <SelectTrigger id="schedule-cadence">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="daily">
                                            Daily
                                        </SelectItem>
                                        <SelectItem value="weekly">
                                            Weekly
                                        </SelectItem>
                                        <SelectItem value="monthly">
                                            Monthly
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            {scheduleForm.cadence === 'weekly' ? (
                                <div className="space-y-2">
                                    <Label htmlFor="schedule-day">
                                        Day of week
                                    </Label>
                                    <Select
                                        value={scheduleForm.dayOfWeek}
                                        onValueChange={(value) =>
                                            setScheduleForm((prev) => ({
                                                ...prev,
                                                dayOfWeek: value,
                                            }))
                                        }
                                    >
                                        <SelectTrigger id="schedule-day">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="monday">
                                                Monday
                                            </SelectItem>
                                            <SelectItem value="tuesday">
                                                Tuesday
                                            </SelectItem>
                                            <SelectItem value="wednesday">
                                                Wednesday
                                            </SelectItem>
                                            <SelectItem value="thursday">
                                                Thursday
                                            </SelectItem>
                                            <SelectItem value="friday">
                                                Friday
                                            </SelectItem>
                                            <SelectItem value="saturday">
                                                Saturday
                                            </SelectItem>
                                            <SelectItem value="sunday">
                                                Sunday
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            ) : null}
                            <div className="space-y-2">
                                <Label htmlFor="schedule-time">
                                    Time (UTC)
                                </Label>
                                <Input
                                    id="schedule-time"
                                    type="time"
                                    value={scheduleForm.time}
                                    onChange={(event) =>
                                        setScheduleForm((prev) => ({
                                            ...prev,
                                            time: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                className="flex items-center gap-2"
                            >
                                <CalendarClock
                                    className="h-4 w-4"
                                    aria-hidden
                                />
                                Save schedule
                            </Button>
                            {savedSchedules.length ? (
                                <div className="rounded-md border bg-muted/40 p-3 text-xs text-muted-foreground">
                                    <div className="font-semibold text-foreground">
                                        Upcoming templates
                                    </div>
                                    <ul className="mt-2 space-y-1">
                                        {savedSchedules
                                            .slice(-3)
                                            .map((schedule, index) => (
                                                <li
                                                    key={`${schedule.feature}-${index}`}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Badge variant="outline">
                                                        {schedule.feature}
                                                    </Badge>
                                                    <span>
                                                        {schedule.cadence} @{' '}
                                                        {schedule.time} UTC
                                                        {schedule.dayOfWeek
                                                            ? ` (${schedule.dayOfWeek})`
                                                            : ''}
                                                    </span>
                                                </li>
                                            ))}
                                    </ul>
                                </div>
                            ) : null}
                        </form>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Active jobs</CardTitle>
                        <CardDescription>
                            Jobs still pending or running.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {runningJobIds.size ? (
                            <ul className="space-y-3 text-sm">
                                {jobs
                                    .filter((job) => runningJobIds.has(job.id))
                                    .map((job) => (
                                        <li
                                            key={job.id}
                                            className="rounded-md border bg-muted/40 p-3"
                                        >
                                            <div className="flex items-center justify-between gap-3">
                                                <div>
                                                    <div className="font-semibold text-foreground">
                                                        {job.feature}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        Company #
                                                        {job.company_id}
                                                    </div>
                                                </div>
                                                <Spinner className="h-5 w-5" />
                                            </div>
                                            <div className="mt-2 text-xs text-muted-foreground">
                                                Started{' '}
                                                {formatDate(job.started_at, {
                                                    dateStyle: 'medium',
                                                    timeStyle: 'short',
                                                }) ?? '—'}
                                            </div>
                                        </li>
                                    ))}
                            </ul>
                        ) : (
                            <EmptyState
                                dense
                                icon={<Gauge className="h-6 w-6" aria-hidden />}
                                title="No active jobs"
                                description="Kick off a run or wait for scheduled cadences."
                            />
                        )}
                    </CardContent>
                </Card>
            </section>

            <Card>
                <CardHeader>
                    <CardTitle>Training history</CardTitle>
                    <CardDescription>
                        Latest {jobs.length} rows (cursor pagination enabled).
                    </CardDescription>
                </CardHeader>
                <CardContent className="overflow-x-auto">
                    {isLoading ? (
                        <div className="py-12 text-center text-sm text-muted-foreground">
                            Loading jobs…
                        </div>
                    ) : jobs.length === 0 ? (
                        <EmptyState
                            icon={
                                <UploadCloud className="h-8 w-8" aria-hidden />
                            }
                            title="No jobs yet"
                            description="Kick off a training run to populate telemetry."
                        />
                    ) : (
                        <table className="w-full table-auto text-sm">
                            <thead className="text-left text-xs tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-2">Job</th>
                                    <th className="px-4 py-2">Feature</th>
                                    <th className="px-4 py-2">Company</th>
                                    <th className="px-4 py-2">Status</th>
                                    <th className="px-4 py-2">Started</th>
                                    <th className="px-4 py-2">Finished</th>
                                    <th className="px-4 py-2">Duration</th>
                                    <th className="px-4 py-2">Result</th>
                                    <th className="px-4 py-2 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {jobs.map((job) => (
                                    <tr key={job.id} className="border-t">
                                        <td className="px-4 py-3">
                                            <div className="font-semibold text-foreground">
                                                #{job.id}
                                            </div>
                                            <div className="max-w-[140px] truncate text-xs text-muted-foreground">
                                                {job.microservice_job_id ?? '—'}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {job.feature}
                                        </td>
                                        <td className="px-4 py-3">
                                            #{job.company_id}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge
                                                status={job.status}
                                                running={runningJobIds.has(
                                                    job.id,
                                                )}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatDate(job.started_at, {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            }) ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatDate(job.finished_at, {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            }) ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatDuration(job)}
                                        </td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">
                                            {summarizeResult(job.result) ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    refreshJob.mutate(job.id)
                                                }
                                                disabled={refreshJob.isPending}
                                            >
                                                Refresh
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </CardContent>
                <CardFooter className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={!prevCursor}
                        onClick={() =>
                            setFilters((prev) => ({
                                ...prev,
                                cursor: prevCursor,
                            }))
                        }
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        disabled={!nextCursor}
                        onClick={() =>
                            setFilters((prev) => ({
                                ...prev,
                                cursor: nextCursor,
                            }))
                        }
                    >
                        Next
                    </Button>
                </CardFooter>
            </Card>

            <TrainingDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                formValues={trainingForm}
                onFormChange={setTrainingForm}
                onSubmit={handleTrainingSubmit}
                isSubmitting={
                    startTraining.isPending || uploadDataset.isPending
                }
            />
        </div>
    );
}

function StatusBadge({
    status,
    running,
}: {
    status?: string;
    running: boolean;
}) {
    if (!status) {
        return (
            <Badge variant="outline" className="tracking-wide uppercase">
                unknown
            </Badge>
        );
    }

    if (running || status === 'running') {
        return (
            <span className="inline-flex items-center gap-2">
                <Spinner className="h-4 w-4" />
                <span className="text-sm font-semibold text-primary">
                    Running
                </span>
            </span>
        );
    }

    return (
        <Badge
            variant="outline"
            className={cn(
                'border tracking-wide uppercase',
                STATUS_VARIANTS[status] ?? '',
            )}
        >
            {status}
        </Badge>
    );
}

function MetricList({
    job,
    metricKeys,
    formatNumber,
}: {
    job?: ModelTrainingJob;
    metricKeys: readonly string[];
    formatNumber: ReturnType<typeof useFormatting>['formatNumber'];
}) {
    const metrics = job?.result ?? {};
    const keys = metricKeys.length ? metricKeys : Object.keys(metrics ?? {});

    if (!job || !metrics || keys.length === 0) {
        return (
            <div className="text-sm text-muted-foreground">
                No metrics captured yet.
            </div>
        );
    }

    return (
        <dl className="grid gap-2">
            {keys.slice(0, 3).map((key) => (
                <div
                    key={key}
                    className="flex items-center justify-between text-sm"
                >
                    <dt className="text-muted-foreground">
                        {formatMetricLabel(key)}
                    </dt>
                    <dd className="font-semibold text-foreground">
                        {formatMetricValue(metrics[key], formatNumber)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function TrainingDialog({
    open,
    onOpenChange,
    formValues,
    onFormChange,
    onSubmit,
    isSubmitting,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    formValues: TrainingFormState;
    onFormChange: Dispatch<SetStateAction<TrainingFormState>>;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    isSubmitting: boolean;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Train {formValues.feature} models</DialogTitle>
                    <DialogDescription>
                        Provide tenant + optional window constraints.
                    </DialogDescription>
                </DialogHeader>
                <form className="space-y-4" onSubmit={onSubmit}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="train-company">Company ID</Label>
                            <Input
                                id="train-company"
                                required
                                placeholder="Tenant ID"
                                value={formValues.companyId}
                                onChange={(event) =>
                                    onFormChange((prev) => ({
                                        ...prev,
                                        companyId: event.target.value,
                                    }))
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="train-horizon">
                                Forecast horizon (days)
                            </Label>
                            <Input
                                id="train-horizon"
                                type="number"
                                min={1}
                                max={365}
                                value={formValues.horizon}
                                onChange={(event) =>
                                    onFormChange((prev) => ({
                                        ...prev,
                                        horizon: event.target.value,
                                    }))
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="train-start">Start date</Label>
                            <Input
                                id="train-start"
                                type="date"
                                value={formValues.startDate}
                                onChange={(event) =>
                                    onFormChange((prev) => ({
                                        ...prev,
                                        startDate: event.target.value,
                                    }))
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="train-end">End date</Label>
                            <Input
                                id="train-end"
                                type="date"
                                value={formValues.endDate}
                                onChange={(event) =>
                                    onFormChange((prev) => ({
                                        ...prev,
                                        endDate: event.target.value,
                                    }))
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="train-dataset">
                                Dataset upload ID
                            </Label>
                            <Input
                                id="train-dataset"
                                placeholder="document_abc123"
                                value={formValues.datasetUploadId}
                                onChange={(event) =>
                                    onFormChange((prev) => ({
                                        ...prev,
                                        datasetUploadId: event.target.value,
                                    }))
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="train-file">
                                Attach dataset (optional)
                            </Label>
                            <Input
                                id="train-file"
                                type="file"
                                accept=".jsonl,.csv,.zip"
                                onChange={(event) =>
                                    onFormChange((prev) => ({
                                        ...prev,
                                        datasetFile:
                                            event.target.files?.[0] ?? null,
                                    }))
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Uploading a file will generate a dataset ID
                                automatically.
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="train-reindex"
                            checked={formValues.reindexAll}
                            onCheckedChange={(value) =>
                                onFormChange((prev) => ({
                                    ...prev,
                                    reindexAll: Boolean(value),
                                }))
                            }
                        />
                        <Label htmlFor="train-reindex">
                            Force full re-index (RAG only)
                        </Label>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="train-extra">
                            Additional parameters (JSON)
                        </Label>
                        <Textarea
                            id="train-extra"
                            rows={4}
                            placeholder='{"window_days": 45}'
                            value={formValues.additionalParams}
                            onChange={(event) =>
                                onFormChange((prev) => ({
                                    ...prev,
                                    additionalParams: event.target.value,
                                }))
                            }
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitting}>
                            {isSubmitting ? 'Starting…' : 'Start training'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function summarizeStatus(status?: string) {
    if (!status) {
        return 'Idle';
    }

    return status.charAt(0).toUpperCase() + status.slice(1);
}

function deriveLatestByFeature(
    jobs: ModelTrainingJob[],
): Map<string, ModelTrainingJob> {
    const map = new Map<string, ModelTrainingJob>();

    for (const job of jobs) {
        const existing = map.get(job.feature);

        if (!existing) {
            map.set(job.feature, job);
            continue;
        }

        const currentTimestamp = new Date(
            job.finished_at ?? job.started_at ?? job.created_at ?? 0,
        ).getTime();
        const existingTimestamp = new Date(
            existing.finished_at ??
                existing.started_at ??
                existing.created_at ??
                0,
        ).getTime();

        if (currentTimestamp > existingTimestamp) {
            map.set(job.feature, job);
        }
    }

    return map;
}

function formatDuration(job: ModelTrainingJob): string {
    if (!job.started_at || !job.finished_at) {
        return '—';
    }

    const start = new Date(job.started_at).getTime();
    const end = new Date(job.finished_at).getTime();
    const minutes = Math.max(0, (end - start) / 60000);

    if (minutes < 1) {
        return `${Math.round(minutes * 60)}s`;
    }

    return `${minutes.toFixed(1)}m`;
}

function summarizeResult(
    result?: Record<string, unknown> | null,
): string | null {
    if (!result) {
        return null;
    }

    const entries = Object.entries(result)
        .filter(
            ([, value]) =>
                typeof value === 'string' || typeof value === 'number',
        )
        .slice(0, 2)
        .map(
            ([key, value]) =>
                `${formatMetricLabel(key)}: ${typeof value === 'number' ? value.toFixed(2) : value}`,
        );

    return entries.length ? entries.join(' • ') : null;
}

function normalizeFilter(value?: string | null): string | undefined {
    if (!value) {
        return undefined;
    }

    const trimmed = value.trim();
    return trimmed === '' ? undefined : trimmed;
}

function normalizeNumber(value?: string | null): number | undefined {
    if (!value) {
        return undefined;
    }

    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : undefined;
}

function formatMetricLabel(label: string): string {
    return label.replace(/_/g, ' ');
}

function formatMetricValue(
    value: unknown,
    formatNumber: ReturnType<typeof useFormatting>['formatNumber'],
): string {
    if (typeof value === 'number') {
        return formatNumber(value, { maximumFractionDigits: 3 });
    }

    if (typeof value === 'string') {
        return value;
    }

    return '—';
}

function buildParameterPayload(
    form: TrainingFormState,
): Record<string, unknown> | undefined | null {
    const payload: Record<string, unknown> = {};

    if (form.additionalParams.trim()) {
        try {
            Object.assign(payload, JSON.parse(form.additionalParams));
        } catch {
            publishToast({
                title: 'Invalid JSON',
                description: 'Additional parameters must be valid JSON.',
                variant: 'destructive',
            });
            return null;
        }
    }

    if (form.datasetFile) {
        payload.dataset_file_name = form.datasetFile.name;
        payload.dataset_file_size_bytes = form.datasetFile.size;
    }

    return Object.keys(payload).length ? payload : undefined;
}
