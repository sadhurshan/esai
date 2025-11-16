import { useEffect, useMemo, useState, useReducer } from 'react';
import { format, formatDistanceToNow } from 'date-fns';
import { Helmet } from 'react-helmet-async';
import { Link, useParams } from 'react-router-dom';
import { Plus, PenLine, Trash2 } from 'lucide-react';

import { AttachmentUploader } from '@/components/rfqs/attachment-uploader';
import { ClarificationThread } from '@/components/rfqs/clarification-thread';
import { RfqLineEditorModal, type RfqLineFormValues } from '@/components/rfqs/rfq-line-editor-modal';
import { InviteSuppliersDialog } from '@/components/rfqs/invite-suppliers-dialog';
import { RfqActionBar } from '@/components/rfqs/rfq-action-bar';
import { RfqStatusBadge } from '@/components/rfqs/rfq-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { useMoneyFormatter } from '@/hooks/use-money-formatter';
import { useUomConversionHelper } from '@/hooks/use-uom-conversion-helper';
import {
    useAddLine,
    useAmendRfq,
    useCloseRfq,
    useDeleteLine,
    usePublishRfq,
    useRfq,
    useRfqAttachments,
    useRfqClarifications,
    useRfqLines,
    useUpdateLine,
    useRfqSuppliers,
    useRfqTimeline,
    useUpdateRfq,
} from '@/hooks/api/rfqs';
import type { Rfq, RfqInvitation, RfqItem, RfqLinePayload, RfqTimelineEntry } from '@/sdk';
import { RfqTypeEnum } from '@/sdk';

interface PublishFormState {
    dueAt: string;
    publishAt: string;
    notifySuppliers: boolean;
    message: string;
}

const DEFAULT_PUBLISH_FORM: PublishFormState = {
    dueAt: '',
    publishAt: '',
    notifySuppliers: true,
    message: '',
};

interface EditDetailsFormState {
    itemName: string;
    type: RfqTypeEnum;
    deadlineAt: string;
    isOpenBidding: boolean;
    notes: string;
}

const DEFAULT_EDIT_FORM: EditDetailsFormState = {
    itemName: '',
    type: RfqTypeEnum.Manufacture,
    deadlineAt: '',
    isOpenBidding: false,
    notes: '',
};

const TIMELINE_EVENT_LABELS: Record<string, string> = {
    created: 'RFQ created',
    published: 'RFQ published',
    amended: 'Amendment recorded',
    invitation_sent: 'Supplier invitation sent',
    question_posted: 'Clarification received',
    answer_posted: 'Clarification answered',
    closed: 'RFQ closed',
    awarded: 'RFQ awarded',
};

function toDateTimeLocalInput(date?: Date | null): string {
    if (!date) {
        return '';
    }

    try {
        return format(date, "yyyy-MM-dd'T'HH:mm");
    } catch (error) {
        void error;
        return '';
    }
}

function toShortDate(date?: Date | null): string {
    if (!date) {
        return '—';
    }

    try {
        return format(date, 'PP');
    } catch (error) {
        void error;
        return '—';
    }
}

function toRelativeDate(date?: Date | null): string {
    if (!date) {
        return '—';
    }

    try {
        return formatDistanceToNow(date, { addSuffix: true });
    } catch (error) {
        void error;
        return '—';
    }
}

function normalizeEventLabel(event: string): string {
    if (TIMELINE_EVENT_LABELS[event]) {
        return TIMELINE_EVENT_LABELS[event];
    }

    return event
        .split('_')
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
}

function normalizeTimelineContext(context?: object): Array<{ key: string; value: string }> {
    if (!context || typeof context !== 'object' || Array.isArray(context)) {
        return [];
    }

    return Object.entries(context as Record<string, unknown>).map(([key, value]) => ({
        key,
        value: typeof value === 'string' ? value : JSON.stringify(value),
    }));
}

function computeSupplierStats(invitations: RfqInvitation[]) {
    const totalInvited = invitations.length;
    const responded = invitations.filter((invitation) => Boolean(invitation.respondedAt)).length;
    const accepted = invitations.filter((invitation) => invitation.status === 'responded' || invitation.status === 'accepted').length;

    return {
        totalInvited,
        responded,
        accepted,
    };
}

interface LinesTableProps {
    items: RfqItem[];
    canManage: boolean;
    isBusy?: boolean;
    onEdit?: (item: RfqItem) => void;
    onDelete?: (item: RfqItem) => void;
}

function LinesTable({ items, canManage, isBusy = false, onEdit, onDelete }: LinesTableProps) {
    const formatMoney = useMoneyFormatter();
    const { baseUomCode, isEnabled: canConvert, convertMany, formatQuantity } = useUomConversionHelper();
    const baseLabel = baseUomCode.toUpperCase();
    type ConversionState = { map: Record<string, string | null>; loading: boolean };
    type ConversionAction =
        | { type: 'reset' }
        | { type: 'start' }
        | { type: 'success'; map: Record<string, string | null> }
        | { type: 'failure' };

    const [conversionState, dispatchConversion] = useReducer(
        (state: ConversionState, action: ConversionAction): ConversionState => {
            switch (action.type) {
                case 'reset':
                    return state.loading || Object.keys(state.map).length > 0 ? { map: {}, loading: false } : state;
                case 'start':
                    return state.loading ? state : { map: state.map, loading: true };
                case 'success':
                    return { map: action.map, loading: false };
                case 'failure':
                    return { map: {}, loading: false };
                default:
                    return state;
            }
        },
        { map: {}, loading: false },
    );
    const baseQuantities = conversionState.map;
    const isConverting = conversionState.loading;

    useEffect(() => {
        if (!canConvert) {
            dispatchConversion({ type: 'reset' });
            return;
        }

        const requests = items
            .map((item) => ({
                key: item.id,
                quantity: Number(item.quantity),
                fromCode: item.uom ?? baseUomCode,
            }))
            .filter((entry) => Number.isFinite(entry.quantity));

        if (requests.length === 0) {
            dispatchConversion({ type: 'reset' });
            return;
        }

        let cancelled = false;
        dispatchConversion({ type: 'start' });

        convertMany(requests)
            .then((result) => {
                if (cancelled) {
                    return;
                }

                const next: Record<string, string | null> = {};
                for (const [key, entry] of Object.entries(result)) {
                    if (entry) {
                        next[key] = entry.formatted ?? null;
                    }
                }
                dispatchConversion({ type: 'success', map: next });
            })
            .catch(() => {
                if (!cancelled) {
                    dispatchConversion({ type: 'failure' });
                }
            });

        return () => {
            cancelled = true;
        };
    }, [baseUomCode, canConvert, convertMany, items]);

    if (items.length === 0) {
        return <p className="text-sm text-muted-foreground">No line items captured yet.</p>;
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[760px] border-collapse text-sm">
                <thead>
                    <tr className="border-b bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <th className="px-3 py-2 font-medium">Line</th>
                        <th className="px-3 py-2 font-medium">Part / Description</th>
                        <th className="px-3 py-2 font-medium">Quantity</th>
                        <th className="px-3 py-2 font-medium">Target price</th>
                        <th className="px-3 py-2 font-medium">Required date</th>
                        <th className="px-3 py-2 font-medium">Notes</th>
                        {canManage ? <th className="px-3 py-2 text-right font-medium">Actions</th> : null}
                    </tr>
                </thead>
                <tbody>
                    {items.map((item) => {
                        const lineNotes = (item as RfqItem & { notes?: string | null }).notes;
                        const requiredDateRaw = item.requiredDate;
                        let requiredDateLabel = '—';

                        if (requiredDateRaw) {
                            const requiredDate = new Date(requiredDateRaw);
                            if (!Number.isNaN(requiredDate.getTime())) {
                                requiredDateLabel = format(requiredDate, 'PP');
                            }
                        }

                        return (
                            <tr key={item.id} className="border-b last:border-none">
                                <td className="px-3 py-2 text-muted-foreground">{item.lineNo}</td>
                                <td className="px-3 py-2">
                                    <div className="font-medium text-foreground">{item.partName}</div>
                                    {item.spec ? <div className="text-xs text-muted-foreground">{item.spec}</div> : null}
                                </td>
                                <td className="px-3 py-2 text-muted-foreground">
                                    <div>
                                        {item.quantity} {item.uom ?? baseLabel}
                                    </div>
                                    {canConvert ? (
                                        <div className="text-xs text-muted-foreground">
                                            {isConverting && baseQuantities[item.id] === undefined
                                                ? 'Converting…'
                                                : baseQuantities[item.id]
                                                      ? `${baseQuantities[item.id]} ${baseLabel} base`
                                                      : item.uom && item.uom.toLowerCase() === baseUomCode
                                                        ? `${formatQuantity(Number(item.quantity)) ?? item.quantity} ${baseLabel} base`
                                                        : 'Conversion unavailable'}
                                        </div>
                                    ) : null}
                                </td>
                                <td className="px-3 py-2 text-muted-foreground">
                                    {item.targetPrice !== undefined && item.targetPrice !== null
                                        ? formatMoney(item.targetPrice)
                                        : '—'}
                                </td>
                                <td className="px-3 py-2 text-muted-foreground">{requiredDateLabel}</td>
                                <td className="px-3 py-2 text-muted-foreground">{lineNotes ?? '—'}</td>
                                {canManage ? (
                                    <td className="px-3 py-2 text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => onEdit?.(item)}
                                                disabled={isBusy}
                                                aria-label={`Edit line ${item.lineNo}`}
                                            >
                                                <PenLine className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => onDelete?.(item)}
                                                disabled={isBusy}
                                                aria-label={`Delete line ${item.lineNo}`}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                ) : null}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

type InvitationSupplierProfile = {
    id?: number | string | null;
    name?: string | null;
    city?: string | null;
    country?: string | null;
    capabilities?: { methods?: string[] | null } | null;
};

function getInvitationSupplier(invitation: RfqInvitation): InvitationSupplierProfile | null {
    const enriched = invitation as RfqInvitation & { supplier?: InvitationSupplierProfile | null };
    return enriched.supplier ?? null;
}

function formatInvitationLocation(invitation: RfqInvitation): string | null {
    const supplier = getInvitationSupplier(invitation);
    const city = supplier?.city?.trim();
    const country = supplier?.country?.trim();

    if (city && country) {
        return `${city}, ${country}`;
    }

    return city || country || null;
}

function extractInvitationMethods(invitation: RfqInvitation): string[] {
    const methods = getInvitationSupplier(invitation)?.capabilities?.methods;
    if (!Array.isArray(methods)) {
        return [];
    }

    return methods
        .filter((method): method is string => typeof method === 'string' && method.trim().length > 0)
        .map((method) => method.trim())
        .slice(0, 3);
}

function SuppliersList({ invitations }: { invitations: RfqInvitation[] }) {
    if (invitations.length === 0) {
        return <p className="text-sm text-muted-foreground">No suppliers invited yet.</p>;
    }

    return (
        <ul className="space-y-3">
            {invitations.map((invitation) => {
                const supplier = getInvitationSupplier(invitation);
                const supplierName = supplier?.name ?? `Supplier #${invitation.supplierId}`;
                const location = formatInvitationLocation(invitation) ?? 'Location unavailable';
                const methods = extractInvitationMethods(invitation);

                return (
                    <li key={invitation.id} className="rounded-md border p-3">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-1">
                                <p className="text-sm font-medium text-foreground">{supplierName}</p>
                                <p className="text-xs text-muted-foreground">{location}</p>
                                {methods.length > 0 ? (
                                    <p className="text-xs text-muted-foreground">Methods: {methods.join(', ')}</p>
                                ) : null}
                                <p className="text-xs text-muted-foreground">
                                    Invited {toRelativeDate(invitation.invitedAt)}
                                    {invitation.respondedAt ? ` • Responded ${toRelativeDate(invitation.respondedAt)}` : ''}
                                </p>
                            </div>
                            <Badge variant={invitation.status === 'responded' ? 'default' : 'secondary'} className="self-start capitalize">
                                {invitation.status.replace(/_/g, ' ')}
                            </Badge>
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

function TimelineView({ items }: { items: RfqTimelineEntry[] }) {
    if (items.length === 0) {
        return <p className="text-sm text-muted-foreground">No timeline entries recorded yet.</p>;
    }

    return (
        <ol className="relative space-y-6 border-l border-border pl-6">
            {items.map((entry, index) => {
                const createdAt = entry.createdAt ?? entry.updatedAt ?? entry.deletedAt ?? null;
                const contextEntries = normalizeTimelineContext(entry.context);

                return (
                    <li key={`${entry.event}-${index}`} className="relative">
                        <span className="absolute -left-[11px] block h-3 w-3 rounded-full border-2 border-background bg-primary shadow ring-2 ring-primary/60" />
                        <div className="rounded-md border bg-card p-4 shadow-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                                <span className="font-semibold text-foreground">{normalizeEventLabel(entry.event)}</span>
                                <span>{createdAt ? format(createdAt, 'PPpp') : 'Timestamp unavailable'}</span>
                            </div>

                            {entry.actor?.name ? (
                                <p className="mt-2 text-xs text-muted-foreground">Initiated by {entry.actor.name}</p>
                            ) : null}

                            {contextEntries.length > 0 ? (
                                <dl className="mt-3 grid gap-1 text-xs text-muted-foreground">
                                    {contextEntries.map((item) => (
                                        <div key={item.key} className="flex gap-2">
                                            <dt className="font-medium text-foreground">{item.key}</dt>
                                            <dd className="flex-1 break-words">{item.value}</dd>
                                        </div>
                                    ))}
                                </dl>
                            ) : null}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

export function RfqDetailPage() {
    const params = useParams<{ id: string }>();
    const rfqId = params.id;

    const [activeTab, setActiveTab] = useState('overview');
    const [isPublishDialogOpen, setPublishDialogOpen] = useState(false);
    const [publishForm, setPublishForm] = useState<PublishFormState>(DEFAULT_PUBLISH_FORM);
    const [isAmendDialogOpen, setAmendDialogOpen] = useState(false);
    const [amendBody, setAmendBody] = useState('');
    const [isCloseDialogOpen, setCloseDialogOpen] = useState(false);
    const [closeReason, setCloseReason] = useState('');
    const [isInviteDialogOpen, setInviteDialogOpen] = useState(false);
    const [isLineEditorOpen, setLineEditorOpen] = useState(false);
    const [editingLine, setEditingLine] = useState<RfqItem | null>(null);
    const [pendingDeleteLine, setPendingDeleteLine] = useState<RfqItem | null>(null);
    const [isEditDialogOpen, setEditDialogOpen] = useState(false);
    const [editForm, setEditForm] = useState<EditDetailsFormState>(DEFAULT_EDIT_FORM);

    const { hasFeature, state: authState } = useAuth();
    const featureFlagsLoaded = Object.keys(authState.featureFlags ?? {}).length > 0;
    const allowFeature = (key: string) => (featureFlagsLoaded ? hasFeature(key) : true);

    const rfqQuery = useRfq(rfqId, { enabled: Boolean(rfqId) });
    const linesQuery = useRfqLines({ rfqId });
    const suppliersQuery = useRfqSuppliers(rfqId);
    const clarificationsQuery = useRfqClarifications(rfqId);
    const timelineQuery = useRfqTimeline(rfqId);
    const attachmentsQuery = useRfqAttachments(rfqId);

    const addLineMutation = useAddLine();
    const updateLineMutation = useUpdateLine();
    const deleteLineMutation = useDeleteLine();
    const publishMutation = usePublishRfq();
    const amendMutation = useAmendRfq();
    const closeMutation = useCloseRfq();
    const updateRfqMutation = useUpdateRfq({
        onSuccess: () => {
            publishToast({
                variant: 'success',
                title: 'RFQ updated',
                description: 'Changes saved successfully.',
            });
            setEditDialogOpen(false);
            setEditForm(DEFAULT_EDIT_FORM);
            void rfqQuery.refetch();
        },
    });

    const isLoading = rfqQuery.isLoading || !rfqId;
    const rfq = rfqQuery.data ?? null;

    const sortedTimeline = useMemo(() => {
        return [...(timelineQuery.items ?? [])].sort((left, right) => {
            const leftTime = (left.createdAt ?? left.updatedAt ?? left.deletedAt ?? new Date(0)).valueOf();
            const rightTime = (right.createdAt ?? right.updatedAt ?? right.deletedAt ?? new Date(0)).valueOf();
            return rightTime - leftTime;
        });
    }, [timelineQuery.items]);

    const supplierStats = useMemo(() => computeSupplierStats(suppliersQuery.items ?? []), [suppliersQuery.items]);

    const canManageLines = allowFeature('rfqs.lines.manage');
    const canManageAttachments = allowFeature('rfqs.attachments.manage');
    const canPublishRfq = allowFeature('rfqs.publish');
    const canInviteSuppliers = allowFeature('rfqs.suppliers.invite');
    const canAmendRfq = allowFeature('rfqs.amend');
    const canCloseRfq = allowFeature('rfqs.close');
    const canEditMetadata = allowFeature('rfqs.edit');
    const canManageClarifications = allowFeature('rfqs.clarifications.manage');

    const isSavingLine = addLineMutation.isPending || updateLineMutation.isPending;
    const isDeletingLine = deleteLineMutation.isPending;
    const isLineMutationPending = isSavingLine || isDeletingLine;

    const handleCreateLine = () => {
        setEditingLine(null);
        setLineEditorOpen(true);
    };

    const handleEditLine = (item: RfqItem) => {
        setEditingLine(item);
        setLineEditorOpen(true);
    };

    const handleLineEditorOpenChange = (open: boolean) => {
        setLineEditorOpen(open);
        if (!open) {
            setEditingLine(null);
        }
    };

    const handleLineSubmit = async (values: RfqLineFormValues) => {
        if (!rfqId) {
            throw new Error('RFQ identifier is required to manage lines.');
        }

        const payload: RfqLinePayload = {
            partName: values.partName.trim(),
            spec: values.spec?.trim() || undefined,
            method: values.method.trim(),
            material: values.material.trim(),
            tolerance: values.tolerance?.trim() || undefined,
            finish: values.finish?.trim() || undefined,
            quantity: values.quantity,
            uom: values.uom?.trim() || undefined,
            targetPrice: typeof values.targetPrice === 'number' ? values.targetPrice : undefined,
            notes: values.notes?.trim() || undefined,
            requiredDate: values.requiredDate ?? undefined,
        };

        try {
            if (editingLine) {
                await updateLineMutation.mutateAsync({
                    rfqId,
                    lineId: editingLine.id,
                    line: payload,
                });
                publishToast({
                    variant: 'success',
                    title: 'Line updated',
                    description: `Line #${editingLine.lineNo} has been updated.`,
                });
            } else {
                await addLineMutation.mutateAsync({
                    rfqId,
                    line: payload,
                });
                publishToast({
                    variant: 'success',
                    title: 'Line added',
                    description: 'New RFQ line captured.',
                });
            }

            setEditingLine(null);
        } catch (error) {
            throw error instanceof Error ? error : new Error('Unable to save RFQ line.');
        }
    };

    const handleRequestDeleteLine = (item: RfqItem) => {
        setPendingDeleteLine(item);
    };

    const handleDeleteLine = async () => {
        if (!rfqId || !pendingDeleteLine) {
            setPendingDeleteLine(null);
            return;
        }

        try {
            await deleteLineMutation.mutateAsync({
                rfqId,
                lineId: pendingDeleteLine.id,
            });

            publishToast({
                variant: 'success',
                title: 'Line removed',
                description: `Line #${pendingDeleteLine.lineNo} deleted.`,
            });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to delete RFQ line.';
            publishToast({
                variant: 'destructive',
                title: 'Delete failed',
                description: message,
            });
        } finally {
            setPendingDeleteLine(null);
        }
    };

    if (!rfqId) {
        return (
            <div className="flex flex-1 items-center justify-center">
                <Card className="max-w-md">
                    <CardHeader>
                        <CardTitle>RFQ not found</CardTitle>
                    </CardHeader>
                    <CardContent>
                        Provide an RFQ identifier to view its detail workspace.
                    </CardContent>
                </Card>
            </div>
        );
    }

    const handleOpenPublishDialog = () => {
        setPublishForm({
            dueAt: toDateTimeLocalInput(rfq?.deadlineAt ?? null),
            publishAt: toDateTimeLocalInput(new Date()),
            notifySuppliers: true,
            message: '',
        });
        setPublishDialogOpen(true);
    };

    const handlePublish = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!rfqId) {
            publishToast({
                variant: 'destructive',
                title: 'Missing RFQ identifier',
                description: 'Unable to publish without a valid RFQ.',
            });
            return;
        }

        if (!publishForm.dueAt) {
            publishToast({
                variant: 'destructive',
                title: 'Due date required',
                description: 'Set the RFQ due date before publishing.',
            });
            return;
        }

        try {
            const dueAtDate = new Date(publishForm.dueAt);
            if (Number.isNaN(dueAtDate.getTime())) {
                publishToast({
                    variant: 'destructive',
                    title: 'Invalid due date',
                    description: 'Choose a valid due date and time before publishing.',
                });
                return;
            }

            const publishAtDate = publishForm.publishAt ? new Date(publishForm.publishAt) : undefined;
            if (publishAtDate && Number.isNaN(publishAtDate.getTime())) {
                publishToast({
                    variant: 'destructive',
                    title: 'Invalid publish time',
                    description: 'Double-check the publish date and time before continuing.',
                });
                return;
            }

            await publishMutation.mutateAsync({
                rfqId,
                dueAt: dueAtDate,
                publishAt: publishAtDate,
                notifySuppliers: publishForm.notifySuppliers,
                message: publishForm.message || undefined,
            });

            publishToast({
                variant: 'success',
                title: 'Publish queued',
                description: 'Suppliers will be notified according to the selected settings.',
            });
            setPublishDialogOpen(false);
            void rfqQuery.refetch();
            void timelineQuery.refetch();
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to publish the RFQ just yet.';
            publishToast({
                variant: 'destructive',
                title: 'Publish failed',
                description: message,
            });
        }
    };

    const handleAmend = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!rfqId) {
            publishToast({
                variant: 'destructive',
                title: 'Missing RFQ identifier',
                description: 'Unable to amend without a valid RFQ.',
            });
            return;
        }

        if (!amendBody.trim()) {
            publishToast({
                variant: 'destructive',
                title: 'Amendment details required',
                description: 'Describe the scope or instructions for the amendment.',
            });
            return;
        }

        try {
            await amendMutation.mutateAsync({
                rfqId,
                amendment: {
                    body: amendBody.trim(),
                },
            });

            publishToast({
                variant: 'success',
                title: 'Amendment logged',
                description: 'Suppliers will see the update in their workspace.',
            });
            setAmendDialogOpen(false);
            setAmendBody('');
            void rfqQuery.refetch();
            void timelineQuery.refetch();
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to submit the amendment right now.';
            publishToast({
                variant: 'destructive',
                title: 'Amendment failed',
                description: message,
            });
        }
    };

    const handleClose = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!rfqId) {
            publishToast({
                variant: 'destructive',
                title: 'Missing RFQ identifier',
                description: 'Unable to close without a valid RFQ.',
            });
            return;
        }

        try {
            await closeMutation.mutateAsync({
                rfqId,
                reason: closeReason.trim() || undefined,
            });

            publishToast({
                variant: 'success',
                title: 'RFQ closed',
                description: 'Suppliers will no longer be able to submit responses.',
            });
            setCloseDialogOpen(false);
            setCloseReason('');
            void rfqQuery.refetch();
            void timelineQuery.refetch();
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to close the RFQ right now.';
            publishToast({
                variant: 'destructive',
                title: 'Close failed',
                description: message,
            });
        }
    };

    const handleEditDetails = () => {
        if (!rfq) {
            return;
        }

        const deadlineDate = rfq.deadlineAt ? new Date(rfq.deadlineAt) : null;

        setEditForm({
            itemName: rfq.itemName ?? '',
            type: rfq.type ?? RfqTypeEnum.Manufacture,
            deadlineAt: toDateTimeLocalInput(deadlineDate),
            isOpenBidding: Boolean(rfq.isOpenBidding),
            notes: rfq.notes ?? '',
        });
        setEditDialogOpen(true);
    };

    const handleEditDialogOpenChange = (open: boolean) => {
        setEditDialogOpen(open);
        if (!open) {
            setEditForm(DEFAULT_EDIT_FORM);
        }
    };

    const handleUpdateRfq = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!rfqId) {
            publishToast({
                variant: 'destructive',
                title: 'RFQ unavailable',
                description: 'Unable to update this RFQ right now.',
            });
            return;
        }

        const trimmedName = editForm.itemName.trim();
        if (!trimmedName) {
            publishToast({
                variant: 'destructive',
                title: 'Title required',
                description: 'Provide a descriptive item name before saving.',
            });
            return;
        }

        let parsedDeadline: Date | undefined;
        if (editForm.deadlineAt) {
            const candidate = new Date(editForm.deadlineAt);
            if (Number.isNaN(candidate.getTime())) {
                publishToast({
                    variant: 'destructive',
                    title: 'Invalid due date',
                    description: 'Double-check the due date value and try again.',
                });
                return;
            }
            parsedDeadline = candidate;
        }

        try {
            await updateRfqMutation.mutateAsync({
                rfqId,
                itemName: trimmedName,
                type: editForm.type,
                isOpenBidding: editForm.isOpenBidding,
                notes: editForm.notes.trim() || undefined,
                deadlineAt: parsedDeadline,
            });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to save changes right now.';
            publishToast({
                variant: 'destructive',
                title: 'Update failed',
                description: message,
            });
        }
    };

    if (isLoading) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>RFQ detail</title>
                </Helmet>
                <div className="flex h-[240px] items-center justify-center rounded-lg border bg-card">
                    <Spinner className="h-6 w-6" />
                </div>
            </div>
        );
    }

    if (!rfq) {
        return (
            <div className="flex flex-1 items-center justify-center">
                <Card className="max-w-md">
                    <CardHeader>
                        <CardTitle>RFQ unavailable</CardTitle>
                    </CardHeader>
                    <CardContent>We could not load this RFQ. Try refreshing the page or selecting a different RFQ.</CardContent>
                </Card>
            </div>
        );
    }

    const extendedRfq = rfq as Rfq & { incoterm?: string | null; paymentTerms?: string | null };
    const incoterm = extendedRfq.incoterm ?? '—';
    const paymentTerms = extendedRfq.paymentTerms ?? '—';
    const visibilityLabel = extendedRfq.isOpenBidding ? 'Public (open bidding)' : 'Invite only';

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>{rfq.itemName} · RFQ workspace</title>
            </Helmet>

            <RfqActionBar
                rfq={rfq as Rfq}
                onEdit={canEditMetadata ? handleEditDetails : undefined}
                onInviteSuppliers={canInviteSuppliers ? () => setInviteDialogOpen(true) : undefined}
                onPublish={canPublishRfq ? handleOpenPublishDialog : undefined}
                onAmend={canAmendRfq ? () => {
                        setAmendBody('');
                        setAmendDialogOpen(true);
                    }
                    : undefined}
                onClose={canCloseRfq ? () => {
                        setCloseReason('');
                        setCloseDialogOpen(true);
                    }
                    : undefined}
            />

            <div className="flex flex-wrap items-center gap-2">
                <Button asChild variant="secondary">
                    <Link to={`/app/rfqs/${rfq.id}/awards`}>Review awards &amp; convert to POs</Link>
                </Button>
            </div>

            <Tabs value={activeTab} onValueChange={setActiveTab} defaultValue="overview">
                <TabsList>
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="lines">Lines</TabsTrigger>
                    <TabsTrigger value="suppliers">Suppliers &amp; clarifications</TabsTrigger>
                    <TabsTrigger value="timeline">Timeline / audit</TabsTrigger>
                    <TabsTrigger value="attachments">Attachments</TabsTrigger>
                </TabsList>

                <TabsContent value="overview">
                    <div className="grid gap-4 lg:grid-cols-3">
                        <Card className="lg:col-span-2">
                            <CardHeader>
                                <CardTitle>RFQ summary</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid grid-cols-1 gap-3 text-sm text-muted-foreground sm:grid-cols-2">
                                    <div>
                                        <span className="block text-xs uppercase tracking-wide text-muted-foreground">Status</span>
                                        <div className="mt-1 flex items-center gap-2">
                                            <RfqStatusBadge status={rfq.status} />
                                            {rfq.deadlineAt ? (
                                                <span>
                                                    Due {toShortDate(rfq.deadlineAt)} ({toRelativeDate(rfq.deadlineAt)})
                                                </span>
                                            ) : null}
                                        </div>
                                    </div>
                                    <div>
                                        <span className="block text-xs uppercase tracking-wide text-muted-foreground">Published</span>
                                        <span className="mt-1 block text-foreground">
                                            {rfq.sentAt ? `${toShortDate(rfq.sentAt)} (${toRelativeDate(rfq.sentAt)})` : 'Not published'}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="block text-xs uppercase tracking-wide text-muted-foreground">Visibility</span>
                                        <span className="mt-1 block text-foreground">{visibilityLabel}</span>
                                    </div>
                                    <div>
                                        <span className="block text-xs uppercase tracking-wide text-muted-foreground">Method</span>
                                        <span className="mt-1 block text-foreground">{rfq.method}</span>
                                    </div>
                                    <div>
                                        <span className="block text-xs uppercase tracking-wide text-muted-foreground">Material</span>
                                        <span className="mt-1 block text-foreground">{rfq.material}</span>
                                    </div>
                                    <div>
                                        <span className="block text-xs uppercase tracking-wide text-muted-foreground">Quantity</span>
                                        <span className="mt-1 block text-foreground">{rfq.quantity}</span>
                                    </div>
                                    <div>
                                        <span className="block text-xs uppercase tracking-wide text-muted-foreground">Incoterm</span>
                                        <span className="mt-1 block text-foreground">{incoterm}</span>
                                    </div>
                                    <div>
                                        <span className="block text-xs uppercase tracking-wide text-muted-foreground">Payment terms</span>
                                        <span className="mt-1 block text-foreground">{paymentTerms}</span>
                                    </div>
                                    {rfq.tolerance ? (
                                        <div>
                                            <span className="block text-xs uppercase tracking-wide text-muted-foreground">Tolerance</span>
                                            <span className="mt-1 block text-foreground">{rfq.tolerance}</span>
                                        </div>
                                    ) : null}
                                    {rfq.finish ? (
                                        <div>
                                            <span className="block text-xs uppercase tracking-wide text-muted-foreground">Finish</span>
                                            <span className="mt-1 block text-foreground">{rfq.finish}</span>
                                        </div>
                                    ) : null}
                                </div>

                                {rfq.notes ? (
                                    <div className="rounded-md border bg-muted/30 p-4 text-sm text-muted-foreground">
                                        <h3 className="text-sm font-semibold text-foreground">Buyer notes</h3>
                                        <p className="mt-2 whitespace-pre-line">{rfq.notes}</p>
                                    </div>
                                ) : null}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Supplier coverage</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 text-sm">
                                <div>
                                    <span className="text-xs uppercase tracking-wide text-muted-foreground">Invited suppliers</span>
                                    <div className="mt-1 text-2xl font-semibold text-foreground">{supplierStats.totalInvited}</div>
                                </div>
                                <Separator />
                                <div>
                                    <span className="text-xs uppercase tracking-wide text-muted-foreground">Responses received</span>
                                    <div className="mt-1 text-lg font-semibold text-foreground">{supplierStats.responded}</div>
                                </div>
                                <Separator />
                                <div>
                                    <span className="text-xs uppercase tracking-wide text-muted-foreground">Accepted invitations</span>
                                    <div className="mt-1 text-lg font-semibold text-foreground">{supplierStats.accepted}</div>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="justify-self-start"
                                    onClick={() => setActiveTab('suppliers')}
                                >
                                    View clarifications
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <TabsContent value="lines">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Line items</CardTitle>
                                <p className="text-sm text-muted-foreground">Track the parts suppliers will respond to.</p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleCreateLine}
                                disabled={!canManageLines || isLineMutationPending}
                                title={!canManageLines ? 'Upgrade plan to manage RFQ lines.' : undefined}
                            >
                                <Plus className="mr-2 h-4 w-4" /> Add line
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {linesQuery.isLoading ? (
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Spinner /> Loading lines…
                                </div>
                            ) : linesQuery.isError ? (
                                <div className="flex items-center justify-between gap-3 rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                                    <span>Unable to load RFQ lines right now.</span>
                                    <Button type="button" variant="outline" size="sm" onClick={() => linesQuery.refetch()}>
                                        Retry
                                    </Button>
                                </div>
                            ) : (
                                <LinesTable
                                    items={linesQuery.items ?? []}
                                    canManage={canManageLines}
                                    isBusy={isLineMutationPending}
                                    onEdit={canManageLines ? handleEditLine : undefined}
                                    onDelete={canManageLines ? handleRequestDeleteLine : undefined}
                                />
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="suppliers">
                    <div className="grid gap-4 lg:grid-cols-[minmax(260px,1fr)_minmax(0,2fr)]">
                        <Card>
                            <CardHeader>
                                <CardTitle>Invited suppliers</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {suppliersQuery.isLoading ? (
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Spinner /> Loading supplier list…
                                    </div>
                                ) : (
                                    <SuppliersList invitations={suppliersQuery.items ?? []} />
                                )}
                            </CardContent>
                        </Card>
                        <div className="flex flex-col gap-4">
                            <ClarificationThread
                                clarifications={clarificationsQuery.items ?? []}
                                onAskQuestion={(body) => clarificationsQuery.askQuestion({ body })}
                                onAnswerQuestion={(body) => clarificationsQuery.answerQuestion({ body })}
                                isSubmittingQuestion={clarificationsQuery.isSubmittingQuestion}
                                isSubmittingAnswer={clarificationsQuery.isSubmittingAnswer}
                                        canAskQuestion={canManageClarifications}
                                        canAnswerQuestion={canManageClarifications}
                            />
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="timeline">
                    <Card>
                        <CardHeader>
                            <CardTitle>Timeline & audit trail</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {timelineQuery.isLoading ? (
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Spinner /> Loading timeline…
                                </div>
                            ) : (
                                <TimelineView items={sortedTimeline} />
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="attachments">
                    <AttachmentUploader
                        rfqId={rfq.id}
                        attachments={attachmentsQuery.items}
                        isLoading={attachmentsQuery.isLoading}
                        canManage={canManageAttachments}
                    />
                </TabsContent>
            </Tabs>

            <RfqLineEditorModal
                open={isLineEditorOpen}
                onOpenChange={handleLineEditorOpenChange}
                onSubmit={handleLineSubmit}
                initialValues={
                    editingLine
                        ? {
                              partName: editingLine.partName,
                              spec: editingLine.spec ?? '',
                              method: editingLine.method ?? '',
                              material: editingLine.material ?? '',
                              tolerance: editingLine.tolerance ?? '',
                              finish: editingLine.finish ?? '',
                              quantity: editingLine.quantity,
                              uom: editingLine.uom ?? 'ea',
                              targetPrice: editingLine.targetPrice ?? undefined,
                              notes: (editingLine as RfqItem & { notes?: string | null }).notes ?? '',
                              requiredDate:
                                  (editingLine as RfqItem & { requiredDate?: string | null }).requiredDate ?? '',
                          }
                        : undefined
                }
                title={editingLine ? 'Edit RFQ line' : 'Add RFQ line'}
                submitLabel={editingLine ? 'Update line' : 'Save line'}
                isSubmitting={isSavingLine}
            />

            <ConfirmDialog
                open={Boolean(pendingDeleteLine)}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingDeleteLine(null);
                    }
                }}
                title="Remove line"
                description="This line will be removed from the RFQ immediately. Continue?"
                confirmLabel="Delete line"
                cancelLabel="Cancel"
                onConfirm={handleDeleteLine}
                isProcessing={isDeletingLine}
            />

            <InviteSuppliersDialog
                open={isInviteDialogOpen && canInviteSuppliers}
                onOpenChange={setInviteDialogOpen}
                rfqId={rfq.id}
            />

            <Dialog open={isEditDialogOpen} onOpenChange={handleEditDialogOpenChange}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit RFQ details</DialogTitle>
                        <DialogDescription>Update the RFQ title, due date, visibility, or buyer notes.</DialogDescription>
                    </DialogHeader>

                    <form className="grid gap-4" onSubmit={handleUpdateRfq}>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-item-name">Item name</Label>
                            <Input
                                id="edit-item-name"
                                value={editForm.itemName}
                                onChange={(event) =>
                                    setEditForm((state) => ({
                                        ...state,
                                        itemName: event.target.value,
                                    }))
                                }
                                placeholder="e.g. CNC machined bracket"
                                required
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-type">RFQ type</Label>
                            <Select
                                value={editForm.type}
                                onValueChange={(value: RfqTypeEnum) =>
                                    setEditForm((state) => ({
                                        ...state,
                                        type: value,
                                    }))
                                }
                            >
                                <SelectTrigger id="edit-type">
                                    <SelectValue placeholder="Select a type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={RfqTypeEnum.Manufacture}>Manufacture</SelectItem>
                                    <SelectItem value={RfqTypeEnum.ReadyMade}>Ready made</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-deadline">Due date</Label>
                            <Input
                                id="edit-deadline"
                                type="datetime-local"
                                value={editForm.deadlineAt}
                                onChange={(event) =>
                                    setEditForm((state) => ({
                                        ...state,
                                        deadlineAt: event.target.value,
                                    }))
                                }
                            />
                            <p className="text-xs text-muted-foreground">Leave blank if the RFQ doesn’t have a due date yet.</p>
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="edit-open-bidding"
                                checked={editForm.isOpenBidding}
                                onCheckedChange={(checked) =>
                                    setEditForm((state) => ({
                                        ...state,
                                        isOpenBidding: Boolean(checked),
                                    }))
                                }
                            />
                            <Label htmlFor="edit-open-bidding">Public (open bidding) RFQ</Label>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-notes">Buyer notes</Label>
                            <Textarea
                                id="edit-notes"
                                rows={4}
                                value={editForm.notes}
                                onChange={(event) =>
                                    setEditForm((state) => ({
                                        ...state,
                                        notes: event.target.value,
                                    }))
                                }
                                placeholder="Optional context for invitees or internal teammates."
                            />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => handleEditDialogOpenChange(false)} disabled={updateRfqMutation.isPending}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={updateRfqMutation.isPending}>
                                {updateRfqMutation.isPending ? 'Saving…' : 'Save changes'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={isPublishDialogOpen} onOpenChange={setPublishDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Publish RFQ</DialogTitle>
                        <DialogDescription>
                            Confirm the publish schedule and notify suppliers when you are ready to go live.
                        </DialogDescription>
                    </DialogHeader>

                    <form className="grid gap-4" onSubmit={handlePublish}>
                        <div className="grid gap-2">
                            <Label htmlFor="publish-due-at">Due date</Label>
                            <Input
                                id="publish-due-at"
                                type="datetime-local"
                                value={publishForm.dueAt}
                                onChange={(event) =>
                                    setPublishForm((state) => ({
                                        ...state,
                                        dueAt: event.target.value,
                                    }))
                                }
                                required
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="publish-at">Publish at</Label>
                            <Input
                                id="publish-at"
                                type="datetime-local"
                                value={publishForm.publishAt}
                                onChange={(event) =>
                                    setPublishForm((state) => ({
                                        ...state,
                                        publishAt: event.target.value,
                                    }))
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Leave blank to publish immediately after confirmation.
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="publish-message">Notification message</Label>
                            <Textarea
                                id="publish-message"
                                rows={3}
                                value={publishForm.message}
                                onChange={(event) =>
                                    setPublishForm((state) => ({
                                        ...state,
                                        message: event.target.value,
                                    }))
                                }
                                placeholder="Optionally include a note for suppliers."
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="publish-notify"
                                checked={publishForm.notifySuppliers}
                                onCheckedChange={(checked) =>
                                    setPublishForm((state) => ({
                                        ...state,
                                        notifySuppliers: Boolean(checked),
                                    }))
                                }
                            />
                            <Label htmlFor="publish-notify">Email suppliers when the RFQ is published</Label>
                        </div>

                        <DialogFooter>
                            <Button type="submit" disabled={publishMutation.isPending}>
                                {publishMutation.isPending ? 'Publishing…' : 'Publish RFQ'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={isAmendDialogOpen} onOpenChange={setAmendDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Log an amendment</DialogTitle>
                        <DialogDescription>Capture the scope of the change so suppliers can respond.</DialogDescription>
                    </DialogHeader>

                    <form className="grid gap-4" onSubmit={handleAmend}>
                        <div className="grid gap-2">
                            <Label htmlFor="amend-body">Amendment details</Label>
                            <Textarea
                                id="amend-body"
                                rows={5}
                                value={amendBody}
                                onChange={(event) => setAmendBody(event.target.value)}
                                placeholder="Summarize the change, highlight impacted line items, and call out required supplier actions."
                            />
                        </div>

                        <DialogFooter>
                            <Button type="submit" disabled={amendMutation.isPending}>
                                {amendMutation.isPending ? 'Submitting…' : 'Submit amendment'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={isCloseDialogOpen} onOpenChange={setCloseDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Close RFQ</DialogTitle>
                        <DialogDescription>
                            Closing prevents new submissions and notifies suppliers that this RFQ is complete.
                        </DialogDescription>
                    </DialogHeader>

                    <form className="grid gap-4" onSubmit={handleClose}>
                        <div className="grid gap-2">
                            <Label htmlFor="close-reason">Closure rationale</Label>
                            <Textarea
                                id="close-reason"
                                rows={3}
                                value={closeReason}
                                onChange={(event) => setCloseReason(event.target.value)}
                                placeholder="Share the reason for closing to retain in the audit log."
                            />
                        </div>

                        <DialogFooter>
                            <Button type="submit" variant="destructive" disabled={closeMutation.isPending}>
                                {closeMutation.isPending ? 'Closing…' : 'Close RFQ'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
