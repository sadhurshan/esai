import type { ReactNode } from 'react';

import { DataTable, type DataTableColumn } from '@/components/data-table';

export interface MetricsTableProps<T> {
    rows: T[];
    columns: DataTableColumn<T>[];
    isLoading?: boolean;
    skeletonRowCount?: number;
    emptyState?: ReactNode;
    sortKey?: string;
    sortDirection?: 'asc' | 'desc';
    onSortChange?: (columnKey: string) => void;
    'data-testid'?: string;
}

/**
 * Thin wrapper around the shared DataTable that keeps analytics pages consistent.
 */
export function MetricsTable<T>({
    rows,
    columns,
    isLoading,
    skeletonRowCount,
    emptyState,
    sortKey,
    sortDirection,
    onSortChange,
    'data-testid': dataTestId,
}: MetricsTableProps<T>) {
    return (
        <DataTable
            data={rows}
            columns={columns}
            isLoading={isLoading}
            skeletonRowCount={skeletonRowCount}
            emptyState={emptyState}
            sortKey={sortKey}
            sortDirection={sortDirection}
            onSortChange={onSortChange}
            data-testid={dataTestId}
        />
    );
}
