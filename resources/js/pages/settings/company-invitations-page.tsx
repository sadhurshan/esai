import { zodResolver } from '@hookform/resolvers/zod';
import { formatDistanceToNow } from 'date-fns';
import { MailPlus, ShieldCheck, UserPlus2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { useFieldArray, useForm } from 'react-hook-form';
import { z } from 'zod';

import { EmptyState } from '@/components/empty-state';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import {
    Form,
    FormControl,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';
import {
    COMPANY_ROLE_LABELS,
    COMPANY_ROLE_OPTIONS,
    COMPANY_ROLE_VALUES,
} from '@/constants/company-roles';
import { useAuth } from '@/contexts/auth-context';
import {
    useCompanyInvitations,
    useRevokeCompanyInvitation,
    useSendCompanyInvitations,
    type CompanyInvitationCollection,
    type InvitationDraft,
} from '@/hooks/api/useCompanyInvitations';
import { cn } from '@/lib/utils';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import type {
    CompanyInvitation,
    CompanyInvitationStatus,
    CompanyUserRole,
} from '@/types/company';

const MAX_INVITES_PER_BATCH = 25;
const DEFAULT_ROLE: CompanyUserRole = 'buyer_requester';

const invitationEntrySchema = z.object({
    email: z
        .string()
        .trim()
        .min(1, 'Email is required.')
        .email('Enter a valid email.'),
    role: z.enum(COMPANY_ROLE_VALUES, { required_error: 'Select a role.' }),
    expiresAt: z
        .string()
        .optional()
        .nullable()
        .refine(
            (value) =>
                !value ||
                value.length === 0 ||
                !Number.isNaN(Date.parse(value)),
            'Enter a valid expiration date.',
        ),
    message: z
        .string()
        .optional()
        .nullable()
        .refine(
            (value) => !value || value.length <= 500,
            'Message must be 500 characters or less.',
        ),
});

const invitationFormSchema = z.object({
    invitations: z
        .array(invitationEntrySchema)
        .min(1, 'Add at least one teammate to invite.')
        .max(
            MAX_INVITES_PER_BATCH,
            `You can send up to ${MAX_INVITES_PER_BATCH} invitations at once.`,
        ),
});

type InvitationFormValues = z.infer<typeof invitationFormSchema>;

const STATUS_META: Record<
    CompanyInvitationStatus,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
        className?: string;
    }
> = {
    pending: { label: 'Pending', variant: 'secondary' },
    accepted: { label: 'Accepted', variant: 'default' },
    revoked: { label: 'Revoked', variant: 'destructive' },
    expired: {
        label: 'Expired',
        variant: 'outline',
        className: 'border-amber-300 text-amber-800 bg-amber-50',
    },
};

function getDefaultInvitationRow() {
    return {
        email: '',
        role: DEFAULT_ROLE,
        expiresAt: '',
        message: '',
    } satisfies InvitationFormValues['invitations'][number];
}

function sanitizeInvitationPayload(
    values: InvitationFormValues['invitations'],
): InvitationDraft[] {
    return values.map((entry) => ({
        email: entry.email.trim(),
        role: entry.role,
        expiresAt: normalizeExpiration(entry.expiresAt),
        message: entry.message?.trim() ? entry.message.trim() : null,
    }));
}

function normalizeExpiration(value?: string | null): string | null {
    if (!value || value.length === 0) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toISOString();
}

function formatRelative(value?: string | null): string {
    if (!value) {
        return '—';
    }

    try {
        return formatDistanceToNow(new Date(value), { addSuffix: true });
    } catch (error) {
        void error;
        return '—';
    }
}

function formatAbsolute(value?: string | null): string {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleString();
    } catch (error) {
        void error;
        return '—';
    }
}

function computeStats(collection?: CompanyInvitationCollection | null) {
    const items = collection?.items ?? [];

    return {
        total: items.length,
        pending: items.filter((invitation) => invitation.status === 'pending')
            .length,
        accepted: items.filter((invitation) => invitation.status === 'accepted')
            .length,
        revokedOrExpired: items.filter(
            (invitation) =>
                invitation.status === 'revoked' ||
                invitation.status === 'expired',
        ).length,
    };
}

export function CompanyInvitationsPage() {
    const { state, isAdmin } = useAuth();
    const userRole = state.user?.role ?? null;
    const isOwner = userRole === 'owner';
    const canManageInvitations = isOwner || isAdmin;

    const invitationsQuery = useCompanyInvitations(undefined, {
        enabled: canManageInvitations,
    });
    const sendInvitations = useSendCompanyInvitations();
    const revokeInvitation = useRevokeCompanyInvitation();
    const [invitationToRevoke, setInvitationToRevoke] =
        useState<CompanyInvitation | null>(null);

    const form = useForm<InvitationFormValues>({
        resolver: zodResolver(invitationFormSchema),
        defaultValues: { invitations: [getDefaultInvitationRow()] },
        mode: 'onChange',
    });

    const invitationFields = useFieldArray({
        control: form.control,
        name: 'invitations',
    });

    const stats = useMemo(
        () => computeStats(invitationsQuery.data),
        [invitationsQuery.data],
    );

    if (!canManageInvitations) {
        return <AccessDeniedPage />;
    }

    const handleSend = form.handleSubmit(async (values) => {
        const payload = sanitizeInvitationPayload(values.invitations);
        try {
            await sendInvitations.mutateAsync(payload);
            publishToast({
                variant: 'success',
                title:
                    payload.length === 1
                        ? 'Invitation sent'
                        : 'Invitations sent',
                description:
                    'Each recipient received an email with their acceptance link.',
            });
            form.reset({ invitations: [getDefaultInvitationRow()] });
        } catch (error) {
            void error;
            publishToast({
                variant: 'destructive',
                title: 'Unable to send invitations',
                description:
                    'Please review the highlighted fields or try again later.',
            });
        }
    });

    const handleConfirmRevoke = async () => {
        if (!invitationToRevoke) {
            return;
        }

        try {
            await revokeInvitation.mutateAsync(invitationToRevoke.id);
            publishToast({
                variant: 'success',
                title: 'Invitation revoked',
                description: `${invitationToRevoke.email} can no longer accept this invite.`,
            });
        } catch (error) {
            void error;
            publishToast({
                variant: 'destructive',
                title: 'Unable to revoke invitation',
                description: 'Please try again.',
            });
        } finally {
            setInvitationToRevoke(null);
        }
    };

    return (
        <div className="space-y-6">
            <Helmet>
                <title>Company invitations · Elements Supply</title>
            </Helmet>
            <div>
                <p className="text-sm text-muted-foreground">
                    Workspace · Settings
                </p>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Invite your team
                </h1>
                <p className="text-sm text-muted-foreground">
                    Send role-scoped invitations so buyers, suppliers, and
                    finance teams can collaborate inside your tenant.
                </p>
            </div>
            <Alert>
                <ShieldCheck className="h-5 w-5" />
                <AlertTitle>Owner or buyer admin required</AlertTitle>
                <AlertDescription>
                    Only owners and buyer admins can invite new users.
                    Invitations expire automatically after 7 days unless you set
                    an earlier deadline.
                </AlertDescription>
            </Alert>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <InvitationStat
                    label="Pending"
                    value={stats.pending}
                    description="Awaiting acceptance"
                />
                <InvitationStat
                    label="Accepted"
                    value={stats.accepted}
                    description="Joined your workspace"
                />
                <InvitationStat
                    label="Revoked / expired"
                    value={stats.revokedOrExpired}
                    description="No longer valid"
                />
                <InvitationStat
                    label="Total sent"
                    value={stats.total}
                    description="All invitations"
                />
            </div>
            <div className="grid gap-6 lg:grid-cols-[1.3fr_1.7fr]">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <UserPlus2 className="h-5 w-5 text-muted-foreground" />{' '}
                            Invite teammates
                        </CardTitle>
                        <CardDescription>
                            Draft up to {MAX_INVITES_PER_BATCH} invitations per
                            batch.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Form {...form}>
                            <form onSubmit={handleSend} className="space-y-4">
                                <div className="space-y-4">
                                    {invitationFields.fields.map(
                                        (field, index) => (
                                            <div
                                                key={field.id}
                                                className="space-y-4 rounded-lg border p-4"
                                            >
                                                <div className="flex items-center justify-between">
                                                    <p className="text-sm font-medium">
                                                        Invitation {index + 1}
                                                    </p>
                                                    {invitationFields.fields
                                                        .length > 1 ? (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                invitationFields.remove(
                                                                    index,
                                                                )
                                                            }
                                                            disabled={
                                                                sendInvitations.isPending
                                                            }
                                                        >
                                                            Remove
                                                        </Button>
                                                    ) : null}
                                                </div>
                                                <div className="grid gap-4">
                                                    <FormField
                                                        control={form.control}
                                                        name={`invitations.${index}.email`}
                                                        render={({
                                                            field: controlField,
                                                        }) => (
                                                            <FormItem>
                                                                <FormLabel>
                                                                    Email
                                                                    address
                                                                </FormLabel>
                                                                <FormControl>
                                                                    <Input
                                                                        type="email"
                                                                        placeholder="teammate@company.com"
                                                                        {...controlField}
                                                                    />
                                                                </FormControl>
                                                                <FormMessage />
                                                            </FormItem>
                                                        )}
                                                    />
                                                    <FormField
                                                        control={form.control}
                                                        name={`invitations.${index}.role`}
                                                        render={({
                                                            field: controlField,
                                                        }) => (
                                                            <FormItem>
                                                                <FormLabel>
                                                                    Role
                                                                </FormLabel>
                                                                <Select
                                                                    value={
                                                                        controlField.value
                                                                    }
                                                                    onValueChange={
                                                                        controlField.onChange
                                                                    }
                                                                    disabled={
                                                                        sendInvitations.isPending
                                                                    }
                                                                >
                                                                    <FormControl>
                                                                        <SelectTrigger>
                                                                            <SelectValue placeholder="Select a role" />
                                                                        </SelectTrigger>
                                                                    </FormControl>
                                                                    <SelectContent>
                                                                        <SelectGroup>
                                                                            {COMPANY_ROLE_OPTIONS.map(
                                                                                (
                                                                                    role,
                                                                                ) => (
                                                                                    <SelectItem
                                                                                        key={
                                                                                            role.value
                                                                                        }
                                                                                        value={
                                                                                            role.value
                                                                                        }
                                                                                    >
                                                                                        <div>
                                                                                            <p className="text-sm font-medium">
                                                                                                {
                                                                                                    role.label
                                                                                                }
                                                                                            </p>
                                                                                            <p className="text-xs text-muted-foreground">
                                                                                                {
                                                                                                    role.description
                                                                                                }
                                                                                            </p>
                                                                                        </div>
                                                                                    </SelectItem>
                                                                                ),
                                                                            )}
                                                                        </SelectGroup>
                                                                    </SelectContent>
                                                                </Select>
                                                                <FormMessage />
                                                            </FormItem>
                                                        )}
                                                    />
                                                    <FormField
                                                        control={form.control}
                                                        name={`invitations.${index}.expiresAt`}
                                                        render={({
                                                            field: controlField,
                                                        }) => (
                                                            <FormItem>
                                                                <FormLabel>
                                                                    Expires at
                                                                </FormLabel>
                                                                <FormControl>
                                                                    <Input
                                                                        type="datetime-local"
                                                                        {...controlField}
                                                                        value={
                                                                            controlField.value ??
                                                                            ''
                                                                        }
                                                                    />
                                                                </FormControl>
                                                                <p className="text-xs text-muted-foreground">
                                                                    Leave blank
                                                                    to keep the
                                                                    default
                                                                    7-day
                                                                    expiration
                                                                    window.
                                                                </p>
                                                                <FormMessage />
                                                            </FormItem>
                                                        )}
                                                    />
                                                    <FormField
                                                        control={form.control}
                                                        name={`invitations.${index}.message`}
                                                        render={({
                                                            field: controlField,
                                                        }) => (
                                                            <FormItem>
                                                                <FormLabel>
                                                                    Personal
                                                                    message
                                                                </FormLabel>
                                                                <FormControl>
                                                                    <Textarea
                                                                        rows={3}
                                                                        placeholder="Let teammates know why you are inviting them."
                                                                        {...controlField}
                                                                        value={
                                                                            controlField.value ??
                                                                            ''
                                                                        }
                                                                    />
                                                                </FormControl>
                                                                <FormMessage />
                                                            </FormItem>
                                                        )}
                                                    />
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                                <div className="flex flex-wrap items-center gap-3">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            invitationFields.append(
                                                getDefaultInvitationRow(),
                                            )
                                        }
                                        disabled={
                                            invitationFields.fields.length >=
                                                MAX_INVITES_PER_BATCH ||
                                            sendInvitations.isPending
                                        }
                                    >
                                        Add another invite
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={
                                            sendInvitations.isPending ||
                                            !form.formState.isValid
                                        }
                                    >
                                        {sendInvitations.isPending
                                            ? 'Sending…'
                                            : 'Send invitations'}
                                    </Button>
                                </div>
                            </form>
                        </Form>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4">
                        <div>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <MailPlus className="h-5 w-5 text-muted-foreground" />{' '}
                                Recent invitations
                            </CardTitle>
                            <CardDescription>
                                Track acceptance status and revoke invites if
                                needed.
                            </CardDescription>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => invitationsQuery.refetch()}
                            disabled={invitationsQuery.isFetching}
                        >
                            Refresh
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {invitationsQuery.isLoading ? (
                            <InvitationListSkeleton />
                        ) : invitationsQuery.isError ? (
                            <Alert variant="destructive">
                                <AlertTitle>
                                    Unable to load invitations
                                </AlertTitle>
                                <AlertDescription>
                                    {invitationsQuery.error?.message ??
                                        'Please try again later.'}
                                </AlertDescription>
                            </Alert>
                        ) : (invitationsQuery.data?.items?.length ?? 0) ===
                          0 ? (
                            <EmptyState
                                title="No invitations yet"
                                description="Invite your sourcing, supplier, or finance teammates to get started."
                                icon={<UserPlus2 className="h-8 w-8" />}
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="text-left text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="py-2 pr-3 font-medium">
                                                Recipient
                                            </th>
                                            <th className="py-2 pr-3 font-medium">
                                                Status
                                            </th>
                                            <th className="py-2 pr-3 font-medium">
                                                Sent
                                            </th>
                                            <th className="py-2 pr-3 font-medium">
                                                Expires
                                            </th>
                                            <th className="py-2 pr-3 text-right font-medium">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {invitationsQuery.data?.items.map(
                                            (invitation) => (
                                                <InvitationRow
                                                    key={invitation.id}
                                                    invitation={invitation}
                                                    onRevoke={() =>
                                                        setInvitationToRevoke(
                                                            invitation,
                                                        )
                                                    }
                                                    disableActions={
                                                        revokeInvitation.isPending
                                                    }
                                                />
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
            <ConfirmDialog
                open={Boolean(invitationToRevoke)}
                onOpenChange={(open) => {
                    if (!open) {
                        setInvitationToRevoke(null);
                    }
                }}
                title="Revoke invitation"
                description={
                    invitationToRevoke
                        ? `This prevents ${invitationToRevoke.email} from joining your workspace unless you send a new invitation.`
                        : undefined
                }
                confirmLabel={
                    revokeInvitation.isPending ? 'Revoking…' : 'Revoke'
                }
                confirmVariant="destructive"
                isProcessing={revokeInvitation.isPending}
                onConfirm={handleConfirmRevoke}
            />
        </div>
    );
}

interface InvitationStatProps {
    label: string;
    value: number;
    description: string;
}

function InvitationStat({ label, value, description }: InvitationStatProps) {
    return (
        <Card>
            <CardContent className="space-y-1 p-4">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p className="text-2xl font-semibold">{value}</p>
                <p className="text-xs text-muted-foreground">{description}</p>
            </CardContent>
        </Card>
    );
}

interface InvitationRowProps {
    invitation: CompanyInvitation;
    onRevoke: () => void;
    disableActions?: boolean;
}

function InvitationRow({
    invitation,
    onRevoke,
    disableActions,
}: InvitationRowProps) {
    const statusMeta = STATUS_META[invitation.status];
    const isPending = invitation.status === 'pending';
    const roleLabel = COMPANY_ROLE_LABELS[invitation.role] ?? invitation.role;

    return (
        <tr className="border-t">
            <td className="py-3 pr-3">
                <div className="font-medium">{invitation.email}</div>
                <div className="text-xs text-muted-foreground">{roleLabel}</div>
                {invitation.message ? (
                    <p className="mt-1 text-xs text-muted-foreground">
                        “{invitation.message}”
                    </p>
                ) : null}
            </td>
            <td className="py-3 pr-3">
                <Badge
                    variant={statusMeta.variant}
                    className={cn('capitalize', statusMeta.className)}
                >
                    {statusMeta.label}
                </Badge>
            </td>
            <td className="py-3 pr-3 align-top">
                <div>{formatAbsolute(invitation.createdAt)}</div>
                <div className="text-xs text-muted-foreground">
                    {formatRelative(invitation.createdAt)}
                </div>
            </td>
            <td className="py-3 pr-3 align-top">
                {invitation.expiresAt ? (
                    <div>
                        <div>{formatAbsolute(invitation.expiresAt)}</div>
                        <div className="text-xs text-muted-foreground">
                            {formatRelative(invitation.expiresAt)}
                        </div>
                    </div>
                ) : (
                    <span className="text-muted-foreground">
                        Default window
                    </span>
                )}
            </td>
            <td className="py-3 text-right">
                {isPending ? (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onRevoke}
                        disabled={disableActions}
                    >
                        Revoke
                    </Button>
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                )}
            </td>
        </tr>
    );
}

function InvitationListSkeleton() {
    return (
        <div className="space-y-3">
            {[0, 1, 2].map((row) => (
                <div key={row} className="flex items-center gap-4">
                    <Skeleton className="h-12 w-full" />
                </div>
            ))}
        </div>
    );
}
