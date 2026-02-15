import { formatDistanceToNow } from 'date-fns';
import { CheckCircle2, Clock9, Factory, XCircle } from 'lucide-react';
import type { ComponentType } from 'react';

import { cn } from '@/lib/utils';
import type {
    GoodsReceiptNoteSummary,
    InvoiceDetail,
    PurchaseOrderEvent,
} from '@/types/sourcing';

export type TimelineAccent = 'muted' | 'warning' | 'success' | 'danger';

export interface InvoiceReviewTimelineEntry {
    id: string;
    title: string;
    description?: string | null;
    timestamp?: string | null;
    accent: TimelineAccent;
    actor?: string | null;
}

const TIMELINE_ACCENTS: Record<TimelineAccent, string> = {
    muted: 'border-border/70 text-muted-foreground',
    warning: 'border-amber-300 bg-amber-50 text-amber-800',
    success: 'border-emerald-300 bg-emerald-50 text-emerald-700',
    danger: 'border-rose-300 bg-rose-50 text-rose-700',
};

const TIMELINE_ICONS: Record<
    TimelineAccent,
    ComponentType<{ className?: string }>
> = {
    muted: Clock9,
    warning: Factory,
    success: CheckCircle2,
    danger: XCircle,
};

interface TimelineProps {
    entries: InvoiceReviewTimelineEntry[];
    emptyLabel?: string;
}

export function InvoiceReviewTimeline({
    entries,
    emptyLabel = 'No review events recorded yet.',
}: TimelineProps) {
    if (!entries || entries.length === 0) {
        return <p className="text-sm text-muted-foreground">{emptyLabel}</p>;
    }

    return (
        <ol className="relative space-y-6 border-l border-border/60 pl-6">
            {entries.map((entry) => {
                const Icon = TIMELINE_ICONS[entry.accent] ?? Clock9;
                const timestamp = formatTimelineTimestamp(entry.timestamp);
                return (
                    <li key={entry.id} className="space-y-1">
                        <span
                            className={cn(
                                'absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full border bg-background',
                                TIMELINE_ACCENTS[entry.accent] ??
                                    TIMELINE_ACCENTS.muted,
                            )}
                        >
                            <Icon className="h-3.5 w-3.5" />
                        </span>
                        <div className="text-sm font-semibold text-foreground">
                            {entry.title}
                        </div>
                        {entry.description ? (
                            <p className="text-sm text-muted-foreground">
                                {entry.description}
                            </p>
                        ) : null}
                        {entry.actor ? (
                            <p className="text-xs text-muted-foreground">
                                By {entry.actor}
                            </p>
                        ) : null}
                        {timestamp ? (
                            <p className="text-xs text-muted-foreground">
                                {timestamp.relative}
                                {timestamp.absolute
                                    ? ` • ${timestamp.absolute}`
                                    : null}
                            </p>
                        ) : null}
                    </li>
                );
            })}
        </ol>
    );
}

type TimelineStage =
    | 'created'
    | 'submitted'
    | 'buyer_review'
    | 'approved'
    | 'rejected'
    | 'paid'
    | 'attachment';

const PO_EVENT_STAGE_MAP: Record<
    string,
    { stage: TimelineStage; accent: TimelineAccent }
> = {
    invoice_created: { stage: 'created', accent: 'muted' },
    invoice_submitted: { stage: 'submitted', accent: 'warning' },
    invoice_review_feedback: { stage: 'buyer_review', accent: 'warning' },
    invoice_approved: { stage: 'approved', accent: 'success' },
    invoice_rejected: { stage: 'rejected', accent: 'danger' },
    invoice_marked_paid: { stage: 'paid', accent: 'success' },
    invoice_attachment: { stage: 'attachment', accent: 'muted' },
};

const RECEIVING_STATUS_ACCENTS: Record<string, TimelineAccent> = {
    posted: 'success',
    complete: 'success',
    accepted: 'success',
    draft: 'muted',
    pending: 'warning',
    inspecting: 'warning',
    variance: 'danger',
    ncr_raised: 'danger',
    rejected: 'danger',
};

interface BuildTimelineOptions {
    formatDate?: (value?: string | null) => string;
    events?: PurchaseOrderEvent[];
    receiving?: GoodsReceiptNoteSummary[];
}

export function buildInvoiceReviewTimeline(
    invoice: InvoiceDetail,
    options: BuildTimelineOptions = {},
): InvoiceReviewTimelineEntry[] {
    const formatDate = options.formatDate ?? defaultFormatDate;
    const entries: InvoiceReviewTimelineEntry[] = [];
    const coveredStages = new Set<TimelineStage>();

    const eventEntries = filterEventsForInvoice(invoice, options.events ?? [])
        .map((event) => mapEventToTimelineEntry(event))
        .filter(
            (
                value,
            ): value is {
                stage: TimelineStage;
                entry: InvoiceReviewTimelineEntry;
            } => value !== null,
        );

    eventEntries.forEach(({ stage, entry }) => {
        coveredStages.add(stage);
        entries.push(entry);
    });

    const pushIfMissing = (
        stage: TimelineStage,
        build: () =>
            | (InvoiceReviewTimelineEntry & { description?: string | null })
            | null,
    ) => {
        if (coveredStages.has(stage)) {
            return;
        }

        const entry = build();
        if (entry) {
            entries.push(entry);
        }
    };

    pushIfMissing('created', () => ({
        id: 'created',
        title:
            invoice.createdByType === 'supplier'
                ? 'Draft created by supplier'
                : 'Invoice created by buyer',
        description: formatDate(invoice.createdAt),
        timestamp: invoice.createdAt,
        accent: 'muted',
        actor:
            invoice.createdByType === 'supplier'
                ? (invoice.supplier?.name ?? undefined)
                : undefined,
    }));

    pushIfMissing('submitted', () => {
        if (!invoice.submittedAt) {
            return null;
        }

        return {
            id: 'submitted',
            title: 'Supplier submitted invoice',
            description: formatDate(invoice.submittedAt),
            timestamp: invoice.submittedAt,
            accent: 'warning',
            actor: invoice.supplier?.name ?? undefined,
        };
    });

    pushIfMissing('buyer_review', () => {
        if (invoice.status !== 'buyer_review' || !invoice.reviewNote) {
            return null;
        }

        return {
            id: 'buyer_review',
            title: 'Buyer requested changes',
            description: invoice.reviewNote,
            timestamp: invoice.reviewedAt,
            accent: 'warning',
            actor: invoice.reviewedBy?.name ?? undefined,
        };
    });

    pushIfMissing('approved', () => {
        if (invoice.status !== 'approved' || !invoice.reviewedAt) {
            return null;
        }

        return {
            id: 'approved',
            title: 'Invoice approved',
            description: invoice.reviewNote ?? formatDate(invoice.reviewedAt),
            timestamp: invoice.reviewedAt,
            accent: 'success',
            actor: invoice.reviewedBy?.name ?? undefined,
        };
    });

    pushIfMissing('rejected', () => {
        if (invoice.status !== 'rejected' || !invoice.reviewedAt) {
            return null;
        }

        return {
            id: 'rejected',
            title: 'Invoice rejected',
            description: invoice.reviewNote ?? formatDate(invoice.reviewedAt),
            timestamp: invoice.reviewedAt,
            accent: 'danger',
            actor: invoice.reviewedBy?.name ?? undefined,
        };
    });

    pushIfMissing('paid', () => {
        if (invoice.status !== 'paid' || !invoice.reviewedAt) {
            return null;
        }

        return {
            id: 'paid',
            title: 'Invoice marked as paid',
            description: invoice.paymentReference
                ? `Payment reference ${invoice.paymentReference}`
                : formatDate(invoice.reviewedAt),
            timestamp: invoice.reviewedAt,
            accent: 'success',
            actor: invoice.reviewedBy?.name ?? undefined,
        };
    });

    (options.receiving ?? []).forEach((grn) => {
        const receivingEntry = mapReceivingToEntry(grn);
        if (receivingEntry) {
            entries.push(receivingEntry);
        }
    });

    return entries.sort((left, right) => {
        const leftTime = new Date(left.timestamp ?? 0).getTime();
        const rightTime = new Date(right.timestamp ?? 0).getTime();
        return rightTime - leftTime;
    });
}

function formatTimelineTimestamp(
    value?: string | null,
): { relative: string | null; absolute: string | null } | null {
    if (!value) {
        return null;
    }

    const relative = formatRelativeTimestamp(value);
    const absolute = defaultFormatDate(value);

    if (!relative && !absolute) {
        return null;
    }

    return {
        relative,
        absolute,
    };
}

function filterEventsForInvoice(
    invoice: InvoiceDetail,
    events: PurchaseOrderEvent[],
): PurchaseOrderEvent[] {
    if (!events || events.length === 0) {
        return [];
    }

    const invoiceId = parseNumericId(invoice.id);
    const invoiceNumber = invoice.invoiceNumber;

    return events.filter((event) => {
        const metadata = (event.metadata ?? {}) as Record<string, unknown>;
        const metaInvoiceId = extractInvoiceIdFromMetadata(metadata);

        if (invoiceId !== null && metaInvoiceId !== null) {
            return metaInvoiceId === invoiceId;
        }

        if (invoiceNumber) {
            const metaNumber = (metadata['invoice_number'] ??
                metadata['invoiceNumber']) as string | undefined;
            if (metaNumber && metaNumber === invoiceNumber) {
                return true;
            }
        }

        return false;
    });
}

function extractInvoiceIdFromMetadata(
    metadata: Record<string, unknown>,
): number | null {
    const keys = ['invoice_id', 'invoiceId', 'invoiceID'];

    for (const key of keys) {
        if (metadata[key] !== undefined) {
            const parsed = parseNumericId(metadata[key]);
            if (parsed !== null) {
                return parsed;
            }
        }
    }

    return null;
}

function parseNumericId(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim().length > 0) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    return null;
}

function mapEventToTimelineEntry(
    event: PurchaseOrderEvent,
): { stage: TimelineStage; entry: InvoiceReviewTimelineEntry } | null {
    const typeKey = (event.type ?? '').toLowerCase();
    const config = PO_EVENT_STAGE_MAP[typeKey];

    if (!config) {
        return null;
    }

    const entry: InvoiceReviewTimelineEntry = {
        id: `po-event-${event.id}`,
        title: event.summary ?? formatEventType(typeKey),
        description: event.description ?? null,
        timestamp: event.occurredAt ?? event.createdAt ?? null,
        accent: config.accent,
        actor: event.actor?.name ?? event.actor?.email ?? null,
    };

    return {
        stage: config.stage,
        entry,
    };
}

function formatEventType(type?: string | null): string {
    if (!type) {
        return 'Invoice event';
    }

    return type
        .split('_')
        .filter(Boolean)
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
}

function mapReceivingToEntry(
    grn: GoodsReceiptNoteSummary,
): InvoiceReviewTimelineEntry | null {
    if (!grn) {
        return null;
    }

    const statusLabel = formatGrnStatusLabel(grn.status);
    const details: string[] = [];

    if (statusLabel) {
        details.push(statusLabel);
    }

    if (typeof grn.linesCount === 'number') {
        details.push(
            `${grn.linesCount} line${grn.linesCount === 1 ? '' : 's'}`,
        );
    }

    if (typeof grn.attachmentsCount === 'number' && grn.attachmentsCount > 0) {
        details.push(
            `${grn.attachmentsCount} attachment${grn.attachmentsCount === 1 ? '' : 's'}`,
        );
    }

    return {
        id: `receiving-${grn.id}`,
        title: `Goods receipt ${grn.grnNumber ?? grn.purchaseOrderNumber ?? grn.id}`,
        description: details.length > 0 ? details.join(' • ') : null,
        timestamp: grn.receivedAt ?? grn.postedAt ?? null,
        accent: resolveReceivingAccent(grn.status),
        actor: grn.createdBy?.name ?? grn.supplierName ?? null,
    };
}

function formatGrnStatusLabel(status?: string | null): string {
    if (!status) {
        return 'Receiving updated';
    }

    return status.replace(/_/g, ' ');
}

function resolveReceivingAccent(status?: string | null): TimelineAccent {
    if (!status) {
        return 'muted';
    }

    const normalized = status.toLowerCase();
    return RECEIVING_STATUS_ACCENTS[normalized] ?? 'muted';
}

function formatRelativeTimestamp(value: string): string | null {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return null;
    }
    return formatDistanceToNow(date, { addSuffix: true });
}

function defaultFormatDate(value?: string | null): string | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleString();
}
