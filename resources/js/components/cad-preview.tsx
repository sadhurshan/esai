import { cn } from '@/lib/utils';
import { Box } from 'lucide-react';

interface CadPreviewProps {
    fileName?: string;
    className?: string;
    message?: string;
}

export function CADPreview({
    fileName,
    className,
    message = 'CAD preview coming soon. Upload a file to enable the viewer.',
}: CadPreviewProps) {
    return (
        <div
            className={cn(
                'flex min-h-[180px] flex-col items-center justify-center rounded-xl border border-dashed border-muted-foreground/40 bg-muted/20 p-6 text-center',
                className,
            )}
        >
            <Box className="size-10 text-muted-foreground" aria-hidden />
            <p className="mt-3 text-sm font-medium text-foreground">
                {fileName ?? 'No CAD file selected'}
            </p>
            <p className="mt-1 max-w-sm text-xs text-muted-foreground">{message}</p>
        </div>
    );
}
