import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { UploadCloud } from 'lucide-react';
import { useRef, type ChangeEventHandler } from 'react';

interface FileDropzoneProps {
    label?: string;
    description?: string;
    accept?: string[];
    acceptLabel?: string;
    multiple?: boolean;
    disabled?: boolean;
    onFilesSelected?: (files: File[]) => void;
    className?: string;
}

export function FileDropzone({
    label = 'Drag and drop files',
    description = 'or click to browse',
    accept,
    acceptLabel,
    multiple = false,
    disabled = false,
    onFilesSelected,
    className,
}: FileDropzoneProps) {
    const inputRef = useRef<HTMLInputElement | null>(null);

    const handleSelect = () => {
        if (!disabled) {
            inputRef.current?.click();
        }
    };

    const handleChange: ChangeEventHandler<HTMLInputElement> = (event) => {
        if (!event.target.files || event.target.files.length === 0) {
            return;
        }

        const files = Array.from(event.target.files);
        onFilesSelected?.(files);
    };

    const acceptedDisplay = acceptLabel ??
        (accept && accept.length > 0 && accept.length <= 6 ? accept.join(', ') : null);

    return (
        <div
            role="group"
            aria-disabled={disabled}
            className={cn(
                'relative flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-muted-foreground/40 bg-muted/40 p-6 text-center transition hover:border-muted-foreground/70 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/40',
                disabled && 'cursor-not-allowed opacity-60',
                className,
            )}
        >
            <input
                ref={inputRef}
                type="file"
                className="hidden"
                onChange={handleChange}
                accept={accept?.join(',')}
                multiple={multiple}
                disabled={disabled}
            />
            <UploadCloud className="size-10 text-muted-foreground" aria-hidden />
            <div className="space-y-1">
                <p className="text-sm font-medium text-foreground">{label}</p>
                <p className="text-xs text-muted-foreground">{description}</p>
                {acceptedDisplay ? (
                    <p className="text-xs text-muted-foreground">Accepted: {acceptedDisplay}</p>
                ) : null}
            </div>
            <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={handleSelect}
                disabled={disabled}
            >
                Browse Files
            </Button>
        </div>
    );
}
