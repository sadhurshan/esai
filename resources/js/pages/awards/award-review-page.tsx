import { useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { useNavigate, useParams } from 'react-router-dom';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';

import { AwardLinePicker } from '@/components/awards/award-line-picker';
import { AwardSummaryCard } from '@/components/awards/award-summary-card';
import { ConvertToPoDialog } from '@/components/awards/convert-to-po-dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { useCreateAwards } from '@/hooks/api/awards/use-create-awards';
import { useDeleteAward } from '@/hooks/api/awards/use-delete-award';
import { useRfqAwardCandidates } from '@/hooks/api/awards/use-rfq-award-candidates';
import { useCreatePo } from '@/hooks/api/pos/use-create-po';
import { awardFormSchema, type AwardFormValues } from '@/pages/awards/award-form-schema';
import { HttpError } from '@/sdk';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';

export function AwardReviewPage() {
    const params = useParams<{ rfqId: string }>();
    const rfqId = Number(params.rfqId);
    const navigate = useNavigate();
    const { data, isLoading, isFetching, refetch } = useRfqAwardCandidates(rfqId);
    const createAwardsMutation = useCreateAwards();
    const deleteAwardMutation = useDeleteAward();
    const createPoMutation = useCreatePo();
    const { notifyPlanLimit } = useAuth();
    const [isConvertDialogOpen, setIsConvertDialogOpen] = useState(false);
    const [deletingAwardId, setDeletingAwardId] = useState<number | null>(null);

    const form = useForm<AwardFormValues>({
        resolver: zodResolver(awardFormSchema),
        defaultValues: {
            lines: [],
        },
    });

    const rfq = data?.rfq;
    const companyCurrency = data?.companyCurrency;
    const lines = useMemo(() => data?.lines ?? [], [data?.lines]);
    const awards = useMemo(() => data?.awards ?? [], [data?.awards]);

    const defaultLineValues = useMemo(() => {
        return lines.map((line) => {
            const persistedAward = awards.find((award) => award.rfqItemId === line.id && !award.poId);
            return {
                rfqItemId: line.id,
                quoteItemId: persistedAward?.quoteItemId,
                awardedQty: persistedAward?.awardedQty ?? line.quantity,
            };
        });
    }, [awards, lines]);

    const defaultHash = useMemo(() => JSON.stringify(defaultLineValues), [defaultLineValues]);

    useEffect(() => {
        form.reset({ lines: defaultLineValues });
    }, [defaultHash, defaultLineValues, form]);

    const watchedSelections = form.watch('lines');

    const handlePersistAwards = form.handleSubmit(async (values) => {
        const items = values.lines
            .filter((line) => line.quoteItemId)
            .map((line) => ({
                rfqItemId: line.rfqItemId,
                quoteItemId: line.quoteItemId!,
                awardedQty: line.awardedQty && line.awardedQty > 0 ? line.awardedQty : undefined,
            }));

        if (!items.length) {
            publishToast({
                variant: 'destructive',
                title: 'No selections',
                description: 'Pick at least one supplier before saving awards.',
            });
            return;
        }

        try {
            await createAwardsMutation.mutateAsync({ rfqId, items });
            await refetch();
        } catch (error) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to save awards',
                description: error instanceof Error ? error.message : 'Unexpected error occurred while saving.',
            });
        }
    });

    const handleDeleteAward = async (awardId: number) => {
        if (!rfqId || deletingAwardId) {
            return;
        }
        setDeletingAwardId(awardId);
        try {
            await deleteAwardMutation.mutateAsync({ awardId, rfqId });
            await refetch();
        } catch (error) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to remove award',
                description: error instanceof Error ? error.message : 'Unexpected error occurred while deleting.',
            });
        } finally {
            setDeletingAwardId(null);
        }
    };

    const handleConvert = async (awardIds: number[]) => {
        try {
            const purchaseOrders = await createPoMutation.mutateAsync({ awardIds, rfqId });
            setIsConvertDialogOpen(false);
            if (purchaseOrders.length > 0) {
                navigate(`/app/purchase-orders/${purchaseOrders[0].id}`);
            }
        } catch (error) {
            if (error instanceof HttpError && error.response.status === 402) {
                const planMessage = (error.body as { message?: string } | undefined)?.message;
                notifyPlanLimit({
                    code: 'purchase_orders',
                    message: planMessage ?? 'Upgrade plan to create purchase orders.',
                });
            } else {
                publishToast({
                    variant: 'destructive',
                    title: 'Conversion failed',
                    description: error instanceof Error ? error.message : 'Unable to convert awards to purchase orders.',
                });
            }
        }
    };

    const showSkeleton = isLoading && !data;

    return (
        <div className="space-y-6">
            <Helmet>
                <title>Award RFQ lines • Elements Supply</title>
            </Helmet>

            <WorkspaceBreadcrumbs />

            <Card className="border-border/70">
                <CardContent className="flex flex-col gap-2 p-6">
                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        {rfq?.number ? <Badge variant="outline">RFQ #{rfq.number}</Badge> : null}
                        {rfq?.status ? (
                            <Badge variant="secondary" className="capitalize">
                                {rfq.status.replace(/_/g, ' ')}
                            </Badge>
                        ) : null}
                        {isFetching ? <span className="text-xs">Refreshing…</span> : null}
                    </div>
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold text-foreground">Award RFQ lines</h1>
                        <p className="text-sm text-muted-foreground">
                            Select winning quotes per line, persist awards, and convert them into purchase order drafts grouped by supplier.
                        </p>
                    </div>
                </CardContent>
            </Card>

            {showSkeleton ? (
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="space-y-4">
                        <Skeleton className="h-40 w-full" />
                        <Skeleton className="h-40 w-full" />
                    </div>
                    <Skeleton className="h-64 w-full" />
                </div>
            ) : (
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <form id="award-lines-form" className="space-y-4" onSubmit={handlePersistAwards}>
                        <AwardLinePicker
                            lines={lines}
                            form={form}
                            isSubmitting={createAwardsMutation.isPending}
                            isLoading={isLoading}
                            companyCurrency={companyCurrency}
                        />
                    </form>
                    <div className="lg:sticky lg:top-20">
                        <AwardSummaryCard
                            lines={lines}
                            selections={watchedSelections}
                            awards={awards}
                            companyCurrency={companyCurrency}
                            isSaving={createAwardsMutation.isPending}
                            deletingAwardId={deletingAwardId}
                            onDeleteAward={handleDeleteAward}
                            onPersist={handlePersistAwards}
                            onOpenConvert={() => setIsConvertDialogOpen(true)}
                            canConvert={awards.some((award) => !award.poId)}
                            isConverting={createPoMutation.isPending}
                        />
                    </div>
                </div>
            )}

            <ConvertToPoDialog
                open={isConvertDialogOpen}
                onOpenChange={setIsConvertDialogOpen}
                awards={awards}
                lines={lines}
                isConverting={createPoMutation.isPending}
                onConfirm={handleConvert}
            />
        </div>
    );
}
