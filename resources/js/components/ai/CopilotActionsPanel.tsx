import { type ComponentType, useEffect, useId, useMemo, useState } from 'react';
import {
    BarChart3,
    ClipboardList,
    ExternalLink,
    FileSpreadsheet,
    Filter,
    Info,
    Mail,
    Plus,
    Sparkles,
    Tag,
    Wrench,
} from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { AiDraftReviewModal } from '@/components/ai/AiDraftReviewModal';
import { errorToast, successToast } from '@/components/toasts';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { ApiError, api } from '@/lib/api';
import { cn } from '@/lib/utils';
import {
    approveCopilotAction,
    CopilotActionDraft,
    CopilotActionPlanPayload,
    CopilotActionType,
    planCopilotAction,
    rejectCopilotAction,
} from '@/services/ai';

const SOURCE_TYPE_OPTIONS: Array<{ value: string; label: string }> = [
    { value: '', label: 'All sources' },
    { value: 'document_control', label: 'Document Control Hub' },
    { value: 'maintenance_manual', label: 'Maintenance manuals' },
    { value: 'rfq', label: 'RFQ text' },
];

const ACTION_CONFIGS: Record<
    CopilotActionType,
    {
        label: string;
        description: string;
        instructionsPlaceholder: string;
        icon: ComponentType<{ className?: string }>;
    }
> = {
    rfq_draft: {
        label: 'RFQ Draft',
        description: 'Copilot drafts sourcing scope, line items, and evaluation rubric. You finalize before publishing.',
        instructionsPlaceholder: 'Describe the sourcing need, quantities, and any compliance notes.',
        icon: FileSpreadsheet,
    },
    supplier_message: {
        label: 'Supplier Message',
        description: 'Compose negotiation-ready outreach with bullets for pricing, lead times, or quality topics.',
        instructionsPlaceholder: 'Share the negotiation goal, blockers, or tone you want Copilot to mirror.',
        icon: Mail,
    },
    maintenance_checklist: {
        label: 'Maintenance Checklist',
        description: 'Generate a grounded run-book for assets, including safety notes and escalation triggers.',
        instructionsPlaceholder: 'Describe the asset symptom, environment, and urgency.',
        icon: Wrench,
    },
    inventory_whatif: {
        label: 'Inventory What-If',
        description: 'Simulate policy changes for parts and get projected stockout risk plus holding-cost deltas.',
        instructionsPlaceholder: 'Explain the scenario (e.g., raise safety stock for part CR-45 due to demand spike).',
        icon: BarChart3,
    },
};

const DEFAULT_TOP_K = 10;

type RfqInputState = {
    category: string;
    method: string;
    material: string;
    incoterm: string;
    currency: string;
    tolerance_finish: string;
    delivery_location: string;
    due_at: string;
    close_at: string;
    open_bidding: boolean;
    commercial_terms: string;
    questions_for_suppliers: string;
    evaluation_criteria: string;
};

type SupplierMessageInputState = {
    supplier_id: string;
    supplier_name: string;
    goal: string;
    tone: string;
    constraints: string;
    context: string;
    negotiation_points: string;
    fallback_options: string;
};

type MaintenanceInputState = {
    asset_id: string;
    asset_name: string;
    task_title: string;
    symptom: string;
    environment: string;
    urgency: string;
    due_at: string;
    safety_notes: string;
    diagnostic_steps: string;
    likely_causes: string;
    recommended_actions: string;
    escalation_rules: string;
};

type InventoryInputState = {
    part_identifier: string;
    scenario_name: string;
    service_level_target: string;
    current_reorder_point: string;
    current_safety_stock: string;
    current_lead_time: string;
    proposed_reorder_point: string;
    proposed_safety_stock: string;
    proposed_lead_time: string;
    forecast_avg_daily: string;
    forecast_std_dev: string;
    holding_cost_per_unit: string;
};

type RfqLineItemDraft = {
    id: number;
    partId: string;
    description: string;
    quantity: string;
    targetDate: string;
};

const createDefaultRfqInputs = (): RfqInputState => ({
    category: '',
    method: '',
    material: '',
    incoterm: 'FOB',
    currency: 'USD',
    tolerance_finish: '',
    delivery_location: '',
    due_at: '',
    close_at: '',
    open_bidding: false,
    commercial_terms: '',
    questions_for_suppliers: '',
    evaluation_criteria: '',
});

const createDefaultSupplierInputs = (): SupplierMessageInputState => ({
    supplier_id: '',
    supplier_name: '',
    goal: '',
    tone: 'professional',
    constraints: '',
    context: '',
    negotiation_points: '',
    fallback_options: '',
});

const createDefaultMaintenanceInputs = (): MaintenanceInputState => ({
    asset_id: '',
    asset_name: '',
    task_title: '',
    symptom: '',
    environment: '',
    urgency: 'medium',
    due_at: '',
    safety_notes: '',
    diagnostic_steps: '',
    likely_causes: '',
    recommended_actions: '',
    escalation_rules: '',
});

const createDefaultInventoryInputs = (): InventoryInputState => ({
    part_identifier: '',
    scenario_name: '',
    service_level_target: '0.95',
    current_reorder_point: '',
    current_safety_stock: '',
    current_lead_time: '',
    proposed_reorder_point: '',
    proposed_safety_stock: '',
    proposed_lead_time: '',
    forecast_avg_daily: '',
    forecast_std_dev: '',
    holding_cost_per_unit: '',
});

const createDefaultLineItem = (id: number): RfqLineItemDraft => ({
    id,
    partId: '',
    description: '',
    quantity: '1',
    targetDate: '',
});

const parseListInput = (value: string): string[] =>
    value
        .split(/[\n,]/)
        .map((entry) => entry.trim())
        .filter((entry) => entry.length > 0);

const toNumber = (value: string): number | undefined => {
    if (!value.trim()) {
        return undefined;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : undefined;
};

const isPlainObject = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null && !Array.isArray(value);

const pruneEmpty = (input: Record<string, unknown>): Record<string, unknown> => {
    const output: Record<string, unknown> = {};

    Object.entries(input).forEach(([key, rawValue]) => {
        if (rawValue === undefined || rawValue === null) {
            return;
        }

        if (typeof rawValue === 'string') {
            const trimmed = rawValue.trim();
            if (trimmed !== '') {
                output[key] = trimmed;
            }
            return;
        }

        if (Array.isArray(rawValue)) {
            const normalized = rawValue
                .map((item) => {
                    if (typeof item === 'string') {
                        const trimmed = item.trim();
                        return trimmed === '' ? null : trimmed;
                    }
                    if (isPlainObject(item)) {
                        const nested = pruneEmpty(item);
                        return Object.keys(nested).length === 0 ? null : nested;
                    }
                    return item;
                })
                .filter((item) => item !== null);

            if (normalized.length > 0) {
                output[key] = normalized;
            }
            return;
        }

        if (isPlainObject(rawValue)) {
            const nested = pruneEmpty(rawValue);
            if (Object.keys(nested).length > 0) {
                output[key] = nested;
            }
            return;
        }

        output[key] = rawValue;
    });

    return output;
};

const parseEvaluationInput = (value: string): Array<Record<string, unknown>> => {
    return value
        .split('\n')
        .map((entry) => entry.trim())
        .filter((entry) => entry.length > 0)
        .map((line) => {
            const [criterionPart, weightPart, guidancePart] = line.split('|').map((token) => token.trim());
            const numericWeight = toNumber(weightPart?.replace('%', '') ?? '');
            const normalizedWeight = typeof numericWeight === 'number' ? numericWeight / (weightPart?.includes('%') ? 100 : 1) : undefined;

            return pruneEmpty({
                criterion: criterionPart,
                weight: normalizedWeight,
                guidance: guidancePart,
            });
        })
        .filter((entry) => Object.keys(entry).length > 0);
};

const formatDateTime = (value?: string | null): string | null => {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleString();
};

const formatConfidence = (value?: number | null): string | null => {
    if (typeof value !== 'number') {
        return null;
    }

    return `${Math.round(value * 100)}% confidence`;
};

const STATUS_LABELS: Record<string, string> = {
    drafted: 'Pending approval',
    approved: 'Approved',
    rejected: 'Rejected',
    expired: 'Expired',
};

const STATUS_CLASSNAMES: Record<string, string> = {
    drafted: 'bg-muted text-foreground',
    approved: 'bg-emerald-100 text-emerald-900',
    rejected: 'bg-destructive/90 text-destructive-foreground',
    expired: 'bg-amber-100 text-amber-900',
};

const buildRfqPayload = (inputs: RfqInputState, lineItems: RfqLineItemDraft[]): Record<string, unknown> => {
    const mappedItems = lineItems
        .map((item) =>
            pruneEmpty({
                part_id: item.partId,
                description: item.description,
                quantity: toNumber(item.quantity),
                target_date: item.targetDate,
            }),
        )
        .filter((entry) => Object.keys(entry).length > 0);

    return pruneEmpty({
        category: inputs.category,
        method: inputs.method,
        material: inputs.material,
        incoterm: inputs.incoterm,
        currency: inputs.currency,
        tolerance_finish: inputs.tolerance_finish,
        delivery_location: inputs.delivery_location,
        due_at: inputs.due_at,
        close_at: inputs.close_at,
        open_bidding: inputs.open_bidding,
        commercial_terms: parseListInput(inputs.commercial_terms),
        questions_for_suppliers: parseListInput(inputs.questions_for_suppliers),
        evaluation_criteria: parseEvaluationInput(inputs.evaluation_criteria),
        items: mappedItems,
    });
};

const buildSupplierPayload = (inputs: SupplierMessageInputState): Record<string, unknown> => {
    return pruneEmpty({
        supplier_id: toNumber(inputs.supplier_id),
        supplier_name: inputs.supplier_name,
        goal: inputs.goal,
        tone: inputs.tone,
        constraints: inputs.constraints,
        context: inputs.context,
        negotiation_points: parseListInput(inputs.negotiation_points),
        fallback_options: parseListInput(inputs.fallback_options),
    });
};

const buildMaintenancePayload = (inputs: MaintenanceInputState): Record<string, unknown> => {
    return pruneEmpty({
        asset_id: inputs.asset_id,
        asset_name: inputs.asset_name,
        task_title: inputs.task_title,
        symptom: inputs.symptom,
        environment: inputs.environment,
        urgency: inputs.urgency,
        due_at: inputs.due_at,
        safety_notes: parseListInput(inputs.safety_notes),
        diagnostic_steps: parseListInput(inputs.diagnostic_steps),
        likely_causes: parseListInput(inputs.likely_causes),
        recommended_actions: parseListInput(inputs.recommended_actions),
        when_to_escalate: parseListInput(inputs.escalation_rules),
    });
};

const buildInventoryPayload = (inputs: InventoryInputState): Record<string, unknown> => {
    const currentPolicy = pruneEmpty({
        reorder_point: toNumber(inputs.current_reorder_point),
        safety_stock: toNumber(inputs.current_safety_stock),
        lead_time: toNumber(inputs.current_lead_time),
    });
    const proposedPolicy = pruneEmpty({
        reorder_point: toNumber(inputs.proposed_reorder_point),
        safety_stock: toNumber(inputs.proposed_safety_stock),
        lead_time: toNumber(inputs.proposed_lead_time),
    });
    const forecastSnapshot = pruneEmpty({
        avg_daily_demand: toNumber(inputs.forecast_avg_daily),
        std_dev: toNumber(inputs.forecast_std_dev),
        holding_cost_per_unit: toNumber(inputs.holding_cost_per_unit),
    });

    return pruneEmpty({
        part_identifier: inputs.part_identifier,
        scenario_name: inputs.scenario_name,
        service_level_target: toNumber(inputs.service_level_target),
        current_policy: currentPolicy,
        proposed_policy: proposedPolicy,
        forecast_snapshot: forecastSnapshot,
    });
};

type DocumentResourcePayload = {
    id: number;
    filename?: string | null;
    download_url?: string | null;
};

interface CopilotActionsPanelProps {
    className?: string;
}

export function CopilotActionsPanel({ className }: CopilotActionsPanelProps) {
    const instructionsFieldId = useId();
    const rejectFieldId = useId();

    const [actionType, setActionType] = useState<CopilotActionType>('rfq_draft');
    const [instructions, setInstructions] = useState('');
    const [rfqInputs, setRfqInputs] = useState<RfqInputState>(() => createDefaultRfqInputs());
    const [supplierInputs, setSupplierInputs] = useState<SupplierMessageInputState>(() => createDefaultSupplierInputs());
    const [maintenanceInputs, setMaintenanceInputs] = useState<MaintenanceInputState>(() => createDefaultMaintenanceInputs());
    const [inventoryInputs, setInventoryInputs] = useState<InventoryInputState>(() => createDefaultInventoryInputs());
    const [lineItems, setLineItems] = useState<RfqLineItemDraft[]>([createDefaultLineItem(1)]);
    const [lineItemCounter, setLineItemCounter] = useState(2);

    const [sourceType, setSourceType] = useState('');
    const [docFilterId, setDocFilterId] = useState('');
    const [tags, setTags] = useState<string[]>([]);
    const [tagInput, setTagInput] = useState('');

    const [draft, setDraft] = useState<CopilotActionDraft | null>(null);
    const [rejectReason, setRejectReason] = useState('');
    const [isPlanning, setIsPlanning] = useState(false);
    const [isApproving, setIsApproving] = useState(false);
    const [isRejecting, setIsRejecting] = useState(false);
    const [openingDocId, setOpeningDocId] = useState<string | null>(null);
    const [isReviewModalOpen, setIsReviewModalOpen] = useState(false);

    const activeConfig = ACTION_CONFIGS[actionType];
    const ActiveIcon = activeConfig.icon;

    const canResolveDraft = draft?.status === 'drafted';

    useEffect(() => {
        if (!draft || draft.status !== 'drafted') {
            setIsReviewModalOpen(false);
        }
    }, [draft]);

    const planPayload: CopilotActionPlanPayload | null = useMemo(() => {
        if (!instructions.trim()) {
            return null;
        }

        let inputsPayload: Record<string, unknown> = {};

        switch (actionType) {
            case 'rfq_draft':
                inputsPayload = buildRfqPayload(rfqInputs, lineItems);
                break;
            case 'supplier_message':
                inputsPayload = buildSupplierPayload(supplierInputs);
                break;
            case 'maintenance_checklist':
                inputsPayload = buildMaintenancePayload(maintenanceInputs);
                break;
            case 'inventory_whatif':
                inputsPayload = buildInventoryPayload(inventoryInputs);
                break;
            default:
                inputsPayload = {};
        }

        const filtersPayload = pruneEmpty({
            source_type: sourceType,
            doc_id: docFilterId,
            tags,
        });

        return {
            action_type: actionType,
            query: instructions.trim(),
            inputs: inputsPayload,
            top_k: DEFAULT_TOP_K,
            filters: Object.keys(filtersPayload).length > 0 ? filtersPayload : undefined,
        };
    }, [actionType, instructions, inventoryInputs, lineItems, maintenanceInputs, rfqInputs, sourceType, docFilterId, supplierInputs, tags]);

    const handlePlan = async (event: React.FormEvent) => {
        event.preventDefault();

        if (!planPayload) {
            errorToast('Missing instructions', 'Describe what Copilot should generate.');
            return;
        }

        setIsPlanning(true);
        setRejectReason('');

        try {
            const response = await planCopilotAction(planPayload);
            const nextDraft = response.data?.draft;

            if (!nextDraft) {
                throw new ApiError('Copilot returned an empty draft.');
            }

            setDraft(nextDraft);
            successToast('Draft generated', 'Review the structured output and either approve or reject.');
        } catch (error) {
            const message = error instanceof ApiError ? error.message : 'Unable to generate a Copilot action right now.';
            errorToast('Draft generation failed', message);
        } finally {
            setIsPlanning(false);
        }
    };

    const handleApprove = async () => {
        if (!draft || !canResolveDraft) {
            return;
        }

        setIsApproving(true);

        try {
            const response = await approveCopilotAction(draft.id);
            const nextDraft = response.data?.draft;

            if (!nextDraft) {
                throw new ApiError('Approval succeeded but no draft payload returned.');
            }

            setDraft(nextDraft);
            setIsReviewModalOpen(false);
            setRejectReason('');
            successToast('Draft approved', 'Copilot action finalized. Assigned entity links are now available.');
        } catch (error) {
            const message = error instanceof ApiError ? error.message : 'Failed to approve this Copilot draft.';
            errorToast('Approval failed', message);
        } finally {
            setIsApproving(false);
        }
    };

    const handleReject = async () => {
        if (!draft || !canResolveDraft) {
            return;
        }

        const normalizedReason = rejectReason.trim();

        if (!normalizedReason) {
            errorToast('Add a rejection note', 'Explain why this draft is being rejected so it appears in activity logs.');
            return;
        }

        setIsRejecting(true);

        try {
            const response = await rejectCopilotAction(draft.id, { reason: normalizedReason });
            const nextDraft = response.data?.draft;

            if (!nextDraft) {
                throw new ApiError('Rejection succeeded but no draft payload returned.');
            }

            setDraft(nextDraft);
            successToast('Draft rejected', 'Copilot will keep the rejection note with this attempt.');
        } catch (error) {
            const message = error instanceof ApiError ? error.message : 'Failed to reject this Copilot draft.';
            errorToast('Rejection failed', message);
        } finally {
            setIsRejecting(false);
        }
    };

    const handleAddTag = () => {
        const normalized = tagInput.trim().toLowerCase();

        if (!normalized || tags.includes(normalized)) {
            setTagInput('');
            return;
        }

        setTags((prev) => [...prev, normalized]);
        setTagInput('');
    };

    const handleRemoveTag = (tag: string) => {
        setTags((prev) => prev.filter((entry) => entry !== tag));
    };

    const handleAddLineItem = () => {
        setLineItems((prev) => [...prev, createDefaultLineItem(lineItemCounter)]);
        setLineItemCounter((prev) => prev + 1);
    };

    const handleRemoveLineItem = (id: number) => {
        setLineItems((prev) => (prev.length === 1 ? prev : prev.filter((item) => item.id !== id)));
    };

    const handleOpenDocument = async (docId: string | number | undefined) => {
        if (!docId) {
            errorToast('Missing document id', 'This citation does not include a valid document reference.');
            return;
        }

        const numericId = Number(docId);

        if (!Number.isFinite(numericId)) {
            errorToast('Invalid document id', 'Document ids must be numeric.');
            return;
        }

        setOpeningDocId(String(docId));

        try {
            const response = await api.get<DocumentResourcePayload>(`/documents/${numericId}`);
            const document = response?.data ?? null;
            const downloadUrl = document?.download_url;

            if (!downloadUrl) {
                throw new ApiError('Document is missing a download URL.');
            }

            if (typeof window !== 'undefined') {
                window.open(downloadUrl, '_blank', 'noopener');
            }
        } catch (error) {
            const message = error instanceof ApiError ? error.message : 'Unable to open the cited document.';
            errorToast('Document not available', message);
        } finally {
            setOpeningDocId(null);
        }
    };

    const renderActionInputs = () => {
        switch (actionType) {
            case 'rfq_draft':
                return (
                    <div className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium">Category / process</label>
                                <Input
                                    placeholder="CNC milling, castings, etc."
                                    value={rfqInputs.category}
                                    onChange={(event) => setRfqInputs((prev) => ({ ...prev, category: event.target.value }))}
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Material</label>
                                <Input
                                    placeholder="6061-T6 aluminum"
                                    value={rfqInputs.material}
                                    onChange={(event) => setRfqInputs((prev) => ({ ...prev, material: event.target.value }))}
                                />
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="text-sm font-medium">Incoterm</label>
                                <Input
                                    placeholder="FOB"
                                    value={rfqInputs.incoterm}
                                    onChange={(event) => setRfqInputs((prev) => ({ ...prev, incoterm: event.target.value }))}
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Currency</label>
                                <Input
                                    placeholder="USD"
                                    value={rfqInputs.currency}
                                    onChange={(event) => setRfqInputs((prev) => ({ ...prev, currency: event.target.value }))}
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Tolerance / finish</label>
                                <Input
                                    placeholder="+/- 0.005 in, anodized"
                                    value={rfqInputs.tolerance_finish}
                                    onChange={(event) =>
                                        setRfqInputs((prev) => ({ ...prev, tolerance_finish: event.target.value }))
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="text-sm font-medium">Due date</label>
                                <Input
                                    type="date"
                                    value={rfqInputs.due_at}
                                    onChange={(event) => setRfqInputs((prev) => ({ ...prev, due_at: event.target.value }))}
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Close date</label>
                                <Input
                                    type="date"
                                    value={rfqInputs.close_at}
                                    onChange={(event) => setRfqInputs((prev) => ({ ...prev, close_at: event.target.value }))}
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Delivery location</label>
                                <Input
                                    placeholder="Bentonville DC"
                                    value={rfqInputs.delivery_location}
                                    onChange={(event) =>
                                        setRfqInputs((prev) => ({ ...prev, delivery_location: event.target.value }))
                                    }
                                />
                            </div>
                        </div>
                        <div className="flex items-center gap-3 rounded-lg border bg-muted/40 p-3">
                            <Checkbox
                                id="open-bidding"
                                checked={rfqInputs.open_bidding}
                                onCheckedChange={(checked) =>
                                    setRfqInputs((prev) => ({ ...prev, open_bidding: checked === true }))
                                }
                            />
                            <label htmlFor="open-bidding" className="text-sm text-muted-foreground">
                                Open bidding (visible to the preferred supplier pool)
                            </label>
                        </div>
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-medium">Line items</p>
                                <Button type="button" variant="outline" size="sm" onClick={handleAddLineItem}>
                                    <Plus className="mr-1 size-4" /> Add item
                                </Button>
                            </div>
                            <div className="space-y-3">
                                {lineItems.map((item, index) => (
                                    <div key={item.id} className="space-y-3 rounded-lg border p-3">
                                        <div className="flex items-center justify-between text-sm font-semibold text-muted-foreground">
                                            <span>Item {index + 1}</span>
                                            {lineItems.length > 1 ? (
                                                <button
                                                    type="button"
                                                    className="text-xs text-destructive"
                                                    onClick={() => handleRemoveLineItem(item.id)}
                                                >
                                                    Remove
                                                </button>
                                            ) : null}
                                        </div>
                                        <div className="grid gap-3 md:grid-cols-4">
                                            <Input
                                                placeholder="Part or SKU"
                                                value={item.partId}
                                                onChange={(event) =>
                                                    setLineItems((prev) =>
                                                        prev.map((entry) =>
                                                            entry.id === item.id
                                                                ? { ...entry, partId: event.target.value }
                                                                : entry,
                                                        ),
                                                    )
                                                }
                                            />
                                            <Input
                                                placeholder="Quantity"
                                                type="number"
                                                min="1"
                                                value={item.quantity}
                                                onChange={(event) =>
                                                    setLineItems((prev) =>
                                                        prev.map((entry) =>
                                                            entry.id === item.id
                                                                ? { ...entry, quantity: event.target.value }
                                                                : entry,
                                                        ),
                                                    )
                                                }
                                            />
                                            <Input
                                                type="date"
                                                value={item.targetDate}
                                                onChange={(event) =>
                                                    setLineItems((prev) =>
                                                        prev.map((entry) =>
                                                            entry.id === item.id
                                                                ? { ...entry, targetDate: event.target.value }
                                                                : entry,
                                                        ),
                                                    )
                                                }
                                            />
                                            <Input
                                                placeholder="Short description"
                                                value={item.description}
                                                onChange={(event) =>
                                                    setLineItems((prev) =>
                                                        prev.map((entry) =>
                                                            entry.id === item.id
                                                                ? { ...entry, description: event.target.value }
                                                                : entry,
                                                        ),
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-1">
                                <label className="text-sm font-medium">Commercial terms (one per line)</label>
                                <Textarea
                                    rows={4}
                                    placeholder="Net-30, manual review before publishing, etc."
                                    value={rfqInputs.commercial_terms}
                                    onChange={(event) =>
                                        setRfqInputs((prev) => ({ ...prev, commercial_terms: event.target.value }))
                                    }
                                />
                            </div>
                            <div className="space-y-1">
                                <label className="text-sm font-medium">Supplier questions (one per line)</label>
                                <Textarea
                                    rows={4}
                                    placeholder="Confirm achievable lead time\nShare warranty coverage"
                                    value={rfqInputs.questions_for_suppliers}
                                    onChange={(event) =>
                                        setRfqInputs((prev) => ({ ...prev, questions_for_suppliers: event.target.value }))
                                    }
                                />
                            </div>
                        </div>
                        <div>
                            <label className="text-sm font-medium">Evaluation criteria (criterion | weight | guidance)</label>
                            <Textarea
                                rows={3}
                                placeholder="Commercial terms | 0.4 | Compare landed cost\nSchedule reliability | 0.3 | Validate lead times"
                                value={rfqInputs.evaluation_criteria}
                                onChange={(event) =>
                                    setRfqInputs((prev) => ({ ...prev, evaluation_criteria: event.target.value }))
                                }
                            />
                        </div>
                    </div>
                );
            case 'supplier_message':
                return (
                    <div className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="text-sm font-medium">Supplier id</label>
                                <Input
                                    placeholder="Internal supplier id"
                                    value={supplierInputs.supplier_id}
                                    onChange={(event) =>
                                        setSupplierInputs((prev) => ({ ...prev, supplier_id: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Supplier name</label>
                                <Input
                                    placeholder="Apex Fasteners"
                                    value={supplierInputs.supplier_name}
                                    onChange={(event) =>
                                        setSupplierInputs((prev) => ({ ...prev, supplier_name: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Tone</label>
                                <Select
                                    value={supplierInputs.tone}
                                    onValueChange={(value) =>
                                        setSupplierInputs((prev) => ({ ...prev, tone: value }))
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select tone" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="professional">Professional</SelectItem>
                                        <SelectItem value="friendly">Friendly</SelectItem>
                                        <SelectItem value="firm">Firm</SelectItem>
                                        <SelectItem value="urgent">Urgent</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium">Negotiation goal</label>
                                <Input
                                    placeholder="Reduce lead time to 2 weeks"
                                    value={supplierInputs.goal}
                                    onChange={(event) =>
                                        setSupplierInputs((prev) => ({ ...prev, goal: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Constraints</label>
                                <Input
                                    placeholder="Budget cap, tooling locked, etc."
                                    value={supplierInputs.constraints}
                                    onChange={(event) =>
                                        setSupplierInputs((prev) => ({ ...prev, constraints: event.target.value }))
                                    }
                                />
                            </div>
                        </div>
                        <div>
                            <label className="text-sm font-medium">Context for Copilot</label>
                            <Textarea
                                rows={3}
                                placeholder="Reference quote Q-1042, supplier missed two PO dates last quarter."
                                value={supplierInputs.context}
                                onChange={(event) =>
                                    setSupplierInputs((prev) => ({ ...prev, context: event.target.value }))
                                }
                            />
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium">Negotiation points (one per line)</label>
                                <Textarea
                                    rows={4}
                                    placeholder="Volume pricing\nPull-in options"
                                    value={supplierInputs.negotiation_points}
                                    onChange={(event) =>
                                        setSupplierInputs((prev) => ({ ...prev, negotiation_points: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Fallback options (one per line)</label>
                                <Textarea
                                    rows={4}
                                    placeholder="Escalate to regional GM\nShift qty to backup supplier"
                                    value={supplierInputs.fallback_options}
                                    onChange={(event) =>
                                        setSupplierInputs((prev) => ({ ...prev, fallback_options: event.target.value }))
                                    }
                                />
                            </div>
                        </div>
                    </div>
                );
            case 'maintenance_checklist':
                return (
                    <div className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="text-sm font-medium">Asset id</label>
                                <Input
                                    placeholder="Twin or asset id"
                                    value={maintenanceInputs.asset_id}
                                    onChange={(event) =>
                                        setMaintenanceInputs((prev) => ({ ...prev, asset_id: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Asset name</label>
                                <Input
                                    placeholder="CR-45 spindle"
                                    value={maintenanceInputs.asset_name}
                                    onChange={(event) =>
                                        setMaintenanceInputs((prev) => ({ ...prev, asset_name: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Urgency</label>
                                <Select
                                    value={maintenanceInputs.urgency}
                                    onValueChange={(value) =>
                                        setMaintenanceInputs((prev) => ({ ...prev, urgency: value }))
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select urgency" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="low">Low</SelectItem>
                                        <SelectItem value="medium">Medium</SelectItem>
                                        <SelectItem value="high">High</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium">Task title</label>
                                <Input
                                    placeholder="Investigate vibration alarm"
                                    value={maintenanceInputs.task_title}
                                    onChange={(event) =>
                                        setMaintenanceInputs((prev) => ({ ...prev, task_title: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Due date</label>
                                <Input
                                    type="date"
                                    value={maintenanceInputs.due_at}
                                    onChange={(event) =>
                                        setMaintenanceInputs((prev) => ({ ...prev, due_at: event.target.value }))
                                    }
                                />
                            </div>
                        </div>
                        <div>
                            <label className="text-sm font-medium">Symptom details</label>
                            <Textarea
                                rows={3}
                                placeholder="High vibration on the z-axis during warm-up"
                                value={maintenanceInputs.symptom}
                                onChange={(event) =>
                                    setMaintenanceInputs((prev) => ({ ...prev, symptom: event.target.value }))
                                }
                            />
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium">Environment</label>
                                <Textarea
                                    rows={3}
                                    placeholder="High humidity area; coolant loop upgraded last year"
                                    value={maintenanceInputs.environment}
                                    onChange={(event) =>
                                        setMaintenanceInputs((prev) => ({ ...prev, environment: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Likely causes (one per line)</label>
                                <Textarea
                                    rows={3}
                                    placeholder="Bearing wear\nContamination"
                                    value={maintenanceInputs.likely_causes}
                                    onChange={(event) =>
                                        setMaintenanceInputs((prev) => ({ ...prev, likely_causes: event.target.value }))
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium">Safety notes (one per line)</label>
                                <Textarea
                                    rows={3}
                                    placeholder="Lockout-tagout before service"
                                    value={maintenanceInputs.safety_notes}
                                    onChange={(event) =>
                                        setMaintenanceInputs((prev) => ({ ...prev, safety_notes: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Diagnostic steps (one per line)</label>
                                <Textarea
                                    rows={3}
                                    placeholder="Capture vibration baseline\nInspect coolant filters"
                                    value={maintenanceInputs.diagnostic_steps}
                                    onChange={(event) =>
                                        setMaintenanceInputs((prev) => ({ ...prev, diagnostic_steps: event.target.value }))
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium">Recommended actions (one per line)</label>
                                <Textarea
                                    rows={3}
                                    placeholder="Lubricate spindle bearings"
                                    value={maintenanceInputs.recommended_actions}
                                    onChange={(event) =>
                                        setMaintenanceInputs((prev) => ({ ...prev, recommended_actions: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Escalation rules (one per line)</label>
                                <Textarea
                                    rows={3}
                                    placeholder="Escalate if downtime exceeds 4 hrs"
                                    value={maintenanceInputs.escalation_rules}
                                    onChange={(event) =>
                                        setMaintenanceInputs((prev) => ({ ...prev, escalation_rules: event.target.value }))
                                    }
                                />
                            </div>
                        </div>
                    </div>
                );
            case 'inventory_whatif':
                return (
                    <div className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium">Part identifier</label>
                                <Input
                                    placeholder="Part number or SKU"
                                    value={inventoryInputs.part_identifier}
                                    onChange={(event) =>
                                        setInventoryInputs((prev) => ({ ...prev, part_identifier: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Scenario name</label>
                                <Input
                                    placeholder="Safety stock increase for CR-45"
                                    value={inventoryInputs.scenario_name}
                                    onChange={(event) =>
                                        setInventoryInputs((prev) => ({ ...prev, scenario_name: event.target.value }))
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="text-sm font-medium">Service level target (0-1)</label>
                                <Input
                                    type="number"
                                    min="0"
                                    max="1"
                                    step="0.01"
                                    value={inventoryInputs.service_level_target}
                                    onChange={(event) =>
                                        setInventoryInputs((prev) => ({ ...prev, service_level_target: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Current reorder point</label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={inventoryInputs.current_reorder_point}
                                    onChange={(event) =>
                                        setInventoryInputs((prev) => ({ ...prev, current_reorder_point: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Current safety stock</label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={inventoryInputs.current_safety_stock}
                                    onChange={(event) =>
                                        setInventoryInputs((prev) => ({ ...prev, current_safety_stock: event.target.value }))
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="text-sm font-medium">Current lead time (days)</label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.5"
                                    value={inventoryInputs.current_lead_time}
                                    onChange={(event) =>
                                        setInventoryInputs((prev) => ({ ...prev, current_lead_time: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Proposed reorder point</label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={inventoryInputs.proposed_reorder_point}
                                    onChange={(event) =>
                                        setInventoryInputs((prev) => ({ ...prev, proposed_reorder_point: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Proposed safety stock</label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={inventoryInputs.proposed_safety_stock}
                                    onChange={(event) =>
                                        setInventoryInputs((prev) => ({ ...prev, proposed_safety_stock: event.target.value }))
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium">Proposed lead time (days)</label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.5"
                                    value={inventoryInputs.proposed_lead_time}
                                    onChange={(event) =>
                                        setInventoryInputs((prev) => ({ ...prev, proposed_lead_time: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Holding cost per unit</label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={inventoryInputs.holding_cost_per_unit}
                                    onChange={(event) =>
                                        setInventoryInputs((prev) => ({ ...prev, holding_cost_per_unit: event.target.value }))
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium">Forecast avg daily demand</label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.1"
                                    value={inventoryInputs.forecast_avg_daily}
                                    onChange={(event) =>
                                        setInventoryInputs((prev) => ({ ...prev, forecast_avg_daily: event.target.value }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Forecast std dev</label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.1"
                                    value={inventoryInputs.forecast_std_dev}
                                    onChange={(event) =>
                                        setInventoryInputs((prev) => ({ ...prev, forecast_std_dev: event.target.value }))
                                    }
                                />
                            </div>
                        </div>
                    </div>
                );
            default:
                return null;
        }
    };

    const renderPayloadSection = () => {
        if (!draft) {
            return null;
        }

        const payload = isPlainObject(draft.payload) ? draft.payload : {};

        switch (draft.action_type) {
            case 'rfq_draft': {
                const lineItemsPayload = Array.isArray(payload.line_items) ? payload.line_items : [];
                const terms = Array.isArray(payload.terms_and_conditions) ? payload.terms_and_conditions : [];
                const questions = Array.isArray(payload.questions_for_suppliers)
                    ? payload.questions_for_suppliers
                    : [];
                const rubric = Array.isArray(payload.evaluation_rubric) ? payload.evaluation_rubric : [];

                return (
                    <div className="space-y-4">
                        <div>
                            <p className="text-sm font-semibold">Line items</p>
                            {lineItemsPayload.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Copilot did not include any line items.</p>
                            ) : (
                                <div className="mt-2 space-y-2">
                                    {lineItemsPayload.map((item, index) => (
                                        <div key={`${item.part_id}-${index}`} className="rounded-lg border bg-muted/30 p-3 text-sm">
                                            <div className="flex flex-wrap items-center justify-between gap-2 text-foreground">
                                                <span className="font-medium">{item.part_id ?? `Item ${index + 1}`}</span>
                                                <span className="text-muted-foreground">
                                                    Qty {item.quantity ?? '—'}
                                                </span>
                                            </div>
                                            <p className="text-muted-foreground">{item.description}</p>
                                            <p className="text-xs text-muted-foreground">Target date: {item.target_date ?? 'TBD'}</p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <ListCard title="Terms" items={terms} emptyLabel="No terms generated." />
                            <ListCard title="Supplier questions" items={questions} emptyLabel="No questions provided." />
                        </div>
                        <div>
                            <p className="text-sm font-semibold">Evaluation rubric</p>
                            {rubric.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No rubric returned.</p>
                            ) : (
                                <div className="mt-2 grid gap-3 md:grid-cols-2">
                                    {rubric.map((entry, index) => (
                                        <div key={`${entry.criterion}-${index}`} className="rounded-lg border bg-muted/20 p-3">
                                            <p className="font-medium">{entry.criterion}</p>
                                            <p className="text-xs text-muted-foreground">Weight {(entry.weight ?? 0) * 100}%</p>
                                            <p className="mt-2 text-sm text-muted-foreground">{entry.guidance}</p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                );
            }
            case 'supplier_message': {
                const negotiationPoints = Array.isArray(payload.negotiation_points) ? payload.negotiation_points : [];
                const fallbackOptions = Array.isArray(payload.fallback_options) ? payload.fallback_options : [];
                const messageBody = typeof payload.message_body === 'string' ? payload.message_body : 'No message provided.';

                return (
                    <div className="space-y-4">
                        <div className="rounded-xl border bg-card/70 p-4">
                            <p className="text-sm font-semibold text-muted-foreground">Message preview</p>
                            <p className="mt-2 whitespace-pre-wrap text-sm leading-relaxed">{messageBody}</p>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <ListCard title="Negotiation points" items={negotiationPoints} emptyLabel="No negotiation points." />
                            <ListCard title="Fallback options" items={fallbackOptions} emptyLabel="No fallback options." />
                        </div>
                    </div>
                );
            }
            case 'maintenance_checklist': {
                const safetyNotes = Array.isArray(payload.safety_notes) ? payload.safety_notes : [];
                const diagnostics = Array.isArray(payload.diagnostic_steps) ? payload.diagnostic_steps : [];
                const likelyCauses = Array.isArray(payload.likely_causes) ? payload.likely_causes : [];
                const recommended = Array.isArray(payload.recommended_actions) ? payload.recommended_actions : [];
                const escalation = Array.isArray(payload.when_to_escalate) ? payload.when_to_escalate : [];

                return (
                    <div className="grid gap-4 md:grid-cols-2">
                        <ListCard title="Safety notes" items={safetyNotes} emptyLabel="No safety notes." />
                        <ListCard title="Diagnostic steps" items={diagnostics} emptyLabel="No diagnostic steps." />
                        <ListCard title="Likely causes" items={likelyCauses} emptyLabel="No causes listed." />
                        <ListCard title="Recommended actions" items={recommended} emptyLabel="No actions provided." />
                        <ListCard title="Escalation" items={escalation} emptyLabel="No escalation guidance." />
                    </div>
                );
            }
            case 'inventory_whatif': {
                const expectedStockoutDays =
                    typeof payload.expected_stockout_days === 'number' ? payload.expected_stockout_days : '—';
                const recommendationText =
                    typeof payload.recommendation === 'string' && payload.recommendation.trim().length > 0
                        ? payload.recommendation
                        : 'No recommendation returned.';

                return (
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="rounded-lg border bg-muted/20 p-4 text-sm">
                            <p className="text-xs uppercase text-muted-foreground">Projected stockout risk</p>
                            <p className="text-2xl font-semibold text-foreground">
                                {typeof payload.projected_stockout_risk === 'number'
                                    ? `${Math.round(payload.projected_stockout_risk * 100)}%`
                                    : '—'}
                            </p>
                            <p className="text-xs text-muted-foreground">Expected stockout days {expectedStockoutDays}</p>
                        </div>
                        <div className="rounded-lg border bg-muted/20 p-4 text-sm">
                            <p className="text-xs uppercase text-muted-foreground">Holding cost change</p>
                            <p className="text-2xl font-semibold text-foreground">
                                {typeof payload.expected_holding_cost_change === 'number'
                                    ? `$ ${payload.expected_holding_cost_change.toFixed(2)}`
                                    : '—'}
                            </p>
                            <p className="text-xs text-muted-foreground">Recommendation</p>
                            <p className="text-sm text-muted-foreground">{recommendationText}</p>
                        </div>
                        <ListCard
                            title="Assumptions"
                            items={Array.isArray(payload.assumptions) ? payload.assumptions : []}
                            emptyLabel="No assumptions detailed."
                        />
                    </div>
                );
            }
            default:
                return (
                    <pre className="overflow-auto rounded-lg bg-muted/30 p-3 text-xs text-muted-foreground">
                        {JSON.stringify(payload, null, 2)}
                    </pre>
                );
        }
    };

    const renderDraftSection = () => {
        if (isPlanning) {
            return (
                <div className="rounded-xl border bg-muted/20 p-8 text-center text-sm text-muted-foreground">
                    <Spinner className="mr-2 inline-block size-4" /> Generating draft…
                </div>
            );
        }

        if (!draft) {
            return (
                <EmptyState
                    title="No draft yet"
                    description="Fill out the inputs and click Generate Draft to preview Copilot's structured output."
                    icon={<Sparkles className="size-6" />}
                    className="bg-transparent"
                />
            );
        }

        const statusLabel = STATUS_LABELS[draft.status] ?? draft.status;
        const statusClass = STATUS_CLASSNAMES[draft.status] ?? STATUS_CLASSNAMES.drafted;
        const confidenceLabel = formatConfidence(draft.confidence);
        const createdAt = formatDateTime(draft.created_at);
        const updatedAt = formatDateTime(draft.updated_at);

        return (
            <div className="space-y-6">
                <div className="flex flex-wrap items-center gap-3">
                    <Badge className={cn('text-xs uppercase', statusClass)}>{statusLabel}</Badge>
                    {draft.needs_human_review ? (
                        <Badge variant="destructive" className="text-xs uppercase">
                            Review required
                        </Badge>
                    ) : null}
                    {confidenceLabel ? <Badge variant="outline">{confidenceLabel}</Badge> : null}
                </div>
                {draft.summary ? (
                    <p className="text-base font-medium text-foreground">{draft.summary}</p>
                ) : null}
                {renderPayloadSection()}
                {draft.warnings?.length ? (
                    <Alert variant="warning">
                        <AlertTitle>Warnings</AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc space-y-1 pl-5 text-sm">
                                {draft.warnings.map((warning) => (
                                    <li key={warning}>{warning}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                ) : null}
                {draft.citations?.length ? (
                    <div className="space-y-2">
                        <p className="text-sm font-semibold">Citations</p>
                        <div className="space-y-3">
                            {draft.citations.map((citation) => (
                                <div key={`${citation.doc_id}-${citation.chunk_id ?? 'chunk'}`} className="rounded-lg border bg-muted/20 p-3 text-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p className="font-medium">Doc #{citation.doc_id}</p>
                                            <p className="text-xs text-muted-foreground">
                                                Chunk {citation.chunk_id ?? '—'} {citation.doc_version ? `• v${citation.doc_version}` : ''}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() => handleOpenDocument(citation.doc_id)}
                                            disabled={openingDocId === citation.doc_id}
                                        >
                                            {openingDocId === citation.doc_id ? (
                                                <>
                                                    <Spinner className="mr-1 size-3" /> Opening…
                                                </>
                                            ) : (
                                                <>
                                                    <ExternalLink className="mr-1 size-3" /> Open
                                                </>
                                            )}
                                        </Button>
                                    </div>
                                    <p className="mt-2 text-muted-foreground">{citation.snippet ?? 'No snippet provided.'}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                ) : null}
                <div className="rounded-xl border bg-muted/30 p-4">
                    <p className="text-sm font-semibold text-muted-foreground">Approval workflow</p>
                    <div className="mt-3 space-y-3">
                        <Textarea
                            id={rejectFieldId}
                            rows={3}
                            disabled={!canResolveDraft}
                            placeholder="Share why this draft is being rejected (required)."
                            value={rejectReason}
                            onChange={(event) => setRejectReason(event.target.value)}
                        />
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                onClick={() => setIsReviewModalOpen(true)}
                                disabled={!canResolveDraft || isApproving || isRejecting}
                            >
                                {isApproving ? <Spinner className="mr-2 size-4" /> : null}
                                Review & approve
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => void handleReject()}
                                disabled={!canResolveDraft || isRejecting}
                            >
                                {isRejecting ? <Spinner className="mr-2 size-4" /> : null}
                                Reject draft
                            </Button>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {canResolveDraft
                                ? 'Approvals create the target record. Rejections keep the action for audit.'
                                : 'Draft already resolved; review entity links below.'}
                        </p>
                        {draft.entity_type && draft.entity_id ? (
                            <p className="text-sm text-muted-foreground">
                                Linked entity: {draft.entity_type} #{draft.entity_id}
                            </p>
                        ) : null}
                        <div className="text-xs text-muted-foreground">
                            <p>Created: {createdAt ?? '—'}</p>
                            <p>Updated: {updatedAt ?? '—'}</p>
                        </div>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <>
            <AiDraftReviewModal
                draft={draft}
                open={isReviewModalOpen}
                onOpenChange={setIsReviewModalOpen}
                payloadPreview={renderPayloadSection()}
                onConfirm={() => void handleApprove()}
                isConfirming={isApproving}
                onOpenDocument={handleOpenDocument}
                openingDocId={openingDocId}
            />
            <Card className={cn('h-full', className)}>
            <CardHeader>
                <div className="flex items-center gap-3">
                    <Sparkles className="size-5 text-primary" />
                    <div>
                        <CardTitle>Copilot Actions</CardTitle>
                        <CardDescription>Draft RFQs, supplier emails, maintenance run-books, or what-if models with approvals.</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-8">
                <form className="space-y-6" onSubmit={handlePlan}>
                    <div className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-[300px,1fr]">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Action type</label>
                                <Select
                                    value={actionType}
                                    onValueChange={(value) => setActionType(value as CopilotActionType)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select action" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(ACTION_CONFIGS).map(([value, config]) => (
                                            <SelectItem key={value} value={value}>
                                                {config.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <ActiveIcon className="size-4 text-primary" />
                                    <span>{activeConfig.description}</span>
                                </p>
                            </div>
                            <div>
                                <label htmlFor={instructionsFieldId} className="text-sm font-medium">
                                    Instructions for Copilot
                                </label>
                                <Textarea
                                    id={instructionsFieldId}
                                    rows={4}
                                    placeholder={activeConfig.instructionsPlaceholder}
                                    value={instructions}
                                    onChange={(event) => setInstructions(event.target.value)}
                                />
                            </div>
                        </div>
                        <div className="rounded-xl border bg-muted/10 p-4">
                            <div className="flex items-center gap-2 text-sm font-semibold text-muted-foreground">
                                <Filter className="size-4" /> Context filters (optional)
                            </div>
                            <div className="mt-4 grid gap-4 md:grid-cols-3">
                                <div>
                                    <label className="text-sm font-medium">Source type</label>
                                    <Select value={sourceType} onValueChange={setSourceType}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="All sources" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {SOURCE_TYPE_OPTIONS.map((option) => (
                                                <SelectItem key={option.value || 'all'} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <label className="text-sm font-medium">Document id</label>
                                    <Input
                                        placeholder="Restrict to document"
                                        value={docFilterId}
                                        onChange={(event) => setDocFilterId(event.target.value)}
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium">Tags</label>
                                    <div className="flex gap-2">
                                        <div className="relative flex-1">
                                            <Tag className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                className="pl-9"
                                                placeholder="Add tag and press Enter"
                                                value={tagInput}
                                                onChange={(event) => setTagInput(event.target.value)}
                                                onKeyDown={(event) => {
                                                    if (event.key === 'Enter') {
                                                        event.preventDefault();
                                                        handleAddTag();
                                                    }
                                                }}
                                            />
                                        </div>
                                        <Button type="button" variant="outline" onClick={handleAddTag}>
                                            Add
                                        </Button>
                                    </div>
                                    {tags.length ? (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {tags.map((tag) => (
                                                <Badge key={tag} variant="secondary" className="flex items-center gap-1 text-xs">
                                                    {tag}
                                                    <button
                                                        type="button"
                                                        aria-label="Remove tag"
                                                        className="text-muted-foreground"
                                                        onClick={() => handleRemoveTag(tag)}
                                                    >
                                                        ×
                                                    </button>
                                                </Badge>
                                            ))}
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="space-y-4">
                        <div className="flex items-center gap-2 text-sm font-semibold text-muted-foreground">
                            <ClipboardList className="size-4" /> Action-specific inputs
                        </div>
                        {renderActionInputs()}
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Button type="submit" disabled={isPlanning}>
                            {isPlanning ? <Spinner className="mr-2 size-4" /> : null}
                            Generate draft
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setInstructions('');
                                setRfqInputs(createDefaultRfqInputs());
                                setSupplierInputs(createDefaultSupplierInputs());
                                setMaintenanceInputs(createDefaultMaintenanceInputs());
                                setInventoryInputs(createDefaultInventoryInputs());
                                setLineItems([createDefaultLineItem(1)]);
                                setLineItemCounter(2);
                                setSourceType('');
                                setDocFilterId('');
                                setTags([]);
                                setTagInput('');
                            }}
                            disabled={isPlanning}
                        >
                            Reset form
                        </Button>
                    </div>
                </form>

                <div className="space-y-4">
                    <div className="flex items-center gap-2 text-sm font-semibold text-muted-foreground">
                        <Info className="size-4" /> Draft output
                    </div>
                    {renderDraftSection()}
                </div>
            </CardContent>
        </Card>
        </>
    );
}

interface ListCardProps {
    title: string;
    items: unknown;
    emptyLabel: string;
}

function ListCard({ title, items, emptyLabel }: ListCardProps) {
    const list = Array.isArray(items) ? (items as Array<string | number | Record<string, unknown>>) : [];

    if (list.length === 0) {
        return (
            <div className="rounded-lg border bg-muted/20 p-4 text-sm text-muted-foreground">
                <p className="font-semibold text-foreground">{title}</p>
                <p className="mt-2 text-sm text-muted-foreground">{emptyLabel}</p>
            </div>
        );
    }

    return (
        <div className="rounded-lg border bg-muted/20 p-4 text-sm">
            <p className="font-semibold text-foreground">{title}</p>
            <ul className="mt-2 list-disc space-y-1 pl-5 text-muted-foreground">
                {list.map((item, index) => (
                    <li key={`${title}-${index}`}>
                        {typeof item === 'string' || typeof item === 'number' ? item : JSON.stringify(item)}
                    </li>
                ))}
            </ul>
        </div>
    );
}
