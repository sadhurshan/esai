import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { HttpError, InventoryModuleApi, type MovementType } from '@/sdk';
import type { StockMovementDetail } from '@/types/inventory';

import { mapStockMovementDetail } from './mappers';

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

export interface CreateMovementLineInput {
    itemId: string | number;
    qty: number;
    uom?: string;
    fromLocationId?: string | null;
    toLocationId?: string | null;
    reason?: string | null;
}

export interface CreateMovementInput {
    type: MovementType;
    movedAt: string;
    lines: CreateMovementLineInput[];
    reference?: {
        source?: 'PO' | 'SO' | 'MANUAL';
        id?: string;
    };
    notes?: string | null;
}

export function useCreateMovement(): UseMutationResult<
    StockMovementDetail,
    HttpError | Error,
    CreateMovementInput
> {
    const queryClient = useQueryClient();
    const inventoryApi = useSdkClient(InventoryModuleApi);

    return useMutation<
        StockMovementDetail,
        HttpError | Error,
        CreateMovementInput
    >({
        mutationFn: async (input) => {
            if (!input.lines || input.lines.length === 0) {
                throw new Error('Add at least one line to post a movement.');
            }

            const normalizedLines = input.lines.map((line, index) => {
                if (!line.itemId) {
                    throw new Error(`Line ${index + 1} is missing an item.`);
                }

                if (!Number.isFinite(line.qty) || line.qty <= 0) {
                    throw new Error(
                        `Line ${index + 1} must have a positive quantity.`,
                    );
                }

                if (
                    (input.type === 'ISSUE' || input.type === 'TRANSFER') &&
                    !line.fromLocationId
                ) {
                    throw new Error(
                        `Line ${index + 1} requires a source location.`,
                    );
                }

                if (
                    (input.type === 'RECEIPT' || input.type === 'TRANSFER') &&
                    !line.toLocationId
                ) {
                    throw new Error(
                        `Line ${index + 1} requires a destination location.`,
                    );
                }

                if (
                    input.type === 'TRANSFER' &&
                    line.fromLocationId &&
                    line.toLocationId &&
                    line.fromLocationId === line.toLocationId
                ) {
                    throw new Error(
                        `Line ${index + 1} cannot transfer to the same location.`,
                    );
                }

                return {
                    itemId: line.itemId,
                    qty: line.qty,
                    uom: line.uom,
                    fromLocationId: line.fromLocationId ?? undefined,
                    toLocationId: line.toLocationId ?? undefined,
                    reason: line.reason ?? undefined,
                };
            });

            const response = (await inventoryApi.createMovement({
                type: input.type,
                movedAt: input.movedAt,
                lines: normalizedLines,
                reference: input.reference,
                notes: input.notes,
            })) as Record<string, unknown>;

            const payload = isRecord(response.movement)
                ? response.movement
                : response;
            return mapStockMovementDetail(payload);
        },
        onSuccess: (movement) => {
            publishToast({
                variant: 'success',
                title: 'Movement posted',
                description: `${movement.movementNumber} · ${movement.type} recorded.`,
            });

            void queryClient.invalidateQueries({
                queryKey: queryKeys.inventory.movementsList({}),
            });
            if (movement.id) {
                void queryClient.invalidateQueries({
                    queryKey: queryKeys.inventory.movement(movement.id),
                });
            }
            if (movement.lines.some((line) => line.itemId)) {
                void queryClient.invalidateQueries({
                    queryKey: queryKeys.inventory.items({}),
                });
                void queryClient.invalidateQueries({
                    queryKey: queryKeys.inventory.lowStock({}),
                });
            }
        },
        onError: (error) => {
            if (error instanceof HttpError) {
                return;
            }

            publishToast({
                variant: 'destructive',
                title: 'Movement failed',
                description:
                    error.message ?? 'Unable to post the stock movement.',
            });
        },
    });
}
