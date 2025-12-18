import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { SupplierDashboardPage } from '@/pages/dashboard/supplier-dashboard-page';
import { useSupplierDashboardMetrics } from '@/hooks/api/useSupplierDashboardMetrics';
import { useAuth } from '@/contexts/auth-context';

vi.mock('@/hooks/api/useSupplierDashboardMetrics');
vi.mock('@/contexts/auth-context');

const mockUseSupplierDashboardMetrics = vi.mocked(useSupplierDashboardMetrics);
const mockUseAuth = vi.mocked(useAuth);

function mockPersona(type: 'buyer' | 'supplier') {
    mockUseAuth.mockReturnValue({
        activePersona: type === 'supplier' ? { type: 'supplier' } : { type: 'buyer' },
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
    it('shows metrics when supplier persona is active', () => {
        mockPersona('supplier');

        render(<SupplierDashboardPage />);

        expect(screen.getByText('Supplier workspace')).toBeInTheDocument();
        expect(screen.getByText('New RFQ invites')).toBeInTheDocument();
    });

    it('shows empty state when buyer persona is active', () => {
        mockPersona('buyer');

        render(<SupplierDashboardPage />);

        expect(screen.getByText('Switch to supplier persona')).toBeInTheDocument();
    });
});
