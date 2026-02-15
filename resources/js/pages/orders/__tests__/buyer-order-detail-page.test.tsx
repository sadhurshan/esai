import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ReactNode } from 'react';
import { HelmetProvider } from 'react-helmet-async';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useBuyerOrder } from '@/hooks/api/orders/use-buyer-order';
import { BuyerOrderDetailPage } from '@/pages/orders/buyer-order-detail-page';
import type { SalesOrderDetail } from '@/types/orders';

vi.mock('@/components/breadcrumbs', () => ({
    WorkspaceBreadcrumbs: () => <div data-testid="breadcrumbs" />,
}));

vi.mock('@/components/plan-upgrade-banner', () => ({
    PlanUpgradeBanner: () => <div data-testid="plan-banner" />,
}));

vi.mock('@/components/orders/order-lines-table', () => ({
    OrderLinesTable: () => <div data-testid="order-lines" />,
}));

vi.mock('@/components/orders/order-timeline', () => ({
    OrderTimeline: () => <div data-testid="order-timeline" />,
}));

vi.mock('@/contexts/formatting-context', () => ({
    useFormatting: () => ({
        formatDate: (value?: string | null) => (value ? `date:${value}` : '—'),
        formatMoney: (value?: number | null) =>
            value === null || value === undefined ? '—' : `$${value}`,
        formatNumber: (value?: number | null) =>
            value === null || value === undefined ? '0' : value.toString(),
    }),
}));

vi.mock('@/hooks/api/orders/use-buyer-order');

const mockedUseBuyerOrder = vi.mocked(useBuyerOrder);

const baseOrder: SalesOrderDetail = {
    id: 77,
    soNumber: 'SO-77',
    poId: 13,
    buyerCompanyId: 1,
    supplierCompanyId: 2,
    supplierCompanyName: 'ACME Co',
    status: 'accepted',
    currency: 'USD',
    issueDate: '2025-01-10',
    dueDate: '2025-02-10',
    lines: [],
    shipments: [
        {
            id: 500,
            soId: 77,
            shipmentNo: 'SHP-1',
            status: 'in_transit',
            carrier: 'DHL',
            trackingNumber: '1Z123',
            shippedAt: '2025-01-12',
            lines: [{ soLineId: 1, qtyShipped: 2 }],
        },
    ],
    timeline: [],
    acknowledgements: [
        {
            decision: 'accept',
            acknowledgedAt: '2025-01-11',
        },
    ],
};

function renderWithRouter(children: ReactNode) {
    return render(
        <HelmetProvider>
            <MemoryRouter initialEntries={['/app/orders/77']}>
                <Routes>
                    <Route path="/app/orders/:soId" element={children} />
                </Routes>
            </MemoryRouter>
        </HelmetProvider>,
    );
}

describe('BuyerOrderDetailPage', () => {
    beforeEach(() => {
        const buyerQueryMock = {
            data: baseOrder,
            isLoading: false,
            isError: false,
            isFetching: false,
            refetch: vi.fn(),
        } as unknown as ReturnType<typeof useBuyerOrder>;
        mockedUseBuyerOrder.mockReturnValue(buyerQueryMock);
    });

    it('renders shipment data for buyers tracking supplier progress', async () => {
        const user = userEvent.setup();
        renderWithRouter(<BuyerOrderDetailPage />);

        expect(screen.getByText(/Sales order SO-77/i)).toBeInTheDocument();

        await user.click(screen.getByRole('tab', { name: /Shipments/i }));

        expect(await screen.findByText(/Shipment SHP-1/i)).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /1Z123/i }),
        ).toBeInTheDocument();
    });
});
