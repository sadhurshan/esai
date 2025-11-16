import { zodResolver } from '@hookform/resolvers/zod';
import { format } from 'date-fns';
import { Plus, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';

import { ApiKeyCard } from '@/components/admin/api-key-card';
import Heading from '@/components/heading';
import { EmptyState } from '@/components/empty-state';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Button } from '@/components/ui/button';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { successToast, errorToast } from '@/components/toasts';
import { useApiKeys } from '@/hooks/api/admin/use-api-keys';
import { useCreateApiKey } from '@/hooks/api/admin/use-create-api-key';
import { useRevokeApiKey } from '@/hooks/api/admin/use-revoke-api-key';
import type { ApiKeyIssueResult, ApiKeyListItem } from '@/types/admin';

type ScopeOption = {
    value: string;
    label: string;
    description?: string;
};

// TODO: Replace scope catalog with source of truth once spec enumerates allowed API scopes.
const DEFAULT_SCOPE_OPTIONS: ScopeOption[] = [
    {
        value: 'public_api',
        label: 'Public API',
        description: 'Full access to buyer REST APIs.',
    },
    {
        value: 'supplier_api',
        label: 'Supplier API',
        description: 'Supplier onboarding and quote submission.',
    },
    {
        value: 'webhooks.manage',
        label: 'Webhook management',
        description: 'Create and update webhook subscriptions programmatically.',
    },
    {
        value: 'analytics.read',
        label: 'Analytics (read only)',
        description: 'Query analytics exports and dashboards.',
    },
    {
        value: 'orders.write',
        label: 'Orders (write)',
        description: 'Create purchase orders and acknowledge fulfillment.',
    },
];

const EMPTY_API_KEYS: ApiKeyListItem[] = [];

const createKeySchema = z.object({
    name: z.string().min(3, 'Name must be at least 3 characters.'),
    scopes: z.array(z.string()).min(1, 'Select at least one scope.'),
    expiresAt: z.string().optional().nullable(),
});

type CreateKeyFormValues = z.infer<typeof createKeySchema>;

export function AdminApiKeysPage() {
    const { data, isLoading } = useApiKeys();
    const createApiKey = useCreateApiKey();
    const revokeApiKey = useRevokeApiKey();

    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [tokenReveal, setTokenReveal] = useState<ApiKeyIssueResult | null>(null);
    const [revokeTarget, setRevokeTarget] = useState<ApiKeyListItem | null>(null);

    const form = useForm<CreateKeyFormValues>({
        resolver: zodResolver(createKeySchema),
        defaultValues: {
            name: '',
            scopes: ['public_api'],
            expiresAt: '',
        },
    });

    const apiKeys = data?.items ?? EMPTY_API_KEYS;

    const scopeOptions = useMemo<ScopeOption[]>(() => {
        const map = new Map<string, ScopeOption>();

        for (const option of DEFAULT_SCOPE_OPTIONS) {
            map.set(option.value, option);
        }

        for (const key of apiKeys) {
            for (const scope of key.scopes ?? []) {
                if (!map.has(scope)) {
                    map.set(scope, { value: scope, label: scope });
                }
            }
        }

        return Array.from(map.values());
    }, [apiKeys]);

    const handleCreate = form.handleSubmit(async (values) => {
        const expiresAt = values.expiresAt ? new Date(values.expiresAt) : undefined;
        const result = await createApiKey.mutateAsync({
            name: values.name.trim(),
            scopes: values.scopes,
            expiresAt,
        });

        setIsCreateOpen(false);
        setTokenReveal(result);
        form.reset();
    });

    const handleRevoke = async () => {
        if (!revokeTarget) {
            return;
        }

        await revokeApiKey.mutateAsync({ keyId: Number(revokeTarget.id) });
        setRevokeTarget(null);
    };

    const handleCopyToken = async (token: string) => {
        try {
            if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(token);
                successToast('Token copied');
            } else {
                throw new Error('Clipboard unavailable');
            }
        } catch {
            errorToast('Clipboard unavailable', `Copy this token manually: ${token}`);
        }
    };

    const issuedToken = tokenReveal?.token ?? null;

    return (
        <div className="space-y-8">
            <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <Heading
                    title="API keys"
                    description="Provision platform credentials for integrations and partners."
                />
                <Button onClick={() => setIsCreateOpen(true)} size="lg">
                    <Plus className="mr-2 h-4 w-4" /> Issue key
                </Button>
            </div>

            {isLoading ? (
                <div className="grid gap-4 md:grid-cols-2">
                    {Array.from({ length: 4 }).map((_, index) => (
                        <div key={index} className="h-56 animate-pulse rounded-xl border border-dashed border-muted" />
                    ))}
                </div>
            ) : apiKeys.length === 0 ? (
                <EmptyState
                    icon={<ShieldCheck className="h-10 w-10" aria-hidden />}
                    title="No API keys yet"
                    description="Issue credentials to allow automated systems to call the Elements Supply API."
                    ctaLabel="Issue key"
                    ctaProps={{ onClick: () => setIsCreateOpen(true) }}
                />
            ) : (
                <div className="grid gap-4 md:grid-cols-2">
                    {apiKeys.map((apiKey) => (
                        <ApiKeyCard key={apiKey.id} apiKey={apiKey} onRevoke={setRevokeTarget} />
                    ))}
                </div>
            )}

            <CreateApiKeyDialog
                open={isCreateOpen}
                onOpenChange={(open) => {
                    setIsCreateOpen(open);
                    if (!open) {
                        form.reset();
                    }
                }}
                onSubmit={handleCreate}
                isSubmitting={createApiKey.isPending}
                form={form}
                scopeOptions={scopeOptions}
            />

            <TokenRevealDialog
                token={issuedToken}
                apiKey={tokenReveal?.apiKey}
                open={Boolean(tokenReveal)}
                onClose={() => setTokenReveal(null)}
                onCopy={handleCopyToken}
            />

            <ConfirmDialog
                open={Boolean(revokeTarget)}
                onOpenChange={(open) => setRevokeTarget(open ? revokeTarget : null)}
                title="Revoke API key?"
                description={revokeTarget ? `This will permanently revoke ${revokeTarget.name}. Applications using it will fail immediately.` : ''}
                confirmLabel="Revoke"
                isProcessing={revokeApiKey.isPending}
                onConfirm={handleRevoke}
            />
        </div>
    );
}

interface CreateDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSubmit: () => void;
    isSubmitting: boolean;
    form: ReturnType<typeof useForm<CreateKeyFormValues>>;
    scopeOptions: ScopeOption[];
}

function CreateApiKeyDialog({ open, onOpenChange, onSubmit, isSubmitting, form, scopeOptions }: CreateDialogProps) {
    const selectedScopes = form.watch('scopes') ?? [];

    const toggleScope = (scope: string, checked: boolean) => {
        const current = new Set(selectedScopes);
        if (checked) {
            current.add(scope);
        } else {
            current.delete(scope);
        }
        form.setValue('scopes', Array.from(current));
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Issue API key</DialogTitle>
                    <DialogDescription>
                        Keys are scoped credentials. You can set an optional expiry to rotate automatically.
                    </DialogDescription>
                </DialogHeader>
                <form className="space-y-6" onSubmit={onSubmit}>
                    <div className="space-y-2">
                        <Label htmlFor="api-key-name">Name</Label>
                        <Input
                            id="api-key-name"
                            placeholder="ERP integration"
                            {...form.register('name')}
                            aria-invalid={Boolean(form.formState.errors.name)}
                        />
                        {form.formState.errors.name ? (
                            <p className="text-sm text-destructive">{form.formState.errors.name.message}</p>
                        ) : null}
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Scopes</Label>
                            <div className="space-y-3">
                                {scopeOptions.map((scope) => {
                                    const checked = selectedScopes?.includes(scope.value) ?? false;
                                    return (
                                        <label key={scope.value} className="flex items-start gap-3">
                                            <Checkbox
                                                checked={checked}
                                                onCheckedChange={(state) => toggleScope(scope.value, Boolean(state))}
                                            />
                                            <span>
                                                <span className="block text-sm font-medium text-foreground">{scope.label}</span>
                                                <span className="text-xs text-muted-foreground">{scope.description}</span>
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                            {form.formState.errors.scopes ? (
                                <p className="text-sm text-destructive">{form.formState.errors.scopes.message}</p>
                            ) : null}
                        </div>
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="api-key-expiry">Expires at</Label>
                                <Input
                                    id="api-key-expiry"
                                    type="datetime-local"
                                    {...form.register('expiresAt')}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Leave blank for a non-expiring key. Use UTC timestamps.
                                </p>
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitting}>
                            Issue key
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

interface TokenDialogProps {
    token: string | null;
    apiKey?: ApiKeyListItem;
    open: boolean;
    onClose: () => void;
    onCopy: (token: string) => void;
}

function TokenRevealDialog({ token, apiKey, open, onClose, onCopy }: TokenDialogProps) {
    if (!token || !apiKey) {
        return null;
    }

    const createdAt = apiKey.createdAt ? format(new Date(apiKey.createdAt), 'PPpp') : null;

    return (
        <Dialog open={open} onOpenChange={(next) => (!next ? onClose() : null)}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Copy this token now</DialogTitle>
                    <DialogDescription>
                        This value is only shown once. Store it securely and treat it like a password.
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-4">
                    <div className="rounded-lg border border-dashed border-muted bg-muted/40 p-4">
                        <code className="block break-all font-mono text-sm text-foreground">{token}</code>
                    </div>
                    <div className="space-y-1 text-sm text-muted-foreground">
                        <p>Key name: {apiKey.name}</p>
                        <p>Issued: {createdAt ?? 'Just now'}</p>
                        <div className="flex flex-wrap gap-2">
                            {apiKey.scopes?.map((scope) => (
                                <Badge key={scope} variant="outline">
                                    {scope}
                                </Badge>
                            ))}
                        </div>
                    </div>
                </div>
                <DialogFooter className="justify-between gap-2 sm:justify-end">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => onCopy(token)}
                        className="sm:w-auto"
                    >
                        Copy token
                    </Button>
                    <Button type="button" onClick={onClose}>
                        I stored it safely
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
