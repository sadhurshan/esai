import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { formatDistanceToNow } from 'date-fns';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    RefreshCw,
    ShieldAlert,
} from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { StatusBadge } from '@/components/status-badge';
import { errorToast, successToast } from '@/components/toasts';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { useAuth } from '@/contexts/auth-context';
import { useAiWorkflows } from '@/hooks/api/ai/use-ai-workflows';
import { useAiWorkflowStep } from '@/hooks/api/ai/use-ai-workflow-step';
import { useResolveAiWorkflowStep } from '@/hooks/api/ai/use-resolve-ai-workflow-step';
import { ApiError } from '@/lib/api';
import { cn } from '@/lib/utils';
import type {
    AiWorkflowListResponse,
    AiWorkflowStatus,
    AiWorkflowStepDetail,
    AiWorkflowSummary,
} from '@/types/ai-workflows';

const STATUS_FILTERS = {
    active: { label: 'Awaiting Review', statuses: ['pending', 'in_progress'] },
    resolved: { label: 'Completed / Closed', statuses: ['completed', 'failed', 'rejected', 'aborted'] },
} satisfies Record<string, { label: string; statuses: AiWorkflowStatus[] }>;

type StatusFilter = keyof typeof STATUS_FILTERS;

const STEP_STATUS_STYLES: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-900 dark:bg-amber-400/20 dark:text-amber-50',
    in_progress: 'bg-sky-100 text-sky-900 dark:bg-sky-400/20 dark:text-sky-50',
    approved: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-400/20 dark:text-emerald-50',
    rejected: 'bg-destructive/10 text-destructive',
};

const APPROVER_ROLE_FALLBACK = new Set(['owner', 'buyer_admin', 'finance', 'platform_super']);

interface WorkflowPanelProps {
    className?: string;
}

interface QuoteRanking {
    supplier_id: string;
    supplier_name: string | null;
    score: number | null;
    normalized_score: number | null;
    price: number | null;
    lead_time_days: number | null;
    quality_rating: number | null;
    risk_score: number | null;
    notes: string | null;
}

interface QuoteComparisonDraft {
    recommendation?: string | null;
    summary?: string[];
    rankings: QuoteRanking[];
}

interface PoLineItem {
    line_number: number | null;
    item_code: string | null;
    description: string | null;
    quantity: number | null;
    uom: string | null;
    unit_price: number | null;
    currency: string | null;
    subtotal: number | null;
    delivery_date: string | null;
}

interface PoDeliveryMilestone {
    milestone: string | null;
    date: string | null;
    quantity: number | null;
    notes: string | null;
}

interface PoDraftPayload {
    po_number?: string | null;
    supplier?: {
        supplier_id?: string | null;
        name?: string | null;
        contact?: string | null;
    } | null;
    currency?: string | null;
    line_items: PoLineItem[];
    delivery_schedule: PoDeliveryMilestone[];
    terms_and_conditions: string[];
    total_value?: number | null;
}

interface CursorMeta {
    next: string | null;
    prev: string | null;
    hasNext: boolean;
    hasPrev: boolean;
}

export function WorkflowPanel({ className }: WorkflowPanelProps) {
    const { hasFeature, isAdmin, state } = useAuth();
    const workflowFeatureEnabled = hasFeature('ai_workflows_enabled') || hasFeature('approvals_enabled');
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('active');
    const [cursor, setCursor] = useState<string | null>(null);
    const [selectedWorkflowId, setSelectedWorkflowId] = useState<string | null>(null);
    const [reviewNotes, setReviewNotes] = useState('');
    const [quoteSelection, setQuoteSelection] = useState<string | null>(null);
    const [poAcknowledged, setPoAcknowledged] = useState(false);

    useEffect(() => {
        setCursor(null);
    }, [statusFilter]);

    const workflowsQuery = useAiWorkflows({
        status: STATUS_FILTERS[statusFilter].statuses,
        cursor,
        per_page: 10,
    });

    const workflowItems = workflowsQuery.data?.items ?? [];
    const meta = workflowsQuery.data?.meta;
    const cursorMeta = extractCursorMeta(meta);

    useEffect(() => {
        if (!workflowItems.length) {
            setSelectedWorkflowId(null);
            return;
        }

        setSelectedWorkflowId((current) => {
            if (current && workflowItems.some((item) => item.workflow_id === current)) {
                return current;
            }
            return workflowItems[0]?.workflow_id ?? null;
        });
    }, [workflowItems]);

    const selectedWorkflow = useMemo(() => {
        if (!selectedWorkflowId) {
            return null;
        }
        return workflowItems.find((item) => item.workflow_id === selectedWorkflowId) ?? null;
    }, [workflowItems, selectedWorkflowId]);

    const stepQuery = useAiWorkflowStep(selectedWorkflowId);
    const step = stepQuery.data?.step ?? null;
    const quoteDraft = useMemo(() => parseQuoteDraft(step?.draft), [step]);
    const poDraft = useMemo(() => parsePoDraft(step?.draft), [step]);

    useEffect(() => {
        if (!step) {
            setReviewNotes('');
            setPoAcknowledged(false);
            setQuoteSelection(null);
            return;
        }

        setReviewNotes('');
        setPoAcknowledged(false);

        if (step.action_type === 'compare_quotes') {
            setQuoteSelection(quoteDraft.recommendation ?? (quoteDraft.rankings[0]?.supplier_id ?? null));
        } else {
            setQuoteSelection(null);
        }
    }, [quoteDraft, step]);

    const resolveStep = useResolveAiWorkflowStep();
    const permissions = useMemo(() => extractPermissions(state.user), [state.user]);
    const roleAllowsApproval = state.user?.role ? APPROVER_ROLE_FALLBACK.has(state.user.role) : false;
    const approvalScope = permissions.length > 0 ? permissions.includes('ai.workflows.approve') : roleAllowsApproval || isAdmin;
    const rfqScope = permissions.length > 0 ? permissions.includes('rfqs.write') : isAdmin;
    const orderScope = permissions.length > 0 ? permissions.includes('orders.write') : isAdmin;

    const stepSpecificPermission = useMemo(() => {
        if (!step) {
            return false;
        }
        if (step.action_type === 'compare_quotes') {
            return rfqScope;
        }
        if (step.action_type === 'po_draft') {
            return orderScope;
        }
        return true;
    }, [orderScope, rfqScope, step]);

    const canResolveStep = Boolean(step) && workflowFeatureEnabled && approvalScope && stepSpecificPermission;
    const approveDisabled =
        !canResolveStep ||
        resolveStep.isPending ||
        !step ||
        (step.action_type === 'compare_quotes' && !quoteSelection) ||
        (step.action_type === 'po_draft' && !poAcknowledged);

    const rejectDisabled = !canResolveStep || resolveStep.isPending || !step;

    const handleApprove = useCallback(async () => {
        if (!step || !selectedWorkflowId) {
            return;
        }

        if (step.action_type === 'compare_quotes' && !quoteSelection) {
            errorToast('Select a supplier before approving this comparison.');
            return;
        }

        if (step.action_type === 'po_draft' && !poAcknowledged) {
            errorToast('Confirm that you reviewed the PO draft before approving.');
            return;
        }

        const output = buildCompletionOutput(step, true, {
            quoteDraft,
            poDraft,
            selectedSupplier: quoteSelection,
        });

        try {
            await resolveStep.mutateAsync({
                workflowId: selectedWorkflowId,
                step_index: step.step_index,
                approval: true,
                output,
                notes: reviewNotes.trim() ? reviewNotes.trim() : undefined,
            });
            successToast('Workflow step approved');
            setReviewNotes('');
            setPoAcknowledged(false);
            stepQuery.refetch();
        } catch (error) {
            handleMutationError(error, 'Unable to approve step.');
        }
    }, [poDraft, poAcknowledged, quoteDraft, quoteSelection, resolveStep, reviewNotes, selectedWorkflowId, step, stepQuery]);

    const handleReject = useCallback(async () => {
        if (!step || !selectedWorkflowId) {
            return;
        }

        const output = buildCompletionOutput(step, false, {
            quoteDraft,
            poDraft,
            selectedSupplier: quoteSelection,
        });

        try {
            await resolveStep.mutateAsync({
                workflowId: selectedWorkflowId,
                step_index: step.step_index,
                approval: false,
                output,
                notes: reviewNotes.trim() ? reviewNotes.trim() : undefined,
            });
            successToast('Workflow step rejected');
            stepQuery.refetch();
        } catch (error) {
            handleMutationError(error, 'Unable to reject step.');
        }
    }, [poDraft, quoteDraft, quoteSelection, resolveStep, reviewNotes, selectedWorkflowId, step, stepQuery]);

    if (!workflowFeatureEnabled) {
        return (
            <Card className={cn('border-dashed', className)}>
                <CardHeader>
                    <CardTitle>AI workflows unavailable</CardTitle>
                    <CardDescription>Upgrade your Elements Supply plan to unlock multi-step approvals.</CardDescription>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        title="Workflow approvals require the Growth plan"
                        description="Ask your workspace admin to enable approvals so AI-generated RFQ, quote, and PO drafts can be reviewed."
                        ctaLabel="Review plans"
                        ctaProps={{
                            onClick: () => window.open('/app/settings/billing', '_blank')?.focus(),
                        }}
                    />
                </CardContent>
            </Card>
        );
    }

    return (
        <div className={cn('grid gap-6 lg:grid-cols-[320px_1fr]', className)}>
            <Card className="h-full">
                <CardHeader className="gap-4">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <CardTitle>AI Workflows</CardTitle>
                            <CardDescription>Review each AI-generated step before it impacts procurement data.</CardDescription>
                        </div>
                        <Button
                            size="icon"
                            variant="ghost"
                            onClick={() => workflowsQuery.refetch()}
                            disabled={workflowsQuery.isFetching}
                            aria-label="Refresh workflows"
                        >
                            <RefreshCw className={cn('size-4', workflowsQuery.isFetching && 'animate-spin')} />
                        </Button>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {(Object.keys(STATUS_FILTERS) as StatusFilter[]).map((filterKey) => (
                            <Button
                                key={filterKey}
                                type="button"
                                size="sm"
                                variant={statusFilter === filterKey ? 'secondary' : 'ghost'}
                                onClick={() => setStatusFilter(filterKey)}
                            >
                                {STATUS_FILTERS[filterKey].label}
                            </Button>
                        ))}
                    </div>
                </CardHeader>
                <CardContent>
                    {workflowsQuery.isLoading ? (
                        <WorkflowListSkeleton />
                    ) : workflowsQuery.isError ? (
                        <Alert variant="destructive">
                            <AlertTitle>Unable to load workflows</AlertTitle>
                            <AlertDescription>
                                {(workflowsQuery.error as ApiError | undefined)?.message ?? 'Please try again in a moment.'}
                            </AlertDescription>
                        </Alert>
                    ) : workflowItems.length === 0 ? (
                        <EmptyState
                            title="No workflows yet"
                            description="Kick off a procurement workflow from an RFQ or quote to see approvals here."
                        />
                    ) : (
                        <div className="space-y-4">
                            <div className="space-y-2">
                                {workflowItems.map((workflow) => (
                                    <button
                                        key={workflow.workflow_id}
                                        type="button"
                                        className={cn(
                                            'w-full rounded-xl border px-4 py-3 text-left transition-colors',
                                            selectedWorkflowId === workflow.workflow_id
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border hover:border-primary/60',
                                        )}
                                        onClick={() => setSelectedWorkflowId(workflow.workflow_id)}
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-semibold text-foreground">
                                                    {formatWorkflowType(workflow.workflow_type)}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Updated {formatRelativeTime(workflow.updated_at ?? workflow.created_at)}
                                                </p>
                                            </div>
                                            <StatusBadge status={workflow.status} />
                                        </div>
                                        <div className="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                                            <ArrowRight className="size-3.5" />
                                            <span>
                                                Step {workflow.current_step !== null ? workflow.current_step + 1 : '—'} of {workflow.steps.length}
                                            </span>
                                        </div>
                                    </button>
                                ))}
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setCursor(cursorMeta.prev)}
                                    disabled={!cursorMeta.hasPrev}
                                >
                                    Previous
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setCursor(cursorMeta.next)}
                                    disabled={!cursorMeta.hasNext}
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card className="h-full">
                <CardHeader className="flex-row items-start justify-between gap-4">
                    <div>
                        <CardTitle>Step details</CardTitle>
                        <CardDescription>Inspect AI drafts, compare suppliers, and record your decision.</CardDescription>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="gap-2"
                        onClick={() => stepQuery.refetch()}
                        disabled={!selectedWorkflowId || stepQuery.isFetching}
                    >
                        <RefreshCw className={cn('size-4', stepQuery.isFetching && 'animate-spin')} />
                        Refresh draft
                    </Button>
                </CardHeader>
                <CardContent className="space-y-6">
                    {!selectedWorkflow ? (
                        <EmptyState
                            title="Select a workflow"
                            description="Pick any active workflow from the list to preview its next AI-generated step."
                        />
                    ) : stepQuery.isLoading ? (
                        <StepDetailsSkeleton />
                    ) : stepQuery.isError ? (
                        <Alert variant="destructive">
                            <AlertTitle>Unable to draft step</AlertTitle>
                            <AlertDescription>
                                {(stepQuery.error as ApiError | undefined)?.message ?? 'The AI service is unavailable right now.'}
                            </AlertDescription>
                        </Alert>
                    ) : !step ? (
                        <EmptyState
                            title="Workflow complete"
                            description="Every step in this workflow has been resolved. Start another workflow from the RFQ or quote record."
                        />
                    ) : (
                        <div className="space-y-6">
                            <div className="space-y-3 rounded-xl border bg-muted/40 p-4">
                                <div className="flex flex-wrap items-center gap-3">
                                    <Badge variant="outline">{formatWorkflowType(selectedWorkflow.workflow_type)}</Badge>
                                    <Badge variant="secondary">{formatActionLabel(step.action_type)}</Badge>
                                    <div className="text-xs text-muted-foreground">
                                        Last updated {formatRelativeTime(step.updated_at ?? selectedWorkflow.updated_at)}
                                    </div>
                                </div>
                                <div className="space-y-2 text-sm">
                                    <p className="font-semibold text-foreground">Step tracker</p>
                                    <div className="space-y-1">
                                        {selectedWorkflow.steps.map((workflowStep) => (
                                            <div
                                                key={`${workflowStep.step_index}-${workflowStep.action_type}`}
                                                className={cn(
                                                    'flex items-center justify-between rounded-lg px-3 py-1.5 text-xs',
                                                    workflowStep.step_index === step.step_index
                                                        ? 'bg-primary/5 text-foreground'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                <span>
                                                    Step {(workflowStep.step_index ?? 0) + 1}: {workflowStep.name ?? formatActionLabel(workflowStep.action_type)}
                                                </span>
                                                <Badge
                                                    variant="secondary"
                                                    className={cn(
                                                        'text-[0.7rem]',
                                                        STEP_STATUS_STYLES[workflowStep.approval_status ?? 'pending'] ?? STEP_STATUS_STYLES.pending,
                                                    )}
                                                >
                                                    {workflowStep.approval_status ?? 'pending'}
                                                </Badge>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            {step.action_type === 'compare_quotes' ? (
                                <QuoteComparisonPreview
                                    draft={quoteDraft}
                                    selection={quoteSelection}
                                    onSelectionChange={setQuoteSelection}
                                />
                            ) : step.action_type === 'po_draft' ? (
                                <PoDraftPreview
                                    draft={poDraft}
                                    acknowledged={poAcknowledged}
                                    onAcknowledgeChange={setPoAcknowledged}
                                />
                            ) : (
                                <GenericDraftPreview payload={step.draft} />
                            )}

                            {!canResolveStep && (
                                <Alert>
                                    <AlertTitle>Approval restricted</AlertTitle>
                                    <AlertDescription>
                                        You need both the AI workflow entitlement and the proper module permission to resolve this step.
                                    </AlertDescription>
                                </Alert>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="workflow-notes">Reviewer notes</Label>
                                <Textarea
                                    id="workflow-notes"
                                    placeholder="Optional: document why you approved, rejected, or overrode the AI recommendation."
                                    value={reviewNotes}
                                    onChange={(event) => setReviewNotes(event.target.value)}
                                    rows={4}
                                />
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <Button type="button" onClick={handleApprove} disabled={approveDisabled}>
                                    <CheckCircle2 className="size-4" /> Approve
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="border-destructive text-destructive hover:bg-destructive/10"
                                    onClick={handleReject}
                                    disabled={rejectDisabled}
                                >
                                    <AlertTriangle className="size-4" /> Reject
                                </Button>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function QuoteComparisonPreview({
    draft,
    selection,
    onSelectionChange,
}: {
    draft: QuoteComparisonDraft;
    selection: string | null;
    onSelectionChange: (value: string) => void;
}) {
    if (!draft.rankings.length) {
        return (
            <EmptyState
                title="No quotes available"
                description="The AI service did not return any supplier quotes to compare."
            />
        );
    }

    const recommendation = draft.recommendation ?? null;

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-3">
                <h3 className="text-sm font-semibold">Supplier comparison</h3>
                {recommendation && (
                    <Badge variant="secondary" className="flex items-center gap-1">
                        <ShieldAlert className="size-3.5" /> AI recommendation: {recommendation}
                    </Badge>
                )}
            </div>
            <div className="overflow-x-auto rounded-xl border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/60 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th className="px-4 py-2">Supplier</th>
                            <th className="px-4 py-2">Score</th>
                            <th className="px-4 py-2">Price</th>
                            <th className="px-4 py-2">Lead time</th>
                            <th className="px-4 py-2">Quality</th>
                            <th className="px-4 py-2">Risk</th>
                        </tr>
                    </thead>
                    <tbody>
                        {draft.rankings.map((ranking) => {
                            const supplierId = ranking.supplier_id;
                            const isSelected = selection === supplierId;
                            const isRecommended = supplierId === recommendation;
                            return (
                                <tr key={supplierId} className={cn(isSelected && 'bg-primary/5')}>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col">
                                            <span className="font-medium text-foreground">
                                                {ranking.supplier_name ?? supplierId}
                                            </span>
                                            {isRecommended && (
                                                <span className="text-xs text-primary">AI recommended</span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {typeof ranking.score === 'number' ? `${ranking.score.toFixed(1)}` : '—'}
                                    </td>
                                    <td className="px-4 py-3">{formatCurrency(ranking.price)}</td>
                                    <td className="px-4 py-3">
                                        {typeof ranking.lead_time_days === 'number'
                                            ? `${ranking.lead_time_days.toFixed(1)} d`
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {typeof ranking.quality_rating === 'number'
                                            ? `${Math.round(ranking.quality_rating * 100)}%`
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {typeof ranking.risk_score === 'number'
                                            ? `${Math.round(ranking.risk_score * 100)}%`
                                            : '—'}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            <div className="grid gap-3 md:grid-cols-2">
                <div className="space-y-1">
                    <Label htmlFor="supplier-selection">Reviewer selection</Label>
                    <Select
                        value={selection ?? undefined}
                        onValueChange={onSelectionChange}
                        defaultValue={selection ?? undefined}
                    >
                        <SelectTrigger id="supplier-selection">
                            <SelectValue placeholder="Choose supplier" />
                        </SelectTrigger>
                        <SelectContent>
                            {draft.rankings.map((ranking) => (
                                <SelectItem key={ranking.supplier_id} value={ranking.supplier_id}>
                                    {ranking.supplier_name ?? ranking.supplier_id}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="rounded-xl border border-dashed p-3 text-sm text-muted-foreground">
                    {selection && selection !== recommendation ? (
                        <p>
                            Manual override in effect. Document why you selected this supplier in the notes before approving.
                        </p>
                    ) : (
                        <p>Your selection matches the AI recommendation.</p>
                    )}
                </div>
            </div>
        </div>
    );
}

function PoDraftPreview({
    draft,
    acknowledged,
    onAcknowledgeChange,
}: {
    draft: PoDraftPayload | null;
    acknowledged: boolean;
    onAcknowledgeChange: (value: boolean) => void;
}) {
    if (!draft) {
        return (
            <EmptyState
                title="No PO draft"
                description="The AI service has not generated a purchase order draft for this workflow yet."
            />
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-start gap-4 rounded-xl border bg-card/60 p-4">
                <div>
                    <Badge variant="outline">{draft.po_number ?? 'PO Draft'}</Badge>
                    <p className="text-base font-semibold text-foreground">
                        {draft.supplier?.name ?? 'Unnamed supplier'}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Supplier ID {draft.supplier?.supplier_id ?? '—'}
                    </p>
                </div>
                <div className="ml-auto text-right">
                    <p className="text-xs uppercase text-muted-foreground">Total value</p>
                    <p className="text-2xl font-semibold text-foreground">
                        {formatCurrency(draft.total_value, draft.currency ?? 'USD')}
                    </p>
                </div>
            </div>

            <CollapsibleSection title="Line items" defaultOpen>
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-left text-xs uppercase text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2">#</th>
                                <th className="px-4 py-2">Description</th>
                                <th className="px-4 py-2">Qty</th>
                                <th className="px-4 py-2">Unit price</th>
                                <th className="px-4 py-2">Subtotal</th>
                                <th className="px-4 py-2">Delivery</th>
                            </tr>
                        </thead>
                        <tbody>
                            {draft.line_items.map((item, index) => (
                                <tr key={`${item.item_code ?? index}-${item.line_number ?? index}`}>
                                    <td className="px-4 py-3">{item.line_number ?? index + 1}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col">
                                            <span className="font-medium text-foreground">{item.description ?? '—'}</span>
                                            <span className="text-xs text-muted-foreground">{item.item_code ?? '—'}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {typeof item.quantity === 'number' ? `${item.quantity} ${item.uom ?? ''}` : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatCurrency(item.unit_price, item.currency ?? draft.currency ?? 'USD')}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatCurrency(item.subtotal, item.currency ?? draft.currency ?? 'USD')}
                                    </td>
                                    <td className="px-4 py-3">{item.delivery_date ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </CollapsibleSection>

            {draft.delivery_schedule.length > 0 && (
                <CollapsibleSection title="Delivery schedule">
                    <ul className="space-y-2 text-sm">
                        {draft.delivery_schedule.map((milestone, index) => (
                            <li key={`${milestone.milestone ?? index}-${milestone.date ?? index}`} className="rounded-lg border p-3">
                                <p className="font-medium text-foreground">{milestone.milestone ?? `Milestone ${index + 1}`}</p>
                                <p className="text-xs text-muted-foreground">{milestone.date ?? 'Date TBD'}</p>
                                <p className="text-sm">
                                    Qty: {milestone.quantity ?? '—'} {milestone.notes ? `• ${milestone.notes}` : ''}
                                </p>
                            </li>
                        ))}
                    </ul>
                </CollapsibleSection>
            )}

            {draft.terms_and_conditions.length > 0 && (
                <CollapsibleSection title="Terms & conditions">
                    <ol className="list-decimal space-y-2 pl-5 text-sm text-muted-foreground">
                        {draft.terms_and_conditions.map((term, index) => (
                            <li key={`${term}-${index}`}>{term}</li>
                        ))}
                    </ol>
                </CollapsibleSection>
            )}

            <label className="flex items-center gap-3 text-sm font-medium text-foreground">
                <Checkbox checked={acknowledged} onCheckedChange={(value) => onAcknowledgeChange(Boolean(value))} />
                I reviewed every line item, delivery, and term above.
            </label>
        </div>
    );
}

function GenericDraftPreview({ payload }: { payload: Record<string, unknown> | undefined }) {
    if (!payload || Object.keys(payload).length === 0) {
        return (
            <EmptyState
                title="No preview data"
                description="This step does not provide structured data yet. Review the workflow evidence in the audit log."
            />
        );
    }

    return (
        <div className="space-y-2">
            <h3 className="text-sm font-semibold">Draft payload</h3>
            <pre className="max-h-[320px] overflow-auto rounded-xl border bg-muted/40 p-4 text-xs">
                {JSON.stringify(payload, null, 2)}
            </pre>
        </div>
    );
}

function CollapsibleSection({
    title,
    defaultOpen = true,
    children,
}: {
    title: string;
    defaultOpen?: boolean;
    children: React.ReactNode;
}) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <CollapsibleTrigger className="flex w-full items-center justify-between rounded-xl border bg-muted/40 px-4 py-2 text-left text-sm font-semibold">
                <span>{title}</span>
                <ChevronDown className={cn('size-4 transition-transform', open && 'rotate-180')} />
            </CollapsibleTrigger>
            <CollapsibleContent className="pt-3">{children}</CollapsibleContent>
        </Collapsible>
    );
}

function WorkflowListSkeleton() {
    return (
        <div className="space-y-3">
            {[...Array(3)].map((_, index) => (
                <Skeleton key={index} className="h-20 w-full rounded-xl" />
            ))}
        </div>
    );
}

function StepDetailsSkeleton() {
    return (
        <div className="space-y-4">
            <Skeleton className="h-24 w-full rounded-xl" />
            <Skeleton className="h-48 w-full rounded-xl" />
            <Skeleton className="h-32 w-full rounded-xl" />
        </div>
    );
}

function extractPermissions(user: unknown): string[] {
    if (!user || typeof user !== 'object') {
        return [];
    }

    const value = (user as Record<string, unknown>).permissions;

    if (Array.isArray(value)) {
        return value.filter((entry): entry is string => typeof entry === 'string' && entry.length > 0);
    }

    if (value && typeof value === 'object') {
        return Object.keys(value as Record<string, unknown>);
    }

    return [];
}

function formatWorkflowType(value?: string | null): string {
    if (!value) {
        return 'Workflow';
    }
    return value
        .split('_')
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
}

function formatActionLabel(value?: string | null): string {
    return formatWorkflowType(value ?? 'Step');
}

function formatRelativeTime(value?: string | null): string {
    if (!value) {
        return 'just now';
    }
    try {
        return formatDistanceToNow(new Date(value), { addSuffix: true });
    } catch (error) {
        void error;
        return 'recently';
    }
}

function formatCurrency(value?: number | null, currency = 'USD'): string {
    if (!(typeof value === 'number' && Number.isFinite(value))) {
        return '—';
    }

    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(value);
    } catch (error) {
        void error;
        return `$${value.toFixed(2)}`;
    }
}

function parseQuoteDraft(raw: Record<string, unknown> | undefined | null): QuoteComparisonDraft {
    if (!raw || typeof raw !== 'object') {
        return { rankings: [] };
    }

    const rankings = Array.isArray((raw as Record<string, unknown>)['rankings'])
        ? ((raw as Record<string, unknown>)['rankings'] as unknown[])
              .map((entry) => {
                  if (!entry || typeof entry !== 'object') {
                      return null;
                  }
                  const map = entry as Record<string, unknown>;
                  const supplier = normalizeId(map['supplier_id'] ?? map['id']);
                  if (!supplier) {
                      return null;
                  }
                  return {
                      supplier_id: supplier,
                      supplier_name: typeof map['supplier_name'] === 'string' ? (map['supplier_name'] as string) : null,
                      score: toNumber(map['score']),
                      normalized_score: toNumber(map['normalized_score']),
                      price: toNumber(map['price']),
                      lead_time_days: toNumber(map['lead_time_days']),
                      quality_rating: toNumber(map['quality_rating']),
                      risk_score: toNumber(map['risk_score']),
                      notes: typeof map['notes'] === 'string' ? (map['notes'] as string) : null,
                  } satisfies QuoteRanking;
              })
              .filter((entry): entry is QuoteRanking => entry !== null)
        : [];

    const summaryPayload = (raw['summary'] ?? []) as unknown;
    const summary = Array.isArray(summaryPayload)
        ? summaryPayload.filter((entry): entry is string => typeof entry === 'string')
        : [];

    return {
        recommendation: normalizeId(raw['recommendation']),
        summary,
        rankings,
    };
}

function parsePoDraft(raw: Record<string, unknown> | undefined | null): PoDraftPayload | null {
    if (!raw || typeof raw !== 'object') {
        return null;
    }

    const supplierBlock = isRecord(raw.supplier)
        ? {
              supplier_id: normalizeId(raw.supplier.supplier_id ?? raw.supplier.id) ?? null,
              name: typeof raw.supplier.name === 'string' ? raw.supplier.name : null,
              contact: typeof raw.supplier.contact === 'string' ? raw.supplier.contact : null,
          }
        : null;

    const lineItems = Array.isArray(raw.line_items)
        ? raw.line_items
              .map((item) => {
                  if (!isRecord(item)) {
                      return null;
                  }
                  return {
                      line_number: toNumber(item.line_number),
                      item_code: typeof item.item_code === 'string' ? item.item_code : null,
                      description: typeof item.description === 'string' ? item.description : null,
                      quantity: toNumber(item.quantity),
                      uom: typeof item.uom === 'string' ? item.uom : null,
                      unit_price: toNumber(item.unit_price),
                      currency: typeof item.currency === 'string' ? item.currency : null,
                      subtotal: toNumber(item.subtotal),
                      delivery_date: typeof item.delivery_date === 'string' ? item.delivery_date : null,
                  } satisfies PoLineItem;
              })
              .filter((entry): entry is PoLineItem => entry !== null)
        : [];

    const deliverySchedule = Array.isArray(raw.delivery_schedule)
        ? raw.delivery_schedule
              .map((entry) => {
                  if (!isRecord(entry)) {
                      return null;
                  }
                  return {
                      milestone: typeof entry.milestone === 'string' ? entry.milestone : null,
                      date: typeof entry.date === 'string' ? entry.date : null,
                      quantity: toNumber(entry.quantity),
                      notes: typeof entry.notes === 'string' ? entry.notes : null,
                  } satisfies PoDeliveryMilestone;
              })
              .filter((entry): entry is PoDeliveryMilestone => entry !== null)
        : [];

    const terms = Array.isArray(raw.terms_and_conditions)
        ? raw.terms_and_conditions
              .filter((term): term is string => typeof term === 'string' && term.trim().length > 0)
              .map((term) => term.trim())
        : [];

    return {
        po_number: typeof raw.po_number === 'string' ? raw.po_number : null,
        supplier: supplierBlock,
        currency: typeof raw.currency === 'string' ? raw.currency : 'USD',
        line_items: lineItems,
        delivery_schedule: deliverySchedule,
        terms_and_conditions: terms,
        total_value: toNumber(raw.total_value),
    };
}

function buildCompletionOutput(
    step: AiWorkflowStepDetail,
    approval: boolean,
    context: { quoteDraft: QuoteComparisonDraft; poDraft: PoDraftPayload | null; selectedSupplier: string | null },
): Record<string, unknown> {
    if (!approval) {
        return {
            summary: `${formatActionLabel(step.action_type)} rejected by reviewer`,
            payload: {
                action_type: step.action_type,
                reason: 'rejected_by_reviewer',
            },
        };
    }

    if (step.action_type === 'compare_quotes') {
        const recommendation = context.selectedSupplier ?? context.quoteDraft.recommendation ?? null;
        return {
            summary: 'Quote comparison approved',
            payload: {
                recommendation,
                rankings: context.quoteDraft.rankings,
                summary: context.quoteDraft.summary,
            },
        };
    }

    if (step.action_type === 'po_draft' && context.poDraft) {
        return {
            summary: `PO draft ${context.poDraft.po_number ?? ''} approved`,
            payload: context.poDraft,
        };
    }

    return {
        summary: `${formatActionLabel(step.action_type)} approved`,
        payload: step.draft ?? {},
    };
}

function extractCursorMeta(meta?: AiWorkflowListResponse['meta']): CursorMeta {
    const cursor = meta?.envelope?.cursor ?? meta?.data ?? {};
    const next = typeof cursor.next_cursor === 'string' ? cursor.next_cursor : null;
    const prev = typeof cursor.prev_cursor === 'string' ? cursor.prev_cursor : null;
    const hasNext = Boolean(cursor.has_next ?? next);
    const hasPrev = Boolean(cursor.has_prev ?? prev);

    return { next, prev, hasNext, hasPrev };
}

function isRecord(value: unknown): value is Record<string, any> {
    return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function normalizeId(value: unknown): string | null {
    if (typeof value === 'string' && value.trim() !== '') {
        return value.trim();
    }
    if (typeof value === 'number' && Number.isFinite(value)) {
        return String(value);
    }
    return null;
}

function toNumber(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }
    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number(value);
        return Number.isNaN(parsed) ? null : parsed;
    }
    return null;
}

function handleMutationError(error: unknown, fallback: string) {
    if (error instanceof ApiError) {
        errorToast(fallback, error.message);
    } else if (error instanceof Error) {
        errorToast(fallback, error.message);
    } else {
        errorToast(fallback);
    }
}
