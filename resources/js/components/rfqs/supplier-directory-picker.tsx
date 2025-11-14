import { useEffect, useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useSuppliers } from '@/hooks/api/useSuppliers';
import type { Supplier } from '@/types/sourcing';

const SEARCH_DEBOUNCE_MS = 250;

function useDebouncedValue<T>(value: T, delay = SEARCH_DEBOUNCE_MS) {
    const [debounced, setDebounced] = useState(value);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            setDebounced(value);
        }, delay);

        return () => {
            window.clearTimeout(handle);
        };
    }, [value, delay]);

    return debounced;
}

export interface SupplierDirectoryPickerProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSelect: (supplier: Supplier) => void;
}

export function SupplierDirectoryPicker({ open, onOpenChange, onSelect }: SupplierDirectoryPickerProps) {
    const [search, setSearch] = useState('');
    const debouncedSearch = useDebouncedValue(search);

    const queryParams = useMemo(() => {
        const params: Record<string, unknown> = {
            per_page: 10,
        };

        if (debouncedSearch.trim().length > 0) {
            params.q = debouncedSearch.trim();
        }

        return params;
    }, [debouncedSearch]);

    const supplierQuery = useSuppliers(queryParams);

    const suppliers = supplierQuery.data?.items ?? [];

    const handleSelect = (supplier: Supplier) => {
        onSelect(supplier);
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-xl">
                <DialogHeader>
                    <DialogTitle>Browse supplier directory</DialogTitle>
                    <DialogDescription>Search your approved supplier network and add participants instantly.</DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="supplier-search">Search suppliers</Label>
                        <Input
                            id="supplier-search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search by name, capability, material..."
                        />
                    </div>

                    {supplierQuery.isLoading ? (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner className="h-4 w-4" /> Searching directory…
                        </div>
                    ) : null}

                    {!supplierQuery.isLoading && suppliers.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No suppliers matched your search yet.</p>
                    ) : null}

                    <ul className="grid gap-2">
                        {suppliers.map((supplier) => (
                            <li key={supplier.id} className="rounded-md border p-3">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-semibold text-foreground">{supplier.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {supplier.address.city ? `${supplier.address.city}, ` : ''}
                                            {supplier.address.country ?? 'Location unavailable'}
                                        </p>
                                        {supplier.capabilities.methods && supplier.capabilities.methods.length > 0 ? (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Methods: {supplier.capabilities.methods.slice(0, 3).join(', ')}
                                                {supplier.capabilities.methods.length > 3 ? '…' : ''}
                                            </p>
                                        ) : null}
                                    </div>
                                    <Button type="button" size="sm" onClick={() => handleSelect(supplier)}>
                                        Add
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            </DialogContent>
        </Dialog>
    );
}
