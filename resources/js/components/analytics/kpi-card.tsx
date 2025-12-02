import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { ComponentType } from 'react';

interface KpiCardProps {
    label: string;
    value: string;
    description?: string;
    icon?: ComponentType<{ className?: string }>;
    loading?: boolean;
    footnote?: string;
}

export function KpiCard({ label, value, description, icon: Icon, loading, footnote }: KpiCardProps) {
    return (
        <Card className="relative overflow-hidden">
            <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-2">
                <div>
                    <CardTitle className="text-sm font-medium text-muted-foreground">{label}</CardTitle>
                    {description ? (
                        <CardDescription className="text-xs text-muted-foreground/80">{description}</CardDescription>
                    ) : null}
                </div>
                {Icon ? (
                    <span className="rounded-md bg-muted p-2 text-muted-foreground">
                        <Icon className="h-4 w-4" />
                    </span>
                ) : null}
            </CardHeader>
            <CardContent className="space-y-2">
                {loading ? (
                    <Skeleton className="h-8 w-24" />
                ) : (
                    <p className="text-3xl font-semibold tracking-tight text-foreground">{value}</p>
                )}
                {footnote ? <p className="text-xs text-muted-foreground">{footnote}</p> : null}
            </CardContent>
        </Card>
    );
}
