import { successToast, errorToast } from '@/components/app';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { useCompany, useRegisterCompany, type RegisterCompanyInput } from '@/hooks/api/useCompany';
import {
    useCompanyDocuments,
    useDeleteCompanyDocument,
    useUploadCompanyDocument,
    type UploadCompanyDocumentInput,
} from '@/hooks/api/useCompanyDocuments';
import { FileDropzone } from '@/components/app/file-dropzone';
import { StatusBadge } from '@/components/app/status-badge';
import { formatDistanceToNow } from 'date-fns';
import { Head, Link, usePage, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Building2, FileText, ShieldAlert } from 'lucide-react';

import type { SharedData } from '@/types';
import type { Company, CompanyDocumentType } from '@/types/company';

const steps = [
    {
        id: 'company-details',
        title: 'Company Details',
        description: 'Provide your legal entity information to begin the review process.',
    },
    {
        id: 'primary-contact',
        title: 'Primary Contact',
        description: 'Tell us who should receive onboarding and compliance notifications.',
    },
    {
        id: 'kyc-documents',
        title: 'KYC Documents',
        description: 'Upload registration and compliance documentation (optional during submission).',
    },
] as const;

const DOCUMENT_OPTIONS: { value: CompanyDocumentType; label: string }[] = [
    { value: 'registration', label: 'Business Registration' },
    { value: 'tax', label: 'Tax Certificate' },
    { value: 'esg', label: 'ESG / Sustainability' },
    { value: 'other', label: 'Other Compliance Document' },
];

const REQUIRED_FIELDS_STEP_ONE: (keyof RegisterCompanyInput)[] = [
    'name',
    'registration_no',
    'tax_id',
    'country',
];

const REQUIRED_FIELDS_STEP_TWO: (keyof RegisterCompanyInput)[] = [
    'email_domain',
    'primary_contact_name',
    'primary_contact_email',
    'primary_contact_phone',
];

const EMPTY_ERRORS: Record<string, string[]> = {};

export default function CompanyRegistrationWizard() {
    const { auth } = usePage<SharedData>().props;
    const existingCompanyId = auth.user?.company_id ?? null;

    const [localStep, setLocalStep] = useState(0);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>(EMPTY_ERRORS);
    const [formValues, setFormValues] = useState<RegisterCompanyInput>({
        name: '',
        registration_no: '',
        tax_id: '',
        country: '',
        email_domain: '',
        primary_contact_name: '',
        primary_contact_email: '',
        primary_contact_phone: '',
        address: '',
        phone: '',
        website: '',
        region: '',
    });
    const [selectedDocumentType, setSelectedDocumentType] = useState<CompanyDocumentType>('registration');
    const [submittedCompany, setSubmittedCompany] = useState<Company | null>(null);

    const { data: existingCompany, isLoading: isCompanyLoading } = useCompany(existingCompanyId ?? undefined);
    const registerCompany = useRegisterCompany();
    const uploadDocument = useUploadCompanyDocument();
    const deleteDocument = useDeleteCompanyDocument();
    const onboardingCompleted = existingCompany?.hasCompletedOnboarding ?? false;
    const registeredCompany = submittedCompany ?? (onboardingCompleted ? existingCompany : null);
    const { data: documents = [], isLoading: isDocumentsLoading } = useCompanyDocuments(registeredCompany?.id);

    const currentStep = registeredCompany ? 2 : localStep;
    const hasCompletedRegistration = Boolean(registeredCompany);
    const isSubmitting = registerCompany.isPending;

    const validateStep = (stepIndex: number): boolean => {
        const nextErrors: Record<string, string[]> = {};

        if (stepIndex === 0) {
            REQUIRED_FIELDS_STEP_ONE.forEach((field) => {
                if (!String(formValues[field] ?? '').trim()) {
                    nextErrors[field] = ['This field is required.'];
                }
            });
        }

        if (stepIndex === 1) {
            REQUIRED_FIELDS_STEP_TWO.forEach((field) => {
                if (!String(formValues[field] ?? '').trim()) {
                    nextErrors[field] = ['This field is required.'];
                }
            });
        }

        setFieldErrors(nextErrors);

        return Object.keys(nextErrors).length === 0;
    };

    const handleFieldChange = <T extends keyof RegisterCompanyInput>(field: T, value: RegisterCompanyInput[T]) => {
        setFormValues((previous) => ({
            ...previous,
            [field]: value,
        }));
        if (fieldErrors[field]) {
            setFieldErrors((previous) => ({
                ...previous,
                [field]: [],
            }));
        }
    };

    const handleRegister = () => {
        if (!validateStep(1)) {
            return;
        }

        setFieldErrors(EMPTY_ERRORS);

        registerCompany.mutate(formValues, {
            onSuccess: (company) => {
                setSubmittedCompany(company);
                setLocalStep(2);
                successToast('Company registration submitted for review.');
                router.reload({ only: ['auth'] });
            },
            onError: (error) => {
                setFieldErrors(error.errors ?? EMPTY_ERRORS);
                errorToast(error.message ?? 'Unable to submit registration.');
            },
        });
    };

    const handleUpload = (files: File[]) => {
        if (!registeredCompany || files.length === 0) {
            return;
        }

        const [file] = files;
        const payload: UploadCompanyDocumentInput = {
            companyId: registeredCompany.id,
            type: selectedDocumentType,
            file,
        };

        uploadDocument.mutate(payload, {
            onSuccess: () => {
                successToast('Document uploaded.');
            },
            onError: (error) => {
                errorToast(error.message ?? 'Unable to upload document.');
            },
        });
    };

    const handleDelete = (documentId: number) => {
        if (!registeredCompany) {
            return;
        }

        deleteDocument.mutate(
            { companyId: registeredCompany.id, documentId },
            {
                onSuccess: () => {
                    successToast('Document removed.');
                },
                onError: (error) => {
                    errorToast(error.message ?? 'Unable to delete document.');
                },
            },
        );
    };

    const handleNext = () => {
        if (currentStep === 0) {
            if (validateStep(0)) {
                setLocalStep(1);
            }
            return;
        }

        if (currentStep === 1) {
            handleRegister();
        }
    };

    const handleBack = () => {
        setFieldErrors(EMPTY_ERRORS);
        setLocalStep((previous) => Math.max(previous - 1, 0));
    };

    useEffect(() => {
        if (!existingCompany || existingCompany.hasCompletedOnboarding) {
            return;
        }

        setFormValues((previous) => ({
            ...previous,
            name: previous.name || existingCompany.name || '',
            registration_no: previous.registration_no || existingCompany.registrationNo || '',
            tax_id: previous.tax_id || existingCompany.taxId || '',
            country: previous.country || existingCompany.country || '',
            email_domain: previous.email_domain || existingCompany.emailDomain || '',
            primary_contact_name: previous.primary_contact_name || existingCompany.primaryContactName || '',
            primary_contact_email: previous.primary_contact_email || existingCompany.primaryContactEmail || '',
            primary_contact_phone: previous.primary_contact_phone || existingCompany.primaryContactPhone || '',
            address: previous.address || existingCompany.address || '',
            phone: previous.phone || existingCompany.phone || '',
            website: previous.website || existingCompany.website || '',
            region: previous.region || existingCompany.region || '',
        }));
    }, [existingCompany]);

    const renderStepContent = () => {
        if (currentStep === 0) {
            return (
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div className="col-span-full space-y-2">
                        <Label htmlFor="name">Company legal name*</Label>
                        <Input
                            id="name"
                            value={formValues.name}
                            onChange={(event) => handleFieldChange('name', event.target.value)}
                            placeholder="Elements Supply AI LLC"
                            autoComplete="organization"
                        />
                        <InputError message={fieldErrors.name?.[0]} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="registration_no">Registration number*</Label>
                        <Input
                            id="registration_no"
                            value={formValues.registration_no}
                            onChange={(event) => handleFieldChange('registration_no', event.target.value)}
                            placeholder="US-1234567"
                        />
                        <InputError message={fieldErrors.registration_no?.[0]} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="tax_id">Tax ID*</Label>
                        <Input
                            id="tax_id"
                            value={formValues.tax_id}
                            onChange={(event) => handleFieldChange('tax_id', event.target.value)}
                            placeholder="99-9999999"
                        />
                        <InputError message={fieldErrors.tax_id?.[0]} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="country">Incorporation country (ISO-2)*</Label>
                        <Input
                            id="country"
                            value={formValues.country}
                            onChange={(event) => handleFieldChange('country', event.target.value.toUpperCase())}
                            placeholder="US"
                        />
                        <InputError message={fieldErrors.country?.[0]} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="region">Primary region</Label>
                        <Input
                            id="region"
                            value={formValues.region ?? ''}
                            onChange={(event) => handleFieldChange('region', event.target.value)}
                            placeholder="North America"
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="phone">Company phone</Label>
                        <Input
                            id="phone"
                            value={formValues.phone ?? ''}
                            onChange={(event) => handleFieldChange('phone', event.target.value)}
                            placeholder="+1 (555) 123-4567"
                        />
                    </div>

                    <div className="col-span-full space-y-2">
                        <Label htmlFor="address">Registered address</Label>
                        <Input
                            id="address"
                            value={formValues.address ?? ''}
                            onChange={(event) => handleFieldChange('address', event.target.value)}
                            placeholder="123 Industrial Way, Suite 200, Austin, TX"
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="website">Company website</Label>
                        <Input
                            id="website"
                            value={formValues.website ?? ''}
                            onChange={(event) => handleFieldChange('website', event.target.value)}
                            placeholder="https://example.com"
                        />
                    </div>
                </div>
            );
        }

        if (currentStep === 1) {
            return (
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="email_domain">Company email domain*</Label>
                        <Input
                            id="email_domain"
                            value={formValues.email_domain}
                            onChange={(event) => handleFieldChange('email_domain', event.target.value.toLowerCase())}
                            placeholder="example.com"
                        />
                        <InputError message={fieldErrors.email_domain?.[0]} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="primary_contact_name">Primary contact name*</Label>
                        <Input
                            id="primary_contact_name"
                            value={formValues.primary_contact_name}
                            onChange={(event) => handleFieldChange('primary_contact_name', event.target.value)}
                            placeholder="Alex Reynolds"
                        />
                        <InputError message={fieldErrors.primary_contact_name?.[0]} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="primary_contact_email">Primary contact email*</Label>
                        <Input
                            id="primary_contact_email"
                            type="email"
                            value={formValues.primary_contact_email}
                            onChange={(event) => handleFieldChange('primary_contact_email', event.target.value)}
                            placeholder="alex@example.com"
                        />
                        <InputError message={fieldErrors.primary_contact_email?.[0]} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="primary_contact_phone">Primary contact phone*</Label>
                        <Input
                            id="primary_contact_phone"
                            value={formValues.primary_contact_phone}
                            onChange={(event) => handleFieldChange('primary_contact_phone', event.target.value)}
                            placeholder="+1 (555) 987-6543"
                        />
                        <InputError message={fieldErrors.primary_contact_phone?.[0]} />
                    </div>

                    <div className="col-span-full rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                        <div className="flex items-start gap-3">
                            <ShieldAlert className="mt-0.5 size-4" aria-hidden />
                            <div>
                                <p className="font-medium">Use a monitored mailbox</p>
                                <p>
                                    The primary contact receives approval decisions, document reminders, and plan activation notices.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            );
        }

        if (registeredCompany) {
            return (
                <div className="space-y-6">
                    <Card>
                        <CardHeader className="space-y-1">
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <Building2 className="size-5" aria-hidden />
                                <span>{registeredCompany.name}</span>
                                <StatusBadge status={registeredCompany.status} />
                            </CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Your registration has been submitted. Platform admins will review the information and notify you once your tenant is approved.
                            </p>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm text-muted-foreground">
                            <div className="grid grid-cols-1 gap-1 md:grid-cols-2">
                                <p><span className="font-medium text-foreground">Registration #:</span> {registeredCompany.registrationNo}</p>
                                <p><span className="font-medium text-foreground">Tax ID:</span> {registeredCompany.taxId}</p>
                                <p><span className="font-medium text-foreground">Email domain:</span> {registeredCompany.emailDomain}</p>
                                <p><span className="font-medium text-foreground">Primary contact:</span> {registeredCompany.primaryContactName} ({registeredCompany.primaryContactEmail})</p>
                            </div>
                            {registeredCompany.rejectionReason && (
                                <div className="rounded-lg border border-red-100 bg-red-50 p-3 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-100">
                                    <p className="text-sm font-medium">Rejection notes</p>
                                    <p className="text-sm">{registeredCompany.rejectionReason}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-base font-semibold">Compliance documents</h3>
                                <p className="text-sm text-muted-foreground">
                                    Upload supporting certificates now or from the company profile settings page later.
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
                            label="Drag and drop KYC documents"
                            description="Accepted formats: PDF, JPG, PNG (max 5 MB)"
                            accept={['application/pdf', 'image/jpeg', 'image/png']}
                            disabled={uploadDocument.isPending}
                            onFilesSelected={handleUpload}
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Uploaded documents</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {isDocumentsLoading ? (
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Spinner className="size-4" />
                                        <span>Loading documents…</span>
                                    </div>
                                ) : documents.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No documents uploaded yet. Add registration, tax, and compliance certificates to accelerate approval.
                                    </p>
                                ) : (
                                    <ul className="divide-y divide-border/60">
                                        {documents.map((document) => {
                                            const friendlyType = DOCUMENT_OPTIONS.find((option) => option.value === document.type)?.label ?? document.type;

                                            return (
                                                <li key={document.id} className="flex items-center justify-between gap-4 py-3 text-sm">
                                                    <div className="flex items-center gap-2">
                                                        <FileText className="size-4 text-muted-foreground" aria-hidden />
                                                        <div className="flex flex-col">
                                                            <span className="font-medium text-foreground">{friendlyType}</span>
                                                            <span className="text-xs text-muted-foreground">
                                                                Uploaded {document.createdAt ? formatDistanceToNow(new Date(document.createdAt), { addSuffix: true }) : 'recently'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        disabled={deleteDocument.isPending}
                                                        onClick={() => handleDelete(document.id)}
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

                        <div className="flex flex-wrap items-center gap-3">
                            <Button asChild variant="secondary">
                                <Link href="/settings/company-profile">
                                    Manage company profile
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href="/dashboard">Back to dashboard</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            );
        }

        return (
            <div className="rounded-lg border border-muted-foreground/20 bg-muted/40 p-6 text-sm text-muted-foreground">
                <div className="flex items-start gap-3">
                    <Spinner className="mt-0.5 size-4" />
                    <p>Submitting your registration…</p>
                </div>
            </div>
        );
    };

    const showBackButton = currentStep > 0 && currentStep < 2 && !hasCompletedRegistration;
    const showNextButton = currentStep < 2 && !hasCompletedRegistration;

    return (
        <AuthLayout
            title="Register your company"
            description="Complete the onboarding wizard so platform admins can approve your tenant."
            maxWidthClass="max-w-4xl"
        >
            <Head title="Company registration" />

            {existingCompanyId && isCompanyLoading && !existingCompany ? (
                <div className="flex items-center justify-center py-12">
                    <Spinner className="size-6" />
                </div>
            ) : (
                <div className="space-y-8">
                    <ol className="flex flex-col gap-4 md:flex-row md:items-start md:gap-6">
                        {steps.map((step, index) => {
                            const isActive = currentStep === index;
                            const isCompleted = currentStep > index || (index === 2 && registeredCompany !== null);

                            return (
                                <li
                                    key={step.id}
                                    className={`flex-1 rounded-lg border border-muted-foreground/30 p-4 transition ${
                                        isActive
                                            ? 'bg-background shadow-sm'
                                            : isCompleted
                                                ? 'bg-emerald-50/70 dark:bg-emerald-500/10'
                                                : 'bg-muted/50'
                                    }`}
                                >
                                    <div className="flex items-center gap-3 text-sm font-medium text-foreground">
                                        <span className={`flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold ${
                                            isCompleted
                                                ? 'bg-emerald-500 text-white'
                                                : isActive
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'bg-muted-foreground/20 text-muted-foreground'
                                        }`}>
                                            {index + 1}
                                        </span>
                                        {step.title}
                                    </div>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {step.description}
                                    </p>
                                </li>
                            );
                        })}
                    </ol>

                    <div className="rounded-xl border border-muted-foreground/30 bg-background/95 p-6 shadow-sm">
                        {hasCompletedRegistration && registeredCompany ? (
                            renderStepContent()
                        ) : (
                            <>
                                {renderStepContent()}
                                <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
                                    {showBackButton ? (
                                        <Button variant="ghost" onClick={handleBack} type="button">
                                            Back
                                        </Button>
                                    ) : (
                                        <span />
                                    )}

                                    {showNextButton && (
                                        <div className="flex items-center gap-3">
                                            {currentStep === 1 && (
                                                <p className="text-xs text-muted-foreground">
                                                    By submitting, you confirm the accuracy of your company details.
                                                </p>
                                            )}
                                            <Button
                                                type="button"
                                                onClick={handleNext}
                                                disabled={isSubmitting}
                                                data-test="company-registration-next"
                                            >
                                                {isSubmitting && <Spinner className="mr-2 size-4" />}
                                                {currentStep === 1 ? 'Submit for review' : 'Continue'}
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </>
                        )}
                    </div>
                </div>
            )}
        </AuthLayout>
    );
}
