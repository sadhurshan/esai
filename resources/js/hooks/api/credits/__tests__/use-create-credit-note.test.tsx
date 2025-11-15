import { renderHook } from '@testing-library/react';
import { waitFor } from '@testing-library/dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { PropsWithChildren } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useCreateCreditNote } from '../use-create-credit-note';
import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';

vi.mock('@/contexts/api-client-context', () => ({
    useSdkClient: vi.fn(),
}));

vi.mock('@/components/ui/use-toast', () => ({
    publishToast: vi.fn(),
}));

const useSdkClientMock = vi.mocked(useSdkClient);
const publishToastMock = vi.mocked(publishToast);

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: {
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

describe('useCreateCreditNote', () => {
    const createCreditNoteFromInvoice = vi.fn();

    beforeEach(() => {
        createCreditNoteFromInvoice.mockReset();
        publishToastMock.mockReset();
        useSdkClientMock.mockReturnValue({
            createCreditNoteFromInvoice,
        });
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    it('creates a credit note and invalidates downstream queries', async () => {
        const apiResponse = {
            id: '101',
            credit_number: 'CN-101',
            status: 'draft',
            amount_minor: 12500,
            currency: 'USD',
            invoice_id: 55,
            purchase_order_id: 88,
            attachments: [],
        } satisfies Record<string, unknown>;

        createCreditNoteFromInvoice.mockResolvedValue(apiResponse);

        const { Wrapper, invalidateSpy } = createWrapper();
        const { result } = renderHook(() => useCreateCreditNote(), { wrapper: Wrapper });

        await result.current.mutateAsync({
            invoiceId: 55,
            reason: 'Damaged goods',
            amountMinor: 12500,
        });

        expect(createCreditNoteFromInvoice).toHaveBeenCalledWith({
            invoiceId: 55,
            reason: 'Damaged goods',
            amountMinor: 12500,
            purchaseOrderId: undefined,
            grnId: undefined,
            attachments: undefined,
        });

        await waitFor(() => expect(publishToastMock).toHaveBeenCalledTimes(1));
        expect(publishToastMock.mock.calls[0]?.[0]?.variant).toBe('success');

        expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['credits', 'list', {}] });
    });

    it('surfaces client-side validation errors via toast', async () => {
        const { Wrapper } = createWrapper();
        const { result } = renderHook(() => useCreateCreditNote(), { wrapper: Wrapper });

        await expect(
            result.current.mutateAsync({
                invoiceId: 0,
                reason: '',
                amountMinor: 1000,
            }),
        ).rejects.toThrow('A valid invoice reference is required to create a credit note.');

        await waitFor(() => expect(publishToastMock).toHaveBeenCalled());
        expect(publishToastMock.mock.calls[0]?.[0]).toMatchObject({
            variant: 'destructive',
            title: 'Credit note creation failed',
        });
    });
});
