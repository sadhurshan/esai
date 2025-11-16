import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import type { ApiKeyListItem } from '@/types/admin';
import { formatDistanceToNow } from 'date-fns';
import { CircleAlert, KeyRound } from 'lucide-react';

interface ApiKeyCardProps {
    apiKey: ApiKeyListItem;
    onRevoke?: (apiKey: ApiKeyListItem) => void;
}

export function ApiKeyCard({ apiKey, onRevoke }: ApiKeyCardProps) {
    const scopes = apiKey.scopes ?? [];
    const lastUsedLabel = formatRelativeTimestamp(apiKey.lastUsedAt);
    const expiresLabel = formatRelativeTimestamp(apiKey.expiresAt);

    return (
        <Card data-testid={`api-key-${apiKey.id}`}>
            <CardHeader className="gap-2">
                <CardTitle className="flex items-center gap-2 text-xl">
                    <KeyRound className="h-5 w-5 text-muted-foreground" aria-hidden />
                    {apiKey.name}
                    <Badge variant={apiKey.active ? 'secondary' : 'outline'}>{apiKey.active ? 'Active' : 'Revoked'}</Badge>
                </CardTitle>
                <CardDescription>
                    Token prefix <span className="font-mono text-sm text-foreground">{apiKey.tokenPrefix}</span>
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <dl className="grid gap-4 text-sm sm:grid-cols-2">
                    <Metadata label="Company ID" value={apiKey.companyId} />
                    <Metadata label="Owner" value={apiKey.ownerUserId ? `User #${apiKey.ownerUserId}` : '—'} />
                    <Metadata label="Last used" value={lastUsedLabel ?? 'Never'} />
                    <Metadata label="Expires" value={expiresLabel ?? 'Never'} warning={Boolean(apiKey.expiresAt && !apiKey.active)} />
                </dl>
                <div className="space-y-2">
                    <p className="text-sm font-medium text-muted-foreground">Scopes</p>
                    {scopes.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No scopes assigned.</p>
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            {scopes.map((scope) => (
                                <Badge key={scope} variant="outline" className="font-mono text-xs">
                                    {scope}
                                </Badge>
                            ))}
                        </div>
                    )}
                </div>
            </CardContent>
            <CardFooter className="justify-between border-t pt-4">
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <CircleAlert className="h-3.5 w-3.5" aria-hidden />
                    Treat API keys like passwords. Revoke unused credentials immediately.
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        size="sm"
                        variant="destructive"
                        disabled={!apiKey.active}
                        onClick={() => onRevoke?.(apiKey)}
                    >
                        Revoke
                    </Button>
                </div>
            </CardFooter>
        </Card>
    );
}

interface MetadataProps {
    label: string;
    value?: string | number | null;
    warning?: boolean;
}

function Metadata({ label, value, warning }: MetadataProps) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">{label}</dt>
            <dd className={warning ? 'text-sm font-semibold text-destructive' : 'text-sm text-foreground'}>
                {value ?? '—'}
            </dd>
        </div>
    );
}

function formatRelativeTimestamp(value?: Date | string | null): string | null {
    if (!value) {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return formatDistanceToNow(date, { addSuffix: true });
}
