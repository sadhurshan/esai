import { zodResolver } from '@hookform/resolvers/zod';
import {
    AlertCircle,
    Building2,
    Clock,
    Download,
    Loader2,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type ComponentType,
} from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';

import { FileDropzone } from '@/components/file-dropzone';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';
import {
    DOCUMENT_ACCEPT_EXTENSIONS,
    DOCUMENT_ACCEPT_LABEL,
    DOCUMENT_MAX_SIZE_MB,
} from '@/config/documents';
import { useAuth } from '@/contexts/auth-context';
import {
    useSupplierApplications,
    useWithdrawSupplierApplication,
} from '@/hooks/api/useSupplierApplications';
import {
    useDeleteSupplierDocument,
    useSupplierDocuments,
    useUploadSupplierDocument,
    type SupplierDocument,
    type SupplierDocumentType,
} from '@/hooks/api/useSupplierDocuments';
import {
    useApplyForSupplier,
    useSupplierSelfStatus,
    useUpdateSupplierVisibility,
    type DirectoryVisibility,
    type SupplierApplicationPayload,
    type SupplierApplicationStatusValue,
    type SupplierSelfStatus,
} from '@/hooks/api/useSupplierSelfService';

const optionalTextField = (max: number, message?: string) =>
    z
        .string()
        .max(max, message ?? `Maximum ${max} characters.`)
        .optional()
        .or(z.literal(''));

const listField = z
    .string()
    .max(500, 'Keep this under 500 characters.')
    .optional()
    .or(z.literal(''));
const optionalEmail = z.union([
    z.literal(''),
    z.string().email('Enter a valid email address.'),
]);
const optionalUrl = z.union([
    z.literal(''),
    z.string().url('Enter a valid URL (https://example.com).'),
]);
const optionalCountry = z.union([
    z.literal(''),
    z.string().length(2, 'Use a 2-letter country code.'),
]);

const positiveInteger = z
    .preprocess(
        (value) => {
            if (value === '' || value === null || value === undefined) {
                return undefined;
            }
            if (typeof value === 'number') {
                return Number.isFinite(value) ? value : undefined;
            }
            if (typeof value === 'string') {
                const trimmed = value.trim();
                if (trimmed === '') {
                    return undefined;
                }
                return Number(trimmed);
            }
            return value;
        },
        z
            .number({ invalid_type_error: 'Enter a valid whole number.' })
            .int('Use whole numbers only.')
            .min(1, 'Must be at least 1.'),
    )
    .optional();

const supplierApplicationSchema = z
    .object({
        description: optionalTextField(500),
        website: optionalUrl,
        address: optionalTextField(191),
        city: optionalTextField(160),
        country: optionalCountry,
        moq: positiveInteger,
        minOrderQty: positiveInteger,
        leadTimeDays: positiveInteger,
        capabilitiesMethods: listField,
        capabilitiesMaterials: listField,
        capabilitiesFinishes: listField,
        capabilitiesTolerances: listField,
        capabilitiesIndustries: listField,
        certifications: listField,
        contactName: optionalTextField(160),
        contactEmail: optionalEmail,
        contactPhone: optionalTextField(60),
        notes: optionalTextField(255),
        facilities: optionalTextField(500),
    })
    .superRefine((values, ctx) => {
        const capabilityInputs = [
            values.capabilitiesMethods,
            values.capabilitiesMaterials,
            values.capabilitiesFinishes,
            values.capabilitiesTolerances,
            values.capabilitiesIndustries,
        ];
        const hasCapabilities = capabilityInputs.some((value) =>
            Boolean(value && value.trim().length > 0),
        );

        if (!hasCapabilities) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                path: ['capabilitiesMethods'],
                message: 'Add at least one capability or industry focus.',
            });
        }

        const hasContact =
            Boolean(
                values.contactEmail && values.contactEmail.trim().length > 0,
            ) ||
            Boolean(
                values.contactPhone && values.contactPhone.trim().length > 0,
            ) ||
            Boolean(values.contactName && values.contactName.trim().length > 0);

        if (!hasContact) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                path: ['contactEmail'],
                message:
                    'Provide a contact email, phone, or name for follow-ups.',
            });
        }
    });

export type SupplierApplicationFormValues = z.infer<
    typeof supplierApplicationSchema
>;

type SupplierStatus =
    | 'none'
    | 'pending'
    | 'approved'
    | 'rejected'
    | 'suspended';

const STATUS_META: Record<
    SupplierStatus,
    {
        label: string;
        description: string;
        icon: ComponentType<{ className?: string }>;
        badge: 'default' | 'secondary' | 'outline' | 'destructive';
    }
> = {
    none: {
        label: 'Not applied',
        description: 'Your company has not requested supplier access yet.',
        icon: Building2,
        badge: 'secondary',
    },
    pending: {
        label: 'Pending review',
        description:
            'Our team is reviewing your submission. Expect an update soon.',
        icon: Clock,
        badge: 'outline',
    },
    approved: {
        label: 'Approved supplier',
        description:
            'Supplier tooling, invitations, and RFQ replies are enabled.',
        icon: ShieldCheck,
        badge: 'default',
    },
    rejected: {
        label: 'Application rejected',
        description:
            'The last application was declined. Update your profile and reapply.',
        icon: AlertCircle,
        badge: 'destructive',
    },
    suspended: {
        label: 'Supplier suspended',
        description:
            'Access is temporarily disabled. Contact support for next steps.',
        icon: AlertCircle,
        badge: 'destructive',
    },
};

const APPLICATION_STATUS_META: Record<
    SupplierApplicationStatusValue,
    {
        label: string;
        badge: 'default' | 'secondary' | 'outline' | 'destructive';
    }
> = {
    pending: {
        label: STATUS_META.pending.label,
        badge: STATUS_META.pending.badge,
    },
    approved: {
        label: STATUS_META.approved.label,
        badge: STATUS_META.approved.badge,
    },
    rejected: {
        label: STATUS_META.rejected.label,
        badge: STATUS_META.rejected.badge,
    },
};

const DOCUMENT_TYPE_OPTIONS: Array<{
    label: string;
    value: SupplierDocumentType;
}> = [
    { label: 'ISO 9001', value: 'iso9001' },
    { label: 'ISO 14001', value: 'iso14001' },
    { label: 'AS 9100', value: 'as9100' },
    { label: 'ITAR', value: 'itar' },
    { label: 'REACH', value: 'reach' },
    { label: 'RoHS', value: 'rohs' },
    { label: 'Insurance', value: 'insurance' },
    { label: 'NDA', value: 'nda' },
    { label: 'Other', value: 'other' },
];

const DIRECTORY_VISIBILITY_META: Record<
    DirectoryVisibility,
    { label: string; description: string }
> = {
    private: {
        label: 'Private (default)',
        description:
            'Only your internal buyers can view this supplier profile. You remain hidden from the marketplace directory.',
    },
    public: {
        label: 'Public listing',
        description:
            'Buyers browsing the directory can discover your profile, capabilities, and compliance summary.',
    },
};

const DIRECTORY_VISIBILITY_OPTIONS: Array<{
    value: DirectoryVisibility;
    label: string;
}> = [
    { value: 'private', label: 'Private' },
    { value: 'public', label: 'Public' },
];

function splitList(value?: string | null): string[] | undefined {
    if (!value) {
        return undefined;
    }

    const items = value
        .split(/[\n,]/)
        .map((item) => item.trim())
        .filter((item) => item.length > 0);

    return items.length > 0 ? items : undefined;
}

function buildCapabilities(values: SupplierApplicationFormValues) {
    const payload = {
        methods: splitList(values.capabilitiesMethods),
        materials: splitList(values.capabilitiesMaterials),
        finishes: splitList(values.capabilitiesFinishes),
        tolerances: splitList(values.capabilitiesTolerances),
        industries: splitList(values.capabilitiesIndustries),
    };

    return Object.entries(payload).reduce<
        SupplierApplicationPayload['capabilities']
    >((acc, [key, list]) => {
        if (list && list.length > 0) {
            acc[key as keyof SupplierApplicationPayload['capabilities']] = list;
        }
        return acc;
    }, {});
}

export function SupplierApplicationPanel() {
    const { state, refresh, activePersona } = useAuth();
    const role = state.user?.role ?? null;
    const isSupplierPersona = activePersona?.type === 'supplier';
    const initialStatus: SupplierSelfStatus | undefined = state.company
        ? {
              supplier_status: (state.company.supplier_status ??
                  'none') as string,
              directory_visibility: (state.company.directory_visibility ??
                  'private') as DirectoryVisibility,
              supplier_profile_completed_at:
                  state.company.supplier_profile_completed_at ?? null,
              is_listed: Boolean(
                  (state.company as { is_listed?: boolean }).is_listed ?? false,
              ),
              current_application: null,
          }
        : undefined;
    const supplierStatusQuery = useSupplierSelfStatus(initialStatus);
    const supplierStatusData = supplierStatusQuery.data ?? initialStatus;
    const rawStatus = (supplierStatusData?.supplier_status ??
        'none') as SupplierStatus;
    const hasApplication = Boolean(supplierStatusData?.current_application);
    const status =
        rawStatus === 'pending' &&
        !hasApplication &&
        state.company?.start_mode === 'supplier'
            ? 'none'
            : rawStatus;
    const directoryVisibility = (supplierStatusData?.directory_visibility ??
        'private') as DirectoryVisibility;
    const supplierProfileCompletedAt =
        supplierStatusData?.supplier_profile_completed_at ?? null;
    const isListed = Boolean(supplierStatusData?.is_listed ?? false);
    const companyStatus = state.company?.status ?? 'pending';
    const isCompanyApproved = ['active', 'trial'].includes(companyStatus);
    const meta = STATUS_META[status] ?? STATUS_META.none;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);
    const applyMutation = useApplyForSupplier();
    const updateVisibilityMutation = useUpdateSupplierVisibility();
    const isOwner = role === 'owner';
    const canViewApplications =
        !isSupplierPersona && (isOwner || role === 'buyer_admin');
    const supplierApplicationsQuery = useSupplierApplications({
        enabled: canViewApplications,
    });
    const supplierApplications = supplierApplicationsQuery.data?.items ?? [];
    const withdrawApplicationMutation = useWithdrawSupplierApplication();
    const [withdrawingId, setWithdrawingId] = useState<number | null>(null);
    const supplierDocumentsQuery = useSupplierDocuments();
    const uploadSupplierDocumentMutation = useUploadSupplierDocument();
    const deleteSupplierDocumentMutation = useDeleteSupplierDocument();
    const documents = useMemo(
        () => supplierDocumentsQuery.data?.items ?? [],
        [supplierDocumentsQuery.data],
    );
    const documentsLoading = supplierDocumentsQuery.isLoading;
    const [visibilityDraft, setVisibilityDraft] =
        useState<DirectoryVisibility>(directoryVisibility);
    const documentSectionRef = useRef<HTMLDivElement | null>(null);
    const expiredDocuments = useMemo(
        () => documents.filter((document) => document.status === 'expired'),
        [documents],
    );
    const expiringDocuments = useMemo(
        () => documents.filter((document) => document.status === 'expiring'),
        [documents],
    );
    const documentAlertDocuments =
        expiredDocuments.length > 0 ? expiredDocuments : expiringDocuments;
    const shouldShowDocumentAlert =
        !documentsLoading && documentAlertDocuments.length > 0;
    const hasCompletedProfile = Boolean(supplierProfileCompletedAt);
    const isUpdatingVisibility = updateVisibilityMutation.isPending;
    const canEditVisibility =
        isOwner && isCompanyApproved && status === 'approved';
    const isVisibilityDirty = visibilityDraft !== directoryVisibility;
    const requiresDocumentReview = expiredDocuments.length > 0;
    const isPublicOptionDisabled =
        requiresDocumentReview || !hasCompletedProfile;
    const visibilitySubmitDisabled =
        !canEditVisibility || !isVisibilityDirty || isUpdatingVisibility;
    const visibilityBlockedReason = (() => {
        if (!isOwner) {
            return 'Only workspace owners can change directory visibility.';
        }
        if (!isCompanyApproved) {
            return 'Company verification must be approved before updating directory listings.';
        }
        if (status !== 'approved') {
            return 'Get supplier approval before publishing the profile to the directory.';
        }
        return null;
    })();
    const publicDisabledReason = (() => {
        if (requiresDocumentReview) {
            return 'Resolve expired compliance documents to list publicly again.';
        }
        if (!hasCompletedProfile) {
            return 'Complete your supplier profile before going public.';
        }
        return null;
    })();
    const listingStatusCopy = (() => {
        if (isListed) {
            return {
                title: 'Visible in directory',
                description:
                    'Buyers searching the Elements directory can reach out with new RFQs and invitations.',
            };
        }

        if (directoryVisibility === 'public') {
            return {
                title: 'Listing paused',
                description: requiresDocumentReview
                    ? 'Expired compliance documents temporarily hid your listing. Upload renewed files to restore visibility.'
                    : 'We are syncing your directory listing. This usually resolves in a few minutes.',
            };
        }

        return {
            title: 'Private listing',
            description:
                'Keep your supplier profile hidden until you are ready for inbound buyer requests.',
        };
    })();
    const [selectedDocumentIds, setSelectedDocumentIds] = useState<number[]>(
        [],
    );
    const [documentType, setDocumentType] =
        useState<SupplierDocumentType>('iso9001');
    const [documentIssuedAt, setDocumentIssuedAt] = useState('');
    const [documentExpiresAt, setDocumentExpiresAt] = useState('');
    const [pendingFile, setPendingFile] = useState<File | null>(null);
    const [documentError, setDocumentError] = useState<string | null>(null);
    const [deletingDocumentId, setDeletingDocumentId] = useState<number | null>(
        null,
    );
    const canApply =
        !isSupplierPersona &&
        isOwner &&
        isCompanyApproved &&
        (status === 'none' || status === 'rejected');
    const actionLabel = useMemo(() => {
        if (!isCompanyApproved) {
            return 'Awaiting company approval';
        }

        if (!isOwner) {
            return 'Owner access required';
        }

        switch (status) {
            case 'pending':
                return 'Application pending';
            case 'approved':
                return 'Supplier access enabled';
            case 'suspended':
                return 'Supplier access suspended';
            case 'rejected':
                return 'Reapply as supplier';
            default:
                return 'Apply as supplier';
        }
    }, [isCompanyApproved, isOwner, status]);

    useEffect(() => {
        setVisibilityDraft(directoryVisibility);
    }, [directoryVisibility]);

    useEffect(() => {
        setSelectedDocumentIds((previous) =>
            previous.filter((id) =>
                documents.some((document) => document.id === id),
            ),
        );
    }, [documents]);

    const scrollToDocumentSection = () => {
        if (documentSectionRef.current) {
            documentSectionRef.current.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }
    };

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
        reset,
    } = useForm<SupplierApplicationFormValues>({
        resolver: zodResolver(supplierApplicationSchema),
        defaultValues: {
            description: '',
            website: '',
            address: '',
            city: '',
            country: '',
            moq: undefined,
            minOrderQty: undefined,
            leadTimeDays: undefined,
            capabilitiesMethods: '',
            capabilitiesMaterials: '',
            capabilitiesFinishes: '',
            capabilitiesTolerances: '',
            capabilitiesIndustries: '',
            certifications: '',
            contactName: '',
            contactEmail: '',
            contactPhone: '',
            notes: '',
            facilities: '',
        },
    });

    const onSubmit = handleSubmit(async (values) => {
        setFormError(null);
        const payload: SupplierApplicationPayload = {
            description: values.description || undefined,
            capabilities: buildCapabilities(values),
            address: values.address || undefined,
            city: values.city || undefined,
            country: values.country || undefined,
            moq: values.moq,
            min_order_qty: values.minOrderQty,
            lead_time_days: values.leadTimeDays,
            certifications: splitList(values.certifications),
            facilities: values.facilities || undefined,
            website: values.website || undefined,
            contact: {
                name: values.contactName || undefined,
                email: values.contactEmail || undefined,
                phone: values.contactPhone || undefined,
            },
            notes: values.notes || undefined,
        };

        if (selectedDocumentIds.length > 0) {
            payload.documents = selectedDocumentIds;
        }

        try {
            await applyMutation.mutateAsync(payload);
            publishToast({
                variant: 'success',
                title: 'Application submitted',
                description:
                    'We will notify you once the supplier review is complete.',
            });
            setDialogOpen(false);
            reset();
            await refresh();
            await supplierStatusQuery.refetch();
            if (canViewApplications) {
                await supplierApplicationsQuery.refetch();
            }
            setSelectedDocumentIds([]);
        } catch (error) {
            setFormError(
                error instanceof Error
                    ? error.message
                    : 'Unable to submit your supplier application.',
            );
        }
    });

    const handleVisibilitySubmit = async () => {
        if (visibilitySubmitDisabled) {
            return;
        }

        try {
            await updateVisibilityMutation.mutateAsync({
                visibility: visibilityDraft,
            });
            publishToast({
                variant: 'success',
                title: 'Directory visibility updated',
                description:
                    visibilityDraft === 'public'
                        ? 'Your supplier profile is now discoverable in the Elements directory.'
                        : 'Your supplier profile is now private to your workspace.',
            });
            await supplierStatusQuery.refetch();
            await refresh();
        } catch (error) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to update visibility',
                description:
                    error instanceof Error
                        ? error.message
                        : 'Try again in a few moments.',
            });
            setVisibilityDraft(directoryVisibility);
        }
    };

    const StatusIcon = meta.icon;
    const pendingApplication =
        status === 'pending'
            ? (supplierStatusData?.current_application ?? null)
            : null;
    const blockingDocuments = pendingApplication?.documents ?? [];
    const statusDescription = useMemo(() => {
        if (status === 'approved') {
            return 'You can now receive RFQs, quotes, and POs from buyers across the Elements Supply network.';
        }

        if (status === 'pending') {
            if (pendingApplication?.auto_reverification) {
                return 'We paused supplier access because critical certificates expired. Upload renewed documents to resume review.';
            }

            return 'Applications typically take 1-2 business days. We will email the owner once a decision is made.';
        }

        return meta.description;
    }, [meta.description, pendingApplication?.auto_reverification, status]);

    const documentStatusVariant: Record<
        SupplierDocument['status'],
        'default' | 'secondary' | 'destructive'
    > = {
        valid: 'default',
        expiring: 'secondary',
        expired: 'destructive',
    };

    const formatDate = (value?: string | null) => {
        if (!value) {
            return '—';
        }

        const parsed = new Date(value);

        return Number.isNaN(parsed.getTime())
            ? '—'
            : parsed.toLocaleDateString();
    };

    const formatFileSize = (value: number): string => {
        if (!value || value <= 0) {
            return '0 KB';
        }

        const units = ['B', 'KB', 'MB', 'GB'];
        let size = value;
        let unitIndex = 0;

        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }

        return `${size.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
    };

    const formatDateTime = (value?: string | null) => {
        if (!value) {
            return '—';
        }

        const parsed = new Date(value);

        if (Number.isNaN(parsed.getTime())) {
            return '—';
        }

        return parsed.toLocaleString();
    };

    const handleDocumentToggle = (documentId: number, checked: boolean) => {
        setSelectedDocumentIds((previous) => {
            if (checked) {
                if (previous.includes(documentId)) {
                    return previous;
                }

                return [...previous, documentId];
            }

            return previous.filter((id) => id !== documentId);
        });
    };

    const handleDocumentUpload = async () => {
        if (!pendingFile) {
            setDocumentError('Attach a file before uploading.');
            return;
        }

        setDocumentError(null);

        try {
            const newDocument =
                await uploadSupplierDocumentMutation.mutateAsync({
                    file: pendingFile,
                    type: documentType,
                    issued_at: documentIssuedAt || undefined,
                    expires_at: documentExpiresAt || undefined,
                });

            setPendingFile(null);
            setDocumentIssuedAt('');
            setDocumentExpiresAt('');
            setSelectedDocumentIds((previous) =>
                previous.includes(newDocument.id)
                    ? previous
                    : [...previous, newDocument.id],
            );

            publishToast({
                variant: 'success',
                title: 'Document uploaded',
                description:
                    'Your compliance document is ready to attach to this application.',
            });
        } catch (error) {
            setDocumentError(
                error instanceof Error
                    ? error.message
                    : 'Unable to upload the document.',
            );
        }
    };

    const handleWithdrawApplication = async (applicationId: number) => {
        if (!isOwner) {
            return;
        }

        setWithdrawingId(applicationId);

        try {
            await withdrawApplicationMutation.mutateAsync(applicationId);
            publishToast({
                variant: 'success',
                title: 'Application withdrawn',
                description:
                    'You can resubmit once your profile and documents are ready.',
            });
            await refresh();
            await supplierStatusQuery.refetch();
            if (canViewApplications) {
                await supplierApplicationsQuery.refetch();
            }
        } catch (error) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to withdraw application',
                description:
                    error instanceof Error
                        ? error.message
                        : 'Try again in a moment.',
            });
        } finally {
            setWithdrawingId(null);
        }
    };

    const handleDeleteDocument = async (documentId: number) => {
        setDeletingDocumentId(documentId);

        try {
            await deleteSupplierDocumentMutation.mutateAsync(documentId);
            setSelectedDocumentIds((previous) =>
                previous.filter((id) => id !== documentId),
            );
            publishToast({
                variant: 'success',
                title: 'Document removed',
                description:
                    'The document is no longer available for future applications.',
            });
        } catch (error) {
            publishToast({
                variant: 'destructive',
                title: 'Failed to delete document',
                description:
                    error instanceof Error
                        ? error.message
                        : 'Try again in a moment.',
            });
        } finally {
            setDeletingDocumentId(null);
        }
    };

    const handleFileSelection = (files: File[]) => {
        if (files.length === 0) {
            return;
        }

        setPendingFile(files[0]);
        setDocumentError(null);
    };

    return (
        <Card>
            <CardHeader className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle className="text-lg">
                        Supplier application
                    </CardTitle>
                    <CardDescription>
                        Control when your company participates as a supplier.
                        Owners can submit the profile below for review.
                    </CardDescription>
                </div>
                <Badge variant={meta.badge} className="flex items-center gap-1">
                    <StatusIcon className="h-3.5 w-3.5" />
                    {meta.label}
                </Badge>
            </CardHeader>
            <CardContent className="space-y-4">
                <p className="text-sm text-muted-foreground">
                    {statusDescription}
                </p>

                {status === 'pending' && pendingApplication ? (
                    <Alert
                        variant={
                            pendingApplication.auto_reverification
                                ? 'destructive'
                                : 'default'
                        }
                    >
                        <AlertDescription className="space-y-3">
                            {pendingApplication.auto_reverification ? (
                                <span>
                                    Access is temporarily paused because
                                    required certificates expired. Upload
                                    refreshed documents so we can complete
                                    re-verification.
                                </span>
                            ) : (
                                <span>
                                    Your submission is waiting for review. We
                                    will notify the owner once a decision is
                                    made.
                                </span>
                            )}
                            <div className="space-y-1 text-xs text-muted-foreground">
                                {pendingApplication.submitted_at ? (
                                    <p>
                                        Submitted{' '}
                                        {formatDateTime(
                                            pendingApplication.submitted_at,
                                        )}
                                    </p>
                                ) : null}
                                {pendingApplication.notes ? (
                                    <p>Notes: {pendingApplication.notes}</p>
                                ) : null}
                            </div>
                            {blockingDocuments.length > 0 ? (
                                <div className="space-y-2 rounded-md border border-border/60 bg-background/80 p-3">
                                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Documents requiring updates
                                    </p>
                                    <div className="space-y-2">
                                        {blockingDocuments.map((document) => {
                                            const documentLabel = document.type
                                                ? document.type.replace(
                                                      /_/g,
                                                      ' ',
                                                  )
                                                : 'Document';

                                            return (
                                                <div
                                                    key={document.id}
                                                    className="flex flex-col gap-1 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
                                                >
                                                    <div className="flex flex-col gap-0.5">
                                                        <p className="font-medium text-foreground capitalize">
                                                            {documentLabel}
                                                        </p>
                                                        <p>
                                                            Expires{' '}
                                                            {formatDate(
                                                                document.expires_at,
                                                            )}{' '}
                                                            •{' '}
                                                            {document.status.toUpperCase()}
                                                        </p>
                                                    </div>
                                                    <Badge
                                                        variant={
                                                            documentStatusVariant[
                                                                document.status
                                                            ]
                                                        }
                                                        className="w-fit"
                                                    >
                                                        {document.status}
                                                    </Badge>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            ) : null}
                        </AlertDescription>
                    </Alert>
                ) : null}

                {!isOwner ? (
                    <Alert>
                        <AlertDescription>
                            Only workspace owners can submit or re-submit
                            supplier applications. Ask your owner to start the
                            process if you need supplier access.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {!isCompanyApproved ? (
                    <Alert>
                        <AlertDescription>
                            Company verification is still pending. Platform
                            operations must approve your documents before
                            supplier tools are unlocked.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {status === 'approved' && isCompanyApproved ? (
                    <Alert>
                        <AlertDescription>
                            Supplier tooling is live. To stay listed in the
                            supplier directory, keep your profile current and
                            ensure your compliance documents remain valid.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {status === 'rejected' ? (
                    <Alert variant="destructive">
                        <AlertDescription>
                            The previous submission was rejected. Update your
                            capabilities and certifications before applying
                            again.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {shouldShowDocumentAlert ? (
                    <Alert
                        variant={
                            expiredDocuments.length > 0
                                ? 'destructive'
                                : 'default'
                        }
                    >
                        <AlertDescription className="space-y-2">
                            <p className="text-sm font-medium text-foreground">
                                {expiredDocuments.length > 0
                                    ? `You have ${expiredDocuments.length} compliance document${expiredDocuments.length === 1 ? '' : 's'} that expired.`
                                    : `You have ${expiringDocuments.length} compliance document${expiringDocuments.length === 1 ? '' : 's'} expiring soon.`}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {expiredDocuments.length > 0
                                    ? 'Upload replacement certificates to resume supplier directory visibility.'
                                    : 'Provide renewed certificates to stay in good standing before access is paused.'}
                            </p>
                            <div className="space-y-1 text-xs text-muted-foreground">
                                {documentAlertDocuments
                                    .slice(0, 3)
                                    .map((document) => (
                                        <div
                                            key={document.id}
                                            className="flex flex-col gap-0.5 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <span className="font-medium text-foreground capitalize">
                                                {document.type?.replace(
                                                    /_/g,
                                                    ' ',
                                                ) ?? 'Document'}
                                            </span>
                                            <span>
                                                {document.status === 'expired'
                                                    ? 'Expired'
                                                    : 'Expires'}{' '}
                                                {formatDate(
                                                    document.expires_at,
                                                )}
                                            </span>
                                        </div>
                                    ))}
                                {documentAlertDocuments.length > 3 ? (
                                    <p>
                                        +{documentAlertDocuments.length - 3}{' '}
                                        more document
                                        {documentAlertDocuments.length - 3 === 1
                                            ? ''
                                            : 's'}{' '}
                                        need attention
                                    </p>
                                ) : null}
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={scrollToDocumentSection}
                            >
                                Review documents
                            </Button>
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="space-y-4 rounded-xl border border-border/80 bg-card/30 p-4">
                    <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-sm font-medium">
                                Directory visibility
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Control whether buyers browsing the Elements
                                directory can discover your supplier profile.
                            </p>
                        </div>
                        <Badge
                            variant={
                                directoryVisibility === 'public'
                                    ? 'default'
                                    : 'outline'
                            }
                            className="w-fit uppercase"
                        >
                            {
                                DIRECTORY_VISIBILITY_META[directoryVisibility]
                                    .label
                            }
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {
                            DIRECTORY_VISIBILITY_META[directoryVisibility]
                                .description
                        }
                    </p>
                    <div className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                        <div className="space-y-3">
                            <div className="space-y-2">
                                <Label htmlFor="directory-visibility">
                                    Visibility setting
                                </Label>
                                <Select
                                    value={visibilityDraft}
                                    onValueChange={(value) =>
                                        setVisibilityDraft(
                                            value as DirectoryVisibility,
                                        )
                                    }
                                    disabled={
                                        !canEditVisibility ||
                                        isUpdatingVisibility
                                    }
                                >
                                    <SelectTrigger
                                        id="directory-visibility"
                                        aria-disabled={
                                            !canEditVisibility ||
                                            isUpdatingVisibility
                                        }
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {DIRECTORY_VISIBILITY_OPTIONS.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                    disabled={
                                                        option.value ===
                                                        'public'
                                                            ? isPublicOptionDisabled
                                                            : false
                                                    }
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                {!canEditVisibility &&
                                visibilityBlockedReason ? (
                                    <p className="text-xs text-muted-foreground">
                                        {visibilityBlockedReason}
                                    </p>
                                ) : null}
                                {canEditVisibility &&
                                isPublicOptionDisabled &&
                                publicDisabledReason ? (
                                    <p className="text-xs text-destructive">
                                        {publicDisabledReason}
                                    </p>
                                ) : null}
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    type="button"
                                    onClick={handleVisibilitySubmit}
                                    disabled={visibilitySubmitDisabled}
                                >
                                    {isUpdatingVisibility ? (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    ) : null}
                                    Save visibility
                                </Button>
                                {isVisibilityDirty ? (
                                    <span className="text-xs text-muted-foreground">
                                        Unsaved changes
                                    </span>
                                ) : null}
                            </div>
                        </div>
                        <div className="rounded-lg border border-dashed border-border/80 bg-background/80 p-3 text-xs text-muted-foreground">
                            <p className="text-sm font-medium text-foreground">
                                {listingStatusCopy.title}
                            </p>
                            <p>{listingStatusCopy.description}</p>
                        </div>
                    </div>
                </div>

                <div
                    ref={documentSectionRef}
                    className="space-y-4 rounded-xl border border-border/80 bg-card/30 p-4"
                >
                    <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-sm font-medium">
                                Compliance documents
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Upload certificates, NDAs, and insurance proofs
                                once, then attach them to every supplier
                                application.
                                {` ${DOCUMENT_ACCEPT_LABEL}`}
                            </p>
                        </div>
                        <Badge variant="outline" className="w-fit">
                            {documentsLoading
                                ? 'Loading…'
                                : `${documents.length} file${documents.length === 1 ? '' : 's'}`}
                        </Badge>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-3">
                            <Label>Attach existing documents</Label>
                            <div className="min-h-[120px] space-y-2 rounded-lg border border-dashed border-muted-foreground/40 p-3">
                                {documentsLoading ? (
                                    <div className="animate-pulse space-y-2 text-xs text-muted-foreground">
                                        <div className="h-4 rounded bg-muted" />
                                        <div className="h-4 w-2/3 rounded bg-muted" />
                                        <div className="h-4 w-1/3 rounded bg-muted" />
                                    </div>
                                ) : documents.length === 0 ? (
                                    <p className="text-xs text-muted-foreground">
                                        No supplier documents uploaded yet. Add
                                        a document using the form on the right.
                                    </p>
                                ) : (
                                    documents.map((document) => (
                                        <div
                                            key={document.id}
                                            className="flex items-start gap-3 rounded-lg border border-border/80 bg-background/80 p-3"
                                        >
                                            <Checkbox
                                                id={`document-${document.id}`}
                                                checked={selectedDocumentIds.includes(
                                                    document.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    handleDocumentToggle(
                                                        document.id,
                                                        checked === true,
                                                    )
                                                }
                                                disabled={
                                                    deletingDocumentId ===
                                                    document.id
                                                }
                                            />
                                            <div className="flex flex-1 flex-col gap-1">
                                                <div className="flex items-center gap-2 text-sm font-medium capitalize">
                                                    {document.type.replace(
                                                        '_',
                                                        ' ',
                                                    )}
                                                    <Badge
                                                        variant={
                                                            documentStatusVariant[
                                                                document.status
                                                            ]
                                                        }
                                                        className="uppercase"
                                                    >
                                                        {document.status}
                                                    </Badge>
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    Issued{' '}
                                                    {formatDate(
                                                        document.issued_at,
                                                    )}{' '}
                                                    • Expires{' '}
                                                    {formatDate(
                                                        document.expires_at,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatFileSize(
                                                        document.size_bytes,
                                                    )}{' '}
                                                    • {document.mime}
                                                </p>
                                            </div>
                                            <div className="flex flex-col items-end gap-2 sm:flex-row sm:items-center">
                                                {document.download_url ? (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="View document"
                                                        asChild
                                                    >
                                                        <a
                                                            href={
                                                                document.download_url
                                                            }
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <Download className="h-4 w-4" />
                                                        </a>
                                                    </Button>
                                                ) : null}
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label="Remove document"
                                                    onClick={() =>
                                                        handleDeleteDocument(
                                                            document.id,
                                                        )
                                                    }
                                                    disabled={
                                                        deletingDocumentId ===
                                                        document.id
                                                    }
                                                >
                                                    {deletingDocumentId ===
                                                    document.id ? (
                                                        <Loader2 className="h-4 w-4 animate-spin" />
                                                    ) : (
                                                        <Trash2 className="h-4 w-4" />
                                                    )}
                                                </Button>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Selected documents will be included with your
                                application for the reviewer.
                            </p>
                        </div>
                        <div className="space-y-3">
                            <Label htmlFor="document-type">
                                Upload new document
                            </Label>
                            <div className="grid gap-3">
                                <Select
                                    value={documentType}
                                    onValueChange={(value) =>
                                        setDocumentType(
                                            value as SupplierDocumentType,
                                        )
                                    }
                                >
                                    <SelectTrigger id="document-type">
                                        <SelectValue placeholder="Choose a document type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {DOCUMENT_TYPE_OPTIONS.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="space-y-1">
                                        <Label htmlFor="document-issued-at">
                                            Issued on
                                        </Label>
                                        <Input
                                            id="document-issued-at"
                                            type="date"
                                            value={documentIssuedAt}
                                            onChange={(event) =>
                                                setDocumentIssuedAt(
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label htmlFor="document-expires-at">
                                            Expires on
                                        </Label>
                                        <Input
                                            id="document-expires-at"
                                            type="date"
                                            value={documentExpiresAt}
                                            min={documentIssuedAt || undefined}
                                            onChange={(event) =>
                                                setDocumentExpiresAt(
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                                <FileDropzone
                                    accept={DOCUMENT_ACCEPT_EXTENSIONS}
                                    acceptLabel={DOCUMENT_ACCEPT_LABEL}
                                    onFilesSelected={handleFileSelection}
                                    disabled={
                                        uploadSupplierDocumentMutation.isPending
                                    }
                                    description={`Drag files or click to browse (max ${DOCUMENT_MAX_SIZE_MB} MB)`}
                                />
                                <div className="rounded-md border border-dashed border-muted-foreground/40 p-2 text-xs text-muted-foreground">
                                    {pendingFile ? (
                                        <span>
                                            Selected:{' '}
                                            <strong>{pendingFile.name}</strong>{' '}
                                            ({formatFileSize(pendingFile.size)})
                                        </span>
                                    ) : (
                                        <span>
                                            No file selected. Files up to{' '}
                                            {DOCUMENT_MAX_SIZE_MB} MB are
                                            supported.
                                        </span>
                                    )}
                                </div>
                                {documentError ? (
                                    <p className="text-xs text-destructive">
                                        {documentError}
                                    </p>
                                ) : null}
                                <Button
                                    type="button"
                                    onClick={handleDocumentUpload}
                                    disabled={
                                        uploadSupplierDocumentMutation.isPending ||
                                        !pendingFile
                                    }
                                >
                                    {uploadSupplierDocumentMutation.isPending ? (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    ) : null}
                                    Upload document
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                {canViewApplications ? (
                    <div className="space-y-4 rounded-xl border border-border/80 bg-card/30 p-4">
                        <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm font-medium">
                                    Submission history
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Owners can withdraw pending requests before
                                    reviews begin.
                                </p>
                            </div>
                            <Badge variant="outline" className="w-fit">
                                {supplierApplications.length === 0
                                    ? 'No submissions yet'
                                    : `${supplierApplications.length} record${supplierApplications.length === 1 ? '' : 's'}`}
                            </Badge>
                        </div>

                        {supplierApplicationsQuery.isLoading ? (
                            <SubmissionHistorySkeleton />
                        ) : supplierApplicationsQuery.isError ? (
                            <Alert variant="destructive">
                                <AlertDescription>
                                    Unable to load supplier applications right
                                    now.
                                </AlertDescription>
                            </Alert>
                        ) : supplierApplications.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Submit your first supplier profile to see the
                                review timeline here.
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {supplierApplications.map((application) => {
                                    const metaEntry =
                                        APPLICATION_STATUS_META[
                                            application.status
                                        ] ?? APPLICATION_STATUS_META.pending;
                                    const documentCount =
                                        application.documents?.length ?? 0;
                                    const showWithdraw =
                                        isOwner &&
                                        application.status === 'pending';
                                    const isWithdrawing =
                                        withdrawingId === application.id &&
                                        withdrawApplicationMutation.isPending;

                                    return (
                                        <div
                                            key={application.id}
                                            className="space-y-2 rounded-lg border border-border/80 bg-background/80 p-3"
                                        >
                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <p className="text-sm font-medium text-foreground">
                                                        Submitted{' '}
                                                        {formatDateTime(
                                                            application.created_at,
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {application.status ===
                                                        'pending'
                                                            ? 'Awaiting review'
                                                            : `${metaEntry.label} ${formatDateTime(application.reviewed_at)}`}
                                                    </p>
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Badge
                                                        variant={
                                                            metaEntry.badge
                                                        }
                                                    >
                                                        {metaEntry.label}
                                                    </Badge>
                                                    {showWithdraw ? (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                handleWithdrawApplication(
                                                                    application.id,
                                                                )
                                                            }
                                                            disabled={
                                                                isWithdrawing
                                                            }
                                                        >
                                                            {isWithdrawing ? (
                                                                <span className="inline-flex items-center gap-1">
                                                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />{' '}
                                                                    Withdrawing…
                                                                </span>
                                                            ) : (
                                                                'Withdraw'
                                                            )}
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            </div>
                                            <div className="grid gap-2 text-xs text-muted-foreground sm:grid-cols-2">
                                                <p>
                                                    Documents attached:{' '}
                                                    {documentCount}
                                                </p>
                                                {application.notes ? (
                                                    <p>
                                                        Notes:{' '}
                                                        {application.notes}
                                                    </p>
                                                ) : null}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                ) : null}

                <Button
                    onClick={() => (canApply ? setDialogOpen(true) : null)}
                    disabled={!canApply}
                >
                    {actionLabel}
                </Button>

                <Dialog
                    open={dialogOpen}
                    onOpenChange={(next) =>
                        !isSubmitting && !applyMutation.isPending
                            ? setDialogOpen(next)
                            : null
                    }
                >
                    <DialogContent className="max-h-[85vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>Supplier application</DialogTitle>
                            <DialogDescription>
                                Share your capabilities, certifications, and
                                business readiness. Our platform team uses this
                                to approve supplier access.
                            </DialogDescription>
                        </DialogHeader>
                        <form className="space-y-5" onSubmit={onSubmit}>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="description">
                                        Overview
                                    </Label>
                                    <Textarea
                                        id="description"
                                        rows={4}
                                        placeholder="What do you manufacture?"
                                        {...register('description')}
                                    />
                                    {errors.description ? (
                                        <p className="text-xs text-destructive">
                                            {errors.description.message}
                                        </p>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            200-400 characters describing your
                                            services.
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="website">Website</Label>
                                    <Input
                                        id="website"
                                        placeholder="https://example.com"
                                        {...register('website', {
                                            setValueAs: (value) =>
                                                typeof value === 'string'
                                                    ? value.trim()
                                                    : value,
                                        })}
                                    />
                                    {errors.website ? (
                                        <p className="text-xs text-destructive">
                                            {errors.website.message}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="address">Address</Label>
                                    <Input
                                        id="address"
                                        placeholder="123 Industrial Way"
                                        {...register('address')}
                                    />
                                    {errors.address ? (
                                        <p className="text-xs text-destructive">
                                            {errors.address.message}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="city">City</Label>
                                    <Input
                                        id="city"
                                        placeholder="Austin"
                                        {...register('city')}
                                    />
                                    {errors.city ? (
                                        <p className="text-xs text-destructive">
                                            {errors.city.message}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="country">
                                        Country (ISO)
                                    </Label>
                                    <Input
                                        id="country"
                                        maxLength={2}
                                        placeholder="US"
                                        {...register('country', {
                                            setValueAs: (value) =>
                                                typeof value === 'string'
                                                    ? value.trim().toUpperCase()
                                                    : value,
                                        })}
                                    />
                                    {errors.country ? (
                                        <p className="text-xs text-destructive">
                                            {errors.country.message}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="moq">MOQ</Label>
                                    <Input
                                        id="moq"
                                        type="number"
                                        min={1}
                                        placeholder="Minimum order quantity"
                                        {...register('moq')}
                                    />
                                    {errors.moq ? (
                                        <p className="text-xs text-destructive">
                                            {errors.moq.message}
                                        </p>
                                    ) : null}
                                </div>
                                {/* <div className="space-y-2">
                                    <Label htmlFor="minOrderQty">Minimum order qty</Label>
                                    <Input id="minOrderQty" type="number" min={1} placeholder="e.g. 25" {...register('minOrderQty')} />
                                    {errors.minOrderQty ? <p className="text-xs text-destructive">{errors.minOrderQty.message}</p> : null}
                                </div> */}
                                <div className="space-y-2">
                                    <Label htmlFor="leadTimeDays">
                                        Lead time (days)
                                    </Label>
                                    <Input
                                        id="leadTimeDays"
                                        type="number"
                                        min={1}
                                        placeholder="e.g. 10"
                                        {...register('leadTimeDays')}
                                    />
                                    {errors.leadTimeDays ? (
                                        <p className="text-xs text-destructive">
                                            {errors.leadTimeDays.message}
                                        </p>
                                    ) : null}
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <Label className="text-sm font-medium">
                                        Capabilities
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Comma or line separated lists.
                                    </p>
                                </div>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="capabilitiesMethods">
                                            Processes
                                        </Label>
                                        <Textarea
                                            id="capabilitiesMethods"
                                            rows={3}
                                            placeholder="CNC machining, stamping"
                                            {...register('capabilitiesMethods')}
                                        />
                                        {errors.capabilitiesMethods ? (
                                            <p className="text-xs text-destructive">
                                                {
                                                    errors.capabilitiesMethods
                                                        .message
                                                }
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="capabilitiesMaterials">
                                            Materials
                                        </Label>
                                        <Textarea
                                            id="capabilitiesMaterials"
                                            rows={3}
                                            placeholder="6061-T6, 17-4PH"
                                            {...register(
                                                'capabilitiesMaterials',
                                            )}
                                        />
                                        {errors.capabilitiesMaterials ? (
                                            <p className="text-xs text-destructive">
                                                {
                                                    errors.capabilitiesMaterials
                                                        .message
                                                }
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="capabilitiesFinishes">
                                            Finishes
                                        </Label>
                                        <Textarea
                                            id="capabilitiesFinishes"
                                            rows={3}
                                            placeholder="Anodizing, powder coat"
                                            {...register(
                                                'capabilitiesFinishes',
                                            )}
                                        />
                                        {errors.capabilitiesFinishes ? (
                                            <p className="text-xs text-destructive">
                                                {
                                                    errors.capabilitiesFinishes
                                                        .message
                                                }
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="capabilitiesTolerances">
                                            Tolerances
                                        </Label>
                                        <Textarea
                                            id="capabilitiesTolerances"
                                            rows={3}
                                            placeholder="±0.05 mm"
                                            {...register(
                                                'capabilitiesTolerances',
                                            )}
                                        />
                                        {errors.capabilitiesTolerances ? (
                                            <p className="text-xs text-destructive">
                                                {
                                                    errors
                                                        .capabilitiesTolerances
                                                        .message
                                                }
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2 md:col-span-2">
                                        <Label htmlFor="capabilitiesIndustries">
                                            Industries served
                                        </Label>
                                        <Textarea
                                            id="capabilitiesIndustries"
                                            rows={3}
                                            placeholder="Aerospace, MedTech"
                                            {...register(
                                                'capabilitiesIndustries',
                                            )}
                                        />
                                        {errors.capabilitiesIndustries ? (
                                            <p className="text-xs text-destructive">
                                                {
                                                    errors
                                                        .capabilitiesIndustries
                                                        .message
                                                }
                                            </p>
                                        ) : null}
                                    </div>
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="certifications">
                                        Certifications
                                    </Label>
                                    <Textarea
                                        id="certifications"
                                        rows={3}
                                        placeholder="ISO 9001, AS9100"
                                        {...register('certifications')}
                                    />
                                    {errors.certifications ? (
                                        <p className="text-xs text-destructive">
                                            {errors.certifications.message}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="facilities">
                                        Facilities / equipment
                                    </Label>
                                    <Textarea
                                        id="facilities"
                                        rows={3}
                                        placeholder="5-axis mills, CMM"
                                        {...register('facilities')}
                                    />
                                    {errors.facilities ? (
                                        <p className="text-xs text-destructive">
                                            {errors.facilities.message}
                                        </p>
                                    ) : null}
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="contactName">
                                        Primary contact
                                    </Label>
                                    <Input
                                        id="contactName"
                                        placeholder="Jordan Supplier"
                                        {...register('contactName')}
                                    />
                                    {errors.contactName ? (
                                        <p className="text-xs text-destructive">
                                            {errors.contactName.message}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="contactPhone">Phone</Label>
                                    <Input
                                        id="contactPhone"
                                        placeholder="+1 555-1234"
                                        {...register('contactPhone')}
                                    />
                                    {errors.contactPhone ? (
                                        <p className="text-xs text-destructive">
                                            {errors.contactPhone.message}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="contactEmail">
                                        Contact email
                                    </Label>
                                    <Input
                                        id="contactEmail"
                                        type="email"
                                        placeholder="supplier@example.com"
                                        {...register('contactEmail')}
                                    />
                                    {errors.contactEmail ? (
                                        <p className="text-xs text-destructive">
                                            {errors.contactEmail.message}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <Textarea
                                        id="notes"
                                        rows={3}
                                        placeholder="Anything else we should know?"
                                        {...register('notes')}
                                    />
                                    {errors.notes ? (
                                        <p className="text-xs text-destructive">
                                            {errors.notes.message}
                                        </p>
                                    ) : null}
                                </div>
                            </div>

                            {formError ? (
                                <Alert variant="destructive">
                                    <AlertDescription>
                                        {formError}
                                    </AlertDescription>
                                </Alert>
                            ) : null}

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() =>
                                        !isSubmitting &&
                                        !applyMutation.isPending
                                            ? setDialogOpen(false)
                                            : null
                                    }
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        isSubmitting || applyMutation.isPending
                                    }
                                >
                                    {isSubmitting || applyMutation.isPending ? (
                                        <span className="inline-flex items-center gap-2">
                                            <Loader2 className="h-4 w-4 animate-spin" />{' '}
                                            Submitting…
                                        </span>
                                    ) : (
                                        'Submit application'
                                    )}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </CardContent>
        </Card>
    );
}

function SubmissionHistorySkeleton() {
    return (
        <div className="space-y-3">
            {[0, 1].map((index) => (
                <div
                    key={index}
                    className="space-y-2 rounded-lg border border-border/80 bg-background/80 p-3"
                >
                    <Skeleton className="h-4 w-1/3" />
                    <Skeleton className="h-3 w-1/5" />
                </div>
            ))}
        </div>
    );
}
