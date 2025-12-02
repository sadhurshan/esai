import { type ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { useAckOrder } from '@/hooks/api/orders/use-ack-order';
import { useCreateShipment } from '@/hooks/api/orders/use-create-shipment';
import { useUpdateShipmentStatus } from '@/hooks/api/orders/use-update-shipment-status';
import type { CreateShipmentPayload, SalesOrderDetail } from '@/types/orders';

vi.mock('@/contexts/api-client-context', () => ({
    useSdkClient: vi.fn(),
}));

vi.mock('@/components/ui/use-toast', () => ({
    publishToast: vi.fn(),
}));

const mockedUseSdkClient = vi.mocked(useSdkClient);
const mockedPublishToast = vi.mocked(publishToast);

const createOrder = (overrides: Partial<SalesOrderDetail> = {}): SalesOrderDetail => ({
    id: 42,
    soNumber: 'SO-42',
    poId: 7,
    buyerCompanyId: 11,
    supplierCompanyId: 22,
    status: 'accepted',
    currency: 'USD',
    lines: [],
    shipments: [],
    timeline: [],
    ...overrides,
});

const mockOrdersApi = {
    acknowledgeOrder: vi.fn(),
    createShipment: vi.fn(),
    updateShipmentStatus: vi.fn(),
};

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: {
                retry: false,
            },
        },
    });

    const wrapper = ({ children }: { children: ReactNode }) => (
        <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
    );

    return { queryClient, wrapper };
}

beforeEach(() => {
    mockedUseSdkClient.mockReturnValue(mockOrdersApi);
    mockedPublishToast.mockReset();
    mockOrdersApi.acknowledgeOrder.mockReset();
    mockOrdersApi.createShipment.mockReset();
    mockOrdersApi.updateShipmentStatus.mockReset();
});

describe('useAckOrder', () => {
    it('submits acknowledgements and raises success toast', async () => {
        const orderResponse = createOrder({ status: 'accepted' });
        mockOrdersApi.acknowledgeOrder.mockResolvedValue(orderResponse);
        const { queryClient, wrapper } = createWrapper();
        const invalidateSpy = vi.spyOn(queryClient, 'invalidateQueries');

        const { result } = renderHook(() => useAckOrder(), { wrapper });

        await act(async () => {
            await result.current.mutateAsync({ orderId: 42, decision: 'accept' });
        });

        expect(mockOrdersApi.acknowledgeOrder).toHaveBeenCalledWith(42, { decision: 'accept', reason: undefined });
        expect(mockedPublishToast).toHaveBeenCalledWith(
            expect.objectContaining({ title: 'Order accepted', variant: 'success' }),
        );
        expect(invalidateSpy).toHaveBeenCalled();
    });
});

describe('useCreateShipment', () => {
    it('creates shipments and notifies downstream caches', async () => {
        const orderResponse = createOrder({ shipments: [{ id: 99, shipmentNo: 'SHP-1', lines: [], status: 'pending', soId: 42 }] });
        mockOrdersApi.createShipment.mockResolvedValue(orderResponse);
        const { queryClient, wrapper } = createWrapper();
        const invalidateSpy = vi.spyOn(queryClient, 'invalidateQueries');
        const payload: CreateShipmentPayload = {
            carrier: 'DHL',
            trackingNumber: '1Z123',
            shippedAt: '2024-01-01T00:00:00Z',
            lines: [{ soLineId: 10, qtyShipped: 5 }],
        };

        const { result } = renderHook(() => useCreateShipment(), { wrapper });

        await act(async () => {
            await result.current.mutateAsync({ orderId: 42, ...payload });
        });

        expect(mockOrdersApi.createShipment).toHaveBeenCalledWith(42, payload);
        expect(mockedPublishToast).toHaveBeenCalledWith(
            expect.objectContaining({ title: 'Shipment created', variant: 'success' }),
        );
        expect(invalidateSpy).toHaveBeenCalled();
    });
});

describe('useUpdateShipmentStatus', () => {
    it('prevents marking delivered without timestamp', async () => {
        const { wrapper } = createWrapper();
        const { result } = renderHook(() => useUpdateShipmentStatus(), { wrapper });

        await expect(
            result.current.mutateAsync({
                shipmentId: 55,
                orderId: 42,
                status: 'delivered',
            }),
        ).rejects.toThrow('deliveredAt is required');
        expect(mockOrdersApi.updateShipmentStatus).not.toHaveBeenCalled();
    });

    it('updates status and dispatches appropriate toast', async () => {
        const orderResponse = createOrder({ status: 'partially_fulfilled' });
        mockOrdersApi.updateShipmentStatus.mockResolvedValue(orderResponse);
        const { queryClient, wrapper } = createWrapper();
        const invalidateSpy = vi.spyOn(queryClient, 'invalidateQueries');
        const { result } = renderHook(() => useUpdateShipmentStatus(), { wrapper });

        await act(async () => {
            await result.current.mutateAsync({
                shipmentId: 55,
                orderId: 42,
                status: 'in_transit',
            });
        });

        expect(mockOrdersApi.updateShipmentStatus).toHaveBeenCalledWith(55, { status: 'in_transit', deliveredAt: undefined });
        expect(mockedPublishToast).toHaveBeenCalledWith(
            expect.objectContaining({ title: 'Shipment in transit', variant: 'success' }),
        );
        expect(invalidateSpy).toHaveBeenCalled();
    });
});
