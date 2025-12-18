import { renderHook } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { PropsWithChildren } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { useCreatePo } from '../use-create-po';
import { useRecalcPo } from '../use-recalc-po';
import { useSendPo } from '../use-send-po';
import { useAckPo } from '../use-ack-po';
import { useCancelPo } from '../use-cancel-po';
import { useExportPo } from '../use-export-po';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { PurchaseOrder } from '@/sdk';
import type { PurchaseOrderResponse } from '@/hooks/api/usePurchaseOrder';

const apiPostMock = vi.fn();
vi.mock('@/lib/api', () => ({
    api: {
        post: (...args: unknown[]) => apiPostMock(...args),
        get: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

vi.mock('@/contexts/api-client-context', () => ({
    useSdkClient: vi.fn(),
}));

const publishToastMock = vi.fn();
type PublishToastArgs = [payload: unknown];
vi.mock('@/components/ui/use-toast', () => ({
    publishToast: (...args: PublishToastArgs) => publishToastMock(...args),
}));

const useSdkClientMock = vi.mocked(useSdkClient);

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: {
                retry: false,
            },
            mutations: {
                retry: false,
            },
        },
    });

    const invalidateSpy = vi.spyOn(queryClient, 'invalidateQueries');

    function Wrapper({ children }: PropsWithChildren) {
        return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
    }

    return { Wrapper, invalidateSpy };
}

describe('purchase order mutation hooks', () => {
    afterEach(() => {
        vi.clearAllMocks();
        useSdkClientMock.mockReset();
        publishToastMock.mockReset();
        apiPostMock.mockReset();
    });

    it('creates purchase orders from awards and invalidates caches', async () => {
        const sdkPurchaseOrder: PurchaseOrder = {
            id: 901,
            companyId: 12,
            poNumber: 'PO-901',
            status: 'draft',
            currency: 'USD',
            incoterm: 'FOB',
            taxPercent: 8,
            revisionNo: 1,
            rfqId: 44,
            quoteId: 55,
            supplier: { id: 200, name: 'Northwind Manufacturing' },
            rfq: { id: 44, number: 'RFQ-044', title: 'Machined Parts' },
            createdAt: new Date('2025-01-01T00:00:00Z'),
            updatedAt: new Date('2025-01-01T00:00:00Z'),
            subtotalMinor: 100_000,
            taxAmountMinor: 8_000,
            totalMinor: 108_000,
            lines: [],
            changeOrders: [],
        } as PurchaseOrder;

        const createPurchaseOrdersFromAwards = vi.fn().mockResolvedValue({
            data: {
                purchaseOrders: [sdkPurchaseOrder],
            },
        });

        useSdkClientMock.mockReturnValue({ createPurchaseOrdersFromAwards });

        const { Wrapper, invalidateSpy } = createWrapper();
        const { result } = renderHook(() => useCreatePo(), { wrapper: Wrapper });

        const response = await result.current.mutateAsync({ awardIds: [1, 2], rfqId: 44 });

        expect(createPurchaseOrdersFromAwards).toHaveBeenCalledWith({
            createPurchaseOrdersFromAwardsRequest: {
                awardIds: [1, 2],
            },
        });

        expect(response).toHaveLength(1);
        expect(response[0]?.id).toBe(sdkPurchaseOrder.id);
        expect(publishToastMock).toHaveBeenCalledWith(
            expect.objectContaining({ title: 'Purchase order draft ready', variant: 'success' }),
        );

        const expectedKeys = [
            queryKeys.purchaseOrders.root(),
            queryKeys.awards.candidates(44),
            queryKeys.awards.summary(44),
            queryKeys.rfqs.detail(44),
            queryKeys.rfqs.lines(44),
            queryKeys.rfqs.quotes(44),
            queryKeys.quotes.rfq(44),
            queryKeys.quotes.root(),
        ];

        for (const key of expectedKeys) {
            expect(invalidateSpy).toHaveBeenCalledWith(expect.objectContaining({ queryKey: key }));
        }
    });

    it('recalculates purchase order totals and refreshes relevant caches', async () => {
        const recalcPurchaseOrderTotals = vi.fn().mockResolvedValue({ status: 'success' });
        useSdkClientMock.mockReturnValue({ recalcPurchaseOrderTotals });

        const { Wrapper, invalidateSpy } = createWrapper();
        const { result } = renderHook(() => useRecalcPo(), { wrapper: Wrapper });

        await result.current.mutateAsync({ poId: 555 });

        expect(recalcPurchaseOrderTotals).toHaveBeenCalledWith({ purchaseOrderId: '555' });
        expect(publishToastMock).toHaveBeenCalledWith(
            expect.objectContaining({ title: 'Totals recalculated', variant: 'success' }),
        );
        expect(invalidateSpy).toHaveBeenCalledWith(
            expect.objectContaining({ queryKey: queryKeys.purchaseOrders.detail(555) }),
        );
        expect(invalidateSpy).toHaveBeenCalledWith(expect.objectContaining({ queryKey: queryKeys.purchaseOrders.root() }));
    });

    it('sends purchase orders and invalidates caches', async () => {
        apiPostMock.mockResolvedValue({ delivery: { id: 1 } });
        const { Wrapper, invalidateSpy } = createWrapper();
        const { result } = renderHook(() => useSendPo(), { wrapper: Wrapper });

        await result.current.mutateAsync({ poId: 777, message: 'Please confirm receipt', overrideEmail: 'ops@example.com' });

        expect(apiPostMock).toHaveBeenCalledWith(
            '/purchase-orders/777/send',
            expect.objectContaining({ message: 'Please confirm receipt', override_email: 'ops@example.com' }),
        );
        expect(publishToastMock).toHaveBeenCalledWith(
            expect.objectContaining({ title: 'Purchase order sent', variant: 'success' }),
        );
        expect(invalidateSpy).toHaveBeenCalledWith(
            expect.objectContaining({ queryKey: queryKeys.purchaseOrders.detail(777) }),
        );
        expect(invalidateSpy).toHaveBeenCalledWith(expect.objectContaining({ queryKey: queryKeys.purchaseOrders.root() }));
        expect(invalidateSpy).toHaveBeenCalledWith(expect.objectContaining({ queryKey: queryKeys.purchaseOrders.events(777) }));
    });

    it('records supplier acknowledgement decisions and invalidates caches', async () => {
        const purchaseOrderResponse: PurchaseOrderResponse = {
            id: 42,
            company_id: 5,
            po_number: 'PO-42',
            status: 'sent',
            currency: 'USD',
            incoterm: 'FOB',
            tax_percent: 0,
            revision_no: 1,
            rfq_id: null,
            quote_id: null,
            created_at: '2025-01-01T00:00:00Z',
            updated_at: '2025-01-01T00:00:00Z',
            lines: [],
            change_orders: [],
        };

        apiPostMock.mockResolvedValue({ purchase_order: purchaseOrderResponse });

        const { Wrapper, invalidateSpy } = createWrapper();
        const { result } = renderHook(() => useAckPo(), { wrapper: Wrapper });

        await result.current.mutateAsync({ poId: 42, decision: 'acknowledged' });

        expect(apiPostMock).toHaveBeenCalledWith('/purchase-orders/42/acknowledge', {
            decision: 'acknowledged',
            reason: undefined,
        });
        expect(publishToastMock).toHaveBeenCalledWith(
            expect.objectContaining({ title: 'Purchase order acknowledged', variant: 'success' }),
        );
        expect(invalidateSpy).toHaveBeenCalledWith(
            expect.objectContaining({ queryKey: queryKeys.purchaseOrders.detail(42) }),
        );
        expect(invalidateSpy).toHaveBeenCalledWith(expect.objectContaining({ queryKey: queryKeys.purchaseOrders.root() }));
        expect(invalidateSpy).toHaveBeenCalledWith(expect.objectContaining({ queryKey: queryKeys.purchaseOrders.events(42) }));
    });

    it('cancels purchase orders and refreshes cached data', async () => {
        const cancelPurchaseOrder = vi.fn().mockResolvedValue({ status: 'success' });
        useSdkClientMock.mockReturnValue({ cancelPurchaseOrder });

        const { Wrapper, invalidateSpy } = createWrapper();
        const { result } = renderHook(() => useCancelPo(), { wrapper: Wrapper });

        await result.current.mutateAsync({ poId: 889, rfqId: 44 });

        expect(cancelPurchaseOrder).toHaveBeenCalledWith({ purchaseOrderId: 889 });
        expect(publishToastMock).toHaveBeenCalledWith(
            expect.objectContaining({ title: 'Purchase order cancelled', variant: 'success' }),
        );

        const expectedKeys = [
            queryKeys.purchaseOrders.detail(889),
            queryKeys.purchaseOrders.root(),
            queryKeys.awards.candidates(44),
            queryKeys.awards.summary(44),
            queryKeys.rfqs.detail(44),
            queryKeys.rfqs.lines(44),
            queryKeys.rfqs.quotes(44),
            queryKeys.quotes.rfq(44),
            queryKeys.quotes.root(),
        ];

        for (const key of expectedKeys) {
            expect(invalidateSpy).toHaveBeenCalledWith(expect.objectContaining({ queryKey: key }));
        }
    });

    it('exports purchase orders to PDF and returns download metadata', async () => {
        const exportPayload = {
            data: {
                document: {
                    id: 1,
                    filename: 'po.pdf',
                    version: 2,
                    downloadUrl: 'https://example.com',
                    createdAt: new Date('2025-01-01T00:00:00Z'),
                },
                downloadUrl: 'https://example.com',
            },
        };
        const exportPurchaseOrder = vi.fn().mockResolvedValue(exportPayload);
        useSdkClientMock.mockReturnValue({ exportPurchaseOrder });

        const { Wrapper, invalidateSpy } = createWrapper();
        const { result } = renderHook(() => useExportPo(), { wrapper: Wrapper });

        const response = await result.current.mutateAsync({ poId: 990 });

        expect(exportPurchaseOrder).toHaveBeenCalledWith({ purchaseOrderId: 990 });
        expect(response.downloadUrl).toBe('https://example.com');
        expect(publishToastMock).toHaveBeenCalledWith(
            expect.objectContaining({ title: 'Purchase order PDF ready', variant: 'success' }),
        );
        expect(invalidateSpy).toHaveBeenCalledWith(
            expect.objectContaining({ queryKey: queryKeys.purchaseOrders.detail(990) }),
        );
    });
});
