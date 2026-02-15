import type { ReactNode } from 'react';

interface HeadingProps {
    title: string;
    description?: string;
    action?: ReactNode;
}

export default function Heading({ title, description, action }: HeadingProps) {
    return (
        <div className="mb-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="space-y-0.5">
                <h2 className="text-xl font-semibold tracking-tight">
                    {title}
                </h2>
                {description ? (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            {action ? (
                <div className="flex flex-wrap gap-2">{action}</div>
            ) : null}
        </div>
    );
}
