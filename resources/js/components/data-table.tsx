import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { ArrowUpDown } from 'lucide-react';
import { Fragment, type ReactNode } from 'react';

type SortDirection = 'asc' | 'desc';

export interface DataTableColumn<T> {
    key: string;
    title: string;
    render?: (row: T) => ReactNode;
    align?: 'left' | 'center' | 'right';
    width?: string;
}

interface DataTableProps<T> {
    data: T[];
    columns: DataTableColumn<T>[];
    isLoading?: boolean;
    skeletonRowCount?: number;
    emptyState?: ReactNode;
    sortKey?: string;
    sortDirection?: SortDirection;
    onSortChange?: (columnKey: string) => void;
    'data-testid'?: string;
}

export function DataTable<T>({
    data,
    columns,
    isLoading = false,
    skeletonRowCount = 3,
    emptyState,
    sortKey,
    sortDirection,
    onSortChange,
    'data-testid': dataTestId,
}: DataTableProps<T>) {
    const renderCell = (row: T, column: DataTableColumn<T>) => {
        if (column.render) {
            return column.render(row);
        }

        const value = (row as Record<string, unknown>)[column.key];

        if (value === null || value === undefined) {
            return '—';
        }

        if (['string', 'number', 'boolean'].includes(typeof value)) {
            return value as ReactNode;
        }

        return String(value);
    };

    const handleSort = (columnKey: string) => {
        if (!onSortChange) {
            return;
        }
        onSortChange(columnKey);
    };

    return (
        <div
            data-testid={dataTestId}
            className="overflow-hidden rounded-xl border border-sidebar-border/60 bg-background/60"
        >
            <div className="relative w-full overflow-x-auto">
                <table className="w-full min-w-[600px] table-fixed border-separate border-spacing-0 text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            {columns.map((column) => {
                                const isSortable = Boolean(onSortChange);
                                const isActive = sortKey === column.key;
                                const nextDirection =
                                    isActive && sortDirection === 'asc'
                                        ? 'desc'
                                        : 'asc';

                                return (
                                    <th
                                        key={column.key}
                                        scope="col"
                                        style={{ width: column.width }}
                                        className={cn(
                                            'sticky top-0 border-b border-sidebar-border/60 px-4 py-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase',
                                            column.align === 'center' &&
                                                'text-center',
                                            column.align === 'right' &&
                                                'text-right',
                                        )}
                                    >
                                        {isSortable ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleSort(column.key)
                                                }
                                                className={cn(
                                                    'flex w-full items-center gap-2 text-left tracking-wide uppercase',
                                                    column.align === 'center' &&
                                                        'justify-center text-center',
                                                    column.align === 'right' &&
                                                        'justify-end text-right',
                                                )}
                                                aria-label={`Sort by ${column.title}`}
                                                aria-pressed={isActive}
                                                data-direction={
                                                    isActive
                                                        ? sortDirection
                                                        : undefined
                                                }
                                            >
                                                <span>{column.title}</span>
                                                <ArrowUpDown
                                                    className={cn(
                                                        'size-3',
                                                        isActive &&
                                                            sortDirection ===
                                                                'desc' &&
                                                            'rotate-180',
                                                    )}
                                                />
                                                <span className="sr-only">
                                                    {isActive
                                                        ? `Sorted ${sortDirection}`
                                                        : `Activate to sort ${nextDirection}`}
                                                </span>
                                            </button>
                                        ) : (
                                            <span>{column.title}</span>
                                        )}
                                    </th>
                                );
                            })}
                        </tr>
                    </thead>
                    <tbody>
                        {isLoading ? (
                            <Fragment>
                                {Array.from({ length: skeletonRowCount }).map(
                                    (_, index) => (
                                        <tr
                                            key={`skeleton-${index}`}
                                            className="border-b border-sidebar-border/40 last:border-b-0"
                                        >
                                            {columns.map((column) => (
                                                <td
                                                    key={column.key}
                                                    className={cn(
                                                        'px-4 py-3 align-middle',
                                                        column.align ===
                                                            'center' &&
                                                            'text-center',
                                                        column.align ===
                                                            'right' &&
                                                            'text-right',
                                                    )}
                                                >
                                                    <Skeleton className="h-4 w-full" />
                                                </td>
                                            ))}
                                        </tr>
                                    ),
                                )}
                            </Fragment>
                        ) : data.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-4 py-10 text-center text-muted-foreground"
                                >
                                    {emptyState ?? 'No records to display.'}
                                </td>
                            </tr>
                        ) : (
                            data.map((row, rowIndex) => (
                                <tr
                                    key={rowIndex}
                                    className="border-b border-sidebar-border/40 last:border-b-0 hover:bg-muted/40"
                                >
                                    {columns.map((column) => (
                                        <td
                                            key={column.key}
                                            className={cn(
                                                'px-4 py-3 align-middle text-sm text-foreground',
                                                column.align === 'center' &&
                                                    'text-center',
                                                column.align === 'right' &&
                                                    'text-right',
                                            )}
                                        >
                                            {renderCell(row, column)}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
