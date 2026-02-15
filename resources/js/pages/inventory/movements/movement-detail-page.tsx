import { ArrowLeft, Boxes, FileWarning, Loader2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Helmet } from 'react-helmet-async';
import { useNavigate, useParams } from 'react-router-dom';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { EmptyState } from '@/components/empty-state';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useMovement } from '@/hooks/api/inventory/use-movement';
import type { StockMovementLine } from '@/types/inventory';

export function MovementDetailPage() {
    const { movementId } = useParams<{ movementId: string }>();
    const navigate = useNavigate();
    const { hasFeature, state } = useAuth();
    const { formatDate, formatNumber } = useFormatting();
    const featureFlagsLoaded =
        state.status !== 'idle' && state.status !== 'loading';
    const inventoryEnabled = hasFeature('inventory_enabled');

    const movementQuery = useMovement(movementId ?? '', {
        enabled: Boolean(movementId),
    });

    if (featureFlagsLoaded && !inventoryEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Inventory · Movement detail</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Inventory unavailable"
                    description="Upgrade your plan to review individual movements."
                    icon={<Boxes className="h-12 w-12 text-muted-foreground" />}
                    ctaLabel="View plans"
                    ctaProps={{
                        onClick: () => navigate('/app/settings/billing'),
                    }}
                />
            </div>
        );
    }

    if (movementQuery.isLoading) {
        return (
            <div className="flex flex-1 items-center justify-center">
                <Loader2 className="h-10 w-10 animate-spin text-muted-foreground" />
            </div>
        );
    }

    if (!movementQuery.data) {
        return (
            <EmptyState
                title="Movement not found"
                description="The requested movement could not be found."
                icon={
                    <FileWarning className="h-12 w-12 text-muted-foreground" />
                }
                ctaLabel="Back to movements"
                ctaProps={{
                    onClick: () => navigate('/app/inventory/movements'),
                }}
            />
        );
    }

    const movement = movementQuery.data;

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>{`${movement.movementNumber} · Movement`}</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-border/70 bg-background/70 p-4">
                <div>
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Movement number
                    </p>
                    <h1 className="text-2xl font-semibold text-foreground">
                        {movement.movementNumber || movement.id}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Posted{' '}
                        {formatDate(movement.movedAt, {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                        })}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <Badge variant="secondary" className="uppercase">
                        {movement.type}
                    </Badge>
                    <Badge
                        variant={
                            movement.status === 'posted'
                                ? 'secondary'
                                : 'outline'
                        }
                        className="uppercase"
                    >
                        {movement.status}
                    </Badge>
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <Card className="border-border/70 md:col-span-2">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Movement lines</CardTitle>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => navigate('/app/inventory/movements')}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" /> Back to list
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {movement.lines.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No lines recorded.
                            </p>
                        ) : (
                            movement.lines.map((line) => (
                                <MovementLineRow
                                    key={
                                        line.id ?? `${line.itemId}-${line.qty}`
                                    }
                                    line={line}
                                />
                            ))
                        )}
                    </CardContent>
                </Card>
                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <SummaryItem label="From">
                            {movement.fromLocationName ??
                                movement.lines[0]?.fromLocation?.name ??
                                '—'}
                        </SummaryItem>
                        <SummaryItem label="To">
                            {movement.toLocationName ??
                                movement.lines[0]?.toLocation?.name ??
                                '—'}
                        </SummaryItem>
                        <SummaryItem label="Reference">
                            {movement.referenceMeta?.source ? (
                                <div>
                                    <p className="font-medium">
                                        {movement.referenceMeta.source}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {movement.referenceMeta.id ?? '—'}
                                    </p>
                                </div>
                            ) : (
                                '—'
                            )}
                        </SummaryItem>
                        <SummaryItem label="Created by">
                            {movement.createdBy?.name ?? 'System'}
                        </SummaryItem>
                        <SummaryItem label="Notes">
                            {movement.notes ?? '—'}
                        </SummaryItem>
                    </CardContent>
                </Card>
            </div>

            {movement.balances && movement.balances.length > 0 ? (
                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle>Resulting balances</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full min-w-[400px] text-sm">
                            <thead>
                                <tr className="text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <th className="px-2 py-2">Location</th>
                                    <th className="px-2 py-2">On-hand</th>
                                    <th className="px-2 py-2">Available</th>
                                </tr>
                            </thead>
                            <tbody>
                                {movement.balances.map((balance) => (
                                    <tr
                                        key={balance.locationId}
                                        className="border-t border-border/60"
                                    >
                                        <td className="px-2 py-2 font-medium">
                                            {balance.locationId}
                                        </td>
                                        <td className="px-2 py-2">
                                            {formatNumber(balance.onHand, {
                                                maximumFractionDigits: 3,
                                            })}
                                        </td>
                                        <td className="px-2 py-2">
                                            {typeof balance.available ===
                                            'number'
                                                ? formatNumber(
                                                      balance.available,
                                                      {
                                                          maximumFractionDigits: 3,
                                                      },
                                                  )
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            ) : null}
        </div>
    );
}

function SummaryItem({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div>
            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="text-sm font-semibold text-foreground">{children}</p>
        </div>
    );
}

function MovementLineRow({ line }: { line: StockMovementLine }) {
    const { formatNumber } = useFormatting();
    return (
        <div className="rounded-lg border border-border/60 p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p className="text-sm font-semibold text-foreground">
                        {line.itemName ?? line.itemSku ?? line.itemId}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Qty{' '}
                        {formatNumber(line.qty, { maximumFractionDigits: 3 })}{' '}
                        {line.uom ?? ''}
                    </p>
                </div>
                <Badge variant="outline" className="uppercase">
                    {line.reason ?? 'Movement line'}
                </Badge>
            </div>
            <Separator className="my-3" />
            <div className="grid gap-3 text-sm md:grid-cols-3">
                <div>
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        From
                    </p>
                    <p className="text-sm font-medium">
                        {line.fromLocation?.name ?? '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        To
                    </p>
                    <p className="text-sm font-medium">
                        {line.toLocation?.name ?? '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Resulting on-hand
                    </p>
                    <p className="text-sm font-medium">
                        {line.resultingOnHand !== null &&
                        line.resultingOnHand !== undefined
                            ? formatNumber(line.resultingOnHand, {
                                  maximumFractionDigits: 3,
                              })
                            : '—'}
                    </p>
                </div>
            </div>
        </div>
    );
}
