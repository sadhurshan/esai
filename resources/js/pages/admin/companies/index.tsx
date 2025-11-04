import { DataTable, EmptyState, Pagination, successToast, errorToast } from '@/components/app';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Inbox } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';

import {
    useApproveCompany,
    usePendingCompanies,
    useRejectCompany,
    type PendingCompaniesParams,
} from '@/hooks/api/usePendingCompanies';
import { StatusBadge } from '@/components/app/status-badge';
import type { Company } from '@/types/company';
import type { BreadcrumbItem, SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Home', href: '/' },
    { title: 'Tenant Approvals', href: '/admin/companies' },
];

const STATUS_FILTERS = [
    { value: 'pending', label: 'Pending review' },
    { value: 'active', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
] as const;

interface TableRow extends Company {
    submittedAt?: string | null;
}

export default function AdminCompaniesIndex() {
    const { auth } = usePage<SharedData>().props;
    const role = auth.user?.role ?? null;
    const canDecide = role === 'platform_super';

    const [params, setParams] = useState<PendingCompaniesParams>({
        status: 'pending',
        page: 1,
        per_page: 10,
    });
    const [rejectCompanyId, setRejectCompanyId] = useState<number | null>(null);
    const [rejectReason, setRejectReason] = useState('');

    const {
        data,
        isLoading,
        isError,
        error,
        refetch,
    } = usePendingCompanies(params);

    const approveCompany = useApproveCompany();
    const rejectCompany = useRejectCompany();

    const rows: TableRow[] = useMemo(() => data?.items ?? [], [data?.items]);
    const meta = data?.meta ?? null;

    const handleStatusChange = (nextStatus: string) => {
        setParams((previous) => ({
            ...previous,
            status: nextStatus,
            page: 1,
        }));
    };

    const handleApprove = (company: Company) => {
        approveCompany.mutate(
            { companyId: company.id },
            {
                onSuccess: () => {
                    successToast(`${company.name} approved.`);
                },
                onError: (mutationError) => {
                    errorToast(mutationError.message ?? 'Unable to approve company.');
                },
            },
        );
    };

    const handleReject = () => {
        if (!rejectCompanyId || rejectReason.trim() === '') {
            errorToast('Provide a rejection reason before submitting.');
            return;
        }

        rejectCompany.mutate(
            { companyId: rejectCompanyId, reason: rejectReason.trim() },
            {
                onSuccess: () => {
                    successToast('Company rejected.');
                    setRejectReason('');
                    setRejectCompanyId(null);
                },
                onError: (mutationError) => {
                    errorToast(mutationError.message ?? 'Unable to reject company.');
                },
            },
        );
    };

    const emptyState = isError ? (
        <EmptyState
            title="Unable to load companies"
            description={error?.message ?? 'Please try again.'}
            ctaLabel="Retry"
            ctaProps={{ onClick: () => refetch() }}
        />
    ) : (
        <EmptyState
            title="No companies in this state"
            description="When new tenants submit registration details they will appear here for review."
            icon={<Inbox className="size-10" aria-hidden />}
        />
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tenant approvals" />
            <div className="flex flex-1 flex-col gap-6 px-4 py-6">
                <header className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Tenant approvals</h1>
                        <p className="text-sm text-muted-foreground">
                            Review pending organizations, verify KYC documents, and approve or reject access to the sourcing platform.
                        </p>
                    </div>
                    <Select value={params.status ?? 'pending'} onValueChange={handleStatusChange}>
                        <SelectTrigger className="w-[220px]">
                            <SelectValue placeholder="Status filter" />
                        </SelectTrigger>
                        <SelectContent>
                            {STATUS_FILTERS.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </header>

                <DataTable<TableRow>
                    data={rows}
                    isLoading={isLoading}
                    emptyState={emptyState}
                    columns={[
                        {
                            key: 'name',
                            title: 'Company',
                            render: (row) => (
                                <div className="flex flex-col">
                                    <span className="font-medium text-foreground">{row.name}</span>
                                    <span className="text-xs text-muted-foreground">
                                        {row.emailDomain}
                                    </span>
                                </div>
                            ),
                        },
                        {
                            key: 'primaryContactName',
                            title: 'Primary contact',
                            render: (row) => (
                                <div className="flex flex-col text-sm">
                                    <span>{row.primaryContactName}</span>
                                    <span className="text-xs text-muted-foreground">{row.primaryContactEmail}</span>
                                </div>
                            ),
                        },
                        {
                            key: 'status',
                            title: 'Status',
                            render: (row) => <StatusBadge status={row.status} />,
                        },
                        {
                            key: 'submittedAt',
                            title: 'Submitted',
                            render: (row) => (
                                <span className="text-sm text-muted-foreground">
                                    {row.createdAt
                                        ? formatDistanceToNow(new Date(row.createdAt), { addSuffix: true })
                                        : '—'}
                                </span>
                            ),
                        },
                        {
                            key: 'actions',
                            title: 'Actions',
                            width: '220px',
                            render: (row) => (
                                <div className="flex items-center gap-2">
                                    {params.status === 'pending' && canDecide ? (
                                        <>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={approveCompany.isPending}
                                                onClick={() => handleApprove(row)}
                                            >
                                                Approve
                                            </Button>
                                            <Dialog
                                                open={rejectCompanyId === row.id}
                                                onOpenChange={(open) => {
                                                    if (!open) {
                                                        setRejectCompanyId(null);
                                                        setRejectReason('');
                                                    }
                                                }}
                                            >
                                                <DialogTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => setRejectCompanyId(row.id)}
                                                    >
                                                        Reject
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogHeader>
                                                        <DialogTitle>Reject {row.name}?</DialogTitle>
                                                        <DialogDescription>
                                                            Provide a short note describing why the tenant is being rejected.
                                                        </DialogDescription>
                                                    </DialogHeader>
                                                    <div className="space-y-2">
                                                        <Label htmlFor="reject-reason">Rejection reason</Label>
                                                        <Input
                                                            id="reject-reason"
                                                            value={rejectReason}
                                                            onChange={(event) => setRejectReason(event.target.value)}
                                                            placeholder="Missing insurance certificate"
                                                        />
                                                    </div>
                                                    <DialogFooter className="gap-2">
                                                        <DialogClose asChild>
                                                            <Button variant="secondary" onClick={() => setRejectCompanyId(null)}>
                                                                Cancel
                                                            </Button>
                                                        </DialogClose>
                                                        <Button
                                                            variant="destructive"
                                                            disabled={rejectCompany.isPending}
                                                            onClick={handleReject}
                                                        >
                                                            Reject company
                                                        </Button>
                                                    </DialogFooter>
                                                </DialogContent>
                                            </Dialog>
                                        </>
                                    ) : (
                                        <span className="text-xs text-muted-foreground">No actions available</span>
                                    )}
                                </div>
                            ),
                        },
                    ]}
                />

                <Pagination
                    meta={meta}
                    onPageChange={(page) => setParams((previous) => ({ ...previous, page }))}
                    isLoading={isLoading}
                />
            </div>
        </AppLayout>
    );
}
