import { useEffect, useMemo, type ReactNode } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate, type NavigateFunction } from 'react-router-dom';
import {
    ArrowRight,
    FileText,
    Image as ImageIcon,
    Layers,
    RefreshCw,
    ShieldAlert,
    Sparkles,
} from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { useDigitalTwins, useUseForRfq } from '@/hooks/api/digital-twins';
import type { CursorPaginationMeta } from '@/lib/pagination';
import type { DigitalTwinCategoryNode, DigitalTwinLibraryListItem } from '@/sdk';

const DIGITAL_TWIN_FEATURE_KEYS = ['digital_twin_enabled', 'digital_twin.access'];
const SUPPLIER_ROLE_PREFIX = 'supplier_';

export function AssetsPage() {
    const navigate = useNavigate();
    const { hasFeature, state, notifyPlanLimit } = useAuth();

    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const hasDigitalTwinAccess = DIGITAL_TWIN_FEATURE_KEYS.some((key) => hasFeature(key));
    const isSupplierRole = (state.user?.role ?? '').startsWith(SUPPLIER_ROLE_PREFIX);

    useEffect(() => {
        if (featureFlagsLoaded && !hasDigitalTwinAccess) {
            notifyPlanLimit({
                code: 'digital_twin_disabled',
                message: 'Upgrade to activate the digital twin workspace.',
            });
        }
    }, [featureFlagsLoaded, hasDigitalTwinAccess, notifyPlanLimit]);

    const twinsQuery = useDigitalTwins(
        {
            perPage: 9,
            sort: 'updated_at',
            includeCategories: true,
        },
        {
            enabled: featureFlagsLoaded && hasDigitalTwinAccess && !isSupplierRole,
        },
    );

    const useForRfq = useUseForRfq({
        onSuccess: (response) => {
            const payload = response.data;
            if (!payload) {
                publishToast({
                    variant: 'destructive',
                    title: 'Unable to open RFQ wizard',
                    description: 'The server response was missing the digital twin draft.',
                });
                return;
            }

            publishToast({
                variant: 'success',
                title: 'Draft ready',
                description: 'Launching the RFQ wizard with this twin attached.',
            });

            navigate('/app/rfqs/new', {
                state: { digitalTwinDraft: payload },
            });
        },
        onError: () => {
            publishToast({
                variant: 'destructive',
                title: 'Unable to use digital twin',
                description: 'Try again in a few seconds or open the library detail page.',
            });
        },
    });

    const totalPublished = useMemo(() => {
        return resolveMetaNumber(twinsQuery.meta, [
            ['totals', 'digital_twins'],
            ['totals', 'items'],
            ['total'],
            ['count'],
        ], twinsQuery.items.length);
    }, [twinsQuery.meta, twinsQuery.items.length]);

    const cadReadyCount = useMemo(() => countWithAssetType(twinsQuery.items, 'CAD'), [twinsQuery.items]);
    const activeCategories = useMemo(() => flattenCategories(twinsQuery.categories).length, [twinsQuery.categories]);

    const spotlightTwins = useMemo(() => twinsQuery.items.slice(0, 6), [twinsQuery.items]);
    const cadSpotlights = useMemo(() => selectWithAssetType(twinsQuery.items, 'CAD', 3), [twinsQuery.items]);
    const docBundles = useMemo(() => selectWithAssetType(twinsQuery.items, 'PDF', 3), [twinsQuery.items]);
    const topCategories = useMemo(() => flattenCategories(twinsQuery.categories).slice(0, 6), [twinsQuery.categories]);

    if (isSupplierRole) {
        return (
            <section className="mx-auto flex w-full max-w-3xl flex-col gap-6 py-10">
                <Helmet>
                    <title>Assets workspace</title>
                </Helmet>
                <EmptyState
                    title="Buyer access required"
                    description="Switch to your buyer workspace to access digital twins, maintenance manuals, and RFQ prefills."
                    icon={<ShieldAlert className="h-12 w-12 text-amber-500" />}
                    ctaLabel="Back to dashboard"
                    ctaProps={{ onClick: () => navigate('/app') }}
                />
            </section>
        );
    }

    if (featureFlagsLoaded && !hasDigitalTwinAccess) {
        return (
            <section className="mx-auto flex w-full max-w-3xl flex-col gap-6 py-10">
                <Helmet>
                    <title>Assets workspace</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Upgrade to unlock the Digital Twin Workspace"
                    description="Growth plans and above include the curated twin library, CAD bundles, and RFQ prefills."
                    icon={<ShieldAlert className="h-12 w-12 text-amber-500" />}
                    ctaLabel="View billing options"
                    ctaProps={{ onClick: () => navigate('/app/settings/billing') }}
                />
            </section>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Assets & Digital Twins</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <header className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-2">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Assets</p>
                    <h1 className="text-3xl font-semibold tracking-tight">Digital Twin Workspace</h1>
                    <p className="text-sm text-muted-foreground">
                        Keep specs, CAD, manuals, and sourcing context synchronized for every mission-critical asset.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={() => twinsQuery.refetch()} disabled={twinsQuery.isFetching}>
                        <RefreshCw className="mr-2 h-4 w-4" /> Refresh
                    </Button>
                    <Button type="button" size="sm" onClick={() => navigate('/app/rfqs/new')}>
                        <Sparkles className="mr-2 h-4 w-4" /> Launch RFQ
                    </Button>
                </div>
            </header>

            <StatGrid
                isLoading={twinsQuery.isLoading}
                stats={[
                    { label: 'Published twins', value: totalPublished, description: 'Curated + audit ready.' },
                    { label: 'CAD ready bundles', value: cadReadyCount, description: 'STEP / CAD attachments available.' },
                    { label: 'Active categories', value: activeCategories, description: 'Asset families mapped by Ops.' },
                ]}
            />

            <QuickActions navigate={navigate} />

            <section className="grid gap-6 lg:grid-cols-[2fr,1fr]">
                <Card className="border-border/70">
                    <CardHeader className="flex flex-row items-start justify-between gap-3 pb-2">
                        <div>
                            <CardTitle>Spotlight digital twins</CardTitle>
                            <p className="text-sm text-muted-foreground">Recently updated releases from the Elements curation team.</p>
                        </div>
                        <Button asChild variant="ghost" size="sm">
                            <Link to="/app/library/digital-twins">Browse library</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {twinsQuery.isLoading ? (
                            <div className="grid gap-4 md:grid-cols-2">
                                {Array.from({ length: 4 }).map((_, index) => (
                                    <Skeleton key={index} className="h-48 w-full" />
                                ))}
                            </div>
                        ) : spotlightTwins.length === 0 ? (
                            <EmptyState
                                className="border-none bg-transparent"
                                title="No published digital twins yet"
                                description="Work with the Elements team to onboard your first asset pack."
                                icon={<Layers className="h-12 w-12 text-muted-foreground" />}
                                ctaLabel="Contact support"
                                ctaProps={{ onClick: () => navigate('/app/settings?tab=help') }}
                            />
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2">
                                {spotlightTwins.map((twin) => (
                                    <TwinSpotlightCard
                                        key={twin.id}
                                        twin={twin}
                                        onView={() => navigate(`/app/library/digital-twins/${twin.id}`)}
                                        onUse={() => useForRfq.mutate({ digitalTwinId: twin.id })}
                                        isUsing={useForRfq.isPending}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="border-border/70">
                    <CardHeader className="gap-2 pb-2">
                        <CardTitle>Active asset families</CardTitle>
                        <p className="text-sm text-muted-foreground">Jump straight into a filtered library view for your top programs.</p>
                    </CardHeader>
                    <CardContent>
                        {twinsQuery.isLoading ? (
                            <div className="space-y-2">
                                {Array.from({ length: 5 }).map((_, index) => (
                                    <Skeleton key={index} className="h-10 w-full" />
                                ))}
                            </div>
                        ) : topCategories.length === 0 ? (
                            <EmptyState
                                className="border-none bg-transparent"
                                title="No categories yet"
                                description="Categories appear after the first batch of digital twins is published."
                                icon={<Layers className="h-10 w-10 text-muted-foreground" />}
                            />
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {topCategories.map((category) => (
                                    <Button
                                        key={category.id}
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            const params = new URLSearchParams({ categoryId: category.id.toString() });
                                            navigate(`/app/library/digital-twins?${params.toString()}`);
                                        }}
                                    >
                                        {category.name}
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </Button>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </section>

            <section className="grid gap-6 lg:grid-cols-2">
                <AssetBundlePanel
                    title="CAD-ready bundles"
                    description="STEP / CAD exports ready for manufacturing partners."
                    icon={<Layers className="h-5 w-5" />}
                    items={cadSpotlights}
                    isLoading={twinsQuery.isLoading}
                    emptyDescription="No CAD-ready bundles yet. Tag uploads with CAD to feature them here."
                    navigate={navigate}
                    onUse={(twinId) => useForRfq.mutate({ digitalTwinId: twinId })}
                    isUsing={useForRfq.isPending}
                />
                <AssetBundlePanel
                    title="Manuals & document kits"
                    description="PDF or spec packs linked directly to maintenance plans."
                    icon={<FileText className="h-5 w-5" />}
                    items={docBundles}
                    isLoading={twinsQuery.isLoading}
                    emptyDescription="Upload PDF/IMAGE assets to surface manuals here."
                    navigate={navigate}
                    onUse={(twinId) => useForRfq.mutate({ digitalTwinId: twinId })}
                    isUsing={useForRfq.isPending}
                />
            </section>
        </div>
    );
}

interface StatGridProps {
    stats: Array<{ label: string; value: number; description: string }>;
    isLoading: boolean;
}

function StatGrid({ stats, isLoading }: StatGridProps) {
    return (
        <div className="grid gap-4 md:grid-cols-3">
            {stats.map((stat) => (
                <Card key={stat.label} className="border-border/70">
                    <CardContent className="p-4">
                        <p className="text-sm text-muted-foreground">{stat.label}</p>
                        {isLoading ? (
                            <Skeleton className="mt-2 h-7 w-24" />
                        ) : (
                            <p className="mt-1 text-3xl font-semibold">{stat.value}</p>
                        )}
                        <p className="text-xs text-muted-foreground">{stat.description}</p>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function QuickActions({ navigate }: { navigate: NavigateFunction }) {
    return (
        <Card className="border-border/70">
            <CardHeader className="gap-2 pb-2">
                <CardTitle>Workspace shortcuts</CardTitle>
                <p className="text-sm text-muted-foreground">Go from spec to RFQ or pull manuals with two clicks.</p>
            </CardHeader>
            <CardContent className="grid gap-4 md:grid-cols-3">
                <ActionTile
                    title="Browse library"
                    description="Search all published digital twins."
                    icon={<Layers className="h-5 w-5" />}
                    onClick={() => navigate('/app/library/digital-twins')}
                />
                <ActionTile
                    title="Launch RFQ"
                    description="Start CAD-aware sourcing flows."
                    icon={<Sparkles className="h-5 w-5" />}
                    onClick={() => navigate('/app/rfqs/new')}
                />
                <ActionTile
                    title="Download manuals"
                    description="Pull the latest PDFs and datasheets."
                    icon={<FileText className="h-5 w-5" />}
                    onClick={() => navigate('/app/downloads')}
                />
            </CardContent>
        </Card>
    );
}

interface ActionTileProps {
    title: string;
    description: string;
    icon: ReactNode;
    onClick: () => void;
}

function ActionTile({ title, description, icon, onClick }: ActionTileProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="flex h-full flex-col justify-between rounded-xl border border-border/70 p-4 text-left transition hover:border-primary"
        >
            <div className="flex items-center gap-2 text-sm font-semibold text-foreground">
                {icon}
                {title}
            </div>
            <p className="mt-2 text-sm text-muted-foreground">{description}</p>
        </button>
    );
}

interface TwinSpotlightCardProps {
    twin: DigitalTwinLibraryListItem;
    onUse: () => void;
    onView: () => void;
    isUsing: boolean;
}

function TwinSpotlightCard({ twin, onUse, onView, isUsing }: TwinSpotlightCardProps) {
    const assetLabel = twin.primary_asset?.type ?? twin.asset_types?.[0];

    return (
        <Card className="h-full overflow-hidden border-border/70">
            <div className="relative h-32 w-full bg-muted">
                {twin.thumbnail_url ? (
                    <img src={twin.thumbnail_url} alt={`${twin.title} thumbnail`} className="h-full w-full object-cover" loading="lazy" />
                ) : (
                    <div className="flex h-full items-center justify-center text-muted-foreground">
                        <ImageIcon className="h-10 w-10" />
                    </div>
                )}
                {assetLabel && (
                    <Badge variant="secondary" className="absolute left-3 top-3 text-[11px]">
                        {assetLabel}
                    </Badge>
                )}
            </div>
            <CardHeader className="gap-2">
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    {twin.category?.name && <span>{twin.category.name}</span>}
                    {twin.version && <span className="rounded-full border px-2 py-0.5 text-[11px]">v{twin.version}</span>}
                </div>
                <CardTitle className="text-base">{twin.title}</CardTitle>
                {twin.summary && <p className="text-sm text-muted-foreground line-clamp-2">{twin.summary}</p>}
            </CardHeader>
            <CardFooter className="flex flex-wrap gap-2">
                <Button variant="secondary" size="sm" onClick={onUse} disabled={isUsing}>
                    Use for RFQ
                </Button>
                <Button variant="ghost" size="sm" onClick={onView}>
                    View detail
                </Button>
            </CardFooter>
        </Card>
    );
}

interface AssetBundlePanelProps {
    title: string;
    description: string;
    icon: ReactNode;
    items: DigitalTwinLibraryListItem[];
    isLoading: boolean;
    emptyDescription: string;
    navigate: NavigateFunction;
    onUse: (twinId: number | string) => void;
    isUsing: boolean;
}

function AssetBundlePanel({
    title,
    description,
    icon,
    items,
    isLoading,
    emptyDescription,
    navigate,
    onUse,
    isUsing,
}: AssetBundlePanelProps) {
    return (
        <Card className="border-border/70">
            <CardHeader className="gap-2 pb-2">
                <div className="flex items-center gap-2 text-sm font-semibold">
                    {icon}
                    {title}
                </div>
                <p className="text-sm text-muted-foreground">{description}</p>
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <div className="space-y-3">
                        {Array.from({ length: 3 }).map((_, index) => (
                            <Skeleton key={index} className="h-20 w-full" />
                        ))}
                    </div>
                ) : items.length === 0 ? (
                    <EmptyState
                        className="border-none bg-transparent"
                        title="Nothing to show yet"
                        description={emptyDescription}
                        icon={<Layers className="h-10 w-10 text-muted-foreground" />}
                    />
                ) : (
                    <div className="space-y-4">
                        {items.map((item) => (
                            <div key={item.id} className="rounded-lg border border-border/60 p-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p className="font-semibold text-foreground">{item.title}</p>
                                        <p className="text-sm text-muted-foreground">{item.category?.name ?? 'Uncategorized'}</p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {(item.asset_types ?? []).slice(0, 3).map((type) => (
                                            <Badge key={`${item.id}-${type}`} variant="outline" className="text-[11px] uppercase">
                                                {type}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    <Button variant="secondary" size="sm" onClick={() => onUse(item.id)} disabled={isUsing}>
                                        Use for RFQ
                                    </Button>
                                    <Button variant="ghost" size="sm" onClick={() => navigate(`/app/library/digital-twins/${item.id}`)}>
                                        View detail
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function flattenCategories(nodes: DigitalTwinCategoryNode[] | undefined): Array<{ id: number; name: string }> {
    if (!nodes || nodes.length === 0) {
        return [];
    }

    return nodes.flatMap((node) => [
        { id: node.id, name: node.name },
        ...flattenCategories(node.children),
    ]);
}

function countWithAssetType(items: DigitalTwinLibraryListItem[], type: string): number {
    const normalized = type.toUpperCase();
    return items.filter((item) => hasAssetType(item, normalized)).length;
}

function selectWithAssetType(items: DigitalTwinLibraryListItem[], type: string, limit: number): DigitalTwinLibraryListItem[] {
    const normalized = type.toUpperCase();
    return items.filter((item) => hasAssetType(item, normalized)).slice(0, limit);
}

function hasAssetType(item: DigitalTwinLibraryListItem, type: string): boolean {
    const assetTypes = item.asset_types ?? [];
    const primaryType = item.primary_asset?.type;
    return assetTypes.some((assetType) => assetType?.toUpperCase() === type) || primaryType?.toUpperCase() === type;
}

function resolveMetaNumber(
    meta: CursorPaginationMeta | Record<string, unknown> | null | undefined,
    paths: Array<string[]> = [['total']],
    fallback: number,
): number {
    if (!meta || typeof meta !== 'object') {
        return fallback;
    }

    for (const path of paths) {
        let cursor: unknown = meta;
        let matched = true;

        for (const segment of path) {
            if (!cursor || typeof cursor !== 'object') {
                matched = false;
                break;
            }
            cursor = (cursor as Record<string, unknown>)[segment];
        }

        if (matched && typeof cursor === 'number') {
            return cursor;
        }
    }

    return fallback;
}
