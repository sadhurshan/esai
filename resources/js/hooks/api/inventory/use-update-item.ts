import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { HttpError, InventoryModuleApi } from '@/sdk';
import type { InventoryItemDetail } from '@/types/inventory';

import { mapInventoryItemDetail } from './mappers';
import type { CreateItemInput } from './use-create-item';

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

export interface UpdateItemInput extends Partial<CreateItemInput> {
    id: string | number;
}

export function useUpdateItem(): UseMutationResult<
    InventoryItemDetail,
    HttpError | Error,
    UpdateItemInput
> {
    const queryClient = useQueryClient();
    const inventoryApi = useSdkClient(InventoryModuleApi);

    return useMutation<InventoryItemDetail, HttpError | Error, UpdateItemInput>(
        {
            mutationFn: async (input) => {
                if (!input.id) {
                    throw new Error('Item id is required.');
                }

                const payload = {
                    sku: input.sku?.trim(),
                    name: input.name?.trim(),
                    uom: input.uom?.trim(),
                    category: input.category?.trim(),
                    minStock: input.minStock,
                    reorderQty: input.reorderQty,
                    leadTimeDays: input.leadTimeDays,
                    active: input.active,
                    description: input.description,
                    attributes: input.attributes,
                    defaultLocationId: input.defaultLocationId,
                };

                const response = (await inventoryApi.updateItem(
                    input.id,
                    payload,
                )) as Record<string, unknown>;
                const itemPayload = isRecord(response.item)
                    ? response.item
                    : response;
                return mapInventoryItemDetail(itemPayload);
            },
            onSuccess: (item) => {
                publishToast({
                    variant: 'success',
                    title: 'Item updated',
                    description: `${item.sku} changes saved.`,
                });

                void queryClient.invalidateQueries({
                    queryKey: queryKeys.inventory.items({}),
                });
                if (item.id) {
                    void queryClient.invalidateQueries({
                        queryKey: queryKeys.inventory.item(item.id),
                    });
                }
            },
            onError: (error) => {
                if (error instanceof HttpError) {
                    return;
                }

                publishToast({
                    variant: 'destructive',
                    title: 'Unable to update item',
                    description:
                        error.message ??
                        'Please review the form and try again.',
                });
            },
        },
    );
}
