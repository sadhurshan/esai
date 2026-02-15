import { formatDistanceToNow } from 'date-fns';
import {
    AlertTriangle,
    ArrowRight,
    BellRing,
    CheckCircle2,
    ChevronDown,
    RefreshCw,
    ShieldAlert,
} from 'lucide-react';
import { useCallback, useMemo, useState, type ReactNode } from 'react';

import { EmptyState } from '@/components/empty-state';
import { StatusBadge } from '@/components/status-badge';
import { errorToast, successToast } from '@/components/toasts';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { useAuth } from '@/contexts/auth-context';
import { useAiWorkflowStep } from '@/hooks/api/ai/use-ai-workflow-step';
import { useAiWorkflows } from '@/hooks/api/ai/use-ai-workflows';
import { useResolveAiWorkflowStep } from '@/hooks/api/ai/use-resolve-ai-workflow-step';
import { ApiError } from '@/lib/api';
import { cn } from '@/lib/utils';
import type {
    AiWorkflowApprovalRequest,
    AiWorkflowListResponse,
    AiWorkflowStatus,
    AiWorkflowStepDetail,
} from '@/types/ai-workflows';

const STATUS_FILTERS = {
    active: { label: 'Awaiting Review', statuses: ['pending', 'in_progress'] },
    resolved: {
        label: 'Completed / Closed',
        statuses: ['completed', 'failed', 'rejected', 'aborted'],
    },
} satisfies Record<string, { label: string; statuses: AiWorkflowStatus[] }>;

type StatusFilter = keyof typeof STATUS_FILTERS;

const STEP_STATUS_STYLES: Record<string, string> = {
    pending:
        'bg-amber-100 text-amber-900 dark:bg-amber-400/20 dark:text-amber-50',
    in_progress: 'bg-sky-100 text-sky-900 dark:bg-sky-400/20 dark:text-sky-50',
    approved:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-400/20 dark:text-emerald-50',
    rejected: 'bg-destructive/10 text-destructive',
};

const APPROVER_ROLE_FALLBACK = new Set([
    'owner',
    'buyer_admin',
    'finance',
    'platform_super',
]);

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

interface AwardQuoteDraft {
    rfq_id: string | null;
    supplier_id: string | null;
    supplier_name: string | null;
    selected_quote_id: string | null;
    justification: string | null;
    delivery_date: string | null;
    terms: string[];
}

interface ReceivingQualityChecklistItem {
    label: string;
    detail: string | null;
    status: 'ok' | 'warning' | 'risk';
    value: string | null;
}

interface ReceivingQualityDraft {
    title: string | null;
    summary: string | null;
    entity_label: string | null;
    highlights: string[];
    checklist: ReceivingQualityChecklistItem[];
    warnings: string[];
    metadata: Array<{ key: string; value: string }>;
}

interface ReceiptDraftLine {
    reference: string;
    lineNumber: number | null;
    description: string;
    uom: string | null;
    expectedQty: number | null;
    receivedQty: number | null;
    acceptedQty: number | null;
    rejectedQty: number | null;
    issues: string[];
    notes: string | null;
}

interface ReceiptDraftDetails {
    poId: string | null;
    reference: string | null;
    inspectedBy: string | null;
    receivedDate: string | null;
    status: string | null;
    totalReceivedQty: number | null;
    notes: string | null;
    lineItems: ReceiptDraftLine[];
    warnings: string[];
}

type InvoiceMatchSeverity = 'info' | 'warning' | 'risk';

interface InvoiceMatchMismatch {
    type: string;
    lineReference: string | null;
    severity: InvoiceMatchSeverity;
    detail: string;
    expected: number | null;
    actual: number | null;
}

interface InvoiceMatchDraft {
    invoiceId: string | null;
    poId: string | null;
    receiptIds: string[];
    matchScore: number | null;
    recommendation: {
        status: string | null;
        explanation: string | null;
    } | null;
    mismatches: InvoiceMatchMismatch[];
    analysisNotes: string[];
    warnings: string[];
}

interface PaymentDraftDetails {
    invoiceId: string | null;
    scheduledDate: string | null;
    paymentMethod: string | null;
    reference: string | null;
    amount: number | null;
    currency: string;
    notes: string | null;
    warnings: string[];
}

interface CursorMeta {
    next: string | null;
    prev: string | null;
    hasNext: boolean;
    hasPrev: boolean;
}

export function WorkflowPanel({ className }: WorkflowPanelProps) {
    const { hasFeature, isAdmin, state } = useAuth();
    const workflowFeatureEnabled =
        hasFeature('ai_workflows_enabled') || hasFeature('approvals_enabled');
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('active');
    const [cursor, setCursor] = useState<string | null>(null);
    const [selectedWorkflowId, setSelectedWorkflowId] = useState<string | null>(
        null,
    );
    const [reviewNotes, setReviewNotes] = useState('');
    const [quoteSelection, setQuoteSelection] = useState<string | null>(null);
    const [poAcknowledged, setPoAcknowledged] = useState(false);
    const [invoiceMatchAcknowledged, setInvoiceMatchAcknowledged] =
        useState(false);

    const workflowsQuery = useAiWorkflows({
        status: STATUS_FILTERS[statusFilter].statuses,
        cursor,
        per_page: 10,
    });

    const workflowItems = useMemo(
        () => workflowsQuery.data?.items ?? [],
        [workflowsQuery.data?.items],
    );
    const meta = workflowsQuery.data?.meta;
    const cursorMeta = extractCursorMeta(meta);

    const resetReviewState = useCallback(() => {
        setReviewNotes('');
        setQuoteSelection(null);
        setPoAcknowledged(false);
        setInvoiceMatchAcknowledged(false);
    }, []);

    const handleStatusFilterChange = useCallback(
        (nextFilter: StatusFilter) => {
            if (nextFilter === statusFilter) {
                return;
            }

            setStatusFilter(nextFilter);
            setCursor(null);
            setSelectedWorkflowId(null);
            resetReviewState();
        },
        [resetReviewState, statusFilter],
    );

    const handleWorkflowSelect = useCallback(
        (workflowId: string) => {
            setSelectedWorkflowId(workflowId);
            resetReviewState();
        },
        [resetReviewState],
    );

    const resolvedSelectedWorkflowId = useMemo(() => {
        if (!workflowItems.length) {
            return null;
        }

        if (
            selectedWorkflowId &&
            workflowItems.some(
                (item) => item.workflow_id === selectedWorkflowId,
            )
        ) {
            return selectedWorkflowId;
        }

        return workflowItems[0]?.workflow_id ?? null;
    }, [selectedWorkflowId, workflowItems]);

    const selectedWorkflow = useMemo(() => {
        if (!resolvedSelectedWorkflowId) {
            return null;
        }
        return (
            workflowItems.find(
                (item) => item.workflow_id === resolvedSelectedWorkflowId,
            ) ?? null
        );
    }, [resolvedSelectedWorkflowId, workflowItems]);

    const stepQuery = useAiWorkflowStep(resolvedSelectedWorkflowId);
    const step = stepQuery.data?.step ?? null;
    const quoteDraft = useMemo(() => parseQuoteDraft(step?.draft), [step]);
    const poDraft = useMemo(() => parsePoDraft(step?.draft), [step]);
    const awardDraft = useMemo(() => parseAwardDraft(step?.draft), [step]);
    const receivingQualityDraft = useMemo(
        () => parseReceivingQualityDraft(step?.draft),
        [step],
    );
    const receiptDraft = useMemo(() => parseReceiptDraft(step?.draft), [step]);
    const invoiceMatchDraft = useMemo(
        () => parseInvoiceMatchDraft(step?.draft),
        [step],
    );
    const paymentDraft = useMemo(() => parsePaymentDraft(step?.draft), [step]);

    const resolvedQuoteSelection = useMemo(() => {
        if (!step || step.action_type !== 'compare_quotes') {
            return null;
        }

        if (quoteSelection) {
            return quoteSelection;
        }

        return (
            quoteDraft.recommendation ??
            quoteDraft.rankings[0]?.supplier_id ??
            null
        );
    }, [quoteDraft, quoteSelection, step]);

    const resolveStep = useResolveAiWorkflowStep();
    const permissions = useMemo(
        () => extractPermissions(state.user),
        [state.user],
    );
    const roleAllowsApproval = state.user?.role
        ? APPROVER_ROLE_FALLBACK.has(state.user.role)
        : false;
    const approvalScope =
        permissions.length > 0
            ? permissions.includes('ai.workflows.approve')
            : roleAllowsApproval || isAdmin;
    const rfqScope =
        permissions.length > 0 ? permissions.includes('rfqs.write') : isAdmin;
    const quoteScope =
        permissions.length > 0 ? permissions.includes('quotes.write') : isAdmin;
    const orderScope =
        permissions.length > 0 ? permissions.includes('orders.write') : isAdmin;

    const stepSpecificPermission = useMemo(() => {
        if (!step) {
            return false;
        }
        if (step.action_type === 'compare_quotes') {
            return rfqScope;
        }
        if (step.action_type === 'award_quote') {
            return quoteScope;
        }
        if (step.action_type === 'po_draft') {
            return orderScope;
        }
        return true;
    }, [orderScope, quoteScope, rfqScope, step]);

    const canResolveStep =
        Boolean(step) &&
        workflowFeatureEnabled &&
        approvalScope &&
        stepSpecificPermission;
    const approveDisabled =
        !canResolveStep ||
        resolveStep.isPending ||
        !step ||
        (step.action_type === 'compare_quotes' && !resolvedQuoteSelection) ||
        (step.action_type === 'po_draft' && !poAcknowledged) ||
        (step.action_type === 'invoice_match' && !invoiceMatchAcknowledged);

    const rejectDisabled = !canResolveStep || resolveStep.isPending || !step;

    const handleApprove = useCallback(async () => {
        if (!step || !resolvedSelectedWorkflowId) {
            return;
        }

        if (step.action_type === 'compare_quotes' && !resolvedQuoteSelection) {
            errorToast('Select a supplier before approving this comparison.');
            return;
        }

        if (step.action_type === 'po_draft' && !poAcknowledged) {
            errorToast(
                'Confirm that you reviewed the PO draft before approving.',
            );
            return;
        }

        if (step.action_type === 'invoice_match' && !invoiceMatchAcknowledged) {
            errorToast(
                'Confirm that you reviewed the invoice match before approving.',
            );
            return;
        }

        const output = buildCompletionOutput(step, true, {
            quoteDraft,
            poDraft,
            selectedSupplier: resolvedQuoteSelection,
            awardDraft,
        });

        try {
            await resolveStep.mutateAsync({
                workflowId: resolvedSelectedWorkflowId,
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
    }, [
        awardDraft,
        invoiceMatchAcknowledged,
        poDraft,
        poAcknowledged,
        quoteDraft,
        resolvedQuoteSelection,
        resolveStep,
        reviewNotes,
        resolvedSelectedWorkflowId,
        step,
        stepQuery,
    ]);

    const handleReject = useCallback(async () => {
        if (!step || !resolvedSelectedWorkflowId) {
            return;
        }

        const output = buildCompletionOutput(step, false, {
            quoteDraft,
            poDraft,
            selectedSupplier: resolvedQuoteSelection,
            awardDraft,
        });

        try {
            await resolveStep.mutateAsync({
                workflowId: resolvedSelectedWorkflowId,
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
    }, [
        awardDraft,
        poDraft,
        quoteDraft,
        resolvedQuoteSelection,
        resolveStep,
        reviewNotes,
        resolvedSelectedWorkflowId,
        step,
        stepQuery,
    ]);

    if (!workflowFeatureEnabled) {
        return (
            <Card className={cn('border-dashed', className)}>
                <CardHeader>
                    <CardTitle>AI workflows unavailable</CardTitle>
                    <CardDescription>
                        Upgrade your Elements Supply plan to unlock multi-step
                        approvals.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        title="Workflow approvals require the Growth plan"
                        description="Ask your workspace admin to enable approvals so AI-generated RFQ, quote, and PO drafts can be reviewed."
                        ctaLabel="Review plans"
                        ctaProps={{
                            onClick: () =>
                                window
                                    .open('/app/settings/billing', '_blank')
                                    ?.focus(),
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
                            <CardDescription>
                                Review each AI-generated step before it impacts
                                procurement data.
                            </CardDescription>
                        </div>
                        <Button
                            size="icon"
                            variant="ghost"
                            onClick={() => workflowsQuery.refetch()}
                            disabled={workflowsQuery.isFetching}
                            aria-label="Refresh workflows"
                        >
                            <RefreshCw
                                className={cn(
                                    'size-4',
                                    workflowsQuery.isFetching && 'animate-spin',
                                )}
                            />
                        </Button>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {(Object.keys(STATUS_FILTERS) as StatusFilter[]).map(
                            (filterKey) => (
                                <Button
                                    key={filterKey}
                                    type="button"
                                    size="sm"
                                    variant={
                                        statusFilter === filterKey
                                            ? 'secondary'
                                            : 'ghost'
                                    }
                                    onClick={() =>
                                        handleStatusFilterChange(filterKey)
                                    }
                                >
                                    {STATUS_FILTERS[filterKey].label}
                                </Button>
                            ),
                        )}
                    </div>
                </CardHeader>
                <CardContent>
                    {workflowsQuery.isLoading ? (
                        <WorkflowListSkeleton />
                    ) : workflowsQuery.isError ? (
                        <Alert variant="destructive">
                            <AlertTitle>Unable to load workflows</AlertTitle>
                            <AlertDescription>
                                {(workflowsQuery.error as ApiError | undefined)
                                    ?.message ??
                                    'Please try again in a moment.'}
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
                                            resolvedSelectedWorkflowId ===
                                                workflow.workflow_id
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border hover:border-primary/60',
                                        )}
                                        onClick={() =>
                                            handleWorkflowSelect(
                                                workflow.workflow_id,
                                            )
                                        }
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-semibold text-foreground">
                                                    {formatWorkflowType(
                                                        workflow.workflow_type,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Updated{' '}
                                                    {formatRelativeTime(
                                                        workflow.updated_at ??
                                                            workflow.created_at,
                                                    )}
                                                </p>
                                            </div>
                                            <StatusBadge
                                                status={workflow.status}
                                            />
                                        </div>
                                        <div className="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                                            <ArrowRight className="size-3.5" />
                                            <span>
                                                Step{' '}
                                                {workflow.current_step !== null
                                                    ? workflow.current_step + 1
                                                    : '—'}{' '}
                                                of {workflow.steps.length}
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
                        <CardDescription>
                            Inspect AI drafts, compare suppliers, and record
                            your decision.
                        </CardDescription>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="gap-2"
                        onClick={() => stepQuery.refetch()}
                        disabled={
                            !resolvedSelectedWorkflowId || stepQuery.isFetching
                        }
                    >
                        <RefreshCw
                            className={cn(
                                'size-4',
                                stepQuery.isFetching && 'animate-spin',
                            )}
                        />
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
                                {(stepQuery.error as ApiError | undefined)
                                    ?.message ??
                                    'The AI service is unavailable right now.'}
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
                                    <Badge variant="outline">
                                        {formatWorkflowType(
                                            selectedWorkflow.workflow_type,
                                        )}
                                    </Badge>
                                    <Badge variant="secondary">
                                        {formatActionLabel(step.action_type)}
                                    </Badge>
                                    <div className="text-xs text-muted-foreground">
                                        Last updated{' '}
                                        {formatRelativeTime(
                                            step.updated_at ??
                                                selectedWorkflow.updated_at,
                                        )}
                                    </div>
                                </div>
                                <div className="space-y-2 text-sm">
                                    <p className="font-semibold text-foreground">
                                        Step tracker
                                    </p>
                                    <div className="space-y-1">
                                        {selectedWorkflow.steps.map(
                                            (workflowStep) => {
                                                const hasPendingApproval =
                                                    Boolean(
                                                        workflowStep.has_pending_approval_request,
                                                    );

                                                return (
                                                    <div
                                                        key={`${workflowStep.step_index}-${workflowStep.action_type}`}
                                                        className={cn(
                                                            'flex items-center justify-between rounded-lg px-3 py-1.5 text-xs',
                                                            workflowStep.step_index ===
                                                                step.step_index
                                                                ? 'bg-primary/5 text-foreground'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        <div className="flex flex-col">
                                                            <span>
                                                                Step{' '}
                                                                {(workflowStep.step_index ??
                                                                    0) + 1}
                                                                :{' '}
                                                                {workflowStep.name ??
                                                                    formatActionLabel(
                                                                        workflowStep.action_type,
                                                                    )}
                                                            </span>
                                                            {hasPendingApproval && (
                                                                <span className="mt-0.5 flex items-center gap-1 text-[0.7rem] text-amber-700 dark:text-amber-200">
                                                                    <BellRing
                                                                        className="size-3"
                                                                        aria-hidden="true"
                                                                    />
                                                                    Approval
                                                                    requested
                                                                </span>
                                                            )}
                                                        </div>
                                                        <Badge
                                                            variant="secondary"
                                                            className={cn(
                                                                'text-[0.7rem]',
                                                                STEP_STATUS_STYLES[
                                                                    workflowStep.approval_status ??
                                                                        'pending'
                                                                ] ??
                                                                    STEP_STATUS_STYLES.pending,
                                                            )}
                                                        >
                                                            {workflowStep.approval_status ??
                                                                'pending'}
                                                        </Badge>
                                                    </div>
                                                );
                                            },
                                        )}
                                    </div>
                                </div>
                            </div>

                            {step.approval_request && (
                                <ApprovalRequestBanner
                                    request={step.approval_request}
                                />
                            )}

                            {step.action_type === 'compare_quotes' ? (
                                <QuoteComparisonPreview
                                    draft={quoteDraft}
                                    selection={resolvedQuoteSelection}
                                    onSelectionChange={setQuoteSelection}
                                />
                            ) : step.action_type === 'award_quote' ? (
                                <AwardQuotePreview draft={awardDraft} />
                            ) : step.action_type === 'po_draft' ? (
                                <PoDraftPreview
                                    draft={poDraft}
                                    acknowledged={poAcknowledged}
                                    onAcknowledgeChange={setPoAcknowledged}
                                />
                            ) : step.action_type === 'receiving_quality' ? (
                                receivingQualityDraft ? (
                                    <ReceivingQualityPreview
                                        draft={receivingQualityDraft}
                                    />
                                ) : (
                                    <GenericDraftPreview payload={step.draft} />
                                )
                            ) : step.action_type === 'receipt_draft' ? (
                                receiptDraft ? (
                                    <ReceiptDraftPreview draft={receiptDraft} />
                                ) : (
                                    <GenericDraftPreview payload={step.draft} />
                                )
                            ) : step.action_type === 'invoice_match' ? (
                                <InvoiceMatchPreview
                                    draft={invoiceMatchDraft}
                                    rawPayload={step.draft}
                                    confirmed={invoiceMatchAcknowledged}
                                    onConfirmChange={
                                        setInvoiceMatchAcknowledged
                                    }
                                />
                            ) : step.action_type === 'payment_process' ? (
                                paymentDraft ? (
                                    <PaymentDraftPreview draft={paymentDraft} />
                                ) : (
                                    <GenericDraftPreview payload={step.draft} />
                                )
                            ) : (
                                <GenericDraftPreview payload={step.draft} />
                            )}

                            {!canResolveStep && (
                                <Alert>
                                    <AlertTitle>Approval restricted</AlertTitle>
                                    <AlertDescription>
                                        You need both the AI workflow
                                        entitlement and the proper module
                                        permission to resolve this step.
                                    </AlertDescription>
                                </Alert>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="workflow-notes">
                                    Reviewer notes
                                </Label>
                                <Textarea
                                    id="workflow-notes"
                                    placeholder="Optional: document why you approved, rejected, or overrode the AI recommendation."
                                    value={reviewNotes}
                                    onChange={(event) =>
                                        setReviewNotes(event.target.value)
                                    }
                                    rows={4}
                                />
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    type="button"
                                    onClick={handleApprove}
                                    disabled={approveDisabled}
                                >
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

function ApprovalRequestBanner({
    request,
}: {
    request: AiWorkflowApprovalRequest;
}) {
    const approverLabel =
        request.approver_user?.name ??
        (request.approver_role
            ? humanizeLabel(request.approver_role)
            : 'Approver');
    const requesterLabel = request.requested_by?.name ?? 'Unknown teammate';
    const statusLabel = humanizeLabel(request.status ?? 'pending');

    return (
        <div className="rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-amber-900 dark:border-amber-300/60 dark:bg-amber-400/10 dark:text-amber-50">
            <div className="flex flex-wrap items-center gap-3">
                <div className="flex items-center gap-2">
                    <BellRing className="size-4" aria-hidden="true" />
                    <div>
                        <p className="text-sm font-semibold">
                            Approval requested
                        </p>
                        <p className="text-xs text-amber-900/70 dark:text-amber-100/70">
                            Waiting on {approverLabel}
                        </p>
                    </div>
                </div>
                <Badge
                    variant="outline"
                    className="ml-auto border-amber-200 bg-white/60 text-[0.7rem] font-semibold tracking-wide text-amber-900 uppercase dark:border-amber-300/80 dark:bg-transparent dark:text-amber-100"
                >
                    {statusLabel}
                </Badge>
            </div>
            <div className="mt-4 grid gap-4 text-sm md:grid-cols-3">
                <ApprovalRequestMetadata
                    label="Requested by"
                    value={requesterLabel}
                />
                <ApprovalRequestMetadata
                    label="Requested"
                    value={formatRelativeTime(request.created_at)}
                />
                <ApprovalRequestMetadata
                    label="Approver"
                    value={approverLabel}
                />
            </div>
            {request.message ? (
                <div className="mt-4 rounded-lg border border-amber-200/70 bg-white/80 p-3 text-sm text-amber-900 dark:border-amber-300/40 dark:bg-transparent dark:text-amber-50">
                    {request.message}
                </div>
            ) : null}
        </div>
    );
}

function ApprovalRequestMetadata({
    label,
    value,
}: {
    label: string;
    value: ReactNode;
}) {
    return (
        <div>
            <p className="text-[0.65rem] font-semibold tracking-wide text-amber-900/70 uppercase dark:text-amber-100/60">
                {label}
            </p>
            <p className="text-sm font-medium text-amber-950 dark:text-amber-50">
                {value}
            </p>
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
                    <Badge
                        variant="secondary"
                        className="flex items-center gap-1"
                    >
                        <ShieldAlert className="size-3.5" /> AI recommendation:{' '}
                        {recommendation}
                    </Badge>
                )}
            </div>
            <div className="overflow-x-auto rounded-xl border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/60 text-left text-xs text-muted-foreground uppercase">
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
                                <tr
                                    key={supplierId}
                                    className={cn(isSelected && 'bg-primary/5')}
                                >
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col">
                                            <span className="font-medium text-foreground">
                                                {ranking.supplier_name ??
                                                    supplierId}
                                            </span>
                                            {isRecommended && (
                                                <span className="text-xs text-primary">
                                                    AI recommended
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {typeof ranking.score === 'number'
                                            ? `${ranking.score.toFixed(1)}`
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatCurrency(ranking.price)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {typeof ranking.lead_time_days ===
                                        'number'
                                            ? `${ranking.lead_time_days.toFixed(1)} d`
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {typeof ranking.quality_rating ===
                                        'number'
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
                    <Label htmlFor="supplier-selection">
                        Reviewer selection
                    </Label>
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
                                <SelectItem
                                    key={ranking.supplier_id}
                                    value={ranking.supplier_id}
                                >
                                    {ranking.supplier_name ??
                                        ranking.supplier_id}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="rounded-xl border border-dashed p-3 text-sm text-muted-foreground">
                    {selection && selection !== recommendation ? (
                        <p>
                            Manual override in effect. Document why you selected
                            this supplier in the notes before approving.
                        </p>
                    ) : (
                        <p>Your selection matches the AI recommendation.</p>
                    )}
                </div>
            </div>
        </div>
    );
}

function AwardQuotePreview({ draft }: { draft: AwardQuoteDraft | null }) {
    if (!draft) {
        return (
            <EmptyState
                title="No award details"
                description="The AI service did not supply an award recommendation for this step."
            />
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-start gap-4 rounded-xl border bg-card/60 p-4">
                <div>
                    <Badge variant="outline">
                        {draft.selected_quote_id
                            ? `Quote ${draft.selected_quote_id}`
                            : 'Quote selection'}
                    </Badge>
                    <p className="text-base font-semibold text-foreground">
                        {draft.supplier_name ??
                            draft.supplier_id ??
                            'Unnamed supplier'}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        RFQ #{draft.rfq_id ?? '—'}
                    </p>
                </div>
                <div className="ml-auto text-right">
                    <p className="text-xs text-muted-foreground uppercase">
                        Target delivery
                    </p>
                    <p className="text-lg font-semibold text-foreground">
                        {draft.delivery_date ?? '—'}
                    </p>
                    {draft.delivery_date && (
                        <p className="text-xs text-muted-foreground">
                            {formatRelativeTime(draft.delivery_date)}
                        </p>
                    )}
                </div>
            </div>

            <div className="grid gap-3 text-sm md:grid-cols-3">
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Supplier ID
                    </p>
                    <p className="font-semibold text-foreground">
                        {draft.supplier_id ?? '—'}
                    </p>
                </div>
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Quote ID
                    </p>
                    <p className="font-semibold text-foreground">
                        {draft.selected_quote_id ?? '—'}
                    </p>
                </div>
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        RFQ ID
                    </p>
                    <p className="font-semibold text-foreground">
                        {draft.rfq_id ?? '—'}
                    </p>
                </div>
            </div>

            <div className="rounded-xl border border-dashed bg-muted/20 p-4">
                <p className="text-sm font-semibold text-foreground">
                    Justification
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {draft.justification ?? 'No justification provided.'}
                </p>
            </div>

            {draft.terms.length > 0 && (
                <CollapsibleSection title="Award terms" defaultOpen>
                    <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                        {draft.terms.map((term, index) => (
                            <li key={`${term}-${index}`}>{term}</li>
                        ))}
                    </ul>
                </CollapsibleSection>
            )}
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
                    <Badge variant="outline">
                        {draft.po_number ?? 'PO Draft'}
                    </Badge>
                    <p className="text-base font-semibold text-foreground">
                        {draft.supplier?.name ?? 'Unnamed supplier'}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Supplier ID {draft.supplier?.supplier_id ?? '—'}
                    </p>
                </div>
                <div className="ml-auto text-right">
                    <p className="text-xs text-muted-foreground uppercase">
                        Total value
                    </p>
                    <p className="text-2xl font-semibold text-foreground">
                        {formatCurrency(
                            draft.total_value,
                            draft.currency ?? 'USD',
                        )}
                    </p>
                </div>
            </div>

            <CollapsibleSection title="Line items" defaultOpen>
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-left text-xs text-muted-foreground uppercase">
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
                                <tr
                                    key={`${item.item_code ?? index}-${item.line_number ?? index}`}
                                >
                                    <td className="px-4 py-3">
                                        {item.line_number ?? index + 1}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col">
                                            <span className="font-medium text-foreground">
                                                {item.description ?? '—'}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {item.item_code ?? '—'}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {typeof item.quantity === 'number'
                                            ? `${item.quantity} ${item.uom ?? ''}`
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatCurrency(
                                            item.unit_price,
                                            item.currency ??
                                                draft.currency ??
                                                'USD',
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatCurrency(
                                            item.subtotal,
                                            item.currency ??
                                                draft.currency ??
                                                'USD',
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {item.delivery_date ?? '—'}
                                    </td>
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
                            <li
                                key={`${milestone.milestone ?? index}-${milestone.date ?? index}`}
                                className="rounded-lg border p-3"
                            >
                                <p className="font-medium text-foreground">
                                    {milestone.milestone ??
                                        `Milestone ${index + 1}`}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {milestone.date ?? 'Date TBD'}
                                </p>
                                <p className="text-sm">
                                    Qty: {milestone.quantity ?? '—'}{' '}
                                    {milestone.notes
                                        ? `• ${milestone.notes}`
                                        : ''}
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
                <Checkbox
                    checked={acknowledged}
                    onCheckedChange={(value) =>
                        onAcknowledgeChange(Boolean(value))
                    }
                />
                I reviewed every line item, delivery, and term above.
            </label>
        </div>
    );
}

function ReceivingQualityPreview({ draft }: { draft: ReceivingQualityDraft }) {
    return (
        <div className="space-y-4">
            <div className="rounded-xl border bg-card/60 p-4">
                <div className="flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground uppercase">
                    <span>Receiving &amp; Quality</span>
                    {draft.entity_label ? (
                        <Badge variant="outline">{draft.entity_label}</Badge>
                    ) : null}
                </div>
                <p className="mt-2 text-base font-semibold text-foreground">
                    {draft.title ?? 'Receiving & Quality review'}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {draft.summary ??
                        'Copilot did not include a summary for this review.'}
                </p>
                {draft.highlights.length ? (
                    <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                        {draft.highlights
                            .slice(0, 4)
                            .map((highlight, index) => (
                                <li key={`${highlight}-${index}`}>
                                    {highlight}
                                </li>
                            ))}
                    </ul>
                ) : null}
            </div>

            {draft.metadata.length ? (
                <div className="grid gap-3 text-sm sm:grid-cols-2">
                    {draft.metadata.map((entry) => (
                        <div
                            key={entry.key}
                            className="rounded-lg border bg-muted/30 p-3"
                        >
                            <p className="text-xs text-muted-foreground uppercase">
                                {humanizeLabel(entry.key)}
                            </p>
                            <p className="font-semibold text-foreground">
                                {entry.value}
                            </p>
                        </div>
                    ))}
                </div>
            ) : null}

            {draft.checklist.length ? (
                <div className="space-y-3">
                    <p className="text-sm font-semibold text-foreground">
                        Quality checklist
                    </p>
                    <div className="space-y-3">
                        {draft.checklist.map((item, index) => (
                            <div
                                key={`${item.label}-${index}`}
                                className="rounded-xl border bg-muted/40 p-4"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <p className="text-sm font-semibold text-foreground">
                                        {item.label}
                                    </p>
                                    <Badge
                                        variant="outline"
                                        className={cn(
                                            'text-[10px] tracking-wide uppercase',
                                            receivingStatusBadgeClass(
                                                item.status,
                                            ),
                                        )}
                                    >
                                        {item.status}
                                    </Badge>
                                </div>
                                {item.value ? (
                                    <p className="text-xs text-muted-foreground">
                                        {item.value}
                                    </p>
                                ) : null}
                                {item.detail ? (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {item.detail}
                                    </p>
                                ) : null}
                            </div>
                        ))}
                    </div>
                </div>
            ) : null}

            {draft.warnings.length ? (
                <Alert variant="warning">
                    <AlertTitle className="flex items-center gap-2 text-sm font-semibold">
                        <AlertTriangle className="size-4" /> Warnings
                    </AlertTitle>
                    <AlertDescription>
                        <ul className="list-disc space-y-1 pl-5 text-sm">
                            {draft.warnings.map((warning, index) => (
                                <li key={`${warning}-${index}`}>{warning}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            ) : null}
        </div>
    );
}

function ReceiptDraftPreview({ draft }: { draft: ReceiptDraftDetails }) {
    const visibleLines = draft.lineItems.slice(0, 10);
    const hiddenLines = Math.max(
        0,
        draft.lineItems.length - visibleLines.length,
    );

    return (
        <div className="space-y-4">
            <div className="grid gap-3 text-sm md:grid-cols-3">
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Purchase order
                    </p>
                    <p className="font-semibold text-foreground">
                        {draft.poId ?? '—'}
                    </p>
                </div>
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Receipt reference
                    </p>
                    <p className="font-semibold text-foreground">
                        {draft.reference ?? '—'}
                    </p>
                </div>
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Received date
                    </p>
                    <p className="font-semibold text-foreground">
                        {formatDateLabel(draft.receivedDate)}
                    </p>
                </div>
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Inspector
                    </p>
                    <p className="font-semibold text-foreground">
                        {draft.inspectedBy ?? '—'}
                    </p>
                </div>
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Status
                    </p>
                    <p className="font-semibold text-foreground">
                        {draft.status ?? 'Draft'}
                    </p>
                </div>
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Total qty received
                    </p>
                    <p className="font-semibold text-foreground">
                        {formatQuantityValue(draft.totalReceivedQty)}
                    </p>
                </div>
            </div>

            <div>
                <p className="text-sm font-semibold text-foreground">
                    Receipt lines
                </p>
                {visibleLines.length ? (
                    <div className="mt-2 space-y-2">
                        {visibleLines.map((line, index) => {
                            const key = `${line.reference}-${line.lineNumber ?? index}`;
                            const isFlagged =
                                line.issues.length > 0 ||
                                Boolean(line.rejectedQty);
                            return (
                                <div
                                    key={key}
                                    className={cn(
                                        'rounded-xl border bg-muted/40 p-3 text-sm',
                                        isFlagged
                                            ? 'border-amber-300/40'
                                            : 'border-muted',
                                    )}
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-foreground">
                                                {line.description}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Line{' '}
                                                {line.lineNumber ??
                                                    line.reference}
                                            </p>
                                        </div>
                                        <span className="text-sm font-semibold text-foreground">
                                            {formatQuantityValue(
                                                line.receivedQty,
                                                line.uom,
                                            )}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Expected{' '}
                                        {formatQuantityValue(
                                            line.expectedQty,
                                            line.uom,
                                        )}{' '}
                                        · Accepted{' '}
                                        {formatQuantityValue(
                                            line.acceptedQty,
                                            line.uom,
                                        )}
                                        {line.rejectedQty
                                            ? ` · Rejected ${formatQuantityValue(line.rejectedQty, line.uom)}`
                                            : ''}
                                    </p>
                                    {line.issues.length ? (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {line.issues.map(
                                                (issue, issueIndex) => (
                                                    <Badge
                                                        key={`${key}-issue-${issueIndex}`}
                                                        variant="outline"
                                                        className="text-[11px] uppercase"
                                                    >
                                                        {issue}
                                                    </Badge>
                                                ),
                                            )}
                                        </div>
                                    ) : null}
                                    {line.notes ? (
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {line.notes}
                                        </p>
                                    ) : null}
                                </div>
                            );
                        })}
                        {hiddenLines > 0 ? (
                            <p className="text-xs text-muted-foreground">
                                +{hiddenLines} more receipt lines not shown.
                            </p>
                        ) : null}
                    </div>
                ) : (
                    <EmptyState
                        title="No receipt lines"
                        description="Copilot did not return individual receipt lines."
                    />
                )}
            </div>

            {draft.notes ? (
                <div className="rounded-xl border bg-muted/30 p-3 text-sm text-muted-foreground">
                    <p className="text-xs text-muted-foreground uppercase">
                        Notes
                    </p>
                    <p className="mt-1 text-foreground">{draft.notes}</p>
                </div>
            ) : null}

            {draft.warnings.length ? (
                <Alert variant="warning">
                    <AlertTitle className="flex items-center gap-2 text-sm font-semibold">
                        <AlertTriangle className="size-4" /> Warnings
                    </AlertTitle>
                    <AlertDescription>
                        <ul className="list-disc space-y-1 pl-5 text-sm">
                            {draft.warnings.map((warning, index) => (
                                <li key={`${warning}-${index}`}>{warning}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            ) : null}
        </div>
    );
}

function InvoiceMatchPreview({
    draft,
    rawPayload,
    confirmed,
    onConfirmChange,
}: {
    draft: InvoiceMatchDraft | null;
    rawPayload: Record<string, unknown>;
    confirmed: boolean;
    onConfirmChange: (value: boolean) => void;
}) {
    if (!draft) {
        return (
            <div className="space-y-4">
                <EmptyState
                    title="No invoice match summary"
                    description="Copilot did not include structured match data. Review the raw payload below."
                />
                <GenericDraftPreview payload={rawPayload} />
                <label className="flex items-center gap-3 text-sm font-medium text-foreground">
                    <Checkbox
                        checked={confirmed}
                        onCheckedChange={(value) =>
                            onConfirmChange(Boolean(value))
                        }
                    />
                    I reviewed the invoice data and want to continue.
                </label>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="grid gap-3 text-sm md:grid-cols-2">
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Invoice
                    </p>
                    <p className="font-semibold text-foreground">
                        {draft.invoiceId ?? '—'}
                    </p>
                </div>
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Matching PO
                    </p>
                    <p className="font-semibold text-foreground">
                        {draft.poId ?? '—'}
                    </p>
                </div>
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Receipts
                    </p>
                    <p className="font-semibold text-foreground">
                        {draft.receiptIds.length
                            ? draft.receiptIds.join(', ')
                            : '—'}
                    </p>
                </div>
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Match score
                    </p>
                    <p className="font-semibold text-foreground">
                        {formatPercent(draft.matchScore)}
                    </p>
                </div>
            </div>

            <div className="rounded-xl border bg-card/70 p-4 text-sm">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant="outline"
                        className={cn(
                            'text-[10px] tracking-wide uppercase',
                            matchRecommendationBadgeClass(
                                draft.recommendation?.status ?? null,
                            ),
                        )}
                    >
                        {formatMatchRecommendation(
                            draft.recommendation?.status ?? null,
                        )}
                    </Badge>
                    <span className="text-xs text-muted-foreground uppercase">
                        Recommendation
                    </span>
                </div>
                <p className="mt-2 text-foreground">
                    {draft.recommendation?.explanation ??
                        'Copilot did not include a recommendation note.'}
                </p>
            </div>

            <div>
                <p className="text-sm font-semibold text-foreground">
                    Mismatches
                </p>
                {draft.mismatches.length ? (
                    <div className="mt-2 space-y-2">
                        {draft.mismatches.map((mismatch, index) => (
                            <div
                                key={`${mismatch.type}-${mismatch.lineReference ?? index}`}
                                className="rounded-xl border bg-muted/40 p-3 text-sm"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <p className="font-semibold text-foreground">
                                        {mismatch.lineReference
                                            ? `Line ${mismatch.lineReference}`
                                            : mismatch.type}
                                    </p>
                                    <Badge
                                        variant="outline"
                                        className={cn(
                                            'text-[10px] tracking-wide uppercase',
                                            matchSeverityBadgeClass(
                                                mismatch.severity,
                                            ),
                                        )}
                                    >
                                        {mismatch.severity}
                                    </Badge>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {mismatch.type}
                                </p>
                                <p className="mt-1 text-sm text-foreground">
                                    {mismatch.detail}
                                </p>
                                {mismatch.expected !== null ||
                                mismatch.actual !== null ? (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {mismatch.expected !== null
                                            ? `Expected ${formatQuantityValue(mismatch.expected)}`
                                            : null}
                                        {mismatch.expected !== null &&
                                        mismatch.actual !== null
                                            ? ' · '
                                            : null}
                                        {mismatch.actual !== null
                                            ? `Actual ${formatQuantityValue(mismatch.actual)}`
                                            : null}
                                    </p>
                                ) : null}
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="mt-2 rounded-xl border border-emerald-300/40 bg-emerald-950/10 p-3 text-sm text-emerald-900 dark:text-emerald-200">
                        No mismatches detected.
                    </div>
                )}
            </div>

            {draft.analysisNotes.length ? (
                <div>
                    <p className="text-sm font-semibold text-foreground">
                        Analysis notes
                    </p>
                    <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                        {draft.analysisNotes.slice(0, 4).map((note, index) => (
                            <li key={`${note}-${index}`}>{note}</li>
                        ))}
                    </ul>
                </div>
            ) : null}

            {draft.warnings.length ? (
                <Alert variant="warning">
                    <AlertTitle className="flex items-center gap-2 text-sm font-semibold">
                        <AlertTriangle className="size-4" /> Warnings
                    </AlertTitle>
                    <AlertDescription>
                        <ul className="list-disc space-y-1 pl-5 text-sm">
                            {draft.warnings.map((warning, index) => (
                                <li key={`${warning}-${index}`}>{warning}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            ) : null}

            <label className="flex items-center gap-3 text-sm font-medium text-foreground">
                <Checkbox
                    checked={confirmed}
                    onCheckedChange={(value) => onConfirmChange(Boolean(value))}
                />
                I reviewed the mismatches above and confirm this invoice match.
            </label>
        </div>
    );
}

function PaymentDraftPreview({ draft }: { draft: PaymentDraftDetails }) {
    return (
        <div className="space-y-4">
            <div className="grid gap-3 text-sm sm:grid-cols-2">
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Invoice
                    </p>
                    <p className="font-semibold text-foreground">
                        {draft.invoiceId ?? '—'}
                    </p>
                </div>
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Scheduled date
                    </p>
                    <p className="font-semibold text-foreground">
                        {formatDateLabel(draft.scheduledDate)}
                    </p>
                </div>
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Payment method
                    </p>
                    <p className="font-semibold text-foreground">
                        {draft.paymentMethod ?? '—'}
                    </p>
                </div>
                <div className="rounded-xl border bg-muted/30 p-3">
                    <p className="text-xs text-muted-foreground uppercase">
                        Reference
                    </p>
                    <p className="font-semibold text-foreground">
                        {draft.reference ?? '—'}
                    </p>
                </div>
            </div>

            <div className="rounded-xl border bg-muted/20 p-4 text-center">
                <p className="text-xs text-muted-foreground uppercase">
                    Planned amount
                </p>
                <p className="mt-2 text-3xl font-semibold text-foreground">
                    {draft.amount === null
                        ? '—'
                        : formatCurrency(draft.amount, draft.currency)}
                </p>
            </div>

            {draft.notes ? (
                <div className="rounded-xl border bg-muted/30 p-3 text-sm text-muted-foreground">
                    <p className="text-xs text-muted-foreground uppercase">
                        Notes
                    </p>
                    <p className="mt-1 text-foreground">{draft.notes}</p>
                </div>
            ) : null}

            {draft.warnings.length ? (
                <Alert variant="warning">
                    <AlertTitle className="flex items-center gap-2 text-sm font-semibold">
                        <AlertTriangle className="size-4" /> Warnings
                    </AlertTitle>
                    <AlertDescription>
                        <ul className="list-disc space-y-1 pl-5 text-sm">
                            {draft.warnings.map((warning, index) => (
                                <li key={`${warning}-${index}`}>{warning}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            ) : null}
        </div>
    );
}

function GenericDraftPreview({
    payload,
}: {
    payload: Record<string, unknown> | undefined;
}) {
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
                <ChevronDown
                    className={cn(
                        'size-4 transition-transform',
                        open && 'rotate-180',
                    )}
                />
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
        return value.filter(
            (entry): entry is string =>
                typeof entry === 'string' && entry.length > 0,
        );
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
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
        }).format(value);
    } catch (error) {
        void error;
        return `$${value.toFixed(2)}`;
    }
}

function unwrapPayload(
    raw: Record<string, unknown> | undefined | null,
): Record<string, unknown> | null {
    if (!raw || typeof raw !== 'object') {
        return null;
    }

    const envelope = raw as Record<string, unknown>;

    if (isRecord(envelope.payload)) {
        return envelope.payload;
    }

    return envelope as Record<string, unknown>;
}

function parseQuoteDraft(
    raw: Record<string, unknown> | undefined | null,
): QuoteComparisonDraft {
    const source = unwrapPayload(raw);

    if (!source) {
        return { rankings: [] };
    }

    const rankings = Array.isArray(source['rankings'])
        ? (source['rankings'] as unknown[])
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
                      supplier_name:
                          typeof map['supplier_name'] === 'string'
                              ? (map['supplier_name'] as string)
                              : null,
                      score: toNumber(map['score']),
                      normalized_score: toNumber(map['normalized_score']),
                      price: toNumber(map['price']),
                      lead_time_days: toNumber(map['lead_time_days']),
                      quality_rating: toNumber(map['quality_rating']),
                      risk_score: toNumber(map['risk_score']),
                      notes:
                          typeof map['notes'] === 'string'
                              ? (map['notes'] as string)
                              : null,
                  } satisfies QuoteRanking;
              })
              .filter((entry): entry is QuoteRanking => entry !== null)
        : [];

    const summaryPayload = (source['summary'] ?? []) as unknown;
    const summary = Array.isArray(summaryPayload)
        ? summaryPayload.filter(
              (entry): entry is string => typeof entry === 'string',
          )
        : [];

    return {
        recommendation: normalizeId(source['recommendation']),
        summary,
        rankings,
    };
}

function parsePoDraft(
    raw: Record<string, unknown> | undefined | null,
): PoDraftPayload | null {
    const source = unwrapPayload(raw);

    if (!source) {
        return null;
    }

    const supplierBlock = isRecord(source.supplier)
        ? {
              supplier_id:
                  normalizeId(
                      source.supplier.supplier_id ?? source.supplier.id,
                  ) ?? null,
              name:
                  typeof source.supplier.name === 'string'
                      ? source.supplier.name
                      : null,
              contact:
                  typeof source.supplier.contact === 'string'
                      ? source.supplier.contact
                      : null,
          }
        : null;

    const lineItems = Array.isArray(source.line_items)
        ? source.line_items
              .map((item) => {
                  if (!isRecord(item)) {
                      return null;
                  }
                  return {
                      line_number: toNumber(item.line_number),
                      item_code:
                          typeof item.item_code === 'string'
                              ? item.item_code
                              : null,
                      description:
                          typeof item.description === 'string'
                              ? item.description
                              : null,
                      quantity: toNumber(item.quantity),
                      uom: typeof item.uom === 'string' ? item.uom : null,
                      unit_price: toNumber(item.unit_price),
                      currency:
                          typeof item.currency === 'string'
                              ? item.currency
                              : null,
                      subtotal: toNumber(item.subtotal),
                      delivery_date:
                          typeof item.delivery_date === 'string'
                              ? item.delivery_date
                              : null,
                  } satisfies PoLineItem;
              })
              .filter((entry): entry is PoLineItem => entry !== null)
        : [];

    const deliverySchedule = Array.isArray(source.delivery_schedule)
        ? source.delivery_schedule
              .map((entry) => {
                  if (!isRecord(entry)) {
                      return null;
                  }
                  return {
                      milestone:
                          typeof entry.milestone === 'string'
                              ? entry.milestone
                              : null,
                      date: typeof entry.date === 'string' ? entry.date : null,
                      quantity: toNumber(entry.quantity),
                      notes:
                          typeof entry.notes === 'string' ? entry.notes : null,
                  } satisfies PoDeliveryMilestone;
              })
              .filter((entry): entry is PoDeliveryMilestone => entry !== null)
        : [];

    const terms = Array.isArray(source.terms_and_conditions)
        ? source.terms_and_conditions
              .filter(
                  (term): term is string =>
                      typeof term === 'string' && term.trim().length > 0,
              )
              .map((term) => term.trim())
        : [];

    return {
        po_number:
            typeof source.po_number === 'string' ? source.po_number : null,
        supplier: supplierBlock,
        currency: typeof source.currency === 'string' ? source.currency : 'USD',
        line_items: lineItems,
        delivery_schedule: deliverySchedule,
        terms_and_conditions: terms,
        total_value: toNumber(source.total_value),
    };
}

function parseAwardDraft(
    raw: Record<string, unknown> | undefined | null,
): AwardQuoteDraft | null {
    const source = unwrapPayload(raw);

    if (!source) {
        return null;
    }

    const supplierBlock = isRecord(source.supplier) ? source.supplier : null;
    const supplierName =
        typeof source.supplier_name === 'string'
            ? (source.supplier_name as string)
            : supplierBlock && typeof supplierBlock.name === 'string'
              ? (supplierBlock.name as string)
              : null;

    const supplierId =
        normalizeId(source.supplier_id) ??
        (supplierBlock
            ? normalizeId(supplierBlock.supplier_id ?? supplierBlock.id)
            : null);

    const termsSource = Array.isArray(source.terms)
        ? source.terms
        : Array.isArray(source.terms_and_conditions)
          ? source.terms_and_conditions
          : [];

    const terms = termsSource
        .filter(
            (entry): entry is string =>
                typeof entry === 'string' && entry.trim().length > 0,
        )
        .map((entry) => entry.trim());

    return {
        rfq_id: normalizeId(source.rfq_id),
        supplier_id: supplierId,
        supplier_name: supplierName,
        selected_quote_id: normalizeId(
            source.selected_quote_id ?? source.quote_id,
        ),
        justification:
            typeof source.justification === 'string'
                ? (source.justification as string)
                : null,
        delivery_date:
            typeof source.delivery_date === 'string'
                ? (source.delivery_date as string)
                : null,
        terms,
    };
}

function parseReceivingQualityDraft(
    raw: Record<string, unknown> | undefined | null,
): ReceivingQualityDraft | null {
    const source = unwrapPayload(raw);

    if (!source) {
        return null;
    }

    const highlights = extractStringList(
        source.highlights ?? source.summary_points ?? [],
    );
    const warnings = extractStringList(source.warnings ?? source.alerts ?? []);

    const metadataEntries = Array.isArray(source.metadata)
        ? source.metadata
              .map((entry) => {
                  if (
                      !isRecord(entry) ||
                      typeof entry.key !== 'string' ||
                      typeof entry.value !== 'string'
                  ) {
                      return null;
                  }
                  return { key: entry.key, value: entry.value };
              })
              .filter(
                  (entry): entry is { key: string; value: string } =>
                      entry !== null,
              )
        : isRecord(source.metadata)
          ? Object.entries(source.metadata as Record<string, unknown>)
                .filter(
                    ([, value]) =>
                        typeof value === 'string' || typeof value === 'number',
                )
                .map(([key, value]) => ({ key, value: String(value) }))
          : [];

    const checklist = Array.isArray(source.checklist)
        ? source.checklist
              .map((entry) => {
                  if (!isRecord(entry) || typeof entry.label !== 'string') {
                      return null;
                  }
                  const statusRaw =
                      typeof entry.status === 'string'
                          ? (entry.status as string).toLowerCase()
                          : 'ok';
                  const status: ReceivingQualityChecklistItem['status'] = [
                      'warning',
                      'risk',
                  ].includes(statusRaw as string)
                      ? (statusRaw as ReceivingQualityChecklistItem['status'])
                      : 'ok';
                  return {
                      label: entry.label,
                      detail:
                          typeof entry.detail === 'string'
                              ? entry.detail
                              : null,
                      status,
                      value:
                          typeof entry.value === 'string' ? entry.value : null,
                  } satisfies ReceivingQualityChecklistItem;
              })
              .filter(
                  (entry): entry is ReceivingQualityChecklistItem =>
                      entry !== null,
              )
        : [];

    return {
        title: typeof source.title === 'string' ? source.title : null,
        summary: typeof source.summary === 'string' ? source.summary : null,
        entity_label:
            typeof source.entity_label === 'string'
                ? source.entity_label
                : null,
        highlights,
        checklist,
        warnings,
        metadata: metadataEntries,
    };
}

function parseReceiptDraft(
    raw: Record<string, unknown> | undefined | null,
): ReceiptDraftDetails | null {
    const source = unwrapPayload(raw);

    if (!source) {
        return null;
    }

    const warnings = extractStringList(source.warnings ?? source.alerts ?? []);

    const lineItemsSource = Array.isArray(source.line_items)
        ? source.line_items
        : Array.isArray(source.lines)
          ? source.lines
          : [];

    const lineItems = lineItemsSource
        .map((entry, index) => {
            if (!isRecord(entry)) {
                return null;
            }
            const issues = extractStringList(entry.issues ?? entry.flags ?? []);
            return {
                reference:
                    typeof entry.reference === 'string'
                        ? entry.reference
                        : `line-${index + 1}`,
                lineNumber: toNumber(entry.line_number ?? entry.lineNumber),
                description:
                    typeof entry.description === 'string'
                        ? entry.description
                        : `Line item ${index + 1}`,
                uom: typeof entry.uom === 'string' ? entry.uom : null,
                expectedQty: toNumber(entry.expected_qty ?? entry.expectedQty),
                receivedQty: toNumber(entry.received_qty ?? entry.receivedQty),
                acceptedQty: toNumber(
                    entry.accepted_qty ??
                        entry.acceptedQty ??
                        entry.received_qty ??
                        entry.receivedQty,
                ),
                rejectedQty: toNumber(entry.rejected_qty ?? entry.rejectedQty),
                issues,
                notes: typeof entry.notes === 'string' ? entry.notes : null,
            } satisfies ReceiptDraftLine;
        })
        .filter((entry): entry is ReceiptDraftLine => entry !== null);

    return {
        poId: normalizeId(
            source.po_id ?? source.purchase_order_id ?? source.poId,
        ),
        reference:
            typeof source.reference === 'string' ? source.reference : null,
        inspectedBy:
            typeof source.inspected_by === 'string'
                ? source.inspected_by
                : typeof source.inspector === 'string'
                  ? source.inspector
                  : null,
        receivedDate:
            typeof source.received_date === 'string'
                ? source.received_date
                : typeof source.receivedDate === 'string'
                  ? source.receivedDate
                  : null,
        status: typeof source.status === 'string' ? source.status : null,
        totalReceivedQty: toNumber(
            source.total_received_qty ??
                source.total_received_quantity ??
                source.totalReceivedQty,
        ),
        notes: typeof source.notes === 'string' ? source.notes : null,
        lineItems,
        warnings,
    };
}

function parseInvoiceMatchDraft(
    raw: Record<string, unknown> | undefined | null,
): InvoiceMatchDraft | null {
    const source = unwrapPayload(raw);

    if (!source) {
        return null;
    }

    const receiptSource = (source.receipt_ids ?? source.receipts) as unknown;
    const receiptIds = Array.isArray(receiptSource)
        ? receiptSource
              .map((entry) => normalizeId(entry))
              .filter((value): value is string => Boolean(value))
        : [];

    const mismatches = Array.isArray(source.mismatches)
        ? source.mismatches
              .map((entry) => {
                  if (!isRecord(entry)) {
                      return null;
                  }
                  const severityRaw =
                      typeof entry.severity === 'string'
                          ? entry.severity.toLowerCase()
                          : 'info';
                  const severity: InvoiceMatchSeverity = [
                      'warning',
                      'risk',
                  ].includes(severityRaw)
                      ? (severityRaw as InvoiceMatchSeverity)
                      : 'info';
                  return {
                      type:
                          typeof entry.type === 'string'
                              ? entry.type
                              : 'mismatch',
                      lineReference:
                          typeof entry.line_reference === 'string'
                              ? entry.line_reference
                              : typeof entry.line === 'string'
                                ? entry.line
                                : null,
                      severity,
                      detail:
                          typeof entry.detail === 'string'
                              ? entry.detail
                              : 'Mismatch detected',
                      expected: toNumber(entry.expected),
                      actual: toNumber(entry.actual),
                  } satisfies InvoiceMatchMismatch;
              })
              .filter((entry): entry is InvoiceMatchMismatch => entry !== null)
        : [];

    const recommendation = isRecord(source.recommendation)
        ? {
              status:
                  typeof source.recommendation.status === 'string'
                      ? source.recommendation.status
                      : null,
              explanation:
                  typeof source.recommendation.explanation === 'string'
                      ? source.recommendation.explanation
                      : typeof source.recommendation.note === 'string'
                        ? source.recommendation.note
                        : null,
          }
        : null;

    return {
        invoiceId: normalizeId(source.invoice_id ?? source.invoiceId),
        poId: normalizeId(
            source.po_id ?? source.purchase_order_id ?? source.poId,
        ),
        receiptIds,
        matchScore: toNumber(source.match_score ?? source.score),
        recommendation,
        mismatches,
        analysisNotes: extractStringList(
            source.analysis_notes ?? source.notes ?? [],
        ),
        warnings: extractStringList(source.warnings ?? source.alerts ?? []),
    };
}

function parsePaymentDraft(
    raw: Record<string, unknown> | undefined | null,
): PaymentDraftDetails | null {
    const source = unwrapPayload(raw);

    if (!source) {
        return null;
    }

    return {
        invoiceId: normalizeId(source.invoice_id ?? source.invoiceId),
        scheduledDate:
            typeof source.scheduled_date === 'string'
                ? source.scheduled_date
                : typeof source.payment_date === 'string'
                  ? source.payment_date
                  : null,
        paymentMethod:
            typeof source.payment_method === 'string'
                ? source.payment_method
                : typeof source.method === 'string'
                  ? source.method
                  : null,
        reference:
            typeof source.reference === 'string' ? source.reference : null,
        amount: toNumber(source.amount),
        currency: typeof source.currency === 'string' ? source.currency : 'USD',
        notes: typeof source.notes === 'string' ? source.notes : null,
        warnings: extractStringList(source.warnings ?? source.alerts ?? []),
    };
}

function buildCompletionOutput(
    step: AiWorkflowStepDetail,
    approval: boolean,
    context: {
        quoteDraft: QuoteComparisonDraft;
        poDraft: PoDraftPayload | null;
        selectedSupplier: string | null;
        awardDraft: AwardQuoteDraft | null;
    },
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
        const recommendation =
            context.selectedSupplier ??
            context.quoteDraft.recommendation ??
            null;
        return {
            summary: 'Quote comparison approved',
            payload: {
                recommendation,
                rankings: context.quoteDraft.rankings,
                summary: context.quoteDraft.summary,
            },
        };
    }

    if (step.action_type === 'award_quote') {
        return {
            summary: 'Quote award approved',
            payload: context.awardDraft ?? step.draft ?? {},
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
    const next =
        typeof cursor.next_cursor === 'string' ? cursor.next_cursor : null;
    const prev =
        typeof cursor.prev_cursor === 'string' ? cursor.prev_cursor : null;
    const hasNext = Boolean(cursor.has_next ?? next);
    const hasPrev = Boolean(cursor.has_prev ?? prev);

    return { next, prev, hasNext, hasPrev };
}

function isRecord(value: unknown): value is Record<string, unknown> {
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

function extractStringList(value: unknown): string[] {
    if (typeof value === 'string' && value.trim().length > 0) {
        return [value.trim()];
    }
    if (!Array.isArray(value)) {
        return [];
    }
    return value
        .filter(
            (entry): entry is string =>
                typeof entry === 'string' && entry.trim().length > 0,
        )
        .map((entry) => entry.trim());
}

function humanizeLabel(value: string): string {
    const text = value.replace(/[_-]+/g, ' ').trim();
    if (!text) {
        return value;
    }
    return text
        .split(' ')
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
}

function formatDateLabel(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    try {
        return new Intl.DateTimeFormat(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        }).format(date);
    } catch (error) {
        void error;
        return date.toISOString().slice(0, 10);
    }
}

function formatQuantityValue(
    value: number | null | undefined,
    uom?: string | null,
): string {
    if (!(typeof value === 'number' && Number.isFinite(value))) {
        return uom ? `— ${uom}` : '—';
    }
    const formatted = value.toLocaleString(undefined, {
        maximumFractionDigits: 2,
        minimumFractionDigits: value % 1 === 0 ? 0 : 2,
    });
    return uom ? `${formatted} ${uom}` : formatted;
}

function formatPercent(value: number | null | undefined): string {
    if (!(typeof value === 'number' && Number.isFinite(value))) {
        return '—';
    }
    const percentage = value <= 1 ? value * 100 : value;
    const decimals = percentage % 1 === 0 ? 0 : 1;
    return `${percentage.toFixed(decimals)}%`;
}

function receivingStatusBadgeClass(
    status: ReceivingQualityChecklistItem['status'],
): string {
    if (status === 'risk') {
        return 'border-destructive/40 text-destructive';
    }
    if (status === 'warning') {
        return 'border-amber-300/60 text-amber-900 dark:text-amber-200';
    }
    return 'border-emerald-300/60 text-emerald-900 dark:text-emerald-200';
}

function matchRecommendationBadgeClass(status: string | null): string {
    switch (status) {
        case 'auto_approve':
        case 'approve':
            return 'border-emerald-300/60 text-emerald-900 dark:text-emerald-200';
        case 'hold':
        case 'manual_review':
            return 'border-amber-300/60 text-amber-900 dark:text-amber-200';
        case 'reject':
            return 'border-destructive/40 text-destructive';
        default:
            return 'border-muted-foreground/40 text-muted-foreground';
    }
}

function matchSeverityBadgeClass(severity: InvoiceMatchSeverity): string {
    if (severity === 'risk') {
        return 'border-destructive/40 text-destructive';
    }
    if (severity === 'warning') {
        return 'border-amber-300/60 text-amber-900 dark:text-amber-200';
    }
    return 'border-muted-foreground/40 text-muted-foreground';
}

function formatMatchRecommendation(status: string | null): string {
    switch (status) {
        case 'auto_approve':
        case 'approve':
            return 'Auto approve';
        case 'manual_review':
            return 'Manual review';
        case 'hold':
            return 'Hold payment';
        case 'reject':
            return 'Reject invoice';
        default:
            return 'Review required';
    }
}
