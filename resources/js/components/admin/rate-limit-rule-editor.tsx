import { AlertTriangle, Plus, RefreshCcw, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import { EmptyState } from '@/components/empty-state';
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
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type {
    RateLimitRuleInput,
    RateLimitRuleItem,
    SyncRateLimitPayload,
} from '@/types/admin';

export interface RateLimitRuleEditorProps {
    rules?: RateLimitRuleItem[];
    isLoading?: boolean;
    isSaving?: boolean;
    onSave: (payload: SyncRateLimitPayload) => Promise<void> | void;
}

interface RuleDraft extends RateLimitRuleInput {
    clientId: string;
    companyId?: number;
}

const LIMIT_EXAMPLES: Record<string, string> = {
    public_api: 'Public REST API traffic',
    supplier_api: 'Supplier surface',
    web_app: 'Web sessions per tenant',
};

const DEFAULT_WINDOW_SECONDS = 60;
const DEFAULT_MAX_REQUESTS = 100;

export function RateLimitRuleEditor({
    rules,
    isLoading = false,
    isSaving = false,
    onSave,
}: RateLimitRuleEditorProps) {
    const [drafts, setDrafts] = useState<RuleDraft[]>(
        () => rules?.map((rule) => mapRuleToDraft(rule)) ?? [],
    );

    const hasChanges = useMemo(
        () => computeDirtyState(drafts, rules ?? []),
        [drafts, rules],
    );
    const errorMap = useMemo(
        () =>
            new Map(
                drafts.map((draft) => [draft.clientId, validateDraft(draft)]),
            ),
        [drafts],
    );
    const hasValidationIssues = useMemo(
        () => Array.from(errorMap.values()).some(Boolean),
        [errorMap],
    );

    const pendingDeletions = drafts.filter((draft) => draft.markForDeletion);

    const handleFieldChange = (
        clientId: string,
        field: keyof RateLimitRuleInput,
        value: string | number | boolean,
    ) => {
        setDrafts((prev) =>
            prev.map((draft) =>
                draft.clientId === clientId
                    ? {
                          ...draft,
                          [field]:
                              typeof value === 'string' && field !== 'scope'
                                  ? Number(value)
                                  : value,
                      }
                    : draft,
            ),
        );
    };

    const handleToggleActive = (clientId: string, checked: boolean) => {
        handleFieldChange(clientId, 'active', checked);
    };

    const handleAddRule = () => {
        setDrafts((prev) => [
            ...prev,
            {
                clientId: cryptoRandomId(),
                scope: '',
                windowSeconds: DEFAULT_WINDOW_SECONDS,
                maxRequests: DEFAULT_MAX_REQUESTS,
                active: true,
            },
        ]);
    };

    const handleRemove = (clientId: string) => {
        setDrafts((prev) =>
            prev
                .map((draft) => {
                    if (draft.clientId !== clientId) {
                        return draft;
                    }

                    if (!draft.id) {
                        return null;
                    }

                    return {
                        ...draft,
                        markForDeletion: !draft.markForDeletion,
                    } satisfies RuleDraft;
                })
                .filter((draft): draft is RuleDraft => Boolean(draft)),
        );
    };

    const handleReset = () => {
        setDrafts(rules?.map((rule) => mapRuleToDraft(rule)) ?? []);
    };

    const handleSubmit = async () => {
        if (!hasChanges || hasValidationIssues || isSaving) {
            return;
        }

        const payload = buildPayload(drafts);
        await onSave(payload);
    };

    if (isLoading) {
        return <LoadingSkeleton />;
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p className="text-base font-semibold text-foreground">
                        Rate limit rules
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Define throttles per integration scope. Tenants share
                        scopes such as `public_api`, `supplier_api`, and
                        `web_app`.
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={handleReset}
                        disabled={!hasChanges || isSaving}
                    >
                        <RefreshCcw className="mr-2 h-4 w-4" aria-hidden />{' '}
                        Reset
                    </Button>
                    <Button
                        type="button"
                        onClick={handleAddRule}
                        disabled={isSaving}
                    >
                        <Plus className="mr-2 h-4 w-4" aria-hidden /> Add rule
                    </Button>
                </div>
            </div>

            {drafts.length === 0 ? (
                <EmptyState
                    icon={<AlertTriangle className="h-10 w-10" aria-hidden />}
                    title="No active rules"
                    description="Add at least one scope to enforce throttles across APIs and web sessions."
                    ctaLabel="Add rule"
                    ctaProps={{ onClick: handleAddRule }}
                />
            ) : (
                <div className="space-y-4">
                    {drafts.map((draft) => {
                        const error = errorMap.get(draft.clientId);
                        const example =
                            LIMIT_EXAMPLES[draft.scope] ?? 'Custom scope';
                        const preview = buildPreviewLabel(draft);
                        const isExisting = Boolean(draft.id);
                        const markedForDeletion = Boolean(
                            draft.markForDeletion,
                        );
                        return (
                            <Card
                                key={draft.clientId}
                                className={cn(
                                    'border border-muted/60',
                                    !markedForDeletion &&
                                        !draft.active &&
                                        'opacity-90',
                                    markedForDeletion &&
                                        'border-destructive/40 bg-destructive/5',
                                )}
                                aria-label={`Rate limit rule for ${draft.scope || 'new scope'}`}
                            >
                                <CardHeader className="gap-1">
                                    <CardTitle className="flex flex-wrap items-center gap-2 text-lg">
                                        <Input
                                            value={draft.scope}
                                            onChange={(event) =>
                                                handleFieldChange(
                                                    draft.clientId,
                                                    'scope',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Scope key (e.g. public_api)"
                                            disabled={isExisting}
                                            className="max-w-sm"
                                        />
                                        <Badge
                                            variant={
                                                markedForDeletion
                                                    ? 'destructive'
                                                    : draft.active
                                                      ? 'default'
                                                      : 'outline'
                                            }
                                        >
                                            {markedForDeletion
                                                ? 'Pending deletion'
                                                : draft.active
                                                  ? 'Active'
                                                  : 'Disabled'}
                                        </Badge>
                                    </CardTitle>
                                    <CardDescription>
                                        {markedForDeletion
                                            ? 'Rule will be removed on save.'
                                            : example}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <div className="space-y-2">
                                            <label className="text-xs font-medium text-muted-foreground uppercase">
                                                Window (seconds)
                                            </label>
                                            <Input
                                                type="number"
                                                min={5}
                                                step={5}
                                                value={
                                                    draft.windowSeconds ?? ''
                                                }
                                                onChange={(event) =>
                                                    handleFieldChange(
                                                        draft.clientId,
                                                        'windowSeconds',
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={markedForDeletion}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <label className="text-xs font-medium text-muted-foreground uppercase">
                                                Max requests
                                            </label>
                                            <Input
                                                type="number"
                                                min={1}
                                                step={1}
                                                value={draft.maxRequests ?? ''}
                                                onChange={(event) =>
                                                    handleFieldChange(
                                                        draft.clientId,
                                                        'maxRequests',
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={markedForDeletion}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <label className="text-xs font-medium text-muted-foreground uppercase">
                                                Status
                                            </label>
                                            <div className="flex items-center gap-2 rounded-lg border bg-muted/40 px-3 py-2 text-sm">
                                                <Checkbox
                                                    checked={
                                                        draft.active ?? true
                                                    }
                                                    onCheckedChange={(state) =>
                                                        handleToggleActive(
                                                            draft.clientId,
                                                            Boolean(state),
                                                        )
                                                    }
                                                    disabled={markedForDeletion}
                                                />
                                                <span>
                                                    {draft.active
                                                        ? 'Enabled'
                                                        : 'Disabled'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                                        <Badge
                                            variant="outline"
                                            className="font-mono text-xs"
                                        >
                                            {preview}
                                        </Badge>
                                        <span>Requests per window</span>
                                    </div>
                                    {error ? (
                                        <p className="text-sm text-destructive">
                                            {error}
                                        </p>
                                    ) : null}
                                </CardContent>
                                <CardContent className="flex flex-wrap justify-between gap-3 border-t pt-4">
                                    <p className="text-sm text-muted-foreground">
                                        {markedForDeletion
                                            ? 'Rule marked for deletion. Save to remove it.'
                                            : isExisting
                                              ? 'Existing rule. Scope cannot be changed.'
                                              : 'Draft rule. Remember to save to enforce.'}
                                    </p>
                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() =>
                                                handleRemove(draft.clientId)
                                            }
                                        >
                                            <Trash2
                                                className="mr-2 h-4 w-4"
                                                aria-hidden
                                            />
                                            {markedForDeletion
                                                ? 'Undo delete'
                                                : isExisting
                                                  ? 'Delete'
                                                  : 'Remove'}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}

            {pendingDeletions.length ? (
                <div className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">
                    {pendingDeletions.length} rule
                    {pendingDeletions.length === 1 ? '' : 's'} marked for
                    deletion.
                </div>
            ) : null}

            <div className="flex flex-wrap justify-end gap-3 border-t pt-4">
                {hasValidationIssues ? (
                    <p className="flex items-center gap-2 text-sm text-destructive">
                        <AlertTriangle className="h-4 w-4" aria-hidden />{' '}
                        Resolve validation issues before saving.
                    </p>
                ) : null}
                <Button
                    type="button"
                    onClick={handleSubmit}
                    disabled={!hasChanges || hasValidationIssues || isSaving}
                >
                    {isSaving ? 'Saving…' : 'Sync rate limits'}
                </Button>
            </div>
        </div>
    );
}

function mapRuleToDraft(rule: RateLimitRuleItem): RuleDraft {
    return {
        clientId: String(rule.id),
        id: rule.id,
        scope: rule.scope,
        windowSeconds: rule.windowSeconds,
        maxRequests: rule.maxRequests,
        active: rule.active ?? true,
        companyId: rule.companyId,
    };
}

function buildPayload(drafts: RuleDraft[]): SyncRateLimitPayload {
    const upserts = drafts
        .filter((draft) => !draft.markForDeletion && draft.scope.trim())
        .map((draft) => {
            const { clientId, ...rest } = draft;
            void clientId;
            return rest;
        });

    const removals = drafts
        .filter((draft) => draft.markForDeletion && draft.id)
        .map((draft) => draft.id as number);

    return {
        upserts,
        removals,
    };
}

function computeDirtyState(
    drafts: RuleDraft[],
    baseline: RateLimitRuleItem[],
): boolean {
    const baselineMap = new Map(baseline.map((rule) => [rule.id, rule]));

    if (drafts.length !== baseline.length) {
        const visibleCount = drafts.filter(
            (draft) => !draft.markForDeletion,
        ).length;
        if (visibleCount !== baseline.length) {
            return true;
        }
    }

    for (const draft of drafts) {
        if (!draft.id) {
            if (!draft.markForDeletion && draft.scope.trim()) {
                return true;
            }
            continue;
        }

        if (draft.markForDeletion) {
            return true;
        }

        const original = baselineMap.get(draft.id);
        if (!original) {
            return true;
        }

        if (
            original.scope !== draft.scope ||
            original.windowSeconds !== draft.windowSeconds ||
            original.maxRequests !== draft.maxRequests ||
            Boolean(original.active) !== Boolean(draft.active)
        ) {
            return true;
        }
    }

    return false;
}

function validateDraft(draft: RuleDraft): string | null {
    if (draft.markForDeletion) {
        return null;
    }

    if (!draft.scope.trim()) {
        return 'Scope is required.';
    }

    if (
        !Number.isFinite(draft.windowSeconds) ||
        (draft.windowSeconds ?? 0) <= 0
    ) {
        return 'Window must be a positive number.';
    }

    if (!Number.isFinite(draft.maxRequests) || (draft.maxRequests ?? 0) <= 0) {
        return 'Max requests must be a positive number.';
    }

    return null;
}

function buildPreviewLabel(draft: RateLimitRuleInput) {
    if (!draft.maxRequests || !draft.windowSeconds) {
        return 'Incomplete rule';
    }
    return `${draft.maxRequests} req / ${draft.windowSeconds}s`;
}

function cryptoRandomId() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return `draft-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function LoadingSkeleton() {
    return (
        <div className="space-y-4">
            {Array.from({ length: 2 }).map((_, index) => (
                <div
                    key={index}
                    className="space-y-3 rounded-xl border border-dashed border-muted p-4"
                >
                    <Skeleton className="h-6 w-1/2" />
                    <Skeleton className="h-4 w-1/3" />
                    <div className="grid gap-3 md:grid-cols-3">
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-10 w-full" />
                    </div>
                </div>
            ))}
        </div>
    );
}
