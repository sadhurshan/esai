import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HelmetProvider } from 'react-helmet-async';
import type { Location } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { UseRfqAwardCandidatesResult } from '@/hooks/api/awards/use-rfq-award-candidates';
import type {
    ListRfqAwardCandidates200ResponseAllOfData,
    RfqAwardCandidateLine,
    RfqItemAwardSummary,
} from '@/sdk';
import { AwardReviewPage } from '../award-review-page';

const mockNavigate = vi.fn();
const mockLocation: Location & { state?: unknown } = {
    pathname: '/app/rfqs/77/awards',
    search: '',
    hash: '',
    key: 'default',
    state: undefined,
};

vi.mock('react-router-dom', async () => {
    const actual =
        await vi.importActual<typeof import('react-router-dom')>(
            'react-router-dom',
        );
    return {
        ...actual,
        useNavigate: () => mockNavigate,
        useParams: () => ({ rfqId: '77' }),
        useLocation: () => mockLocation,
    };
});

vi.mock('@/components/breadcrumbs', () => ({
    WorkspaceBreadcrumbs: () => <nav data-testid="breadcrumbs" />,
}));

const mockNotifyPlanLimit = vi.fn();
vi.mock('@/contexts/auth-context', () => ({
    useAuth: () => ({
        hasFeature: () => true,
        notifyPlanLimit: mockNotifyPlanLimit,
        state: {
            status: 'authenticated',
            company: { name: 'Elements Supply' },
        },
    }),
}));

const useRfqAwardCandidatesMock = vi.fn();
vi.mock('@/hooks/api/awards/use-rfq-award-candidates', () => ({
    useRfqAwardCandidates: (...args: unknown[]) =>
        useRfqAwardCandidatesMock(...args),
}));

const createAwardsMutation = {
    mutateAsync: vi.fn(),
    isPending: false,
};
vi.mock('@/hooks/api/awards/use-create-awards', () => ({
    useCreateAwards: () => createAwardsMutation,
}));

const deleteAwardMutation = {
    mutateAsync: vi.fn(),
    isPending: false,
};
vi.mock('@/hooks/api/awards/use-delete-award', () => ({
    useDeleteAward: () => deleteAwardMutation,
}));

const createPoMutation = {
    mutateAsync: vi.fn(),
    isPending: false,
};
vi.mock('@/hooks/api/pos/use-create-po', () => ({
    useCreatePo: () => createPoMutation,
}));

vi.mock('@/hooks/api/pos/use-recalc-po', () => ({
    useRecalcPo: () => ({ mutateAsync: vi.fn(), isPending: false }),
}));

const rfqId = 77;

const candidateLine: RfqAwardCandidateLine = {
    id: 10,
    lineNo: 1,
    partName: 'Bracket',
    quantity: 5,
    uom: 'ea',
    currency: 'USD',
    candidates: [
        {
            quoteId: 100,
            quoteItemId: 200,
            supplierId: 44,
            supplierName: 'Alpha Manufacturing',
            unitPriceMinor: 5000,
            unitPriceCurrency: 'USD',
            convertedUnitPriceMinor: 5000,
            convertedCurrency: 'USD',
            leadTimeDays: 7,
        },
    ],
    bestPrice: {
        quoteId: 100,
        quoteItemId: 200,
        supplierId: 44,
        supplierName: 'Alpha Manufacturing',
        unitPriceMinor: 5000,
        unitPriceCurrency: 'USD',
        convertedUnitPriceMinor: 5000,
        convertedCurrency: 'USD',
    },
};

function buildAwardCandidatesData(
    overrides?: Partial<ListRfqAwardCandidates200ResponseAllOfData>,
): ListRfqAwardCandidates200ResponseAllOfData {
    return {
        rfq: {
            id: rfqId,
            number: 'RFQ-77',
            title: 'Bracket Machining',
            status: 'award_review',
            currency: 'USD',
            isPartiallyAwarded: false,
            ...(overrides?.rfq ?? {}),
        },
        companyCurrency: overrides?.companyCurrency ?? 'USD',
        lines: overrides?.lines ?? [candidateLine],
        awards: overrides?.awards ?? [],
        meta: overrides?.meta ?? {},
    } satisfies ListRfqAwardCandidates200ResponseAllOfData;
}

function mockAwardCandidatesResult(
    dataOverrides?: Partial<ListRfqAwardCandidates200ResponseAllOfData>,
) {
    const refetch = vi.fn().mockResolvedValue(undefined);
    const result: Partial<UseRfqAwardCandidatesResult> = {
        data: buildAwardCandidatesData(dataOverrides),
        isLoading: false,
        isFetching: false,
        refetch,
    };
    useRfqAwardCandidatesMock.mockReturnValue(
        result as UseRfqAwardCandidatesResult,
    );
    return { refetch };
}

function renderPage() {
    return render(
        <HelmetProvider>
            <AwardReviewPage />
        </HelmetProvider>,
    );
}

describe('AwardReviewPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        createAwardsMutation.mutateAsync = vi.fn();
        createPoMutation.mutateAsync = vi.fn();
        deleteAwardMutation.mutateAsync = vi.fn();
        mockLocation.state = undefined;
    });

    afterEach(() => {
        useRfqAwardCandidatesMock.mockReset();
    });

    it('persists selected supplier choices via the Create awards CTA', async () => {
        const { refetch } = mockAwardCandidatesResult();
        createAwardsMutation.mutateAsync.mockResolvedValueOnce([]);

        renderPage();
        const user = userEvent.setup();

        await user.click(
            screen.getByRole('radio', { name: /Alpha Manufacturing/i }),
        );
        await user.click(
            screen.getByRole('button', { name: /Create awards/i }),
        );

        await waitFor(() => {
            expect(createAwardsMutation.mutateAsync).toHaveBeenCalledWith({
                rfqId,
                items: [
                    {
                        rfqItemId: candidateLine.id,
                        quoteItemId: candidateLine.candidates[0]?.quoteItemId,
                        awardedQty: candidateLine.quantity,
                    },
                ],
            });
        });

        expect(refetch).toHaveBeenCalled();
    });

    it('converts persisted awards to purchase orders and redirects to the PO detail', async () => {
        const pendingAward: RfqItemAwardSummary = {
            id: 301,
            rfqItemId: candidateLine.id,
            supplierId: 44,
            supplierName: 'Alpha Manufacturing',
            quoteId: 100,
            quoteItemId: 200,
            awardedQty: 5,
            status: 'draft',
        };

        mockAwardCandidatesResult({ awards: [pendingAward] });
        createPoMutation.mutateAsync.mockResolvedValueOnce([
            {
                id: 900,
                companyId: 1,
                poNumber: 'PO-900',
                status: 'draft',
                currency: 'USD',
                revisionNo: 1,
                rfqId,
                quoteId: pendingAward.quoteId,
            },
        ]);

        renderPage();
        const user = userEvent.setup();

        await user.click(
            screen.getByRole('button', { name: /Convert to PO/i }),
        );
        await user.click(
            await screen.findByRole('button', {
                name: /Create purchase orders/i,
            }),
        );

        await waitFor(() => {
            expect(createPoMutation.mutateAsync).toHaveBeenCalledWith({
                awardIds: [pendingAward.id],
                rfqId,
            });
        });

        expect(mockNavigate).toHaveBeenCalledWith('/app/purchase-orders/900');
    });

    it('prefills line selections when quoteIds context is provided', async () => {
        mockLocation.state = { quoteIds: ['100'], source: 'compare' };
        mockAwardCandidatesResult();

        renderPage();

        await waitFor(() => {
            expect(
                screen.getByRole('radio', { name: /Alpha Manufacturing/i }),
            ).toBeChecked();
        });
    });
});
