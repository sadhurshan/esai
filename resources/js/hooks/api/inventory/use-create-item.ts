import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { InventoryItemDetail } from '@/types/inventory';
import { HttpError, InventoryModuleApi } from '@/sdk';

import { mapInventoryItemDetail } from './mappers';

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null;

type ApiErrorBody = {
    message?: string | null;
    errors?: Record<string, unknown> | null;
};

export interface CreateItemInput {
    sku: string;
    name: string;
    uom: string;
    category?: string | null;
    minStock?: number | null;
    reorderQty?: number | null;
    leadTimeDays?: number | null;
    active?: boolean;
    description?: string | null;
    attributes?: Record<string, string | number | null>;
    defaultLocationId?: string | null;
}

export function useCreateItem(): UseMutationResult<InventoryItemDetail, HttpError | Error, CreateItemInput> {
    const queryClient = useQueryClient();
    const inventoryApi = useSdkClient(InventoryModuleApi);

    return useMutation<InventoryItemDetail, HttpError | Error, CreateItemInput>({
        mutationFn: async (input) => {
            const trimmedSku = input.sku.trim();
            const trimmedName = input.name.trim();
            const trimmedUom = input.uom.trim();

            if (!trimmedSku || !trimmedName || !trimmedUom) {
                throw new Error('SKU, name, and default UoM are required.');
            }

            const payload = {
                sku: trimmedSku,
                name: trimmedName,
                uom: trimmedUom,
                category: input.category?.trim() || undefined,
                minStock: input.minStock,
                reorderQty: input.reorderQty,
                leadTimeDays: input.leadTimeDays,
                active: input.active ?? true,
                description: input.description,
                attributes: input.attributes,
                defaultLocationId: input.defaultLocationId,
            };

            const response = (await inventoryApi.createItem(payload)) as Record<string, unknown>;
            const itemPayload = isRecord(response.item) ? response.item : response;
            return mapInventoryItemDetail(itemPayload);
        },
        onSuccess: (item) => {
            publishToast({
                variant: 'success',
                title: 'Item created',
                description: `${item.sku} · ${item.name} is now available in your catalog.`,
            });

            void queryClient.invalidateQueries({ queryKey: queryKeys.inventory.items({}) });
            if (item.id) {
                void queryClient.invalidateQueries({ queryKey: queryKeys.inventory.item(item.id) });
            }
        },
        onError: (error) => {
            publishToast({
                variant: 'destructive',
                title: 'Unable to create item',
                description: resolveCreateItemErrorMessage(error),
            });
        },
    });
}

const resolveCreateItemErrorMessage = (error: unknown): string => {
    const fallback = 'Please review the form and try again.';

    if (error instanceof HttpError) {
        const body = error.body as ApiErrorBody | undefined;
        const envelopeMessage = typeof body?.message === 'string' ? body.message.trim() : undefined;
        const errorBag = isRecord(body?.errors) ? (body?.errors as Record<string, unknown>) : undefined;
        const bagMessage = pickFirstErrorMessage(errorBag);
        const rawBodyText = typeof error.body === 'string' ? error.body.trim() : undefined;
        const normalizedEnvelope = envelopeMessage && envelopeMessage.length > 0 ? envelopeMessage : undefined;
        const normalizedBody = rawBodyText && rawBodyText.length > 0 ? rawBodyText : undefined;

        return normalizedEnvelope ?? bagMessage ?? normalizedBody ?? error.message ?? fallback;
    }

    if (error instanceof Error) {
        return error.message || fallback;
    }

    return fallback;
};

const pickFirstErrorMessage = (errors?: Record<string, unknown>): string | undefined => {
    if (!errors) {
        return undefined;
    }

    for (const value of Object.values(errors)) {
        if (typeof value === 'string') {
            const trimmed = value.trim();
            if (trimmed.length > 0) {
                return trimmed;
            }
            continue;
        }

        if (Array.isArray(value)) {
            const first = value.find((entry): entry is string => typeof entry === 'string' && entry.trim().length > 0);
            if (first) {
                return first.trim();
            }
            continue;
        }

        if (isRecord(value)) {
            const nested = pickFirstErrorMessage(value);
            if (nested) {
                return nested;
            }
        }
    }

    return undefined;
};
