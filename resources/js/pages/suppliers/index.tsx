import { FilterBar } from '@/components/app';
import { EmptyState } from '@/components/app/empty-state';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { home, rfq, suppliers as supplierRoutes } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Building2, Clock, Factory, MapPin } from 'lucide-react';
import { useMemo, useState } from 'react';
import { type Supplier } from '@/types/sourcing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Home', href: home().url },
    { title: 'Supplier Directory', href: supplierRoutes.index().url },
];

const mockSuppliers: Supplier[] = [
    {
        id: 1,
        name: 'PrecisionForge Manufacturing',
        rating: 4.8,
        capabilities: ['CNC Machining', 'Sheet Metal'],
        materials: ['Aluminum 6061', 'Stainless Steel 304'],
        locationRegion: 'Austin, TX (USA)',
        minimumOrderQuantity: 10,
        averageResponseHours: 4,
    },
    {
        id: 2,
        name: 'Nova Additive Labs',
        rating: 4.6,
        capabilities: ['Metal AM', 'Polymer AM'],
        materials: ['Inconel 718', 'ULTEM 9085'],
        locationRegion: 'Toronto, ON (Canada)',
        minimumOrderQuantity: 1,
        averageResponseHours: 6,
    },
    {
        id: 3,
        name: 'Coastal Cast & Forge',
        rating: 4.3,
        capabilities: ['Investment Casting', 'Heat Treatment'],
        materials: ['Titanium Grade 5', 'Ductile Iron'],
        locationRegion: 'Charleston, SC (USA)',
        minimumOrderQuantity: 25,
        averageResponseHours: 12,
    },
];

export default function SuppliersIndex() {
    const [search, setSearch] = useState('');
    const [filters, setFilters] = useState({
        method: '',
        material: '',
        location: '',
    });

    const filteredSuppliers = useMemo(() => {
        return mockSuppliers.filter((supplier) => {
            const matchesSearch = supplier.name
                .toLowerCase()
                .includes(search.toLowerCase());

            const matchesMethod =
                !filters.method ||
                supplier.capabilities
                    .join(' ')
                    .toLowerCase()
                    .includes(filters.method.toLowerCase());

            const matchesMaterial =
                !filters.material ||
                supplier.materials
                    .join(' ')
                    .toLowerCase()
                    .includes(filters.material.toLowerCase());

            const matchesLocation =
                !filters.location ||
                supplier.locationRegion
                    .toLowerCase()
                    .includes(filters.location.toLowerCase());

            return (
                matchesSearch && matchesMethod && matchesMaterial && matchesLocation
            );
        });
    }, [filters.location, filters.material, filters.method, search]);

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
                                    { label: 'CNC Machining', value: 'cnc machining' },
                                    { label: 'Sheet Metal', value: 'sheet metal' },
                                    { label: 'Metal AM', value: 'metal am' },
                                    { label: 'Investment Casting', value: 'investment casting' },
                                ],
                                value: filters.method,
                            },
                            {
                                id: 'material',
                                label: 'Material',
                                options: [
                                    { label: 'All Materials', value: '' },
                                    { label: 'Aluminum', value: 'aluminum' },
                                    { label: 'Titanium', value: 'titanium' },
                                    { label: 'Stainless Steel', value: 'stainless steel' },
                                    { label: 'Inconel', value: 'inconel' },
                                ],
                                value: filters.material,
                            },
                            {
                                id: 'location',
                                label: 'Location',
                                options: [
                                    { label: 'All Regions', value: '' },
                                    { label: 'United States', value: 'usa' },
                                    { label: 'Canada', value: 'canada' },
                                    { label: 'Europe', value: 'europe' },
                                ],
                                value: filters.location,
                            },
                        ]}
                        searchPlaceholder="Search suppliers"
                        searchValue={search}
                        onSearchChange={setSearch}
                        onFilterChange={(id, value) =>
                            setFilters((prev) => ({ ...prev, [id]: value }))
                        }
                        onReset={() => {
                            setSearch('');
                            setFilters({ method: '', material: '', location: '' });
                        }}
                    />
                </section>

                {filteredSuppliers.length === 0 ? (
                    <EmptyState
                        title="No suppliers match your filters"
                        description="Adjust manufacturing methods, materials, or regions to expand your supplier pool."
                        ctaLabel="Clear Filters"
                        ctaProps={{ onClick: () => {
                            setSearch('');
                            setFilters({ method: '', material: '', location: '' });
                        } }}
                    />
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {filteredSuppliers.map((supplier) => (
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
                                        <span>{supplier.capabilities.join(', ')}</span>
                                    </div>
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <Building2 className="size-4" aria-hidden />
                                        <span>{supplier.materials.join(', ')}</span>
                                    </div>
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <MapPin className="size-4" aria-hidden />
                                        <span>{supplier.locationRegion}</span>
                                    </div>
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <Clock className="size-4" aria-hidden />
                                        <span>
                                            MOQ {supplier.minimumOrderQuantity} • Avg response {supplier.averageResponseHours} hrs
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
                )}
            </div>
        </AppLayout>
    );
}
