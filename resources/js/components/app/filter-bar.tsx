import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { type ChangeEvent } from 'react';

const EMPTY_OPTION_VALUE = '__filter_empty__';

export interface FilterOption {
    label: string;
    value: string;
}

export interface FilterConfig {
    id: string;
    label: string;
    options: FilterOption[];
    value?: string;
}

interface FilterBarProps {
    filters: FilterConfig[];
    searchPlaceholder?: string;
    searchValue?: string;
    isLoading?: boolean;
    onFilterChange?: (id: string, value: string) => void;
    onSearchChange?: (value: string) => void;
    onReset?: () => void;
    className?: string;
}

export function FilterBar({
    filters,
    searchPlaceholder = 'Search',
    searchValue = '',
    isLoading = false,
    onFilterChange,
    onSearchChange,
    onReset,
    className,
}: FilterBarProps) {
    const handleSearchChange = (event: ChangeEvent<HTMLInputElement>) => {
        onSearchChange?.(event.target.value);
    };

    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-xl border border-sidebar-border/60 bg-background/50 p-4 shadow-xs md:flex-row md:items-end',
                className,
            )}
        >
            <div className="flex flex-1 flex-wrap items-center gap-3">
                <div className="min-w-[200px] flex-1">
                    {isLoading ? (
                        <Skeleton className="h-9 w-full" />
                    ) : (
                        <Input
                            value={searchValue}
                            onChange={handleSearchChange}
                            placeholder={searchPlaceholder}
                            className="h-9"
                            aria-label={searchPlaceholder}
                        />
                    )}
                </div>

                {filters.map((filter) => (
                    <div key={filter.id} className="min-w-[180px] flex-1 md:flex-none">
                        {isLoading ? (
                            <Skeleton className="h-9 w-full" />
                        ) : (
                            <Select
                                value={
                                    !filter.value
                                        ? EMPTY_OPTION_VALUE
                                        : filter.value
                                }
                                onValueChange={(value) =>
                                    onFilterChange?.(
                                        filter.id,
                                        value === EMPTY_OPTION_VALUE ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger className="h-9">
                                    <SelectValue placeholder={filter.label} />
                                </SelectTrigger>
                                <SelectContent>
                                    {filter.options.map((option) => {
                                        const optionValue =
                                            option.value === ''
                                                ? EMPTY_OPTION_VALUE
                                                : option.value;

                                        return (
                                            <SelectItem key={optionValue} value={optionValue}>
                                                {option.label}
                                            </SelectItem>
                                        );
                                    })}
                                </SelectContent>
                            </Select>
                        )}
                    </div>
                ))}
            </div>

            <div className="flex flex-shrink-0 items-center gap-2">
                <Button variant="secondary" size="sm" onClick={() => onReset?.()}>
                    Reset
                </Button>
            </div>
        </div>
    );
}
