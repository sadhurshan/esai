import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { type ComponentProps, type ReactNode } from 'react';

interface EmptyStateProps {
    title: string;
    description?: string;
    icon?: ReactNode;
    className?: string;
    ctaLabel?: string;
    ctaProps?: ComponentProps<typeof Button>;
}

export function EmptyState({
    title,
    description,
    icon,
    className,
    ctaLabel,
    ctaProps,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-muted-foreground/40 bg-muted/10 p-8 text-center',
                className,
            )}
        >
            {icon && <div className="text-muted-foreground">{icon}</div>}
            <h3 className="text-base font-semibold text-foreground">{title}</h3>
            {description && (
                <p className="max-w-md text-sm text-muted-foreground">{description}</p>
            )}
            {ctaLabel && (
                <Button size="sm" {...ctaProps}>
                    {ctaLabel}
                </Button>
            )}
        </div>
    );
}
