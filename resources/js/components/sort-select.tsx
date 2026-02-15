import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface SortSelectOption {
    label: string;
    value: string;
}

interface SortSelectProps {
    id: string;
    label: string;
    value: string;
    options: SortSelectOption[];
    onChange: (value: string) => void;
    disabled?: boolean;
}

export function SortSelect({
    id,
    label,
    value,
    options,
    onChange,
    disabled = false,
}: SortSelectProps) {
    return (
        <div className="flex flex-col gap-1 text-sm">
            <Label
                htmlFor={id}
                className="text-xs tracking-wide text-muted-foreground uppercase"
            >
                {label}
            </Label>
            <Select value={value} onValueChange={onChange} disabled={disabled}>
                <SelectTrigger id={id} className="h-9 text-sm">
                    <SelectValue placeholder="Select" />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
