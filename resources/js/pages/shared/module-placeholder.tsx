import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { EmptyState } from '@/components/empty-state';
import { Skeleton } from '@/components/ui/skeleton';
import { Branding } from '@/config/branding';
import { useModuleBootstrap } from '@/hooks/api/use-module-bootstrap';
import { FileQuestion } from 'lucide-react';
import { Helmet } from 'react-helmet-async';
import { type ReactNode } from 'react';

interface ModulePlaceholderProps {
    moduleKey: string;
    title: string;
    description: string;
    hero?: ReactNode;
    ctaLabel?: string;
    onCtaClick?: () => void;
}

export function ModulePlaceholder({ moduleKey, title, description, hero, ctaLabel, onCtaClick }: ModulePlaceholderProps) {
    const { data, isFetching, isLoading, isError, error } = useModuleBootstrap(moduleKey);
    const loading = isLoading || isFetching;

    return (
        <section className="space-y-6">
            <Helmet>
                <title>
                    {title} • {Branding.name}
                </title>
            </Helmet>
            <header className="space-y-1">
                <h1 className="text-2xl font-semibold tracking-tight text-foreground">{title}</h1>
                <p className="text-sm text-muted-foreground">{description}</p>
            </header>

            {loading ? (
                <div className="grid gap-4 md:grid-cols-2">
                    <Skeleton className="h-32 w-full" />
                    <Skeleton className="h-32 w-full" />
                    <Skeleton className="h-32 w-full" />
                    <Skeleton className="h-32 w-full" />
                </div>
            ) : null}

            {isError ? (
                <Alert variant="destructive">
                    <AlertTitle>Unable to load module data</AlertTitle>
                    <AlertDescription>
                        {(error as Error)?.message ?? 'We hit an unexpected issue while contacting the API. Please refresh and try again.'}
                    </AlertDescription>
                </Alert>
            ) : null}

            {!loading && !isError ? (
                <EmptyState
                    title="Implementation pending"
                    description={
                        data
                            ? 'This module will soon render real data from the Elements Supply API. The backend endpoint responded successfully, confirming the SDK wiring.'
                            : description
                    }
                    icon={hero ?? <FileQuestion className="h-12 w-12" />}
                    ctaLabel={ctaLabel}
                    ctaProps={ctaLabel && onCtaClick ? { onClick: onCtaClick } : undefined}
                    className="bg-background"
                />
            ) : null}
        </section>
    );
}
