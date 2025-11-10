import { EmptyState, FilterBar, Pagination } from '@/components/app';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { home } from '@/routes';
import rfq from '@/routes/rfq';
import suppliers from '@/routes/suppliers';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Building2, Clock, Factory, MapPin } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

import { useSuppliers, type SupplierQueryParams } from '@/hooks/api/useSuppliers';
import type { Supplier } from '@/types/sourcing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Home', href: home().url },
    { title: 'Supplier Directory', href: suppliers.index().url },
];

export default function SuppliersIndex() {
    const [search, setSearch] = useState('');
    const [params, setParams] = useState({
        capability: '',
        material: '',
        industry: '',
        cert: '',
        location: '',
        sort: 'match_score' as SupplierQueryParams['sort'],
        page: 1,
        per_page: 9,
    });

    const { data, isLoading, isError, error, refetch } = useSuppliers({
        q: search || undefined,
        capability: params.capability || undefined,
        material: params.material || undefined,
        industry: params.industry || undefined,
        cert: params.cert || undefined,
        location: params.location || undefined,
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
            capability: '',
            material: '',
            industry: '',
            cert: '',
            location: '',
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
                                id: 'capability',
                                label: 'Manufacturing Method',
                                options: [
                                    { label: 'All Methods', value: '' },
                                    { label: 'CNC Machining', value: 'cnc_machining' },
                                    { label: 'Sheet Metal', value: 'sheet_metal' },
                                    { label: 'Metal AM', value: 'metal_am' },
                                    { label: 'Investment Casting', value: 'investment_casting' },
                                ],
                                value: params.capability,
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
                                id: 'industry',
                                label: 'Industry Focus',
                                options: [
                                    { label: 'All Industries', value: '' },
                                    { label: 'Aerospace', value: 'aerospace' },
                                    { label: 'Automotive', value: 'automotive' },
                                    { label: 'Medical Devices', value: 'medical' },
                                    { label: 'Industrial Equipment', value: 'industrial' },
                                ],
                                value: params.industry,
                            },
                            {
                                id: 'cert',
                                label: 'Certification',
                                options: [
                                    { label: 'All Certifications', value: '' },
                                    { label: 'ISO 9001', value: 'iso9001' },
                                    { label: 'AS9100', value: 'as9100' },
                                    { label: 'ITAR', value: 'itar' },
                                    { label: 'ISO 14001', value: 'iso14001' },
                                ],
                                value: params.cert,
                            },
                            {
                                id: 'location',
                                label: 'Region',
                                options: [
                                    { label: 'All Regions', value: '' },
                                    { label: 'North America', value: 'na' },
                                    { label: 'Europe', value: 'eu' },
                                    { label: 'Asia Pacific', value: 'apac' },
                                    { label: 'Latin America', value: 'latam' },
                                ],
                                value: params.location,
                            },
                            {
                                id: 'sort',
                                label: 'Sort By',
                                options: [
                                    { label: 'Smart Match', value: 'match_score' },
                                    { label: 'Highest Rating', value: 'rating' },
                                    { label: 'Quickest Lead Time', value: 'lead_time' },
                                    { label: 'Nearest Distance', value: 'distance' },
                                    { label: 'Lowest Price Band', value: 'price_band' },
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
                            {suppliers.map((supplier) => {
                                const rating = Number.isFinite(supplier.ratingAvg)
                                    ? supplier.ratingAvg
                                    : 0;
                                const methods = Array.isArray(supplier.capabilities?.methods)
                                    ? supplier.capabilities.methods
                                    : [];
                                const materials = Array.isArray(supplier.capabilities?.materials)
                                    ? supplier.capabilities.materials
                                    : [];
                                const location = [supplier.address.city, supplier.address.country]
                                    .filter(Boolean)
                                    .join(', ');

                                return (
                                    <Card key={supplier.id} className="flex h-full flex-col border-muted/70">
                                    <CardHeader>
                                        <CardTitle className="flex items-start justify-between text-lg">
                                            <span>{supplier.name}</span>
                                            <Badge variant="secondary" className="text-xs">
                                                    Rating {rating.toFixed(1)} / 5.0
                                            </Badge>
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3 text-sm">
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <Factory className="size-4" aria-hidden />
                                                <span>
                                                    {methods.length > 0
                                                        ? methods.join(', ')
                                                        : 'Capabilities coming soon'}
                                                </span>
                                        </div>
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <Building2 className="size-4" aria-hidden />
                                                <span>
                                                    {materials.length > 0
                                                        ? materials.join(', ')
                                                        : 'Materials coming soon'}
                                                </span>
                                        </div>
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <MapPin className="size-4" aria-hidden />
                                                <span>{location || 'Region not specified'}</span>
                                        </div>
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <Clock className="size-4" aria-hidden />
                                            <span>
                                                MOQ {supplier.moq ?? '—'} • Lead time {supplier.leadTimeDays ?? '—'} days
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
                                );
                            })}
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
