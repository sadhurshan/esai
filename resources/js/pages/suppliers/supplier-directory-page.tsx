import { useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { useNavigate } from 'react-router-dom';
import { Aperture, BadgeCheck, Building2, ChevronRight, Factory, Layers, MapPin, Star } from 'lucide-react';

import { LazySupplierRiskBadge } from '@/components/ai/LazySupplierRiskBadge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { EmptyState } from '@/components/empty-state';
import { useSuppliers } from '@/hooks/api/useSuppliers';
import { useSupplierRiskAccess } from '@/hooks/use-supplier-risk-access';
import type { Supplier } from '@/types/sourcing';
import { cn } from '@/lib/utils';

const METHOD_OPTIONS = ['CNC Milling', 'CNC Turning', 'Sheet Metal', 'Injection Molding', 'Additive'];
const MATERIAL_OPTIONS = ['Aluminum 6061', 'Aluminum 7075', 'Stainless Steel 304', 'Stainless Steel 316', 'ABS', 'PEEK'];
const FINISH_OPTIONS = ['Anodizing', 'Powder Coat', 'Black Oxide', 'Passivation', 'Polishing'];
const TOLERANCE_OPTIONS = ['+/- 0.010"', '+/- 0.005"', '+/- 0.002"', 'ISO 2768-m', 'ISO 2768-f'];
const INDUSTRY_OPTIONS = ['Aerospace', 'Automotive', 'Medical', 'Industrial Equipment', 'Robotics'];

const LEAD_TIME_OPTIONS = [
    { value: '5', label: '≤ 5 days' },
    { value: '10', label: '≤ 10 days' },
    { value: '15', label: '≤ 15 days' },
    { value: '30', label: '≤ 30 days' },
    { value: '45', label: '≤ 45 days' },
];

const RATING_OPTIONS = [
    { value: '4.5', label: '4.5 +' },
    { value: '4', label: '4.0 +' },
    { value: '3.5', label: '3.5 +' },
    { value: '3', label: '3.0 +' },
];

const SORT_OPTIONS = [
    { value: 'match_score', label: 'Best match' },
    { value: 'rating', label: 'Top rated' },
    { value: 'lead_time', label: 'Fastest lead time' },
];

const PER_PAGE = 25;

interface DirectoryFilters {
    capability: string;
    material: string;
    finish: string;
    tolerance: string;
    industry: string;
    location: string;
    rating: string;
    leadTime: string;
    sort: 'match_score' | 'rating' | 'lead_time' | 'distance' | 'price_band';
}

const initialFilters: DirectoryFilters = {
    capability: '',
    material: '',
    finish: '',
    tolerance: '',
    industry: '',
    location: '',
    rating: '',
    leadTime: '',
    sort: 'match_score',
};

export function SupplierDirectoryPage() {
    const [filters, setFilters] = useState<DirectoryFilters>(initialFilters);
    const [page, setPage] = useState(1);
    const [searchInput, setSearchInput] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const navigate = useNavigate();

    useEffect(() => {
        const handle = window.setTimeout(() => setSearchTerm(searchInput.trim()), 300);
        return () => window.clearTimeout(handle);
    }, [searchInput]);

    const queryParams = useMemo(() => {
        return {
            page,
            per_page: PER_PAGE,
            q: searchTerm || undefined,
            capability: filters.capability || undefined,
            material: filters.material || undefined,
            finish: filters.finish || undefined,
            tolerance: filters.tolerance || undefined,
            industry: filters.industry || undefined,
            location: filters.location || undefined,
            rating_min: filters.rating ? Number(filters.rating) : undefined,
            lead_time_max: filters.leadTime ? Number(filters.leadTime) : undefined,
            sort: filters.sort,
        };
    }, [filters, page, searchTerm]);

    const supplierQuery = useSuppliers(queryParams);
    const { canViewSupplierRisk, isSupplierRiskLocked } = useSupplierRiskAccess();
    const suppliers = supplierQuery.data?.items ?? [];
    const meta = supplierQuery.data?.meta;

    const canGoPrev = meta ? meta.current_page > 1 : false;
    const canGoNext = meta ? meta.current_page < meta.last_page : false;

    const handleFilterChange = (key: keyof DirectoryFilters, value: string) => {
        setFilters((current) => ({ ...current, [key]: value }));
        setPage(1);
    };

    const handleResetFilters = () => {
        setFilters(initialFilters);
        setSearchInput('');
        setSearchTerm('');
        setPage(1);
    };

    const showSkeleton = supplierQuery.isLoading && !supplierQuery.isError;
    const showEmptyState = !showSkeleton && !supplierQuery.isError && suppliers.length === 0;

    const paginationCopy = meta
        ? `Showing ${(meta.current_page - 1) * meta.per_page + (suppliers.length > 0 ? 1 : 0)}–${
              suppliers.length > 0 ? (meta.current_page - 1) * meta.per_page + suppliers.length : 0
          } of ${meta.total} suppliers`
        : 'Showing 0 suppliers';

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Supplier Directory</title>
            </Helmet>

            <div className="space-y-2">
                <div className="flex flex-wrap items-center gap-3">
                    <Factory className="h-5 w-5 text-muted-foreground" />
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Supplier Directory</h1>
                        <p className="text-sm text-muted-foreground">
                            Discover qualified manufacturers, review certifications, and invite partners directly into RFQs.
                        </p>
                    </div>
                </div>
            </div>

            <section className="rounded-xl border bg-card p-4 shadow-sm">
                <div className="grid gap-3 md:grid-cols-3 lg:grid-cols-4">
                    <div className="flex flex-col gap-1">
                        <label className="text-xs font-medium text-muted-foreground">Search</label>
                        <Input
                            value={searchInput}
                            onChange={(event) => {
                                setSearchInput(event.target.value);
                                setPage(1);
                            }}
                            placeholder="Search by name, capability, material"
                        />
                    </div>
                    <FilterSelect
                        label="Capability"
                        value={filters.capability}
                        onValueChange={(value) => handleFilterChange('capability', value)}
                        options={METHOD_OPTIONS}
                    />
                    <FilterSelect
                        label="Material"
                        value={filters.material}
                        onValueChange={(value) => handleFilterChange('material', value)}
                        options={MATERIAL_OPTIONS}
                    />
                    <FilterSelect
                        label="Finish"
                        value={filters.finish}
                        onValueChange={(value) => handleFilterChange('finish', value)}
                        options={FINISH_OPTIONS}
                    />
                    <FilterSelect
                        label="Tolerance"
                        value={filters.tolerance}
                        onValueChange={(value) => handleFilterChange('tolerance', value)}
                        options={TOLERANCE_OPTIONS}
                    />
                    <FilterSelect
                        label="Industry"
                        value={filters.industry}
                        onValueChange={(value) => handleFilterChange('industry', value)}
                        options={INDUSTRY_OPTIONS}
                    />
                    <div className="flex flex-col gap-1">
                        <label className="text-xs font-medium text-muted-foreground">Location</label>
                        <Input
                            value={filters.location}
                            onChange={(event) => handleFilterChange('location', event.target.value)}
                            placeholder="City or country"
                        />
                    </div>
                    <FilterSelect
                        label="Rating"
                        value={filters.rating}
                        onValueChange={(value) => handleFilterChange('rating', value)}
                        options={RATING_OPTIONS}
                    />
                    <FilterSelect
                        label="Lead time"
                        value={filters.leadTime}
                        onValueChange={(value) => handleFilterChange('leadTime', value)}
                        options={LEAD_TIME_OPTIONS}
                    />
                    <div className="flex flex-col gap-1">
                        <label className="text-xs font-medium text-muted-foreground">Sort</label>
                        <Select value={filters.sort} onValueChange={(value) => handleFilterChange('sort', value as DirectoryFilters['sort'])}>
                            <SelectTrigger>
                                <SelectValue placeholder="Best match" />
                            </SelectTrigger>
                            <SelectContent>
                                {SORT_OPTIONS.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div className="mt-3 flex flex-wrap items-center gap-3">
                    <Button type="button" variant="outline" size="sm" onClick={handleResetFilters}>
                        Clear filters
                    </Button>
                    {searchTerm ? (
                        <Badge variant="secondary" className="text-xs">
                            Search: {searchTerm}
                        </Badge>
                    ) : null}
                </div>
            </section>

            {supplierQuery.isError ? (
                <div className="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
                    Unable to load suppliers. Please retry in a few seconds.
                </div>
            ) : null}

            <div className="grid gap-4">
                {showSkeleton
                    ? Array.from({ length: 6 }).map((_, index) => <SupplierCardSkeleton key={`supplier-skeleton-${index}`} />)
                    : suppliers.map((supplier) => (
                          <SupplierCard
                              key={supplier.id}
                              supplier={supplier}
                              onViewProfile={() => navigate(`/app/suppliers/${supplier.id}`)}
                              showRiskBadge={canViewSupplierRisk}
                              riskBadgeLocked={isSupplierRiskLocked}
                          />
                      ))}

                {showEmptyState ? (
                    <EmptyState
                        title="No suppliers matched"
                        description="Try adjusting the filters or search terms to find nearby or qualified partners."
                        icon={<Aperture className="h-8 w-8 text-muted-foreground" />}
                        ctaLabel="Reset filters"
                        ctaProps={{ onClick: handleResetFilters }}
                    />
                ) : null}
            </div>

            <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-xs text-muted-foreground">{paginationCopy}</p>
                <div className="flex items-center gap-2">
                    <Button variant="outline" size="sm" disabled={!canGoPrev || supplierQuery.isFetching} onClick={() => setPage((current) => Math.max(current - 1, 1))}>
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!canGoNext || supplierQuery.isFetching}
                        onClick={() => setPage((current) => (meta ? Math.min(meta.last_page, current + 1) : current))}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}

interface SupplierCardProps {
    supplier: Supplier;
    onViewProfile: () => void;
    showRiskBadge: boolean;
    riskBadgeLocked: boolean;
}

function SupplierCard({ supplier, onViewProfile, showRiskBadge, riskBadgeLocked }: SupplierCardProps) {
    const branding = supplier.branding ?? { logoUrl: null, markUrl: null };
    const location = [supplier.address.city, supplier.address.country].filter(Boolean).join(', ');
    const topMethods = supplier.capabilities.methods?.slice(0, 3) ?? [];
    const topMaterials = supplier.capabilities.materials?.slice(0, 2) ?? [];

    return (
        <div className="rounded-2xl border border-border/70 bg-card/60 p-4 shadow-sm transition hover:border-primary/40">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-1 items-start gap-4">
                    <Avatar className="h-14 w-14 border">
                        {branding.logoUrl ? <AvatarImage src={branding.logoUrl} alt={supplier.name} /> : null}
                        <AvatarFallback>{supplier.name.slice(0, 2).toUpperCase()}</AvatarFallback>
                    </Avatar>
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-3">
                            <p className="text-lg font-semibold text-foreground">{supplier.name}</p>
                            {supplier.company?.isVerified ? (
                                <Badge variant="secondary" className="inline-flex items-center gap-1 text-[11px]">
                                    <BadgeCheck className="h-3 w-3" /> Verified
                                </Badge>
                            ) : null}
                        </div>
                        <div className="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                            {location ? (
                                <span className="inline-flex items-center gap-1">
                                    <MapPin className="h-3.5 w-3.5" /> {location}
                                </span>
                            ) : null}
                            {typeof supplier.ratingAvg === 'number' ? (
                                <span className="inline-flex items-center gap-1">
                                    <Star className="h-3.5 w-3.5 fill-yellow-400 text-yellow-500" /> {supplier.ratingAvg.toFixed(1)} rating
                                </span>
                            ) : null}
                            {supplier.leadTimeDays ? (
                                <span className="inline-flex items-center gap-1">
                                    <ClockIcon /> {supplier.leadTimeDays} day lead time
                                </span>
                            ) : null}
                            {showRiskBadge ? (
                                <LazySupplierRiskBadge
                                    supplierId={supplier.id}
                                    supplier={buildDirectorySupplierRiskPayload(supplier)}
                                    entityType="supplier_directory"
                                    entityId={supplier.id}
                                    disabled={riskBadgeLocked}
                                />
                            ) : null}
                        </div>
                        {topMethods.length > 0 ? (
                            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span className="inline-flex items-center gap-1 font-medium text-foreground">
                                    <Layers className="h-3.5 w-3.5" /> Methods:
                                </span>
                                {topMethods.map((method) => (
                                    <Badge key={method} variant="outline" className="rounded-full text-[11px]">
                                        {method}
                                    </Badge>
                                ))}
                            </div>
                        ) : null}
                        {topMaterials.length > 0 ? (
                            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span className="inline-flex items-center gap-1 font-medium text-foreground">
                                    <Building2 className="h-3.5 w-3.5" /> Materials:
                                </span>
                                {topMaterials.map((material) => (
                                    <Badge key={material} variant="outline" className="rounded-full text-[11px]">
                                        {material}
                                    </Badge>
                                ))}
                            </div>
                        ) : null}
                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <span className="inline-flex items-center gap-1 font-medium text-foreground">
                                <BadgeCheck className="h-3.5 w-3.5" /> Certifications:
                            </span>
                            <Badge variant="secondary" className="rounded-full">
                                Valid {supplier.certificates.valid}
                            </Badge>
                            <Badge variant="outline" className="rounded-full">
                                Expiring {supplier.certificates.expiring}
                            </Badge>
                            <Badge variant="outline" className="rounded-full">
                                Expired {supplier.certificates.expired}
                            </Badge>
                        </div>
                    </div>
                </div>
                <div className="flex flex-col gap-2">
                    {supplier.company?.supplierStatus ? (
                        <Badge className={cn('w-fit capitalize', supplier.company.supplierStatus !== 'approved' ? 'bg-amber-100 text-amber-800' : undefined)}>
                            {supplier.company.supplierStatus}
                        </Badge>
                    ) : null}
                    <Button variant="outline" onClick={onViewProfile} className="inline-flex items-center gap-1">
                        View profile <ChevronRight className="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}

function buildDirectorySupplierRiskPayload(supplier: Supplier) {
    return {
        supplier_id: supplier.id,
        supplier_name: supplier.name,
        company_id: supplier.companyId,
        company_name: supplier.company?.name ?? null,
        rating_avg: supplier.ratingAvg,
        risk_grade: supplier.riskGrade ?? null,
        lead_time_days: supplier.leadTimeDays ?? null,
        capabilities: supplier.capabilities,
        certificates: supplier.certificates,
        verified_at: supplier.verifiedAt ?? null,
        address: supplier.address,
        geo: supplier.geo,
    } satisfies Record<string, unknown>;
}

function SupplierCardSkeleton() {
    return (
        <div className="rounded-2xl border border-border/70 bg-card/60 p-4">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-1 items-start gap-4">
                    <Skeleton className="h-14 w-14 rounded-full" />
                    <div className="space-y-3">
                        <Skeleton className="h-5 w-48" />
                        <Skeleton className="h-4 w-64" />
                        <Skeleton className="h-4 w-72" />
                    </div>
                </div>
                <Skeleton className="h-9 w-32" />
            </div>
        </div>
    );
}

type FilterOption = string | { value: string; label: string };

const CLEAR_FILTER_VALUE = '__any__';

interface FilterSelectProps {
    label: string;
    value: string;
    onValueChange: (value: string) => void;
    options: FilterOption[];
}

function FilterSelect({ label, value, onValueChange, options }: FilterSelectProps) {
    const normalizedOptions = options.map((option) =>
        typeof option === 'string' ? { value: option, label: option } : option,
    );

    const selectValue = value === '' ? CLEAR_FILTER_VALUE : value;

    return (
        <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-muted-foreground">{label}</label>
            <Select
                value={selectValue}
                onValueChange={(nextValue) =>
                    onValueChange(nextValue === CLEAR_FILTER_VALUE ? '' : nextValue)
                }
            >
                <SelectTrigger>
                    <SelectValue placeholder={`Any ${label.toLowerCase()}`} />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={CLEAR_FILTER_VALUE}>Any</SelectItem>
                    {normalizedOptions.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

function ClockIcon() {
    return (
        <svg viewBox="0 0 24 24" className="h-3.5 w-3.5 text-muted-foreground" aria-hidden>
            <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" strokeWidth="2" />
            <path d="M12 6v6l3 2" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}
