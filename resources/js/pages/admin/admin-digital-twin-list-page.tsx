import { formatDistanceToNow } from 'date-fns';
import {
    FolderTree,
    MoreHorizontal,
    PlusCircle,
    RefreshCcw,
    Search,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';

import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import {
    useAdminDigitalTwinCategories,
    useAdminDigitalTwins,
    useArchiveAdminDigitalTwin,
    useDeleteAdminDigitalTwin,
    usePublishAdminDigitalTwin,
} from '@/hooks/api/digital-twins';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import type {
    AdminDigitalTwinCategoryNode,
    AdminDigitalTwinListItem,
} from '@/sdk';
import { HttpError } from '@/sdk';

const STATUS_OPTIONS: Array<{
    value: AdminDigitalTwinStatusFilter;
    label: string;
}> = [
    { value: 'all', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' },
];

type AdminDigitalTwinStatusFilter = 'all' | 'draft' | 'published' | 'archived';

export function AdminDigitalTwinListPage() {
    const navigate = useNavigate();
    const { isAdmin } = useAuth();
    const [searchInput, setSearchInput] = useState('');
    const [statusFilter, setStatusFilter] =
        useState<AdminDigitalTwinStatusFilter>('all');
    const [categoryFilter, setCategoryFilter] = useState<string>('all');
    const [cursor, setCursor] = useState<string | undefined>(undefined);
    const [pendingDelete, setPendingDelete] =
        useState<AdminDigitalTwinListItem | null>(null);

    const debouncedSearch = useDebouncedValue(searchInput, 400);
    const categoryId =
        categoryFilter !== 'all' ? Number(categoryFilter) : undefined;

    useEffect(() => {
        setCursor(undefined);
    }, [debouncedSearch, statusFilter, categoryId]);

    const { items, meta, isLoading, isFetching, error, refetch } =
        useAdminDigitalTwins({
            cursor,
            q: debouncedSearch || undefined,
            status: statusFilter !== 'all' ? statusFilter : undefined,
            categoryId: Number.isFinite(categoryId) ? categoryId : undefined,
        });

    const { categories } = useAdminDigitalTwinCategories();
    const categoryOptions = useMemo(
        () => flattenCategories(categories),
        [categories],
    );

    const publishTwin = usePublishAdminDigitalTwin();
    const archiveTwin = useArchiveAdminDigitalTwin();
    const deleteTwin = useDeleteAdminDigitalTwin();

    const isRefreshing = isFetching && !isLoading;
    const isEmpty = !isLoading && items.length === 0 && !error;

    if (!isAdmin) {
        return <AccessDeniedPage />;
    }

    const handleResetFilters = () => {
        setSearchInput('');
        setStatusFilter('all');
        setCategoryFilter('all');
        setCursor(undefined);
    };

    const handleView = (twin: AdminDigitalTwinListItem) => {
        navigate(`/app/admin/digital-twins/${twin.id}`);
    };

    const handlePublish = async (twin: AdminDigitalTwinListItem) => {
        try {
            await publishTwin.mutateAsync({ digitalTwinId: twin.id });
            publishToast({
                title: 'Digital twin published',
                description: `${twin.title} is now visible in the buyer library.`,
                variant: 'success',
            });
        } catch (err) {
            publishToast({
                title: 'Unable to publish digital twin',
                description: resolveErrorMessage(err),
                variant: 'destructive',
            });
        }
    };

    const handleArchive = async (twin: AdminDigitalTwinListItem) => {
        try {
            await archiveTwin.mutateAsync({ digitalTwinId: twin.id });
            publishToast({
                title: 'Digital twin archived',
                description: `${twin.title} is hidden from buyers.`,
            });
        } catch (err) {
            publishToast({
                title: 'Unable to archive digital twin',
                description: resolveErrorMessage(err),
                variant: 'destructive',
            });
        }
    };

    const handleDelete = async () => {
        if (!pendingDelete) {
            return;
        }

        try {
            await deleteTwin.mutateAsync({ digitalTwinId: pendingDelete.id });
            publishToast({
                title: 'Digital twin deleted',
                description: `${pendingDelete.title} has been removed.`,
            });
        } catch (err) {
            publishToast({
                title: 'Unable to delete digital twin',
                description: resolveErrorMessage(err),
                variant: 'destructive',
            });
        } finally {
            setPendingDelete(null);
        }
    };

    return (
        <div className="space-y-6">
            <Heading
                title="Digital twin library"
                description="Curate published assets buyers can reuse across RFQs."
                action={
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() => refetch()}
                            disabled={isRefreshing}
                        >
                            <RefreshCcw className="mr-2 h-4 w-4" /> Refresh
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                navigate('/app/admin/digital-twins/categories')
                            }
                        >
                            <FolderTree className="mr-2 h-4 w-4" /> Manage
                            categories
                        </Button>
                        <Button
                            onClick={() =>
                                navigate('/app/admin/digital-twins/new')
                            }
                        >
                            <PlusCircle className="mr-2 h-4 w-4" /> New digital
                            twin
                        </Button>
                    </div>
                }
            />

            <Card>
                <CardContent className="space-y-4 pt-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="space-y-2">
                            <Label htmlFor="digital-twin-search">Search</Label>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="digital-twin-search"
                                    placeholder="Title, summary, tag"
                                    value={searchInput}
                                    onChange={(event) =>
                                        setSearchInput(
                                            event.currentTarget.value,
                                        )
                                    }
                                    className="pl-9"
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="digital-twin-status-filter">
                                Status
                            </Label>
                            <Select
                                value={statusFilter}
                                onValueChange={(
                                    value: AdminDigitalTwinStatusFilter,
                                ) => setStatusFilter(value)}
                            >
                                <SelectTrigger id="digital-twin-status-filter">
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {STATUS_OPTIONS.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="digital-twin-category-filter">
                                Category
                            </Label>
                            <Select
                                value={categoryFilter}
                                onValueChange={setCategoryFilter}
                            >
                                <SelectTrigger id="digital-twin-category-filter">
                                    <SelectValue placeholder="All categories" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All categories
                                    </SelectItem>
                                    {categoryOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleResetFilters}
                        >
                            Reset filters
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {error ? (
                <Alert variant="destructive">
                    <AlertTitle>Unable to load digital twins</AlertTitle>
                    <AlertDescription>
                        {resolveErrorMessage(error)}
                    </AlertDescription>
                </Alert>
            ) : null}

            <Card>
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[640px] border-collapse">
                            <thead className="border-b bg-muted/40 text-left text-sm font-medium text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3">Title</th>
                                    <th className="px-4 py-3">Category</th>
                                    <th className="px-4 py-3">Version</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Updated</th>
                                    <th className="px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {isLoading ? (
                                    <SkeletonRows />
                                ) : isEmpty ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-8 text-center text-sm text-muted-foreground"
                                        >
                                            No digital twins match your filters.
                                        </td>
                                    </tr>
                                ) : (
                                    items.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="border-b last:border-b-0"
                                        >
                                            <td className="px-4 py-4 align-top">
                                                <div className="font-medium text-foreground">
                                                    {item.title}
                                                </div>
                                                {item.summary ? (
                                                    <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                                        {item.summary}
                                                    </p>
                                                ) : null}
                                                {item.tags &&
                                                item.tags.length > 0 ? (
                                                    <div className="mt-2 flex flex-wrap gap-1">
                                                        {item.tags
                                                            .slice(0, 3)
                                                            .map((tag) => (
                                                                <Badge
                                                                    key={tag}
                                                                    variant="outline"
                                                                >
                                                                    {tag}
                                                                </Badge>
                                                            ))}
                                                        {item.tags.length >
                                                        3 ? (
                                                            <Badge variant="outline">
                                                                +
                                                                {item.tags
                                                                    .length - 3}
                                                            </Badge>
                                                        ) : null}
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-4 align-top text-sm text-muted-foreground">
                                                {item.category?.name ??
                                                    'Uncategorised'}
                                            </td>
                                            <td className="px-4 py-4 align-top text-sm text-muted-foreground">
                                                {item.version ?? '—'}
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <StatusBadge
                                                    status={item.status}
                                                />
                                            </td>
                                            <td className="px-4 py-4 align-top text-sm text-muted-foreground">
                                                {item.updated_at
                                                    ? formatDistanceToNow(
                                                          new Date(
                                                              item.updated_at,
                                                          ),
                                                          { addSuffix: true },
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-4 text-right align-top">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label="Digital twin actions"
                                                        >
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                        className="w-48"
                                                    >
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                handleView(item)
                                                            }
                                                        >
                                                            View details
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                item.status ===
                                                                'published'
                                                                    ? handleArchive(
                                                                          item,
                                                                      )
                                                                    : handlePublish(
                                                                          item,
                                                                      )
                                                            }
                                                        >
                                                            {item.status ===
                                                            'published'
                                                                ? 'Archive'
                                                                : 'Publish'}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            className="text-destructive focus:text-destructive"
                                                            onClick={() =>
                                                                setPendingDelete(
                                                                    item,
                                                                )
                                                            }
                                                        >
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center justify-between border-t px-4 py-3 text-sm text-muted-foreground">
                        <span>
                            {items.length} result{items.length === 1 ? '' : 's'}{' '}
                            · Page size {meta?.perPage ?? '—'}
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!meta?.prevCursor || isFetching}
                                onClick={() =>
                                    setCursor(meta?.prevCursor ?? undefined)
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!meta?.nextCursor || isFetching}
                                onClick={() =>
                                    setCursor(meta?.nextCursor ?? undefined)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <ConfirmDialog
                open={Boolean(pendingDelete)}
                onOpenChange={(open) =>
                    setPendingDelete(open ? pendingDelete : null)
                }
                title="Delete digital twin?"
                description={`Deleting ${pendingDelete?.title ?? 'this digital twin'} removes all associated specs and assets.`}
                confirmLabel="Delete"
                confirmVariant="destructive"
                isProcessing={deleteTwin.isPending}
                onConfirm={handleDelete}
            />
        </div>
    );
}

function StatusBadge({ status }: { status?: string | null }) {
    if (!status) {
        return <Badge variant="outline">Unknown</Badge>;
    }

    switch (status) {
        case 'draft':
            return <Badge variant="secondary">Draft</Badge>;
        case 'published':
            return <Badge>Published</Badge>;
        case 'archived':
            return <Badge variant="outline">Archived</Badge>;
        default:
            return <Badge variant="outline">{status}</Badge>;
    }
}

function SkeletonRows() {
    return (
        <>
            {Array.from({ length: 5 }).map((_, index) => (
                <tr key={index} className="border-b last:border-b-0">
                    <td colSpan={6} className="px-4 py-4">
                        <Skeleton className="h-12 w-full" />
                    </td>
                </tr>
            ))}
        </>
    );
}

function flattenCategories(
    nodes: AdminDigitalTwinCategoryNode[],
    depth = 0,
): Array<{ value: number; label: string }> {
    const prefix = depth > 0 ? `${'-- '.repeat(depth)}` : '';

    return nodes.flatMap((node) => {
        const current = { value: node.id, label: `${prefix}${node.name}` };
        const children = node.children
            ? flattenCategories(node.children, depth + 1)
            : [];
        return [current, ...children];
    });
}

function resolveErrorMessage(error: unknown): string {
    if (error instanceof HttpError) {
        const body = (error.body ?? {}) as { message?: string };
        return body.message ?? error.message ?? 'Unexpected API error.';
    }

    if (error instanceof Error) {
        return error.message;
    }

    if (typeof error === 'string') {
        return error;
    }

    return 'Something went wrong. Please try again.';
}
