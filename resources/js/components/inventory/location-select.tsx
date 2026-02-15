import { MapPin } from 'lucide-react';

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { InventoryLocationOption } from '@/types/inventory';

interface LocationSelectProps {
    value?: string | null;
    onChange?: (value: string | null) => void;
    options: InventoryLocationOption[];
    placeholder?: string;
    disabled?: boolean;
    loading?: boolean;
    allowUnassigned?: boolean;
    className?: string;
    id?: string;
}

export function LocationSelect({
    value,
    onChange,
    options,
    placeholder = 'Select location',
    disabled,
    loading,
    allowUnassigned = false,
    className,
    id,
}: LocationSelectProps) {
    if (loading) {
        return <Skeleton className={cn('h-9 w-full rounded-md', className)} />;
    }

    return (
        <Select
            value={value ?? undefined}
            onValueChange={(next) =>
                onChange?.(next === '__none__' ? null : next)
            }
            disabled={disabled}
        >
            <SelectTrigger
                id={id}
                className={cn('h-9 justify-start gap-2', className)}
            >
                <MapPin className="h-4 w-4 text-muted-foreground" />
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {allowUnassigned ? (
                    <SelectItem value="__none__">Unassigned</SelectItem>
                ) : null}
                {options.length === 0 ? (
                    <div className="px-3 py-2 text-sm text-muted-foreground">
                        No locations available.
                    </div>
                ) : (
                    options.map((option) => (
                        <SelectItem
                            key={option.id}
                            value={option.id}
                            className="flex flex-col gap-0.5"
                        >
                            <span className="text-sm font-medium">
                                {option.name}
                            </span>
                            {option.siteName ? (
                                <span className="text-xs text-muted-foreground">
                                    {option.siteName}
                                </span>
                            ) : null}
                        </SelectItem>
                    ))
                )}
            </SelectContent>
        </Select>
    );
}
