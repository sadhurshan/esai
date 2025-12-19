import { type ReactNode, useEffect, useMemo, useState } from 'react';
import { AlertTriangle, CheckCircle2, ExternalLink, ShieldCheck } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { CopilotActionDraft, CopilotActionType } from '@/services/ai';

const ACTION_EFFECTS: Record<
    CopilotActionType,
    {
        title: string;
        description: string;
    }
> = {
    rfq_draft: {
        title: 'Creates an RFQ draft',
        description: 'Approval converts this output into a Draft RFQ record with the provided scope, line items, and rubric.',
    },
    supplier_message: {
        title: 'Creates a supplier message draft',
        description: 'Approval saves this outreach as a supplier communication draft. It is not sent automatically.',
    },
    maintenance_checklist: {
        title: 'Creates a maintenance task draft',
        description: 'Approval opens a maintenance task populated with the safety notes, diagnostics, and actions suggested here.',
    },
    inventory_whatif: {
        title: 'Stores an inventory what-if snapshot',
        description: 'Approval archives this simulation so planners can compare policy deltas and risk forecasts.',
    },
};

const DEFAULT_EFFECT = {
    title: 'Creates a record',
    description: 'Approval promotes this draft into its target entity.',
};

const formatConfidence = (value?: number | null): string | null => {
    if (typeof value !== 'number') {
        return null;
    }

    return `${Math.round(value * 100)}% confidence`;
};

export interface AiDraftReviewModalProps {
    draft: CopilotActionDraft | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    payloadPreview?: ReactNode;
    onConfirm: () => void;
    isConfirming?: boolean;
    onOpenDocument?: (docId: string | number | undefined) => void;
    openingDocId?: string | null;
}

export function AiDraftReviewModal({
    draft,
    open,
    onOpenChange,
    payloadPreview,
    onConfirm,
    isConfirming,
    onOpenDocument,
    openingDocId,
}: AiDraftReviewModalProps) {
    const [acknowledged, setAcknowledged] = useState(false);

    useEffect(() => {
        if (!open) {
            setAcknowledged(false);
        }
    }, [open, draft?.id]);

    const effect = useMemo(() => {
        if (!draft) {
            return DEFAULT_EFFECT;
        }

        return ACTION_EFFECTS[draft.action_type] ?? DEFAULT_EFFECT;
    }, [draft]);

    const confidenceLabel = formatConfidence(draft?.confidence);

    if (!draft) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-hidden p-0 sm:max-w-4xl">
                <div className="flex max-h-[90vh] flex-col">
                    <DialogHeader className="px-6 pb-0 pt-6 text-left">
                        <DialogTitle>Review Copilot draft</DialogTitle>
                        <DialogDescription>
                            Confirm the generated payload before converting it into production data.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex-1 space-y-6 overflow-y-auto px-6 py-6">
                        <Alert className="border-primary/40 bg-primary/5">
                            <AlertTitle className="flex items-center gap-2 text-sm font-semibold">
                                <ShieldCheck className="size-4" /> {effect.title}
                            </AlertTitle>
                            <AlertDescription className="text-sm text-muted-foreground">
                                {effect.description}
                            </AlertDescription>
                        </Alert>
                        <div className="space-y-2">
                            <p className="text-xs font-semibold uppercase text-muted-foreground">Summary</p>
                            <div className="rounded-lg border bg-muted/20 p-4 text-sm leading-relaxed">
                                {draft.summary ?? 'Copilot did not include a summary for this action.'}
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                            <Badge variant="secondary" className="capitalize">
                                {draft.action_type.replace(/_/g, ' ')}
                            </Badge>
                            {draft.needs_human_review ? (
                                <Badge variant="destructive" className="uppercase">
                                    Review required
                                </Badge>
                            ) : null}
                            {confidenceLabel ? <Badge variant="outline">{confidenceLabel}</Badge> : null}
                        </div>
                        <div className="space-y-3">
                            <p className="text-sm font-semibold text-foreground">Payload preview</p>
                            <div className="rounded-xl border bg-card/80 p-4">
                                {payloadPreview ?? (
                                    <pre className="max-h-96 overflow-auto rounded bg-muted/20 p-3 text-xs text-muted-foreground">
                                        {JSON.stringify(draft.payload, null, 2)}
                                    </pre>
                                )}
                            </div>
                        </div>
                        {draft.warnings?.length ? (
                            <Alert variant="warning">
                                <AlertTitle className="flex items-center gap-2 text-sm font-semibold">
                                    <AlertTriangle className="size-4" /> Copilot warnings
                                </AlertTitle>
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
                            <div className="space-y-3">
                                <p className="text-sm font-semibold">Citations</p>
                                <div className="space-y-3">
                                    {draft.citations.map((citation) => {
                                        const docKey = `${citation.doc_id}-${citation.chunk_id ?? 'chunk'}`;
                                        const isOpening = openingDocId ? String(openingDocId) === String(citation.doc_id) : false;

                                        return (
                                            <div key={docKey} className="rounded-lg border bg-muted/30 p-3 text-sm">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <div>
                                                        <p className="font-medium">Doc #{citation.doc_id}</p>
                                                        <p className="text-xs text-muted-foreground">
                                                            Chunk {citation.chunk_id ?? '—'}{' '}
                                                            {citation.doc_version ? `• v${citation.doc_version}` : ''}
                                                        </p>
                                                    </div>
                                                    {onOpenDocument ? (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => onOpenDocument(citation.doc_id)}
                                                            disabled={isOpening}
                                                        >
                                                            {isOpening ? (
                                                                <>
                                                                    <Spinner className="mr-1 size-3" /> Opening…
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <ExternalLink className="mr-1 size-3" /> Open
                                                                </>
                                                            )}
                                                        </Button>
                                                    ) : null}
                                                </div>
                                                <p className="mt-2 text-muted-foreground">{citation.snippet ?? 'No snippet provided.'}</p>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ) : null}
                        <div className="rounded-lg border bg-muted/10 p-4">
                            <label className="flex items-start gap-3 text-sm">
                                <Checkbox
                                    id="copilot-approve-confirmation"
                                    checked={acknowledged}
                                    onCheckedChange={(checked) => setAcknowledged(Boolean(checked))}
                                />
                                <span>
                                    I have reviewed the proposed changes and understand this approval will {effect.title.toLowerCase()}.
                                </span>
                            </label>
                        </div>
                    </div>
                    <DialogFooter className="border-t bg-background px-6 py-4">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={Boolean(isConfirming)}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={onConfirm} disabled={!acknowledged || Boolean(isConfirming)}>
                            {isConfirming ? <Spinner className="mr-2 size-4" /> : <CheckCircle2 className="mr-2 size-4" />}
                            Approve draft
                        </Button>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>
    );
}
