import { useMemo, useState, type ComponentType } from 'react';
import { Helmet } from 'react-helmet-async';
import { formatDistanceToNow } from 'date-fns';
import { AlertTriangle, ShieldCheck, UserCog, UserMinus2, Users } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import {
    useCompanyMembers,
    useRemoveCompanyMember,
    useUpdateCompanyMember,
} from '@/hooks/api/useCompanyMembers';
import { COMPANY_ROLE_LABELS, COMPANY_ROLE_OPTIONS } from '@/constants/company-roles';
import type { CompanyMember, CompanyMemberRoleConflict, CompanyUserRole } from '@/types/company';

const PAGE_SIZE = 25;
const BUYER_ROLES = new Set<CompanyUserRole>(['owner', 'buyer_admin', 'buyer_member', 'buyer_requester', 'finance']);
const SUPPLIER_ROLES = new Set<CompanyUserRole>(['supplier_admin', 'supplier_estimator']);

export function CompanyMembersPage() {
    const { state, isAdmin } = useAuth();
    const currentUserId = state.user?.id ?? null;
    const userRole = state.user?.role ?? null;
    const isOwner = userRole === 'owner';
    const canManageMembers = isOwner || isAdmin;

    const [cursor, setCursor] = useState<string | undefined>(undefined);
    const [memberToRemove, setMemberToRemove] = useState<CompanyMember | null>(null);

    const membersQuery = useCompanyMembers({ cursor, perPage: PAGE_SIZE });
    const updateMember = useUpdateCompanyMember();
    const removeMember = useRemoveCompanyMember();

    const members = useMemo(() => membersQuery.data?.items ?? [], [membersQuery.data]);
    const meta = useMemo(() => membersQuery.data?.meta, [membersQuery.data]);
    const perPage = meta?.perPage ?? PAGE_SIZE;
    const canGoPrev = Boolean(meta?.prevCursor);
    const canGoNext = Boolean(meta?.nextCursor);

    const stats = useMemo(() => computeStats(members), [members]);
    const conflictMembers = useMemo(
        () => members.filter((member) => member.roleConflict?.hasConflict),
        [members]
    );
    const hasRoleConflicts = conflictMembers.length > 0;

    if (!canManageMembers) {
        return <AccessDeniedPage />;
    }

    const handleRoleChange = async (member: CompanyMember, nextRole: CompanyUserRole) => {
        if (member.role === nextRole) {
            return;
        }

        try {
            await updateMember.mutateAsync({ memberId: member.id, role: nextRole });
            publishToast({
                variant: 'success',
                title: 'Role updated',
                description: `${member.name} now has ${COMPANY_ROLE_LABELS[nextRole] ?? nextRole} access.`,
            });
        } catch (error) {
            void error;
            publishToast({
                variant: 'destructive',
                title: 'Unable to update role',
                description: 'Please try again or ensure at least one owner remains.',
            });
        }
    };

    const handleRemoveConfirmed = async () => {
        if (!memberToRemove) {
            return;
        }

        try {
            await removeMember.mutateAsync(memberToRemove.id);
            publishToast({
                variant: 'success',
                title: 'Member removed',
                description: `${memberToRemove.name} no longer has access to this workspace.`,
            });
        } catch (error) {
            void error;
            publishToast({
                variant: 'destructive',
                title: 'Unable to remove member',
                description: 'Ensure another owner exists before removing this user.',
            });
        } finally {
            setMemberToRemove(null);
        }
    };

    const isLoading = membersQuery.isLoading;
    const isError = membersQuery.isError;
    const hasMembers = members.length > 0;

    return (
        <div className="space-y-6">
            <Helmet>
                <title>Team roster · Elements Supply</title>
            </Helmet>
            <div>
                <p className="text-sm text-muted-foreground">Workspace · Settings</p>
                <h1 className="text-2xl font-semibold tracking-tight">Manage your team</h1>
                <p className="text-sm text-muted-foreground">
                    Downgrade, remove, or reassign roles for teammates who already accepted their invitations.
                </p>
            </div>
            <Alert>
                <ShieldCheck className="h-5 w-5" />
                <AlertTitle>Owner or buyer admin required</AlertTitle>
                <AlertDescription>
                    Only owners and buyer admins can change roles or revoke workspace access. Every company must retain at least one owner.
                </AlertDescription>
            </Alert>
            {hasRoleConflicts ? (
                <Alert variant="destructive">
                    <AlertTriangle className="h-5 w-5" />
                    <AlertTitle>Cross-company role conflicts detected</AlertTitle>
                    <AlertDescription className="space-y-2">
                        <p>
                            Some teammates hold different roles in other workspaces. Confirm these cross-tenant assignments are intentional before downgrading or removing access.
                        </p>
                        <ul className="space-y-1 text-xs text-muted-foreground">
                            {conflictMembers.slice(0, 3).map((member) => (
                                <li key={member.id}>
                                    <span className="font-medium text-foreground">{member.name}</span>
                                    <span> · {formatConflictSummary(member.roleConflict)}</span>
                                </li>
                            ))}
                        </ul>
                        {conflictMembers.length > 3 ? (
                            <p className="text-xs font-medium text-muted-foreground">
                                +{conflictMembers.length - 3} additional conflicts
                            </p>
                        ) : null}
                    </AlertDescription>
                </Alert>
            ) : null}
            <div className="grid gap-4 md:grid-cols-3">
                <MemberStatCard icon={Users} label="Members on this page" value={stats.total} helper={`Up to ${perPage} per page`} />
                <MemberStatCard
                    icon={UserCog}
                    label="Buyer roles"
                    value={stats.buyerCount}
                    helper="Owners, admins, members, requesters, finance"
                />
                <MemberStatCard
                    icon={UserMinus2}
                    label="Supplier roles"
                    value={stats.supplierCount}
                    helper="Supplier admins & estimators"
                />
            </div>
            <Card>
                <CardHeader className="flex flex-row items-center justify-between gap-4">
                    <div>
                        <CardTitle className="text-xl">Team roster</CardTitle>
                        <CardDescription>View active members, adjust their roles, or revoke workspace access.</CardDescription>
                    </div>
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <span>
                            Showing {members.length} of {perPage} members
                        </span>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setCursor(meta?.prevCursor ?? undefined)}
                                disabled={isLoading || !canGoPrev}
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    if (meta?.nextCursor) {
                                        setCursor(meta.nextCursor);
                                    }
                                }}
                                disabled={isLoading || !canGoNext}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <MemberListSkeleton />
                    ) : isError ? (
                        <Alert variant="destructive">
                            <AlertTitle>Unable to load team roster</AlertTitle>
                            <AlertDescription>Please refresh the page or try again later.</AlertDescription>
                        </Alert>
                    ) : !hasMembers ? (
                        <EmptyState
                            title="No teammates yet"
                            description="Invite collaborators from the invitations tab, then manage their access here."
                            icon={<Users className="h-8 w-8" />}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs uppercase text-muted-foreground">
                                    <tr>
                                        <th className="py-2 pr-3 font-medium">Member</th>
                                        <th className="py-2 pr-3 font-medium">Role</th>
                                        <th className="py-2 pr-3 font-medium">Last activity</th>
                                        <th className="py-2 pr-3 font-medium">Membership</th>
                                        <th className="py-2 pl-3 text-right font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {members.map((member) => (
                                        <MemberRow
                                            key={member.id}
                                            member={member}
                                            currentUserId={currentUserId}
                                            isOwner={isOwner}
                                            onRoleChange={handleRoleChange}
                                            onRemove={setMemberToRemove}
                                            isUpdating={updateMember.isPending}
                                            isRemoving={removeMember.isPending}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>
            <ConfirmDialog
                open={Boolean(memberToRemove)}
                onOpenChange={(open) => {
                    if (!open) {
                        setMemberToRemove(null);
                    }
                }}
                title="Remove member"
                description={
                    memberToRemove
                        ? `Revoking access will remove ${memberToRemove.name} from this workspace immediately.`
                        : undefined
                }
                confirmLabel={removeMember.isPending ? 'Removing…' : 'Remove member'}
                confirmVariant="destructive"
                isProcessing={removeMember.isPending}
                onConfirm={handleRemoveConfirmed}
            />
        </div>
    );
}

interface MemberStatCardProps {
    icon: ComponentType<{ className?: string }>;
    label: string;
    value: number;
    helper: string;
}

function MemberStatCard({ icon: Icon, label, value, helper }: MemberStatCardProps) {
    return (
        <Card>
            <CardContent className="flex items-center gap-4 p-4">
                <div className="rounded-full bg-primary/10 p-3 text-primary">
                    <Icon className="h-5 w-5" />
                </div>
                <div>
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p className="text-2xl font-semibold">{value}</p>
                    <p className="text-xs text-muted-foreground">{helper}</p>
                </div>
            </CardContent>
        </Card>
    );
}

interface MemberRowProps {
    member: CompanyMember;
    currentUserId: number | null;
    isOwner: boolean;
    onRoleChange: (member: CompanyMember, nextRole: CompanyUserRole) => void;
    onRemove: (member: CompanyMember | null) => void;
    isUpdating: boolean;
    isRemoving: boolean;
}

function MemberRow({
    member,
    currentUserId,
    isOwner,
    onRoleChange,
    onRemove,
    isUpdating,
    isRemoving,
}: MemberRowProps) {
    const initials = member.name
        .split(' ')
        .filter((segment) => segment.length > 0)
        .slice(0, 2)
        .map((segment) => segment[0].toUpperCase())
        .join('');

    const isSelf = currentUserId === member.id;
    const isOwnerRole = member.role === 'owner';
    const disableRoleChange = (isOwnerRole && !isOwner) || isUpdating;
    const disableRemoval = isSelf || (isOwnerRole && !isOwner) || isRemoving;

    return (
        <tr className="border-t">
            <td className="py-3 pr-3">
                <div className="flex items-center gap-3">
                    <Avatar className="h-9 w-9">
                        {member.avatarUrl ? <AvatarImage src={member.avatarUrl} alt={member.name} /> : null}
                        <AvatarFallback>{initials || member.email[0]?.toUpperCase() || '?'}</AvatarFallback>
                    </Avatar>
                    <div>
                        <p className="font-medium leading-tight">{member.name}</p>
                        <p className="text-xs text-muted-foreground">{member.email}</p>
                        {member.jobTitle ? (
                            <p className="text-xs text-muted-foreground">{member.jobTitle}</p>
                        ) : null}
                    </div>
                </div>
            </td>
            <td className="py-3 pr-3">
                <Select
                    value={member.role}
                    onValueChange={(value) => onRoleChange(member, value as CompanyUserRole)}
                    disabled={disableRoleChange}
                >
                    <SelectTrigger className="w-[14rem]">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {COMPANY_ROLE_OPTIONS.map((role) => (
                            <SelectItem key={role.value} value={role.value}>
                                <div>
                                    <p className="text-sm font-medium">{role.label}</p>
                                    <p className="text-xs text-muted-foreground">{role.description}</p>
                                </div>
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </td>
            <td className="py-3 pr-3">
                {member.lastLoginAt ? (
                    <div>
                        <p>{formatDate(member.lastLoginAt)}</p>
                        <p className="text-xs text-muted-foreground">{formatRelative(member.lastLoginAt)}</p>
                    </div>
                ) : (
                    <span className="text-xs text-muted-foreground">No activity</span>
                )}
            </td>
            <td className="py-3 pr-3">
                <div className="space-y-1 text-xs text-muted-foreground">
                    <div className="flex items-center gap-2">
                        <Badge variant={member.membership.isDefault ? 'default' : 'secondary'}>
                            {member.membership.isDefault ? 'Default' : 'Non-default'}
                        </Badge>
                        {member.isActiveCompany ? (
                            <Badge variant="outline" className="border-green-300 text-green-700">
                                Active session
                            </Badge>
                        ) : null}
                        {member.roleConflict?.hasConflict ? (
                            <RoleConflictBadge conflict={member.roleConflict} />
                        ) : null}
                    </div>
                    {member.membership.lastUsedAt ? (
                        <p>Last used {formatRelative(member.membership.lastUsedAt)}</p>
                    ) : null}
                </div>
            </td>
            <td className="py-3 pl-3 text-right">
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-destructive"
                    onClick={() => onRemove(member)}
                    disabled={disableRemoval}
                >
                    Remove
                </Button>
            </td>
        </tr>
    );
}

function RoleConflictBadge({ conflict }: { conflict: CompanyMemberRoleConflict }) {
    const label = conflict.buyerSupplierConflict ? 'Buyer vs supplier' : 'Role mismatch';

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Badge variant="destructive">Role conflict</Badge>
            </TooltipTrigger>
            <TooltipContent>
                <p className="font-semibold">{label}</p>
                <p className="text-xs text-white/90">{formatConflictSummary(conflict)}</p>
            </TooltipContent>
        </Tooltip>
    );
}

function MemberListSkeleton() {
    return (
        <div className="space-y-4">
            {[0, 1, 2, 3].map((row) => (
                <div key={row} className="grid grid-cols-5 gap-4">
                    <Skeleton className="h-12" />
                    <Skeleton className="h-12" />
                    <Skeleton className="h-12" />
                    <Skeleton className="h-12" />
                    <Skeleton className="h-12" />
                </div>
            ))}
        </div>
    );
}

function computeStats(members: CompanyMember[]) {
    const buyerCount = members.filter((member) => BUYER_ROLES.has(member.role)).length;
    const supplierCount = members.filter((member) => SUPPLIER_ROLES.has(member.role)).length;
    const total = members.length;

    return { buyerCount, supplierCount, total };
}

function formatDate(value: string) {
    try {
        return new Date(value).toLocaleString();
    } catch (error) {
        void error;
        return value;
    }
}

function formatRelative(value: string) {
    try {
        return formatDistanceToNow(new Date(value), { addSuffix: true });
    } catch (error) {
        void error;
        return '—';
    }
}

function formatConflictSummary(conflict: CompanyMemberRoleConflict) {
    const roleList = conflict.distinctRoles.join(', ');
    const companyLabel = conflict.totalCompanies === 1 ? 'company' : 'companies';

    if (conflict.buyerSupplierConflict) {
        return `${roleList} across buyer and supplier workspaces (${conflict.totalCompanies} ${companyLabel})`;
    }

    return `${roleList} across ${conflict.totalCompanies} ${companyLabel}`;
}
