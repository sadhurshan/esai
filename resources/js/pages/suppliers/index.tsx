import { EmptyState, FilterBar, Pagination } from '@/components/app';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { home, rfq, suppliers as supplierRoutes } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Building2, Clock, Factory, MapPin } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

import { useSuppliers } from '@/hooks/api/useSuppliers';
import type { Supplier } from '@/types/sourcing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Home', href: home().url },
    { title: 'Supplier Directory', href: supplierRoutes.index().url },
];

export default function SuppliersIndex() {
    const [search, setSearch] = useState('');
    const [params, setParams] = useState({
        method: '',
        material: '',
        region: '',
        sort: 'rating' as 'rating' | 'avg_response_hours',
        page: 1,
        per_page: 9,
    });

    const { data, isLoading, isError, error, refetch } = useSuppliers({
        q: search || undefined,
        method: params.method || undefined,
        material: params.material || undefined,
        region: params.region || undefined,
        sort: params.sort,
        page: params.page,
        per_page: params.per_page,
    });

    const suppliers: Supplier[] = data?.items ?? [];
    const meta = data?.meta ?? null;

    const handleFilterChange = useCallback((id: string, value: string) => {
        setParams((previous) => ({
            ...previous,
            [id]: value,
            page: 1,
        }));
    }, []);

    const resetFilters = useCallback(() => {
        setSearch('');
        setParams((previous) => ({
            ...previous,
            method: '',
            material: '',
            region: '',
            page: 1,
        }));
    }, []);

    const emptyState = useMemo(() => {
        if (isError) {
            return (
                <EmptyState
                    title="Unable to load suppliers"
                    description={error?.message ?? 'Please try again.'}
                    ctaLabel="Retry"
                    ctaProps={{ onClick: () => refetch() }}
                />
            );
        }

        return (
            <EmptyState
                title="No suppliers match your filters"
                description="Adjust manufacturing methods, materials, or regions to expand your supplier pool."
                ctaLabel="Clear filters"
                ctaProps={{ onClick: resetFilters }}
            />
        );
    }, [error?.message, isError, refetch, resetFilters]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Supplier Directory" />
            <div className="flex flex-1 flex-col gap-6 px-4 py-6">
                <section className="space-y-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold text-foreground">
                            Supplier Directory
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Discover verified manufacturers with the certifications, lead times, and materials coverage needed for your RFQs.
                        </p>
                    </div>

                    <FilterBar
                        filters={[
                            {
                                id: 'method',
                                label: 'Manufacturing Method',
                                options: [
                                    { label: 'All Methods', value: '' },
                                    { label: 'CNC Machining', value: 'cnc_machining' },
                                    { label: 'Sheet Metal', value: 'sheet_metal' },
                                    { label: 'Metal AM', value: 'metal_am' },
                                    { label: 'Investment Casting', value: 'investment_casting' },
                                ],
                                value: params.method,
                            },
                            {
                                id: 'material',
                                label: 'Material',
                                options: [
                                    { label: 'All Materials', value: '' },
                                    { label: 'Aluminum', value: 'aluminum' },
                                    { label: 'Titanium', value: 'titanium' },
                                    { label: 'Stainless Steel', value: 'stainless_steel' },
                                    { label: 'Inconel', value: 'inconel' },
                                ],
                                value: params.material,
                            },
                            {
                                id: 'region',
                                label: 'Region',
                                options: [
                                    { label: 'All Regions', value: '' },
                                    { label: 'United States', value: 'usa' },
                                    { label: 'Canada', value: 'canada' },
                                    { label: 'Europe', value: 'europe' },
                                    { label: 'Asia Pacific', value: 'apac' },
                                ],
                                value: params.region,
                            },
                            {
                                id: 'sort',
                                label: 'Sort By',
                                options: [
                                    { label: 'Highest Rating', value: 'rating' },
                                    { label: 'Fastest Response', value: 'avg_response_hours' },
                                ],
                                value: params.sort,
                            },
                        ]}
                        searchPlaceholder="Search suppliers"
                        searchValue={search}
                        onSearchChange={(value) => {
                            setSearch(value);
                            setParams((previous) => ({ ...previous, page: 1 }));
                        }}
                        onFilterChange={handleFilterChange}
                        onReset={resetFilters}
                        isLoading={isLoading}
                    />
                </section>

                {isLoading ? (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {Array.from({ length: params.per_page }).map((_, index) => (
                            <Card key={`skeleton-${index}`} className="flex h-full flex-col border-muted/70">
                                <CardHeader>
                                    <Skeleton className="h-5 w-1/2" />
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <Skeleton className="h-4 w-3/4" />
                                    <Skeleton className="h-4 w-5/6" />
                                    <Skeleton className="h-4 w-2/3" />
                                    <Skeleton className="h-4 w-1/2" />
                                </CardContent>
                                <CardFooter className="flex gap-2">
                                    <Skeleton className="h-9 w-full" />
                                    <Skeleton className="h-9 w-full" />
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                ) : suppliers.length === 0 ? (
                    emptyState
                ) : (
                    <>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {suppliers.map((supplier) => (
                                <Card key={supplier.id} className="flex h-full flex-col border-muted/70">
                                    <CardHeader>
                                        <CardTitle className="flex items-start justify-between text-lg">
                                            <span>{supplier.name}</span>
                                            <Badge variant="secondary" className="text-xs">
                                                Rating {supplier.rating.toFixed(1)} / 5.0
                                            </Badge>
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3 text-sm">
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <Factory className="size-4" aria-hidden />
                                            <span>{supplier.capabilities.join(', ') || 'Capabilities coming soon'}</span>
                                        </div>
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <Building2 className="size-4" aria-hidden />
                                            <span>{supplier.materials.join(', ') || 'Materials coming soon'}</span>
                                        </div>
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <MapPin className="size-4" aria-hidden />
                                            <span>{supplier.locationRegion || 'Region not specified'}</span>
                                        </div>
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <Clock className="size-4" aria-hidden />
                                            <span>
                                                MOQ {supplier.minimumOrderQuantity ?? '—'} • Avg response{' '}
                                                {supplier.averageResponseHours ?? '—'} hrs
                                            </span>
                                        </div>
                                    </CardContent>
                                    <CardFooter className="flex gap-2">
                                        <Button asChild className="flex-1">
                                            <Link href={rfq.new()}>Send Quote</Link>
                                        </Button>
                                        <Button variant="outline" className="flex-1" type="button">
                                            View Profile
                                        </Button>
                                    </CardFooter>
                                </Card>
                            ))}
                        </div>

                        <Pagination
                            meta={meta}
                            onPageChange={(page) => setParams((previous) => ({ ...previous, page }))}
                            isLoading={isLoading}
                        />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
