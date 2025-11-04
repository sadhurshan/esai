import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { Head, usePage } from '@inertiajs/react';
import { Building2, FileText } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';

import { useCompany } from '@/hooks/api/useCompany';
import { useCompanyDocuments } from '@/hooks/api/useCompanyDocuments';
import { StatusBadge } from '@/components/app/status-badge';

import type { SharedData } from '@/types';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Home', href: '/' },
    { title: 'Supplier Company Profile', href: '/supplier/company-profile' },
];

const DOCUMENT_LABELS: Record<string, string> = {
    registration: 'Business Registration',
    tax: 'Tax Certificate',
    esg: 'ESG / Sustainability',
    other: 'Other Document',
};

export default function SupplierCompanyProfile() {
    const { auth } = usePage<SharedData>().props;
    const companyId = auth.user?.company_id ?? null;

    const { data: company, isLoading: isCompanyLoading, isError: companyError, error: companyErrorData } = useCompany(companyId ?? undefined);
    const { data: documents = [], isLoading: docsLoading } = useCompanyDocuments(company?.id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Supplier company profile" />
            <div className="flex flex-1 flex-col gap-6 px-4 py-6">
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold text-foreground">Company profile</h1>
                    <p className="text-sm text-muted-foreground">
                        Review your organization&apos;s approval status and uploaded KYC documentation. Contact your buyer success manager if updates are required.
                    </p>
                </header>

                {isCompanyLoading ? (
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <Skeleton className="h-6 w-48" />
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <Skeleton className="h-4 w-32" />
                                <Skeleton className="h-4 w-64" />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <Skeleton className="h-5 w-40" />
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {Array.from({ length: 3 }).map((_, index) => (
                                    <Skeleton key={index} className="h-4 w-full" />
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                ) : companyError || !company ? (
                    <div className="rounded-lg border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive">
                        <p className="font-medium">Company record unavailable</p>
                        <p>{companyErrorData?.message ?? 'We could not locate your company details. Please contact support.'}</p>
                    </div>
                ) : (
                    <div className="space-y-6">
                        <Card className="border-muted/70">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Building2 className="size-5" aria-hidden />
                                    <span>{company.name}</span>
                                    <StatusBadge status={company.status} />
                                </CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Domain {company.emailDomain} • Registration #{company.registrationNo}
                                </p>
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm text-muted-foreground">
                                <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
                                    <p><span className="font-medium text-foreground">Tax ID:</span> {company.taxId}</p>
                                    <p><span className="font-medium text-foreground">Primary contact:</span> {company.primaryContactName} ({company.primaryContactEmail})</p>
                                    {company.address && (
                                        <p><span className="font-medium text-foreground">Address:</span> {company.address}</p>
                                    )}
                                    {company.phone && (
                                        <p><span className="font-medium text-foreground">Phone:</span> {company.phone}</p>
                                    )}
                                    {company.website && (
                                        <p><span className="font-medium text-foreground">Website:</span> {company.website}</p>
                                    )}
                                </div>
                                {company.rejectionReason && (
                                    <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-100">
                                        {company.rejectionReason}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="border-muted/70">
                            <CardHeader>
                                <CardTitle className="text-base font-semibold">KYC documentation</CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Platform administrators review these documents before approving supplier access to RFQs and orders.
                                </p>
                            </CardHeader>
                            <CardContent>
                                {docsLoading ? (
                                    <div className="space-y-3">
                                        {Array.from({ length: 3 }).map((_, index) => (
                                            <Skeleton key={index} className="h-4 w-full" />
                                        ))}
                                    </div>
                                ) : documents.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No documents uploaded. Please coordinate with your administrator to add business registration, tax, and compliance certificates.
                                    </p>
                                ) : (
                                    <ul className="divide-y divide-border/60">
                                        {documents.map((document) => {
                                            const title = DOCUMENT_LABELS[document.type] ?? document.type;

                                            return (
                                                <li key={document.id} className="flex items-center justify-between gap-4 py-3 text-sm">
                                                    <div className="flex items-center gap-3">
                                                        <FileText className="size-4 text-muted-foreground" aria-hidden />
                                                        <div className="flex flex-col">
                                                            <span className="font-medium text-foreground">{title}</span>
                                                        <span className="text-xs text-muted-foreground">
                                                            Uploaded {document.createdAt ? formatDistanceToNow(new Date(document.createdAt), { addSuffix: true }) : 'recently'}
                                                        </span>
                                                    </div>
                                                </div>
                                                {document.verifiedAt && (
                                                    <span className="text-xs font-medium text-emerald-600 dark:text-emerald-300">
                                                        Verified {formatDistanceToNow(new Date(document.verifiedAt), { addSuffix: true })}
                                                    </span>
                                                )}
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
