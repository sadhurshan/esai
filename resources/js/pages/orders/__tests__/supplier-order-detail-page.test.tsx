import { ReactNode } from 'react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { HelmetProvider } from 'react-helmet-async';

import { SupplierOrderDetailPage } from '@/pages/orders/supplier-order-detail-page';
import type { SalesOrderDetail } from '@/types/orders';
import { useSupplierOrder } from '@/hooks/api/orders/use-supplier-order';
import { useAckOrder } from '@/hooks/api/orders/use-ack-order';
import { useCreateShipment } from '@/hooks/api/orders/use-create-shipment';
import { useUpdateShipmentStatus } from '@/hooks/api/orders/use-update-shipment-status';
import type { ShipmentCreateDialogProps } from '@/components/orders/shipment-create-dialog';

vi.mock('@/components/breadcrumbs', () => ({
    WorkspaceBreadcrumbs: () => <div data-testid="breadcrumbs" />,
}));

vi.mock('@/components/plan-upgrade-banner', () => ({
    PlanUpgradeBanner: () => <div data-testid="plan-upgrade" />,
}));

vi.mock('@/components/orders/order-lines-table', () => ({
    OrderLinesTable: () => <div data-testid="order-lines" />,
}));

vi.mock('@/components/orders/order-timeline', () => ({
    OrderTimeline: () => <div data-testid="order-timeline" />,
}));

const mockShipmentPayload = {
    carrier: 'DHL',
    trackingNumber: '1Z999',
    shippedAt: '2025-01-15T08:00',
    lines: [{ soLineId: 1, qtyShipped: 2 }],
};

vi.mock('@/components/orders/shipment-create-dialog', () => ({
    ShipmentCreateDialog: ({ open, onSubmit }: ShipmentCreateDialogProps) => (
        <div data-testid="shipment-dialog">
            {open ? (
                <button type="button" onClick={() => onSubmit(mockShipmentPayload)}>
                    Submit mock shipment
                </button>
            ) : null}
        </div>
    ),
}));

vi.mock('@/contexts/formatting-context', () => ({
    useFormatting: () => ({
        formatDate: (value?: string | null) => (value ? `date:${value}` : '—'),
        formatMoney: (value?: number | null) => (value === null || value === undefined ? '—' : `$${value}`),
        formatNumber: (value?: number | null) => (value === null || value === undefined ? '0' : value.toString()),
    }),
}));

vi.mock('@/hooks/api/orders/use-supplier-order');
vi.mock('@/hooks/api/orders/use-ack-order');
vi.mock('@/hooks/api/orders/use-create-shipment');
vi.mock('@/hooks/api/orders/use-update-shipment-status');

const mockedUseSupplierOrder = vi.mocked(useSupplierOrder);
const mockedUseAckOrder = vi.mocked(useAckOrder);
const mockedUseCreateShipment = vi.mocked(useCreateShipment);
const mockedUseUpdateShipmentStatus = vi.mocked(useUpdateShipmentStatus);

const baseOrder: SalesOrderDetail = {
    id: 101,
    soNumber: 'SO-101',
    poId: 55,
    buyerCompanyId: 10,
    supplierCompanyId: 20,
    status: 'pending_ack',
    currency: 'USD',
    issueDate: '2025-01-01',
    dueDate: '2025-02-01',
    lines: [],
    shipments: [],
    timeline: [],
    acknowledgements: [],
};

type SupplierOrderOverride = Partial<SalesOrderDetail>;

let ackMutationMock: ReturnType<typeof useAckOrder>;
let ackMutateSpy: ReturnType<typeof vi.fn>;
let createShipmentMutateAsyncSpy: ReturnType<typeof vi.fn>;

function setupHooks(overrides: SupplierOrderOverride = {}) {
    const supplierQueryMock = {
        data: { ...baseOrder, ...overrides },
        isLoading: false,
        isError: false,
        isFetching: false,
        refetch: vi.fn(),
    } as unknown as ReturnType<typeof useSupplierOrder>;
    mockedUseSupplierOrder.mockReturnValue(supplierQueryMock);

    ackMutateSpy = vi.fn();
    ackMutationMock = {
        mutate: ackMutateSpy,
        mutateAsync: vi.fn(),
        isPending: false,
    } as unknown as ReturnType<typeof useAckOrder>;
    mockedUseAckOrder.mockReturnValue(ackMutationMock);

    createShipmentMutateAsyncSpy = vi.fn().mockResolvedValue(undefined);
    const createShipmentMock = {
        mutate: vi.fn(),
        mutateAsync: createShipmentMutateAsyncSpy,
        isPending: false,
    } as unknown as ReturnType<typeof useCreateShipment>;
    mockedUseCreateShipment.mockReturnValue(createShipmentMock);

    const updateShipmentMock = {
        mutate: vi.fn(),
        mutateAsync: vi.fn(),
        isPending: false,
    } as unknown as ReturnType<typeof useUpdateShipmentStatus>;
    mockedUseUpdateShipmentStatus.mockReturnValue(updateShipmentMock);
}

function renderWithRouter(children: ReactNode) {
    return render(
        <HelmetProvider>
            <MemoryRouter initialEntries={['/app/supplier/orders/101']}>
                <Routes>
                    <Route path="/app/supplier/orders/:soId" element={children} />
                </Routes>
            </MemoryRouter>
        </HelmetProvider>,
    );
}

describe('SupplierOrderDetailPage', () => {
    beforeEach(() => {
        setupHooks();
    });

    it('renders pending acknowledgement state and triggers accept mutation', async () => {
        const user = userEvent.setup();
        renderWithRouter(<SupplierOrderDetailPage />);
        expect(screen.getByText(/Awaiting acknowledgement/i)).toBeInTheDocument();

        const acceptButton = screen.getByRole('button', { name: /accept order/i });
        await user.click(acceptButton);

        expect(ackMutateSpy).toHaveBeenCalledWith({ orderId: 101, decision: 'accept' });
    });

    it('submits shipment creation from the dialog happy path', async () => {
        setupHooks({
            status: 'accepted',
            lines: [
                {
                    id: 1,
                    soLineId: 1,
                    description: 'Widget',
                    qtyOrdered: 5,
                    qtyShipped: 0,
                    uom: 'EA',
                },
            ],
        });

        const user = userEvent.setup();
        renderWithRouter(<SupplierOrderDetailPage />);

        await user.click(screen.getByRole('tab', { name: /Shipments/i }));
        const createShipmentButton = screen.getByRole('button', { name: /Create shipment/i });
        expect(createShipmentButton).toBeEnabled();
        await user.click(createShipmentButton);

        const mockSubmitButton = await screen.findByRole('button', { name: /Submit mock shipment/i });
        await user.click(mockSubmitButton);

        expect(createShipmentMutateAsyncSpy).toHaveBeenCalledWith({
            orderId: 101,
            ...mockShipmentPayload,
        });
    });
});
