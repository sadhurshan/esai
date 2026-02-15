import { Globe, RadioTower } from 'lucide-react';
import { useMemo, useState } from 'react';

import { WebhookDeliveryTable } from '@/components/admin/webhook-delivery-table';
import {
    WebhookEndpointEditor,
    type WebhookEventOption,
} from '@/components/admin/webhook-endpoint-editor';
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
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { useAuth } from '@/contexts/auth-context';
import { useCreateWebhook } from '@/hooks/api/admin/use-create-webhook';
import { useDeleteWebhook } from '@/hooks/api/admin/use-delete-webhook';
import { useRetryWebhookDelivery } from '@/hooks/api/admin/use-retry-webhook-delivery';
import { useTestWebhook } from '@/hooks/api/admin/use-test-webhook';
import { useUpdateWebhook } from '@/hooks/api/admin/use-update-webhook';
import { useWebhookDeliveries } from '@/hooks/api/admin/use-webhook-deliveries';
import { useWebhooks } from '@/hooks/api/admin/use-webhooks';
import type {
    CreateWebhookPayload,
    WebhookDeliveryItem,
    WebhookSubscriptionItem,
} from '@/types/admin';

const WEBHOOK_EVENT_OPTIONS: WebhookEventOption[] = [
    {
        value: 'rfq.published',
        label: 'RFQ published',
        description: 'Triggered when an RFQ is shared with suppliers.',
    },
    {
        value: 'quote.submitted',
        label: 'Quote submitted',
        description: 'Emitted whenever a supplier submits a quote.',
    },
    {
        value: 'po.issued',
        label: 'Purchase order issued',
        description: 'Fires when a PO is issued or re-sent.',
    },
    {
        value: 'invoice.payable',
        label: 'Invoice payable',
        description: 'Sent when an invoice is cleared for payment.',
    },
];

const EMPTY_WEBHOOKS: WebhookSubscriptionItem[] = [];

export function AdminWebhooksPage() {
    const { state } = useAuth();
    const { data, isLoading } = useWebhooks({ perPage: 100 });
    const createWebhook = useCreateWebhook();
    const updateWebhook = useUpdateWebhook();
    const deleteWebhook = useDeleteWebhook();
    const retryDelivery = useRetryWebhookDelivery();
    const testWebhook = useTestWebhook();

    const [selectionOverride, setSelectionOverride] = useState<string | null>(
        null,
    );
    const [cursorMap, setCursorMap] = useState<Record<string, string | null>>(
        {},
    );
    const [editorState, setEditorState] = useState<{
        mode: 'create' | 'edit';
        subscription?: WebhookSubscriptionItem | null;
    } | null>(null);
    const [deleteTarget, setDeleteTarget] =
        useState<WebhookSubscriptionItem | null>(null);

    const webhooks = data?.items ?? EMPTY_WEBHOOKS;

    const selectedSubscriptionId = useMemo(() => {
        if (!webhooks.length) {
            return null;
        }
        if (
            selectionOverride &&
            webhooks.some(
                (subscription) => String(subscription.id) === selectionOverride,
            )
        ) {
            return selectionOverride;
        }
        return String(webhooks[0].id);
    }, [selectionOverride, webhooks]);

    const deliveryCursor = selectedSubscriptionId
        ? (cursorMap[selectedSubscriptionId] ?? null)
        : null;
    const selectedSubscription =
        webhooks.find(
            (subscription) =>
                String(subscription.id) === selectedSubscriptionId,
        ) ?? null;

    const companyOptions = state.company?.id
        ? [
              {
                  value: state.company.id,
                  label: state.company.name ?? `Company #${state.company.id}`,
              },
          ]
        : undefined;

    const deliveriesQuery = useWebhookDeliveries({
        subscriptionId: selectedSubscriptionId ?? undefined,
        cursor: deliveryCursor ?? undefined,
    });

    const retryingDeliveryId = retryDelivery.isPending
        ? (retryDelivery.variables?.deliveryId ?? null)
        : null;

    const handleEditorSubmit = async (payload: CreateWebhookPayload) => {
        if (editorState?.mode === 'edit' && editorState.subscription) {
            await updateWebhook.mutateAsync({
                subscriptionId: editorState.subscription.id,
                ...payload,
            });
        } else {
            await createWebhook.mutateAsync(payload);
        }
        setEditorState(null);
    };

    const handleDelete = async () => {
        if (!deleteTarget) {
            return;
        }
        await deleteWebhook.mutateAsync({ subscriptionId: deleteTarget.id });
        setDeleteTarget(null);
    };

    const handleRetry = (delivery: WebhookDeliveryItem) => {
        retryDelivery.mutate({
            deliveryId: String(delivery.id),
            subscriptionId: selectedSubscriptionId ?? undefined,
        });
    };

    const handleSendTest = (subscription: WebhookSubscriptionItem) => {
        const defaultEvent = subscription.events?.[0] ?? 'webhook.test';
        testWebhook.mutate({
            subscriptionId: subscription.id,
            event: defaultEvent,
        });
    };

    return (
        <div className="space-y-8">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <Heading
                    title="Webhooks"
                    description="Create outbound subscriptions, rotate secrets, and inspect delivery health."
                />
                <Button
                    type="button"
                    onClick={() => setEditorState({ mode: 'create' })}
                >
                    Create endpoint
                </Button>
            </div>

            <section className="space-y-4">
                {isLoading ? (
                    <div className="grid gap-4 md:grid-cols-2">
                        {Array.from({ length: 4 }).map((_, index) => (
                            <div
                                key={index}
                                className="h-48 animate-pulse rounded-xl border border-dashed border-muted"
                            />
                        ))}
                    </div>
                ) : webhooks.length === 0 ? (
                    <EmptyState
                        icon={<RadioTower className="h-10 w-10" aria-hidden />}
                        title="No endpoints yet"
                        description="Create a webhook subscription to receive RFQ, quote, and fulfillment events."
                        ctaLabel="Create endpoint"
                        ctaProps={{
                            onClick: () => setEditorState({ mode: 'create' }),
                        }}
                    />
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {webhooks.map((subscription) => {
                            const isActive = subscription.active;
                            const isSelected =
                                selectedSubscriptionId ===
                                String(subscription.id);
                            const events = subscription.events ?? [];
                            return (
                                <Card
                                    key={subscription.id}
                                    className={
                                        isSelected
                                            ? 'border-primary/60 shadow-sm'
                                            : ''
                                    }
                                >
                                    <CardHeader className="gap-2">
                                        <CardTitle className="flex flex-wrap items-center gap-2 text-lg">
                                            {subscription.url}
                                            <Badge
                                                variant={
                                                    isActive
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {isActive ? 'Active' : 'Paused'}
                                            </Badge>
                                        </CardTitle>
                                        <CardDescription>
                                            {events.length} event
                                            {events.length === 1
                                                ? ''
                                                : 's'}{' '}
                                            subscribed
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-3 text-sm">
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <Globe
                                                className="h-4 w-4"
                                                aria-hidden
                                            />{' '}
                                            Company #{subscription.companyId}
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {events.map((event) => (
                                                <Badge
                                                    key={`${subscription.id}-${event}`}
                                                    variant="outline"
                                                    className="font-mono text-[11px]"
                                                >
                                                    {event}
                                                </Badge>
                                            ))}
                                        </div>
                                        {subscription.secretHint ? (
                                            <p className="text-xs text-muted-foreground">
                                                Secret ends with{' '}
                                                {subscription.secretHint}
                                            </p>
                                        ) : null}
                                    </CardContent>
                                    <CardFooter className="flex flex-wrap gap-2 border-t pt-4">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setSelectionOverride(
                                                    String(subscription.id),
                                                )
                                            }
                                        >
                                            View deliveries
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setEditorState({
                                                    mode: 'edit',
                                                    subscription,
                                                })
                                            }
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                handleSendTest(subscription)
                                            }
                                            disabled={testWebhook.isPending}
                                        >
                                            {testWebhook.isPending &&
                                            testWebhook.variables
                                                ?.subscriptionId ===
                                                subscription.id
                                                ? 'Testing…'
                                                : 'Send test'}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            onClick={() =>
                                                setDeleteTarget(subscription)
                                            }
                                        >
                                            Delete
                                        </Button>
                                    </CardFooter>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </section>

            <section className="space-y-4">
                <div className="flex flex-col gap-2">
                    <div className="flex items-center gap-3">
                        <RadioTower
                            className="h-5 w-5 text-muted-foreground"
                            aria-hidden
                        />
                        <div>
                            <h3 className="text-base font-semibold">
                                Delivery log
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Inspect payload delivery attempts for the
                                selected endpoint.
                            </p>
                        </div>
                    </div>
                    <Separator />
                </div>
                {selectedSubscription ? (
                    <WebhookDeliveryTable
                        deliveries={deliveriesQuery.data?.items ?? []}
                        meta={deliveriesQuery.data?.meta}
                        isLoading={deliveriesQuery.isLoading}
                        onNextPage={(cursor) => {
                            if (!selectedSubscriptionId) {
                                return;
                            }
                            setCursorMap((prev) => ({
                                ...prev,
                                [selectedSubscriptionId]: cursor,
                            }));
                        }}
                        onPrevPage={(cursor) => {
                            if (!selectedSubscriptionId) {
                                return;
                            }
                            setCursorMap((prev) => ({
                                ...prev,
                                [selectedSubscriptionId]: cursor,
                            }));
                        }}
                        onRetry={handleRetry}
                        retryingDeliveryId={retryingDeliveryId}
                    />
                ) : (
                    <EmptyState
                        icon={<RadioTower className="h-10 w-10" aria-hidden />}
                        title="Select an endpoint"
                        description="Choose an endpoint above to review its delivery history."
                    />
                )}
            </section>

            <Dialog
                open={Boolean(editorState)}
                onOpenChange={(open) =>
                    setEditorState(open ? editorState : null)
                }
            >
                <DialogContent className="max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editorState?.mode === 'edit'
                                ? 'Edit endpoint'
                                : 'Create endpoint'}
                        </DialogTitle>
                    </DialogHeader>
                    <WebhookEndpointEditor
                        subscription={editorState?.subscription ?? undefined}
                        availableEvents={WEBHOOK_EVENT_OPTIONS}
                        companyOptions={companyOptions}
                        onSubmit={handleEditorSubmit}
                        onCancel={() => setEditorState(null)}
                        isSubmitting={
                            editorState?.mode === 'edit'
                                ? updateWebhook.isPending
                                : createWebhook.isPending
                        }
                        mode={editorState?.mode ?? 'create'}
                    />
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={Boolean(deleteTarget)}
                onOpenChange={(open) =>
                    setDeleteTarget(open ? deleteTarget : null)
                }
                title="Delete webhook?"
                description={
                    deleteTarget
                        ? `This will remove ${deleteTarget.url} immediately.`
                        : ''
                }
                confirmLabel="Delete"
                onConfirm={handleDelete}
                isProcessing={deleteWebhook.isPending}
            />
        </div>
    );
}
