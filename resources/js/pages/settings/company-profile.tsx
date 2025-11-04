import { successToast, errorToast } from '@/components/app';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { useCompany, useUpdateCompany, type UpdateCompanyInput } from '@/hooks/api/useCompany';
import {
    useCompanyDocuments,
    useDeleteCompanyDocument,
    useUploadCompanyDocument,
} from '@/hooks/api/useCompanyDocuments';
import { FileDropzone } from '@/components/app/file-dropzone';
import { StatusBadge } from '@/components/app/status-badge';
import { Head, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Building, FileText } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';

import type { SharedData } from '@/types';
import type { CompanyDocumentType } from '@/types/company';

const DOCUMENT_OPTIONS: { value: CompanyDocumentType; label: string }[] = [
    { value: 'registration', label: 'Business Registration' },
    { value: 'tax', label: 'Tax Certificate' },
    { value: 'esg', label: 'ESG / Sustainability' },
    { value: 'other', label: 'Other Compliance Document' },
];

const breadcrumbs = [
    {
        title: 'Settings',
        href: '/settings/company-profile',
    },
    {
        title: 'Company profile',
        href: '/settings/company-profile',
    },
];

const FIELD_LABELS: Record<keyof UpdateCompanyInput, string> = {
    name: 'Company legal name',
    registration_no: 'Registration number',
    tax_id: 'Tax ID',
    country: 'Country (ISO-2)',
    email_domain: 'Email domain',
    primary_contact_name: 'Primary contact name',
    primary_contact_email: 'Primary contact email',
    primary_contact_phone: 'Primary contact phone',
    address: 'Registered address',
    phone: 'Company phone',
    website: 'Company website',
    region: 'Region',
};

export default function CompanyProfileSettings() {
    const { auth } = usePage<SharedData>().props;
    const companyId = auth.user?.company_id ?? null;

    const { data: company, isLoading, isError, error } = useCompany(companyId ?? undefined);
    const updateCompany = useUpdateCompany(companyId ?? 0);
    const uploadDocument = useUploadCompanyDocument();
    const deleteDocument = useDeleteCompanyDocument();
    const { data: documents = [], isLoading: isDocumentsLoading } = useCompanyDocuments(company?.id);

    const [formValues, setFormValues] = useState<UpdateCompanyInput>({});
    const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
    const [selectedDocumentType, setSelectedDocumentType] = useState<CompanyDocumentType>('registration');

    useEffect(() => {
        if (!company) {
            return;
        }

        setFormValues({
            name: company.name,
            registration_no: company.registrationNo,
            tax_id: company.taxId,
            country: company.country,
            email_domain: company.emailDomain,
            primary_contact_name: company.primaryContactName,
            primary_contact_email: company.primaryContactEmail,
            primary_contact_phone: company.primaryContactPhone,
            address: company.address ?? '',
            phone: company.phone ?? '',
            website: company.website ?? '',
            region: company.region ?? '',
        });
    }, [company]);

    const isSaving = updateCompany.isPending;

    const handleFieldChange = <T extends keyof UpdateCompanyInput>(field: T, value: UpdateCompanyInput[T]) => {
        setFormValues((previous) => ({
            ...previous,
            [field]: value,
        }));

        if (fieldErrors[field as string]) {
            setFieldErrors((previous) => ({
                ...previous,
                [field as string]: [],
            }));
        }
    };

    const handleSubmit = () => {
        if (!company) {
            return;
        }

        updateCompany.mutate(formValues, {
            onSuccess: () => {
                successToast('Company profile updated.');
            },
            onError: (mutationError) => {
                setFieldErrors(mutationError.errors ?? {});
                errorToast(mutationError.message ?? 'Unable to update company profile.');
            },
        });
    };

    const handleUpload = (files: File[]) => {
        if (!company || files.length === 0) {
            return;
        }

        const [file] = files;

        uploadDocument.mutate(
            {
                companyId: company.id,
                type: selectedDocumentType,
                file,
            },
            {
                onSuccess: () => successToast('Document uploaded.'),
                onError: (mutationError) => errorToast(mutationError.message ?? 'Unable to upload document.'),
            },
        );
    };

    const handleDelete = (documentId: number) => {
        if (!company) {
            return;
        }

        deleteDocument.mutate(
            { companyId: company.id, documentId },
            {
                onSuccess: () => successToast('Document removed.'),
                onError: (mutationError) => errorToast(mutationError.message ?? 'Unable to delete document.'),
            },
        );
    };

    const disableForm = !company || isSaving;

    let formContent: React.ReactNode = null;

    if (isLoading) {
        formContent = (
            <div className="flex items-center gap-2 py-12 text-sm text-muted-foreground">
                <Spinner className="size-4" />
                <span>Loading company profile…</span>
            </div>
        );
    } else if (isError || !company) {
        formContent = (
            <div className="rounded-lg border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive">
                <p className="font-medium">Company details unavailable</p>
                <p>{error?.message ?? 'We could not load your company information. Try again later.'}</p>
            </div>
        );
    } else {
        formContent = (
            <div className="space-y-6">
                <div className="rounded-lg border border-muted-foreground/30 bg-muted/40 p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <Building className="size-5 text-muted-foreground" aria-hidden />
                        <p className="text-sm text-muted-foreground">
                            Status: <StatusBadge status={company.status} />
                        </p>
                    </div>
                    {company.rejectionReason && (
                        <p className="mt-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-100">
                            {company.rejectionReason}
                        </p>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    {(Object.keys(FIELD_LABELS) as (keyof UpdateCompanyInput)[]).map((field) => (
                        <div key={field as string} className="space-y-2">
                            <Label htmlFor={field as string}>{FIELD_LABELS[field]}</Label>
                            <Input
                                id={field as string}
                                value={String(formValues[field] ?? '')}
                                onChange={(event) => handleFieldChange(field, event.target.value)}
                                disabled={disableForm}
                            />
                            <InputError message={fieldErrors[field as string]?.[0]} />
                        </div>
                    ))}
                </div>

                <div className="flex items-center gap-3">
                    <Button type="button" onClick={handleSubmit} disabled={isSaving}>
                        {isSaving && <Spinner className="mr-2 size-4" />}
                        Save changes
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Company profile" />
            <SettingsLayout>
                <div className="space-y-12">
                    <section className="space-y-4">
                        <div>
                            <h2 className="text-xl font-semibold text-foreground">Company profile</h2>
                            <p className="text-sm text-muted-foreground">
                                Maintain your organization&apos;s legal and compliance information. Approved status is required before issuing RFQs or quotes.
                            </p>
                        </div>
                        {formContent}
                    </section>

                    <section className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 className="text-lg font-semibold text-foreground">Compliance documents</h3>
                                <p className="text-sm text-muted-foreground">
                                    Keep registrations, tax certificates, and ESG disclosures up to date for review and audits.
                                </p>
                            </div>
                            <Select value={selectedDocumentType} onValueChange={(value: CompanyDocumentType) => setSelectedDocumentType(value)}>
                                <SelectTrigger className="w-[220px]">
                                    <SelectValue placeholder="Document type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {DOCUMENT_OPTIONS.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <FileDropzone
                            label="Upload compliance documents"
                            description="PDF, JPEG, or PNG up to 5 MB"
                            accept={['application/pdf', 'image/jpeg', 'image/png']}
                            disabled={!company || uploadDocument.isPending}
                            onFilesSelected={handleUpload}
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base font-semibold">Document library</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {isDocumentsLoading ? (
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Spinner className="size-4" />
                                        <span>Loading documents…</span>
                                    </div>
                                ) : documents.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No documents uploaded yet. Submit registration, tax, and compliance certificates to accelerate approval.
                                    </p>
                                ) : (
                                    <ul className="divide-y divide-border/60">
                                        {documents.map((document) => {
                                            const title = DOCUMENT_OPTIONS.find((option) => option.value === document.type)?.label ?? document.type;

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
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDelete(document.id)}
                                                        disabled={deleteDocument.isPending}
                                                    >
                                                        Remove
                                                    </Button>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </section>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
