import {
    Copy,
    Image as ImageIcon,
    Layers,
    RefreshCw,
    Search,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import {
    useLocation,
    useNavigate,
    type Location,
    type NavigateFunction,
} from 'react-router-dom';

import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { publishToast } from '@/components/ui/use-toast';
import { useDigitalTwins, useUseForRfq } from '@/hooks/api/digital-twins';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { cn } from '@/lib/utils';
import type {
    DigitalTwinCategoryNode,
    DigitalTwinLibraryListItem,
} from '@/sdk';

type SortOption = 'relevance' | 'updated_at' | 'title';
type AssetFilter = 'CAD' | 'STEP' | 'STL' | 'PDF' | 'IMAGE';

interface QueryFilterState {
    q?: string;
    categoryId?: number;
    tag?: string;
    hasAsset?: AssetFilter;
    cursor?: string;
    sort?: SortOption;
    updatedFrom?: string;
    updatedTo?: string;
}

const ASSET_FILTERS: AssetFilter[] = ['CAD', 'STEP', 'STL', 'PDF', 'IMAGE'];
const SEARCH_DEBOUNCE_MS = 400;

export function DigitalTwinLibraryPage() {
    const location = useLocation();
    return (
        <DigitalTwinLibraryPageInner key={location.key} location={location} />
    );
}

function DigitalTwinLibraryPageInner({ location }: { location: Location }) {
    const navigate = useNavigate();
    const filters = useMemo(
        () => parseQueryFilters(location.search),
        [location.search],
    );

    const [searchInput, setSearchInput] = useState(filters.q ?? '');
    const debouncedSearch = useDebouncedValue(searchInput, SEARCH_DEBOUNCE_MS);

    useEffect(() => {
        const normalized = debouncedSearch.trim();
        if (normalized === (filters.q ?? '')) {
            return;
        }

        updateQuery({ q: normalized || undefined }, location, navigate);
    }, [debouncedSearch, filters.q, location, navigate]);

    const { items, categories, meta, isLoading, isFetching, refetch } =
        useDigitalTwins({
            q: filters.q,
            categoryId: filters.categoryId,
            tag: filters.tag,
            hasAsset: filters.hasAsset,
            cursor: filters.cursor,
            sort: filters.sort,
            updatedFrom: filters.updatedFrom,
            updatedTo: filters.updatedTo,
            includeCategories: true,
        });

    const tagOptions = useMemo(() => {
        const unique = new Set<string>();
        items.forEach((item) =>
            (item.tags ?? []).forEach((tag) => unique.add(tag)),
        );
        return Array.from(unique).slice(0, 12);
    }, [items]);

    const useForRfq = useUseForRfq({
        onSuccess: (response) => {
            const payload = response.data;
            if (!payload) {
                publishToast({
                    variant: 'destructive',
                    title: 'Unable to launch RFQ',
                    description:
                        'The server response did not include the draft payload.',
                });
                return;
            }

            publishToast({
                variant: 'success',
                title: 'Draft ready',
                description:
                    'Opening the RFQ wizard with the selected digital twin.',
            });

            navigate('/app/rfqs/new', {
                state: { digitalTwinDraft: payload },
            });
        },
        onError: () => {
            publishToast({
                variant: 'destructive',
                title: 'Unable to use digital twin',
                description: 'Try again in a moment or open the detail page.',
            });
        },
    });

    const isEmpty = !isLoading && items.length === 0;

    return (
        <section className="flex flex-col gap-6">
            <Helmet>
                <title>Digital Twin Library</title>
            </Helmet>

            <header className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Digital Twin Library
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Browse curated production-ready twins and launch RFQs
                        with one click.
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    className="gap-2"
                    onClick={() => refetch()}
                    disabled={isFetching}
                >
                    <RefreshCw className="h-4 w-4" /> Refresh
                </Button>
            </header>

            <div className="grid gap-6 lg:grid-cols-[280px_1fr]">
                <aside className="rounded-xl border bg-card">
                    <div className="flex items-center justify-between border-b px-5 py-3">
                        <p className="text-sm font-medium">Filters</p>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearchInput('');
                                navigate(location.pathname, { replace: true });
                            }}
                            disabled={!location.search}
                        >
                            Clear
                        </Button>
                    </div>
                    <div className="space-y-6 px-5 py-6 text-sm">
                        <label className="flex flex-col gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Search
                            <div className="flex items-center gap-2 rounded-lg border px-3 py-2">
                                <Search className="h-4 w-4 text-muted-foreground" />
                                <Input
                                    value={searchInput}
                                    onChange={(event) =>
                                        setSearchInput(event.target.value)
                                    }
                                    placeholder="Search title, summary, specs"
                                    className="h-8 border-none p-0 text-sm shadow-none"
                                />
                            </div>
                        </label>

                        <div className="space-y-2">
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Asset types
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {ASSET_FILTERS.map((asset) => (
                                    <Badge
                                        key={asset}
                                        variant={
                                            filters.hasAsset === asset
                                                ? 'default'
                                                : 'outline'
                                        }
                                        className="cursor-pointer px-2 py-1 text-[11px]"
                                        onClick={() =>
                                            updateQuery(
                                                {
                                                    hasAsset:
                                                        filters.hasAsset ===
                                                        asset
                                                            ? undefined
                                                            : asset,
                                                },
                                                location,
                                                navigate,
                                            )
                                        }
                                    >
                                        {asset}
                                    </Badge>
                                ))}
                            </div>
                        </div>

                        {tagOptions.length > 0 && (
                            <div className="space-y-2">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Tags
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {tagOptions.map((tag) => (
                                        <Badge
                                            key={tag}
                                            variant={
                                                filters.tag === tag
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            className="cursor-pointer px-2 py-1 text-[11px]"
                                            onClick={() =>
                                                updateQuery(
                                                    {
                                                        tag:
                                                            filters.tag === tag
                                                                ? undefined
                                                                : tag,
                                                    },
                                                    location,
                                                    navigate,
                                                )
                                            }
                                        >
                                            {tag}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="space-y-2">
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Sort
                            </p>
                            <Select
                                value={filters.sort ?? 'relevance'}
                                onValueChange={(value) =>
                                    updateQuery(
                                        { sort: value as SortOption },
                                        location,
                                        navigate,
                                    )
                                }
                            >
                                <SelectTrigger className="h-9">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="relevance">
                                        Relevance
                                    </SelectItem>
                                    <SelectItem value="updated_at">
                                        Recently updated
                                    </SelectItem>
                                    <SelectItem value="title">
                                        Title A-Z
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Categories
                            </p>
                            <ScrollArea className="h-64 rounded-md border">
                                <div className="flex flex-col">
                                    <CategoryRow
                                        depth={0}
                                        node={{ id: 0, name: 'All categories' }}
                                        isActive={!filters.categoryId}
                                        onSelect={() =>
                                            updateQuery(
                                                { categoryId: undefined },
                                                location,
                                                navigate,
                                            )
                                        }
                                    />
                                    {categories.length === 0 && isLoading && (
                                        <Skeleton className="m-3 h-5 rounded" />
                                    )}
                                    {categories.map((category) => (
                                        <CategoryTree
                                            key={category.id}
                                            node={category}
                                            depth={0}
                                            selectedId={filters.categoryId}
                                            onSelect={(categoryId) =>
                                                updateQuery(
                                                    { categoryId },
                                                    location,
                                                    navigate,
                                                )
                                            }
                                        />
                                    ))}
                                </div>
                            </ScrollArea>
                        </div>

                        <div className="space-y-3">
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Updated date
                            </p>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <label className="flex flex-col gap-1 text-xs text-muted-foreground">
                                    From
                                    <Input
                                        type="date"
                                        value={filters.updatedFrom ?? ''}
                                        onChange={(event) =>
                                            updateQuery(
                                                {
                                                    updatedFrom:
                                                        event.target.value ||
                                                        undefined,
                                                },
                                                location,
                                                navigate,
                                            )
                                        }
                                    />
                                </label>
                                <label className="flex flex-col gap-1 text-xs text-muted-foreground">
                                    To
                                    <Input
                                        type="date"
                                        value={filters.updatedTo ?? ''}
                                        min={filters.updatedFrom ?? undefined}
                                        onChange={(event) =>
                                            updateQuery(
                                                {
                                                    updatedTo:
                                                        event.target.value ||
                                                        undefined,
                                                },
                                                location,
                                                navigate,
                                                {
                                                    resetCursor: event.target
                                                        .value
                                                        ? false
                                                        : undefined,
                                                },
                                            )
                                        }
                                    />
                                </label>
                            </div>
                        </div>
                    </div>
                </aside>

                <div className="space-y-4">
                    {isLoading ? (
                        <SkeletonGrid />
                    ) : isEmpty ? (
                        <EmptyState
                            title="No digital twins found"
                            description="Try relaxing your filters or updating the search keywords."
                            icon={
                                <Layers className="h-12 w-12 text-muted-foreground" />
                            }
                        />
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {items.map((item) => {
                                const detailPath = `/app/library/digital-twins/${item.id}`;
                                return (
                                    <DigitalTwinCard
                                        key={item.id}
                                        twin={item}
                                        onView={() => navigate(detailPath)}
                                        onUse={() =>
                                            useForRfq.mutate({
                                                digitalTwinId: item.id,
                                            })
                                        }
                                        onCopyLink={() =>
                                            copyTwinLink(detailPath)
                                        }
                                        isUsing={useForRfq.isPending}
                                    />
                                );
                            })}
                        </div>
                    )}

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!meta?.prevCursor}
                            onClick={() =>
                                updateQuery(
                                    { cursor: meta?.prevCursor ?? undefined },
                                    location,
                                    navigate,
                                    { resetCursor: false },
                                )
                            }
                        >
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!meta?.nextCursor}
                            onClick={() =>
                                updateQuery(
                                    { cursor: meta?.nextCursor ?? undefined },
                                    location,
                                    navigate,
                                    { resetCursor: false },
                                )
                            }
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>
        </section>
    );
}

function SkeletonGrid() {
    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {Array.from({ length: 6 }).map((_, index) => (
                <Skeleton key={index} className="h-60 rounded-xl" />
            ))}
        </div>
    );
}

interface DigitalTwinCardProps {
    twin: DigitalTwinLibraryListItem;
    onUse: () => void;
    onView: () => void;
    onCopyLink: () => void;
    isUsing?: boolean;
}

function DigitalTwinCard({
    twin,
    onUse,
    onView,
    onCopyLink,
    isUsing,
}: DigitalTwinCardProps) {
    const assetLabel = twin.primary_asset?.type ?? twin.asset_types?.[0];

    return (
        <Card data-testid="digital-twin-card" className="overflow-hidden">
            <div className="relative h-36 w-full bg-muted">
                {twin.thumbnail_url ? (
                    <img
                        src={twin.thumbnail_url}
                        alt={`${twin.title} thumbnail`}
                        className="h-full w-full object-cover"
                        loading="lazy"
                    />
                ) : (
                    <div className="flex h-full items-center justify-center text-muted-foreground">
                        <ImageIcon className="h-10 w-10" />
                    </div>
                )}
                {assetLabel && (
                    <Badge
                        variant="secondary"
                        className="absolute top-3 left-3 text-[11px]"
                    >
                        {assetLabel}
                    </Badge>
                )}
            </div>
            <CardHeader className="gap-2">
                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    {twin.category?.name && <span>{twin.category.name}</span>}
                    {twin.version && (
                        <span className="rounded-full border px-2 py-0.5 text-[11px]">
                            v{twin.version}
                        </span>
                    )}
                </div>
                <CardTitle className="line-clamp-2 text-base">
                    {twin.title}
                </CardTitle>
                {twin.summary && (
                    <p className="line-clamp-3 text-sm text-muted-foreground">
                        {twin.summary}
                    </p>
                )}
            </CardHeader>
            <CardContent className="space-y-2">
                <div className="flex flex-wrap gap-1">
                    {(twin.tags ?? []).slice(0, 5).map((tag) => (
                        <Badge
                            key={tag}
                            variant="outline"
                            className="text-[11px]"
                        >
                            {tag}
                        </Badge>
                    ))}
                </div>
            </CardContent>
            <CardFooter className="flex flex-wrap items-center gap-2 py-4">
                <Button
                    variant="secondary"
                    size="sm"
                    onClick={onUse}
                    disabled={isUsing}
                >
                    Use for RFQ
                </Button>
                <Button variant="ghost" size="sm" onClick={onView}>
                    View detail
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    className="ml-auto gap-2"
                    onClick={onCopyLink}
                >
                    <Copy className="h-3.5 w-3.5" /> Copy link
                </Button>
            </CardFooter>
        </Card>
    );
}

interface CategoryTreeProps {
    node: DigitalTwinCategoryNode;
    depth: number;
    selectedId?: number;
    onSelect: (id: number) => void;
}

function CategoryTree({
    node,
    depth,
    selectedId,
    onSelect,
}: CategoryTreeProps) {
    return (
        <div>
            <CategoryRow
                node={node}
                depth={depth}
                isActive={selectedId === node.id}
                onSelect={() => onSelect(node.id)}
            />
            {(node.children ?? []).map((child) => (
                <CategoryTree
                    key={child.id}
                    node={child}
                    depth={depth + 1}
                    selectedId={selectedId}
                    onSelect={onSelect}
                />
            ))}
        </div>
    );
}

interface CategoryRowProps {
    node: { id: number; name: string };
    depth: number;
    isActive: boolean;
    onSelect: () => void;
}

function CategoryRow({ node, depth, isActive, onSelect }: CategoryRowProps) {
    return (
        <button
            type="button"
            onClick={onSelect}
            className={cn(
                'flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-muted',
                isActive && 'bg-muted font-medium',
            )}
            style={{ paddingLeft: `${12 + depth * 12}px` }}
        >
            {node.name}
        </button>
    );
}

function parseQueryFilters(search: string): QueryFilterState {
    const params = new URLSearchParams(search);
    const categoryId = params.get('categoryId');
    const cursor = params.get('cursor');
    const sort = params.get('sort') as SortOption | null;

    return {
        q: params.get('q') ?? undefined,
        categoryId: categoryId ? Number(categoryId) : undefined,
        tag: params.get('tag') ?? undefined,
        hasAsset: (params.get('hasAsset') as AssetFilter | null) ?? undefined,
        cursor: cursor ?? undefined,
        sort: sort ?? undefined,
        updatedFrom: params.get('updatedFrom') ?? undefined,
        updatedTo: params.get('updatedTo') ?? undefined,
    };
}

function updateQuery(
    patch: Partial<QueryFilterState>,
    location: Location,
    navigate: NavigateFunction,
    options: { resetCursor?: boolean } = {},
) {
    const params = new URLSearchParams(location.search);
    Object.entries(patch).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') {
            params.delete(key);
            return;
        }
        params.set(key, String(value));
    });

    if (options.resetCursor !== false) {
        params.delete('cursor');
    }

    navigate(
        {
            pathname: location.pathname,
            search: params.toString() ? `?${params.toString()}` : undefined,
        },
        { replace: true },
    );
}

async function copyTwinLink(detailPath: string) {
    const origin = typeof window !== 'undefined' ? window.location.origin : '';
    const target = origin ? `${origin}${detailPath}` : detailPath;

    try {
        if (typeof navigator === 'undefined' || !navigator.clipboard) {
            throw new Error('Clipboard API unavailable');
        }
        await navigator.clipboard.writeText(target);
        publishToast({
            title: 'Link copied',
            description: 'The digital twin link is ready to share.',
        });
    } catch {
        publishToast({
            variant: 'destructive',
            title: 'Unable to copy link',
            description: 'Please copy it manually from the address bar.',
        });
    }
}
