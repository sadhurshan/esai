import {
    ArrowLeft,
    BadgeInfo,
    Download,
    FileText,
    Layers,
    Loader2,
    Package,
    Tags,
} from 'lucide-react';
import { Helmet } from 'react-helmet-async';
import { useNavigate, useParams } from 'react-router-dom';

import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { publishToast } from '@/components/ui/use-toast';
import { useDigitalTwin, useUseForRfq } from '@/hooks/api/digital-twins';
import type {
    DigitalTwinLibraryAsset,
    DigitalTwinLibraryDetail,
    DigitalTwinLibrarySpec,
} from '@/sdk';

export function DigitalTwinDetailPage() {
    const navigate = useNavigate();
    const params = useParams<{ id: string }>();
    const { digitalTwin, isLoading, isError, refetch } = useDigitalTwin(
        params.id,
    );

    const useForRfq = useUseForRfq({
        onSuccess: (response) => {
            const payload = response.data?.draft;
            if (!payload) {
                publishToast({
                    variant: 'destructive',
                    title: 'Unable to launch RFQ',
                    description:
                        'The draft payload was missing from the response.',
                });
                return;
            }

            publishToast({
                variant: 'success',
                title: 'Digital twin attached',
                description:
                    'Opening the RFQ wizard with the draft prefilled from this twin.',
            });

            navigate('/app/rfqs/new', {
                state: { digitalTwinDraft: payload },
            });
        },
        onError: () => {
            publishToast({
                variant: 'destructive',
                title: 'Unable to use digital twin',
                description:
                    'Try again shortly or download the assets manually.',
            });
        },
    });

    if (isLoading) {
        return <DetailSkeleton />;
    }

    if (isError) {
        return (
            <section className="flex flex-col gap-6">
                <Helmet>
                    <title>Digital Twin</title>
                </Helmet>
                <EmptyState
                    title="Unable to load digital twin"
                    description="Something went wrong while fetching the latest data."
                    icon={<Layers className="h-12 w-12" />}
                    ctaLabel="Retry"
                    ctaProps={{ onClick: () => refetch() }}
                />
            </section>
        );
    }

    if (!digitalTwin) {
        return (
            <section className="flex flex-col gap-6">
                <Helmet>
                    <title>Digital Twin</title>
                </Helmet>
                <EmptyState
                    title="Digital twin not found"
                    description="This digital twin may have been removed or you no longer have access to it."
                    icon={<Layers className="h-12 w-12" />}
                    ctaLabel="Back to library"
                    ctaProps={{
                        onClick: () => navigate('/app/library/digital-twins'),
                    }}
                />
            </section>
        );
    }

    const formattedUpdatedAt = formatDate(digitalTwin.updated_at);
    const tagList = digitalTwin.tags ?? [];

    return (
        <section className="flex flex-col gap-6">
            <Helmet>
                <title>{`${digitalTwin.title} • Digital Twin`}</title>
            </Helmet>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button
                    variant="ghost"
                    size="sm"
                    className="gap-2"
                    onClick={() => navigate(-1)}
                >
                    <ArrowLeft className="h-4 w-4" /> Back
                </Button>
                <div className="flex flex-wrap gap-2">
                    {digitalTwin.category?.name && (
                        <Badge variant="outline">
                            {digitalTwin.category.name}
                        </Badge>
                    )}
                    {digitalTwin.version && (
                        <Badge variant="secondary">
                            v{digitalTwin.version}
                        </Badge>
                    )}
                    <Badge className="bg-emerald-500 text-white hover:bg-emerald-500/90">
                        Published
                    </Badge>
                </div>
            </div>

            <header className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div className="space-y-4">
                    <div className="space-y-2">
                        <p className="text-sm text-muted-foreground">
                            Last updated {formattedUpdatedAt}
                        </p>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {digitalTwin.title}
                        </h1>
                        {digitalTwin.summary && (
                            <p className="text-base text-muted-foreground">
                                {digitalTwin.summary}
                            </p>
                        )}
                    </div>
                    {tagList.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {tagList.map((tag) => (
                                <Badge
                                    key={tag}
                                    variant="outline"
                                    className="text-xs"
                                >
                                    {tag}
                                </Badge>
                            ))}
                        </div>
                    )}
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            className="gap-2"
                            onClick={() =>
                                useForRfq.mutate({
                                    digitalTwinId: digitalTwin.id,
                                })
                            }
                            disabled={useForRfq.isPending}
                        >
                            {useForRfq.isPending ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Package className="h-4 w-4" />
                            )}
                            Use for RFQ
                        </Button>
                        {digitalTwin.primary_asset?.download_url && (
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="gap-2"
                            >
                                <a
                                    href={
                                        digitalTwin.primary_asset.download_url
                                    }
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Download className="h-4 w-4" /> Primary
                                    asset
                                </a>
                            </Button>
                        )}
                    </div>
                </div>
                <PreviewPanel digitalTwin={digitalTwin} />
            </header>

            <Tabs defaultValue="overview">
                <TabsList>
                    <TabsTrigger value="overview" className="gap-2">
                        <FileText className="h-4 w-4" /> Overview
                    </TabsTrigger>
                    <TabsTrigger value="assets" className="gap-2">
                        <Download className="h-4 w-4" /> Assets
                    </TabsTrigger>
                    <TabsTrigger value="notes" className="gap-2">
                        <BadgeInfo className="h-4 w-4" /> Notes
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="overview">
                    <Card>
                        <CardHeader>
                            <CardTitle>Key specifications</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <SpecTable specs={digitalTwin.specs} />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="assets">
                    <Card>
                        <CardHeader>
                            <CardTitle>Files & assets</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <AssetList assets={digitalTwin.assets} />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="notes">
                    <Card>
                        <CardHeader>
                            <CardTitle>Revision notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {digitalTwin.revision_notes ? (
                                <p className="text-sm whitespace-pre-line text-muted-foreground">
                                    {digitalTwin.revision_notes}
                                </p>
                            ) : (
                                <EmptyState
                                    title="No notes provided"
                                    description="This digital twin does not have revision notes yet."
                                    icon={<BadgeInfo className="h-8 w-8" />}
                                />
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </section>
    );
}

interface PreviewPanelProps {
    digitalTwin: DigitalTwinLibraryDetail;
}

function PreviewPanel({ digitalTwin }: PreviewPanelProps) {
    const thumbnail =
        digitalTwin.thumbnail_url ?? digitalTwin.primary_asset?.download_url;

    if (!thumbnail) {
        return (
            <Card className="h-full">
                <CardContent className="flex h-full flex-col items-center justify-center gap-3 text-center">
                    <Layers className="h-12 w-12 text-muted-foreground" />
                    <p className="text-sm text-muted-foreground">
                        No preview available
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="h-full overflow-hidden">
            <div className="relative h-full min-h-[220px] bg-muted">
                <img
                    src={thumbnail}
                    alt={`${digitalTwin.title} preview`}
                    className="h-full w-full object-cover"
                    loading="lazy"
                />
            </div>
        </Card>
    );
}

interface SpecTableProps {
    specs: DigitalTwinLibrarySpec[];
}

function SpecTable({ specs }: SpecTableProps) {
    if (!specs?.length) {
        return (
            <EmptyState
                title="No specifications"
                description="Published digital twins usually include tolerances, materials, or other key specs."
                icon={<Tags className="h-8 w-8" />}
            />
        );
    }

    return (
        <div className="divide-y rounded-lg border">
            {specs.map((spec) => (
                <div
                    key={spec.id}
                    className="grid gap-3 p-4 text-sm md:grid-cols-[220px_1fr]"
                >
                    <div className="font-medium">{spec.name}</div>
                    <div className="text-muted-foreground">
                        {spec.value ?? '—'}
                        {spec.uom && (
                            <span className="ml-2 text-xs text-muted-foreground/80 uppercase">
                                {spec.uom}
                            </span>
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
}

interface AssetListProps {
    assets: DigitalTwinLibraryAsset[];
}

function AssetList({ assets }: AssetListProps) {
    if (!assets?.length) {
        return (
            <EmptyState
                title="No assets"
                description="Asset uploads from the super admin will appear here once available."
                icon={<FileText className="h-8 w-8" />}
            />
        );
    }

    return (
        <ScrollArea className="max-h-[420px]">
            <div className="divide-y">
                {assets.map((asset) => (
                    <AssetRow key={asset.id} asset={asset} />
                ))}
            </div>
        </ScrollArea>
    );
}

interface AssetRowProps {
    asset: DigitalTwinLibraryAsset;
}

function AssetRow({ asset }: AssetRowProps) {
    const label = asset.type ?? 'FILE';
    const sizeLabel = formatFileSize(asset.size_bytes);

    return (
        <div className="flex flex-wrap items-center gap-3 px-2 py-3 text-sm">
            <div className="flex min-w-0 flex-1 items-center gap-3">
                <Badge variant="secondary" className="uppercase">
                    {label}
                </Badge>
                <div className="min-w-0">
                    <p className="truncate font-medium">{asset.filename}</p>
                    <p className="text-xs text-muted-foreground">{sizeLabel}</p>
                </div>
            </div>
            {asset.download_url && (
                <Button asChild variant="ghost" size="sm" className="gap-1">
                    <a
                        href={asset.download_url}
                        target="_blank"
                        rel="noreferrer"
                    >
                        <Download className="h-4 w-4" /> Download
                    </a>
                </Button>
            )}
        </div>
    );
}

function DetailSkeleton() {
    return (
        <section className="flex flex-col gap-6">
            <Helmet>
                <title>Digital Twin</title>
            </Helmet>
            <div className="flex gap-3">
                <Skeleton className="h-10 w-32" />
                <Skeleton className="h-10 w-24" />
            </div>
            <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <Skeleton className="h-48 w-full" />
                <Skeleton className="h-48 w-full" />
            </div>
            <Skeleton className="h-12 w-64" />
            <Skeleton className="h-96 w-full" />
        </section>
    );
}

function formatFileSize(bytes?: number | null): string {
    if (!bytes || bytes <= 0) {
        return 'Unknown size';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const exponent = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );
    const value = bytes / Math.pow(1024, exponent);
    return `${value.toFixed(1)} ${units[exponent]}`;
}

function formatDate(value?: string | null): string {
    if (!value) {
        return 'recently';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return 'recently';
    }

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        parsed,
    );
}
