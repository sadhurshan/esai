import { useMemo, useState, type ReactNode } from 'react';

import Heading from '@/components/heading';
import {
    CompanyApprovalTable,
    companyStatusLabel,
    companyStatusVariant,
    isCompanyModerationEligible,
} from '@/components/admin/company-approval-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useAuth } from '@/contexts/auth-context';
import { useApproveCompany } from '@/hooks/api/admin/use-approve-company';
import { useCompaniesHouseProfile } from '@/hooks/api/admin/use-companies-house-profile';
import { useCompanyApprovals } from '@/hooks/api/admin/use-company-approvals';
import { useCompanyDocuments } from '@/hooks/api/useCompanyDocuments';
import { useRejectCompany } from '@/hooks/api/admin/use-reject-company';
import { formatDate } from '@/lib/format';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import type { CompanyApprovalItem, CompanyStatusValue, CompaniesHouseProfile } from '@/types/admin';

type CompanyStatusFilter = CompanyStatusValue | 'all';

const DEFAULT_STATUS: CompanyStatusFilter = 'all';
const PAGE_SIZE = 20;

const STATUS_FILTERS: Array<{ label: string; value: CompanyStatusFilter; description: string }> = [
    {
        label: 'All companies',
        value: 'all',
        description: 'Browse every tenant, regardless of onboarding status.',
    },
    {
        label: 'Pending verification',
        value: 'pending_verification',
        description: 'Awaiting document review and compliance checks.',
    },
    {
        label: 'Pending submission',
        value: 'pending',
        description: 'Registrations started but not fully submitted.',
    },
    {
        label: 'Rejected',
        value: 'rejected',
        description: 'Provide feedback or reinstate if needed.',
    },
];

export function AdminCompanyApprovalsPage() {
    const { isAdmin } = useAuth();
    const [status, setStatus] = useState<CompanyStatusFilter>(DEFAULT_STATUS);
    const [page, setPage] = useState(1);
    const [approvingId, setApprovingId] = useState<number | null>(null);
    const [selectedCompany, setSelectedCompany] = useState<CompanyApprovalItem | null>(null);
    const [rejectModal, setRejectModal] = useState<{ company: CompanyApprovalItem | null; reason: string }>({
        company: null,
        reason: '',
    });

    const queryParams = useMemo(
        () => ({ status, page, perPage: PAGE_SIZE }),
        [status, page],
    );

    const { data, isLoading } = useCompanyApprovals(queryParams);
    const approveMutation = useApproveCompany();
    const rejectMutation = useRejectCompany();
    const documentsCompanyId = selectedCompany?.id ?? null;
    const companyDocumentsQuery = useCompanyDocuments({
        companyId: documentsCompanyId,
        enabled: Boolean(documentsCompanyId),
    });
    const companyDocuments = companyDocumentsQuery.data?.items ?? [];
    const selectedCompanyIsUk = selectedCompany ? isUkCountry(selectedCompany.country) : false;
    const companiesHouseEligible = selectedCompanyIsUk && Boolean(selectedCompany?.registration_no);
    const companiesHouseQuery = useCompaniesHouseProfile({
        companyId: selectedCompany?.id ?? null,
        enabled: Boolean(selectedCompany) && companiesHouseEligible,
    });
    const companiesHouseProfile = companiesHouseQuery.data?.profile ?? null;
    const companiesHouseErrorMessage = companiesHouseQuery.isError
        ? companiesHouseQuery.error instanceof Error
            ? companiesHouseQuery.error.message
            : 'Unable to load Companies House data.'
        : null;

    const companies = data?.items ?? [];
    const pagination = data?.meta;
    const documentsLoading = companyDocumentsQuery.isLoading;
    const documentsErrorMessage = companyDocumentsQuery.isError
        ? companyDocumentsQuery.error?.message ?? 'Unable to load registration documents.'
        : null;

    if (!isAdmin) {
        return <AccessDeniedPage />;
    }

    const activeFilter = STATUS_FILTERS.find((filter) => filter.value === status) ?? STATUS_FILTERS[0];
    const rejectingId = rejectMutation.isPending && rejectModal.company ? rejectModal.company.id : null;
    const detailEligible = selectedCompany ? isCompanyModerationEligible(selectedCompany.status) : false;
    const detailAwaitingApproval = selectedCompany ? approvingId === selectedCompany.id : false;
    const detailRejecting = selectedCompany
        ? rejectMutation.isPending && rejectModal.company?.id === selectedCompany.id
        : false;

    const handleStatusChange = (nextStatus: string) => {
        if (nextStatus === status) {
            return;
        }
        setStatus(nextStatus as CompanyStatusFilter);
        setPage(1);
    };

    const handleApprove = (company: CompanyApprovalItem) => {
        setApprovingId(company.id);
        approveMutation.mutate(
            { companyId: company.id },
            {
                onSuccess: () => {
                    setSelectedCompany((prev) => (prev?.id === company.id ? null : prev));
                },
                onSettled: () => setApprovingId(null),
            },
        );
    };

    const handleViewDetails = (company: CompanyApprovalItem) => {
        setSelectedCompany(company);
    };

    const closeCompanyDetails = () => {
        setSelectedCompany(null);
    };

    const openRejectDialog = (company: CompanyApprovalItem) => {
        setRejectModal({ company, reason: '' });
    };

    const closeRejectDialog = () => {
        setRejectModal({ company: null, reason: '' });
    };

    const handleRejectFromDetails = (company: CompanyApprovalItem) => {
        closeCompanyDetails();
        openRejectDialog(company);
    };

    const submitReject = () => {
        if (!rejectModal.company) {
            return;
        }
        const reason = rejectModal.reason.trim();
        if (!reason) {
            return;
        }
        rejectMutation.mutate(
            { companyId: rejectModal.company.id, reason },
            {
                onSuccess: () => closeRejectDialog(),
            },
        );
    };

    const reasonIsValid = rejectModal.reason.trim().length >= 5;

    return (
        <div className="space-y-8">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <Heading
                    title="Company approvals"
                    description="Review newly registered tenants, verify their documentation, and approve or reject onboarding requests."
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Queues</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <Tabs defaultValue={DEFAULT_STATUS} value={status} onValueChange={handleStatusChange}>
                        <TabsList className="grid gap-2 sm:grid-cols-3">
                            {STATUS_FILTERS.map((filter) => (
                                <TabsTrigger key={filter.value} value={filter.value} className="text-sm">
                                    {filter.label}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </Tabs>
                    <p className="text-sm text-muted-foreground">{activeFilter.description}</p>
                </CardContent>
            </Card>

            <CompanyApprovalTable
                companies={companies}
                meta={pagination}
                isLoading={isLoading}
                approvingId={approvingId}
                rejectingId={rejectingId}
                onApprove={handleApprove}
                onReject={openRejectDialog}
                onPageChange={setPage}
                onView={handleViewDetails}
            />

            <Dialog
                open={Boolean(selectedCompany)}
                onOpenChange={(open) => {
                    if (!open) {
                        closeCompanyDetails();
                    }
                }}
            >
                <DialogContent className="flex max-h-[90vh] max-w-3xl flex-col">
                    <DialogHeader>
                        <DialogTitle>{selectedCompany?.name ?? 'Company details'}</DialogTitle>
                        <DialogDescription>
                            Verify the submitted registration details before approving or rejecting the tenant.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedCompany ? (
                        <ScrollArea className="max-h-[60vh] pr-2">
                            <div className="space-y-6">
                                {/* <div className="flex flex-wrap gap-2">
                                    <Badge variant={companyStatusVariant(selectedCompany.status)}>
                                        {companyStatusLabel(selectedCompany.status)}
                                    </Badge>
                                    {selectedCompany.has_completed_onboarding ? (
                                        <Badge variant="outline">Onboarding complete</Badge>
                                    ) : null}
                                    {selectedCompany.is_verified ? <Badge variant="outline">Verified</Badge> : null}
                                    {selectedCompany.supplier_status ? (
                                        <Badge variant="outline">{selectedCompany.supplier_status}</Badge>
                                    ) : null}
                                    {selectedCompany.directory_visibility ? (
                                        <Badge variant="outline">{selectedCompany.directory_visibility}</Badge>
                                    ) : null}
                                </div> */}

                                <DetailSection title="Company profile">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <DetailField label="Slug" value={selectedCompany.slug} />
                                        <DetailField label="Email domain" value={selectedCompany.email_domain ?? '—'} />
                                        <DetailField label="Website" value={renderWebsite(selectedCompany.website)} />
                                        <DetailField label="Phone" value={selectedCompany.phone ?? '—'} />
                                        <DetailField label="Region" value={selectedCompany.region ?? '—'} />
                                        <DetailField label="Country" value={selectedCompany.country ?? '—'} />
                                        <DetailField label="Directory visibility" value={selectedCompany.directory_visibility ?? '—'} />
                                        <DetailField
                                            label="Address"
                                            value={selectedCompany.address ? (
                                                <span className="whitespace-pre-line">{selectedCompany.address}</span>
                                            ) : (
                                                '—'
                                            )}
                                        />
                                    </div>
                                </DetailSection>

                                <DetailSection title="Primary contact">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <DetailField label="Name" value={selectedCompany.primary_contact_name ?? '—'} />
                                        <DetailField
                                            label="Email"
                                            value={selectedCompany.primary_contact_email ? (
                                                <a
                                                    href={`mailto:${selectedCompany.primary_contact_email}`}
                                                    className="text-primary underline"
                                                >
                                                    {selectedCompany.primary_contact_email}
                                                </a>
                                            ) : (
                                                '—'
                                            )}
                                        />
                                        <DetailField label="Phone" value={selectedCompany.primary_contact_phone ?? '—'} />
                                    </div>
                                </DetailSection>

                                <DetailSection title="Compliance & verification">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <DetailField label="Registration #" value={selectedCompany.registration_no ?? '—'} />
                                        <DetailField label="Tax ID" value={selectedCompany.tax_id ?? '—'} />
                                        <DetailField label="Supplier status" value={selectedCompany.supplier_status ?? '—'} />
                                        <DetailField
                                            label="Onboarding completed"
                                            value={selectedCompany.has_completed_onboarding ? 'Yes' : 'No'}
                                        />
                                        <DetailField label="Verified at" value={formatDate(selectedCompany.verified_at)} />
                                        <DetailField
                                            label="Verified by"
                                            value={selectedCompany.verified_by ? `User #${selectedCompany.verified_by}` : '—'}
                                        />
                                        <DetailField
                                            label="Supplier profile completed"
                                            value={formatDate(selectedCompany.supplier_profile_completed_at)}
                                        />
                                    </div>
                                    {selectedCompany.rejection_reason ? (
                                        <div className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                                            <p className="font-medium">Rejection reason</p>
                                            <p className="text-muted-foreground">{selectedCompany.rejection_reason}</p>
                                        </div>
                                    ) : null}
                                </DetailSection>

                                <DetailSection title="Companies House verification">
                                    {!companiesHouseEligible ? (
                                        <p className="text-sm text-muted-foreground">
                                            Available for United Kingdom companies that provided a registration number.
                                        </p>
                                    ) : companiesHouseQuery.isLoading ? (
                                        <p className="text-sm text-muted-foreground">Fetching Companies House profile…</p>
                                    ) : companiesHouseErrorMessage ? (
                                        <p className="text-sm text-destructive">{companiesHouseErrorMessage}</p>
                                    ) : companiesHouseProfile ? (
                                        <CompaniesHouseProfileSummary profile={companiesHouseProfile} />
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            No Companies House record was found for registration #{selectedCompany.registration_no}
                                        </p>
                                    )}
                                </DetailSection>

                                <DetailSection title="Registration documents">
                                    {documentsLoading ? (
                                        <p className="text-sm text-muted-foreground">Loading submitted documents…</p>
                                    ) : documentsErrorMessage ? (
                                        <p className="text-sm text-destructive">{documentsErrorMessage}</p>
                                    ) : companyDocuments.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No documents were uploaded with this registration.
                                        </p>
                                    ) : (
                                        <div className="space-y-3">
                                            {companyDocuments.map((document) => (
                                                <div
                                                    key={document.id}
                                                    className="flex flex-col gap-3 rounded-lg border bg-muted/30 p-3 sm:flex-row sm:items-center sm:justify-between"
                                                >
                                                    <div>
                                                        <p className="text-sm font-medium text-foreground">
                                                            {formatDocumentType(document.type)}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {document.filename ?? 'Unnamed file'} · {formatDocumentSize(document.sizeBytes)} ·{' '}
                                                            {document.mime ?? 'Unknown format'}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            Uploaded {formatDate(document.createdAt)}
                                                            {document.verifiedAt
                                                                ? ` • Verified ${formatDate(document.verifiedAt)}`
                                                                : ''}
                                                        </p>
                                                    </div>
                                                    {document.downloadUrl ? (
                                                        <Button asChild variant="outline" size="sm">
                                                            <a href={document.downloadUrl} target="_blank" rel="noreferrer">
                                                                View file
                                                            </a>
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </DetailSection>
                            </div>
                        </ScrollArea>
                    ) : null}
                    {selectedCompany ? (
                        <DialogFooter className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-muted-foreground">
                                Submitted {formatDate(selectedCompany.created_at)} • Updated {formatDate(selectedCompany.updated_at)}
                            </p>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <Button type="button" variant="outline" onClick={closeCompanyDetails}>
                                    Close
                                </Button>
                                <Button
                                    type="button"
                                    disabled={!detailEligible || detailAwaitingApproval || detailRejecting}
                                    onClick={() => handleApprove(selectedCompany)}
                                >
                                    {detailAwaitingApproval ? 'Approving…' : 'Approve'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    disabled={!detailEligible || detailAwaitingApproval || detailRejecting}
                                    onClick={() => handleRejectFromDetails(selectedCompany)}
                                >
                                    {detailRejecting ? 'Rejecting…' : 'Reject'}
                                </Button>
                            </div>
                        </DialogFooter>
                    ) : null}
                </DialogContent>
            </Dialog>

            <Dialog open={Boolean(rejectModal.company)} onOpenChange={(open) => {
                if (!open) {
                    closeRejectDialog();
                }
            }}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Reject {rejectModal.company?.name ?? 'company'}</DialogTitle>
                        <DialogDescription>
                            Provide a short reason so the tenant understands how to remediate their submission.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3">
                        <div className="space-y-2">
                            <Label htmlFor="reject-reason">Reason</Label>
                            <Textarea
                                id="reject-reason"
                                rows={4}
                                placeholder="Missing tax documents, incorrect incorporation details, etc."
                                value={rejectModal.reason}
                                onChange={(event) =>
                                    setRejectModal((prev) => ({
                                        ...prev,
                                        reason: event.target.value,
                                    }))
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Minimum 5 characters. This note is shared with the tenant owner.
                            </p>
                        </div>
                    </div>
                    <DialogFooter className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <Button type="button" variant="outline" onClick={closeRejectDialog} disabled={rejectMutation.isPending}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={!reasonIsValid || rejectMutation.isPending}
                            onClick={submitReject}
                        >
                            {rejectMutation.isPending ? 'Rejecting…' : 'Reject tenant'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function DetailSection({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="space-y-3">
            <h3 className="text-sm font-semibold text-foreground">{title}</h3>
            {children}
        </section>
    );
}

function DetailField({ label, value }: { label: string; value: ReactNode }) {
    const content = value === null || value === undefined || value === '' ? '—' : value;

    return (
        <div className="rounded-lg border bg-muted/30 p-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{label}</p>
            <div className="mt-1 text-sm text-foreground">{content}</div>
        </div>
    );
}

function CompaniesHouseProfileSummary({ profile }: { profile: CompaniesHouseProfile }) {
    const address = formatCompaniesHouseAddress(profile.registered_office_address);
    const previousNames = profile.previous_company_names ?? [];

    return (
        <div className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-2">
                <DetailField label="Official name" value={profile.company_name ?? '—'} />
                <DetailField label="Company number" value={profile.company_number ?? '—'} />
                <DetailField label="Status" value={profile.company_status ?? '—'} />
                <DetailField label="Type" value={profile.type ?? '—'} />
                <DetailField label="Jurisdiction" value={profile.jurisdiction ?? '—'} />
                <DetailField label="Date of incorporation" value={formatDate(profile.date_of_creation)} />
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <DetailField
                    label="Registered office"
                    value={address ? <span className="whitespace-pre-line">{address}</span> : '—'}
                />
                <DetailField label="SIC codes" value={formatSicCodes(profile.sic_codes)} />
                <DetailField label="Can file" value={formatCompaniesHouseBoolean(profile.can_file)} />
                <DetailField
                    label="Has been liquidated"
                    value={formatCompaniesHouseBoolean(profile.has_been_liquidated)}
                />
                <DetailField
                    label="Undeliverable address"
                    value={formatCompaniesHouseBoolean(profile.undeliverable_registered_office_address)}
                />
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <DetailField
                    label="Accounts next due"
                    value={formatDate(profile.accounts?.next_due ?? undefined)}
                />
                <DetailField
                    label="Confirmation statement next due"
                    value={formatDate(profile.confirmation_statement?.next_due ?? undefined)}
                />
            </div>
            {previousNames.length ? (
                <div className="rounded-lg border bg-muted/10 p-3">
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Previous names</p>
                    <ul className="mt-2 space-y-1 text-sm text-foreground">
                        {previousNames.map((entry, index) => (
                            <li key={`${entry.name ?? 'name'}-${index}`}>
                                <span className="font-medium">{entry.name ?? 'Unknown'}</span>
                                <span className="block text-xs text-muted-foreground">
                                    {formatPreviousNameRange(entry)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
            {profile.retrieved_at ? (
                <p className="text-xs text-muted-foreground">Synced {formatDate(profile.retrieved_at)}</p>
            ) : null}
        </div>
    );
}

const UK_COUNTRY_IDENTIFIERS = ['uk', 'united kingdom', 'great britain', 'gb', 'gbr', 'england', 'scotland', 'wales', 'northern ireland'];

function isUkCountry(country?: string | null): boolean {
    if (!country) {
        return false;
    }

    const normalized = country.trim().toLowerCase();

    return (
        UK_COUNTRY_IDENTIFIERS.some((identifier) => normalized === identifier || normalized.includes(identifier))
    );
}

function formatCompaniesHouseAddress(address?: CompaniesHouseProfile['registered_office_address']): string | null {
    if (!address) {
        return null;
    }

    const parts = [
        address.address_line_1,
        address.address_line_2,
        address.locality,
        address.region,
        address.postal_code,
        address.country,
    ].filter((part) => Boolean(part && part.trim()));

    return parts.length ? parts.join('\n') : null;
}

function formatCompaniesHouseBoolean(value?: boolean | null): string {
    if (value === undefined || value === null) {
        return 'Unknown';
    }

    return value ? 'Yes' : 'No';
}

function formatSicCodes(codes?: string[] | null): string {
    if (!codes || codes.length === 0) {
        return '—';
    }

    return codes.join(', ');
}

function formatPreviousNameRange(entry: NonNullable<CompaniesHouseProfile['previous_company_names']>[number]): string {
    const parts: string[] = [];

    if (entry.effective_from) {
        parts.push(`From ${formatDate(entry.effective_from)}`);
    }

    if (entry.ceased_on) {
        parts.push(`Until ${formatDate(entry.ceased_on)}`);
    }

    return parts.length ? parts.join(' · ') : 'Dates unavailable';
}

const DOCUMENT_TYPE_LABELS: Record<string, string> = {
    registration: 'Registration certificate',
    tax: 'Tax certificate',
    esg: 'ESG / compliance policy',
    other: 'Supporting document',
};

function formatDocumentType(type?: string | null): string {
    if (!type) {
        return 'Document';
    }

    return DOCUMENT_TYPE_LABELS[type] ?? type.replace(/_/g, ' ').replace(/^./, (char) => char.toUpperCase());
}

function formatDocumentSize(bytes?: number | null): string {
    if (!bytes || Number.isNaN(bytes)) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    const precision = size >= 10 || unitIndex === 0 ? 0 : 1;
    return `${size.toFixed(precision)} ${units[unitIndex]}`;
}

function renderWebsite(url?: string | null): ReactNode {
    const link = formatWebsiteLink(url);

    if (!link) {
        return '—';
    }

    return (
        <a href={link.href} className="text-primary underline" target="_blank" rel="noreferrer">
            {link.label}
        </a>
    );
}

function formatWebsiteLink(url?: string | null): { href: string; label: string } | null {
    if (!url) {
        return null;
    }

    const trimmed = url.trim();

    if (!trimmed) {
        return null;
    }

    const hasProtocol = /^https?:\/\//i.test(trimmed);
    const href = hasProtocol ? trimmed : `https://${trimmed}`;
    const label = trimmed.replace(/^https?:\/\//i, '');

    return { href, label };
}
