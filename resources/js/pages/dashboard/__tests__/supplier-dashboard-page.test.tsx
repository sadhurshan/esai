import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { useAuth } from '@/contexts/auth-context';
import { useSupplierDashboardMetrics } from '@/hooks/api/useSupplierDashboardMetrics';
import { SupplierDashboardPage } from '@/pages/dashboard/supplier-dashboard-page';

vi.mock('@/hooks/api/useSupplierDashboardMetrics');
vi.mock('@/contexts/auth-context');

const mockUseSupplierDashboardMetrics = vi.mocked(useSupplierDashboardMetrics);
const mockUseAuth = vi.mocked(useAuth);

function mockPersona(
    type: 'buyer' | 'supplier',
    startMode: 'buyer' | 'supplier' = 'buyer',
    supplierStatus: 'none' | 'pending' | 'approved' = 'none',
) {
    mockUseAuth.mockReturnValue({
        activePersona:
            type === 'supplier' ? { type: 'supplier' } : { type: 'buyer' },
        state: {
            company: {
                start_mode: startMode,
                supplier_status: supplierStatus,
            },
        },
    } as unknown as ReturnType<typeof useAuth>);

    mockUseSupplierDashboardMetrics.mockReturnValue({
        data: {
            rfqInvitationCount: 2,
            quotesDraftCount: 1,
            quotesSubmittedCount: 3,
            purchaseOrdersPendingAckCount: 4,
            invoicesUnpaidCount: 5,
        },
        isLoading: false,
        isPlaceholderData: false,
    } as unknown as ReturnType<typeof useSupplierDashboardMetrics>);
}

describe('SupplierDashboardPage', () => {
    it('shows metrics when supplier persona is active and approved', () => {
        mockPersona('supplier', 'buyer', 'approved');

        render(<SupplierDashboardPage />);

        expect(screen.getByText('Supplier workspace')).toBeInTheDocument();
        expect(screen.getByText('New RFQ invites')).toBeInTheDocument();
    });

    it('shows empty state when buyer persona is active', () => {
        mockPersona('buyer');

        render(<SupplierDashboardPage />);

        expect(
            screen.getByText('Switch to supplier persona'),
        ).toBeInTheDocument();
    });

    it('shows metrics when supplier-first company is pending', () => {
        mockPersona('buyer', 'supplier', 'pending');

        render(<SupplierDashboardPage />);

        expect(
            screen.getByText('Complete your supplier application'),
        ).toBeInTheDocument();
    });
});
