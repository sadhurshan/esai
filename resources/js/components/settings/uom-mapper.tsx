import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';

export interface UomOption {
    value: string;
    label: string;
    dimension?: string;
    siBase?: boolean;
}

export interface UomMappingRow {
    id: string;
    from: string;
    to: string;
}

interface UomMapperProps {
    value: UomMappingRow[];
    onChange: (rows: UomMappingRow[]) => void;
    baseUom: string;
    onBaseChange: (uom: string) => void;
    options: UomOption[];
    disabled?: boolean;
}

function createRow(): UomMappingRow {
    const id =
        typeof crypto !== 'undefined' && crypto.randomUUID
            ? crypto.randomUUID()
            : `uom-${Date.now()}-${Math.random()}`;
    return { id, from: '', to: '' };
}

export function UomMapper({
    value,
    onChange,
    baseUom,
    onBaseChange,
    options,
    disabled,
}: UomMapperProps) {
    const rows = value ?? [];

    const optionLookup = useMemo(() => {
        return options.reduce<Record<string, string>>((acc, option) => {
            acc[option.value] = option.label;
            return acc;
        }, {});
    }, [options]);

    const handleRowChange = (
        rowId: string,
        key: 'from' | 'to',
        nextValue: string,
    ) => {
        onChange(
            rows.map((row) =>
                row.id === rowId ? { ...row, [key]: nextValue } : row,
            ),
        );
    };

    const handleRemove = (rowId: string) => {
        onChange(rows.filter((row) => row.id !== rowId));
    };

    const handleAdd = () => {
        onChange([...rows, createRow()]);
    };

    return (
        <div className="space-y-4 rounded-lg border p-4">
            <div className="space-y-1">
                <p className="text-sm font-medium text-foreground">
                    Units of measure
                </p>
                <p className="text-sm text-muted-foreground">
                    Select the base unit for conversions. Map supplier-provided
                    units to the base unit for reporting.
                </p>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                    <Label>Base UoM</Label>
                    <Select
                        value={baseUom}
                        onValueChange={onBaseChange}
                        disabled={disabled}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select base" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                {options.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <p className="text-sm font-medium text-foreground">
                        Mappings
                    </p>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={handleAdd}
                        type="button"
                        disabled={disabled}
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        Add mapping
                    </Button>
                </div>
                {rows.length === 0 ? (
                    <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                        No conversions configured yet.
                    </div>
                ) : (
                    <div className="space-y-3">
                        {rows.map((row) => (
                            <div
                                key={row.id}
                                className="flex flex-wrap items-center gap-3 rounded-md border p-3"
                            >
                                <div className="flex-1 space-y-2">
                                    <Label className="text-xs text-muted-foreground uppercase">
                                        Supplier unit
                                    </Label>
                                    <Input
                                        value={row.from}
                                        onChange={(event) =>
                                            handleRowChange(
                                                row.id,
                                                'from',
                                                event.target.value.toUpperCase(),
                                            )
                                        }
                                        placeholder="LB"
                                        disabled={disabled}
                                    />
                                    {row.from && optionLookup[row.from] ? (
                                        <p className="text-xs text-muted-foreground">
                                            {optionLookup[row.from]}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="flex-1 space-y-2">
                                    <Label className="text-xs text-muted-foreground uppercase">
                                        Convert to
                                    </Label>
                                    <Select
                                        value={row.to}
                                        onValueChange={(value) =>
                                            handleRowChange(row.id, 'to', value)
                                        }
                                        disabled={disabled}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {options.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => handleRemove(row.id)}
                                    disabled={disabled}
                                    aria-label="Remove mapping"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
