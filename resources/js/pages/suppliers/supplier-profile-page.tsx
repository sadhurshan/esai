import { useMemo, type CSSProperties } from 'react';
import { Helmet } from 'react-helmet-async';
import { useNavigate, useParams } from 'react-router-dom';
import {
    AlertTriangle,
    BadgeCheck,
    Building2,
    CheckCircle2,
    Globe,
    Layers,
    Mail,
    MapPin,
    Phone,
    Shield,
    ShieldAlert,
    Star,
} from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Skeleton } from '@/components/ui/skeleton';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { EmptyState } from '@/components/empty-state';
import { useSupplier } from '@/hooks/api/useSupplier';
import { formatDate } from '@/lib/format';
import type { SupplierDocument } from '@/types/sourcing';

export function SupplierProfilePage() {
    const params = useParams<{ supplierId?: string }>();
    const parsedSupplierId = Number(params.supplierId);
    const supplierId = Number.isFinite(parsedSupplierId) && parsedSupplierId > 0 ? parsedSupplierId : undefined;
    const supplierQuery = useSupplier(supplierId);
    const navigate = useNavigate();

    if (!supplierId) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center">
                <EmptyState
                    title="Invalid supplier"
                    description="The supplier identifier is missing or incorrect."
                    icon={<AlertTriangle className="h-10 w-10 text-destructive" />}
                    ctaLabel="Back to directory"
                    ctaProps={{ onClick: () => navigate('/app/suppliers') }}
                />
            </div>
        );
    }

    if (supplierQuery.isLoading) {
        return <SupplierProfileSkeleton />;
    }

    if (supplierQuery.isError || !supplierQuery.data) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center">
                <EmptyState
                    title="Supplier unavailable"
                    description="We could not load this profile. It may have been removed or you may not have access."
                    icon={<AlertTriangle className="h-10 w-10 text-destructive" />}
                    ctaLabel="Back to directory"
                    ctaProps={{ onClick: () => navigate('/app/suppliers') }}
                />
            </div>
        );
    }

    const supplier = supplierQuery.data;
    const documents = supplier.documents ?? [];
    const location = [supplier.address.city, supplier.address.country].filter(Boolean).join(', ');
    const heroStyle = buildHeroStyle(supplier.branding?.markUrl ?? null);

    const capabilityGroups = [
        { label: 'Methods', values: supplier.capabilities.methods },
        { label: 'Materials', values: supplier.capabilities.materials },
        { label: 'Finishes', values: supplier.capabilities.finishes },
        { label: 'Tolerances', values: supplier.capabilities.tolerances },
        { label: 'Industries', values: supplier.capabilities.industries },
    ];

    const contactRows = [
        {
            label: 'Website',
            icon: <Globe className="h-4 w-4 text-muted-foreground" />,
            value: supplier.contact.website ?? '—',
            href: supplier.contact.website ?? undefined,
        },
        {
            label: 'Email',
            icon: <Mail className="h-4 w-4 text-muted-foreground" />,
            value: supplier.contact.email ?? '—',
            href: supplier.contact.email ? `mailto:${supplier.contact.email}` : undefined,
        },
        {
            label: 'Phone',
            icon: <Phone className="h-4 w-4 text-muted-foreground" />,
            value: supplier.contact.phone ?? '—',
            href: supplier.contact.phone ? `tel:${supplier.contact.phone}` : undefined,
        },
    ];

    const certificateCards = [
        { label: 'Valid', value: supplier.certificates.valid, tone: 'text-emerald-600 bg-emerald-50' },
        { label: 'Expiring', value: supplier.certificates.expiring, tone: 'text-amber-600 bg-amber-50' },
        { label: 'Expired', value: supplier.certificates.expired, tone: 'text-rose-600 bg-rose-50' },
    ];

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>{supplier.name} · Supplier Profile</title>
            </Helmet>

            <div className="flex flex-wrap items-center gap-2">
                <Button variant="ghost" onClick={() => navigate('/app/suppliers')}>
                    ← Back to directory
                </Button>
                <div className="ml-auto flex gap-2">
                    <Button variant="outline" onClick={() => navigate('/app/rfqs')}>
                        Invite to RFQ
                    </Button>
                    <Button onClick={() => navigate('/app/rfqs/new')}>Start RFQ</Button>
                </div>
            </div>

            <section className="overflow-hidden rounded-3xl border bg-card shadow-sm">
                <div className="relative h-44 w-full" style={heroStyle}>
                    {/* TODO: replace placeholder gradient with supplier-provided cover asset once uploads are supported. */}
                    <div className="absolute inset-0 bg-black/30" />
                    <div className="relative flex h-full items-end gap-4 p-6">
                        <Avatar className="h-20 w-20 border-4 border-background">
                            {supplier.branding?.logoUrl ? (
                                <AvatarImage src={supplier.branding.logoUrl} alt={supplier.name} />
                            ) : null}
                            <AvatarFallback>{supplier.name.slice(0, 2).toUpperCase()}</AvatarFallback>
                        </Avatar>
                        <div className="space-y-2">
                            <h1 className="text-3xl font-semibold text-white">{supplier.name}</h1>
                            <div className="flex flex-wrap items-center gap-3 text-sm text-white/80">
                                {location ? (
                                    <span className="inline-flex items-center gap-1">
                                        <MapPin className="h-4 w-4" /> {location}
                                    </span>
                                ) : null}
                                {supplier.ratingAvg ? (
                                    <span className="inline-flex items-center gap-1">
                                        <Star className="h-4 w-4 fill-yellow-400 text-yellow-300" /> {supplier.ratingAvg.toFixed(1)} rating
                                    </span>
                                ) : null}
                                {supplier.leadTimeDays ? (
                                    <span className="inline-flex items-center gap-1">
                                        <Layers className="h-4 w-4" /> {supplier.leadTimeDays} day lead time
                                    </span>
                                ) : null}
                                {supplier.company?.isVerified ? (
                                    <span className="inline-flex items-center gap-1">
                                        <BadgeCheck className="h-4 w-4" /> Verified directory listing
                                    </span>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </div>
                <CardContent className="grid gap-4 border-t bg-background/80 p-6 md:grid-cols-2 lg:grid-cols-4">
                    <MetricTile label="Status" icon={<Shield className="h-4 w-4" />}>
                        <Badge variant="outline" className="capitalize">
                            {supplier.status}
                        </Badge>
                    </MetricTile>
                    <MetricTile label="Risk grade" icon={<ShieldAlert className="h-4 w-4" />}>
                        {supplier.riskGrade ? supplier.riskGrade : 'Not scored'}
                    </MetricTile>
                    <MetricTile label="MOQ" icon={<Building2 className="h-4 w-4" />}>
                        {supplier.moq ?? '—'}
                    </MetricTile>
                    <MetricTile label="Verified on" icon={<CheckCircle2 className="h-4 w-4" />}>
                        {supplier.verifiedAt ? formatDate(supplier.verifiedAt) : 'Pending'}
                    </MetricTile>
                </CardContent>
            </section>

            <Tabs defaultValue="overview" className="space-y-4">
                <TabsList className="w-full justify-start">
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="capabilities">Capabilities</TabsTrigger>
                    <TabsTrigger value="certificates">Certificates & Documents</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Contact & Location</CardTitle>
                            <CardDescription>Keep this vendor record updated to ensure compliance reviews stay current.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            {contactRows.map((row) => (
                                <div key={row.label} className="flex items-start gap-3">
                                    {row.icon}
                                    <div>
                                        <p className="text-sm font-medium text-foreground">{row.label}</p>
                                        {row.href ? (
                                            <a href={row.href} className="text-sm text-primary hover:underline" target="_blank" rel="noreferrer">
                                                {row.value}
                                            </a>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">{row.value}</p>
                                        )}
                                    </div>
                                </div>
                            ))}
                            <div className="flex items-start gap-3">
                                <MapPin className="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium text-foreground">Address</p>
                                    <p className="text-sm text-muted-foreground">
                                        {supplier.address.line1 ? `${supplier.address.line1}, ` : ''}
                                        {location || 'Not provided'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Compliance summary</CardTitle>
                            <CardDescription>Certification health and timing for this supplier.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-3">
                            {certificateCards.map((card) => (
                                <div key={card.label} className={cn('rounded-2xl border p-4', card.tone)}>
                                    <p className="text-sm font-medium">{card.label}</p>
                                    <p className="text-2xl font-semibold">{card.value}</p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="capabilities" className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Manufacturing coverage</CardTitle>
                            <CardDescription>Declared production strengths and industry focus areas.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6 md:grid-cols-2">
                            {capabilityGroups.map((group) => (
                                <CapabilityGroup key={group.label} label={group.label} values={group.values ?? []} />
                            ))}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="certificates" className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Compliance documents</CardTitle>
                            <CardDescription>Monitor expiry windows and request updated certificates as needed.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {documents.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No documents uploaded yet.</p>
                            ) : (
                                <div className="overflow-hidden rounded-xl border">
                                    <table className="min-w-full divide-y divide-border text-sm">
                                        <thead className="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3">Type</th>
                                                <th className="px-4 py-3">Status</th>
                                                <th className="px-4 py-3">Issued</th>
                                                <th className="px-4 py-3">Expires</th>
                                                <th className="px-4 py-3">Size</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {documents.map((document) => (
                                                <tr key={document.id} className="hover:bg-muted/30">
                                                    <td className="px-4 py-3 font-medium capitalize text-foreground">{document.type}</td>
                                                    <td className="px-4 py-3"><DocumentStatusBadge status={document.status} /></td>
                                                    <td className="px-4 py-3 text-muted-foreground">{formatDate(document.issuedAt)}</td>
                                                    <td className="px-4 py-3 text-muted-foreground">{formatDate(document.expiresAt)}</td>
                                                    <td className="px-4 py-3 text-muted-foreground">{formatFileSize(document.sizeBytes)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    );
}

function SupplierProfileSkeleton() {
    return (
        <div className="flex flex-1 flex-col gap-6">
            <Skeleton className="h-8 w-48" />
            <Skeleton className="h-64 w-full rounded-3xl" />
            <Skeleton className="h-12 w-1/2" />
            <Skeleton className="h-80 w-full rounded-2xl" />
        </div>
    );
}

function CapabilityGroup({ label, values }: { label: string; values: string[] }) {
    return (
        <div className="space-y-2">
            <p className="text-sm font-medium text-foreground">{label}</p>
            {values.length === 0 ? (
                <p className="text-sm text-muted-foreground">Not provided.</p>
            ) : (
                <div className="flex flex-wrap gap-2">
                    {values.map((value) => (
                        <Badge key={value} variant="outline" className="rounded-full text-[11px]">
                            {value}
                        </Badge>
                    ))}
                </div>
            )}
        </div>
    );
}

function MetricTile({ label, icon, children }: { label: string; icon: React.ReactNode; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-1 rounded-2xl border bg-background/80 p-4">
            <div className="flex items-center gap-2 text-xs uppercase tracking-wide text-muted-foreground">
                {icon}
                {label}
            </div>
            <div className="text-lg font-semibold text-foreground">{children}</div>
        </div>
    );
}

function DocumentStatusBadge({ status }: { status: SupplierDocument['status'] }) {
    const { label, className } = useMemo(() => {
        switch (status) {
            case 'valid':
                return { label: 'Valid', className: 'bg-emerald-100 text-emerald-700' };
            case 'expiring':
                return { label: 'Expiring soon', className: 'bg-amber-100 text-amber-700' };
            case 'expired':
            default:
                return { label: 'Expired', className: 'bg-rose-100 text-rose-700' };
        }
    }, [status]);

    return <Badge className={cn('rounded-full text-xs font-medium', className)}>{label}</Badge>;
}

function buildHeroStyle(markUrl: string | null): CSSProperties {
    if (markUrl) {
        return {
            backgroundImage: `linear-gradient(120deg, rgba(15,23,42,0.75), rgba(79,70,229,0.65)), url(${markUrl})`,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
        };
    }

    return {
        backgroundImage: 'linear-gradient(120deg, rgba(15,23,42,0.85), rgba(30,64,175,0.7))',
    };
}

function formatFileSize(bytes?: number | null): string {
    if (!bytes || bytes <= 0) {
        return '—';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${size.toFixed(size >= 10 ? 0 : 1)} ${units[unitIndex]}`;
}

function cn(...classes: Array<string | false | null | undefined>) {
    return classes.filter(Boolean).join(' ');
}
