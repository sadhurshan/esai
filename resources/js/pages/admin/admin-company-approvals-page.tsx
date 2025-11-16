import { useMemo, useState } from 'react';

import Heading from '@/components/heading';
import { CompanyApprovalTable } from '@/components/admin/company-approval-table';
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useAuth } from '@/contexts/auth-context';
import { useApproveCompany } from '@/hooks/api/admin/use-approve-company';
import { useCompanyApprovals } from '@/hooks/api/admin/use-company-approvals';
import { useRejectCompany } from '@/hooks/api/admin/use-reject-company';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import type { CompanyApprovalItem, CompanyStatusValue } from '@/types/admin';

const DEFAULT_STATUS: CompanyStatusValue = 'pending_verification';
const PAGE_SIZE = 20;

const STATUS_FILTERS: Array<{ label: string; value: CompanyStatusValue; description: string }> = [
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
    const [status, setStatus] = useState<CompanyStatusValue>(DEFAULT_STATUS);
    const [page, setPage] = useState(1);
    const [approvingId, setApprovingId] = useState<number | null>(null);
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

    const companies = data?.items ?? [];
    const pagination = data?.meta;

    if (!isAdmin) {
        return <AccessDeniedPage />;
    }

    const activeFilter = STATUS_FILTERS.find((filter) => filter.value === status) ?? STATUS_FILTERS[0];
    const rejectingId = rejectMutation.isPending && rejectModal.company ? rejectModal.company.id : null;

    const handleStatusChange = (nextStatus: string) => {
        if (nextStatus === status) {
            return;
        }
        setStatus(nextStatus as CompanyStatusValue);
        setPage(1);
    };

    const handleApprove = (company: CompanyApprovalItem) => {
        setApprovingId(company.id);
        approveMutation.mutate(
            { companyId: company.id },
            {
                onSettled: () => setApprovingId(null),
            },
        );
    };

    const openRejectDialog = (company: CompanyApprovalItem) => {
        setRejectModal({ company, reason: '' });
    };

    const closeRejectDialog = () => {
        setRejectModal({ company: null, reason: '' });
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
            />

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
