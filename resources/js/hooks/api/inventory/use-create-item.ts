import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { InventoryItemDetail } from '@/types/inventory';
import { HttpError, InventoryModuleApi } from '@/sdk';

import { mapInventoryItemDetail } from './mappers';

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null;

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
            if (error instanceof HttpError) {
                return;
            }

            publishToast({
                variant: 'destructive',
                title: 'Unable to create item',
                description: error.message ?? 'Please try again later.',
            });
        },
    });
}
