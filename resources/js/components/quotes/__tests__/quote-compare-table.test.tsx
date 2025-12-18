import type { ReactNode } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { QuoteCompareTable } from '../quote-compare-table';
import type { Quote, RfqItem } from '@/sdk';
import type { QuoteComparisonRow } from '@/types/quotes';

const useQuoteComparisonMock = vi.fn();
const navigateMock = vi.fn();

vi.mock('@/hooks/api/quotes/use-quote-comparison', () => ({
    useQuoteComparison: (...args: unknown[]) => useQuoteComparisonMock(...args),
}));

vi.mock('react-router-dom', () => ({
    useNavigate: () => navigateMock,
}));

vi.mock('@/contexts/formatting-context', () => ({
    useFormatting: () => ({
        locale: 'en-US',
        timezone: 'UTC',
        currency: 'USD',
        displayFx: false,
        rawSettings: {
            timezone: 'UTC',
            locale: 'en-US',
            dateFormat: 'YYYY-MM-DD',
            numberFormat: '1,234.56',
            currency: { primary: 'USD', displayFx: false },
            uom: { baseUom: 'EA', maps: {} },
        },
        formatNumber: (value: number | string | null | undefined) => (value ?? '—').toString(),
        formatMoney: (value: number | string | null | undefined, options?: { currency?: string }) => {
            const amount = value ?? '—';
            const currency = options?.currency ?? 'USD';
            return `${amount} ${currency}`;
        },
        formatDate: () => '2024-01-01',
    }),
}));

vi.mock('@/components/ui/sheet', () => ({
    Sheet: ({ children }: { children: ReactNode }) => <div data-testid="sheet">{children}</div>,
    SheetContent: ({ children }: { children: ReactNode }) => <div data-testid="sheet-content">{children}</div>,
    SheetHeader: ({ children }: { children: ReactNode }) => <div>{children}</div>,
    SheetTitle: ({ children }: { children: ReactNode }) => <h2>{children}</h2>,
    SheetDescription: ({ children }: { children: ReactNode }) => <p>{children}</p>,
}));

vi.mock('@/components/quotes/money-cell', () => ({
    MoneyCell: ({ amountMinor, currency, label }: { amountMinor?: number | null; currency?: string | null; label?: string }) => (
        <div data-testid={`money-cell-${label ?? 'total'}`}>
            {label}: {amountMinor ?? 0} {currency ?? 'USD'}
        </div>
    ),
}));

vi.mock('@/components/quotes/delivery-leadtime-chip', () => ({
    DeliveryLeadTimeChip: ({ leadTimeDays }: { leadTimeDays?: number | null }) => (
        <span data-testid="lead-chip">Lead {leadTimeDays ?? '—'} days</span>
    ),
}));

vi.mock('@/components/quotes/quote-status-badge', () => ({
    QuoteStatusBadge: ({ status }: { status: string }) => <span data-testid="status">{status}</span>,
}));

const onOpenChange = vi.fn();

const baseRfqItems: RfqItem[] = [
    {
        id: 'line-1',
        lineNo: 1,
        partName: 'Bracket',
        spec: '6061-T6',
        quantity: 25,
        uom: 'ea',
    },
    {
        id: 'line-2',
        lineNo: 2,
        partName: 'Spacer',
        spec: 'Delrin',
        quantity: 10,
        uom: 'ea',
    },
];

const alphaQuote: Quote = {
    id: 'quote-alpha',
    rfqId: 1,
    supplierId: 101,
    supplier: { id: 101, name: 'Alpha Fabrication' } as Quote['supplier'],
    status: 'submitted',
    currency: 'USD',
    totalMinor: 125000,
    leadTimeDays: 7,
    revisionNo: 1,
    incoterm: 'FOB',
    paymentTerms: 'Net 30',
    items: [
        {
            id: 'qi-1',
            quoteId: 'quote-alpha',
            rfqItemId: 'line-1',
            currency: 'USD',
            quantity: 25,
            unitPriceMinor: 4000,
            lineSubtotalMinor: 100000,
            note: 'Ships in standard cartons.',
        },
        {
            id: 'qi-2',
            quoteId: 'quote-alpha',
            rfqItemId: 'line-2',
            currency: 'USD',
            quantity: 10,
            unitPriceMinor: 2500,
            lineSubtotalMinor: 25000,
        },
    ],
};

const betaQuote: Quote = {
    id: 'quote-beta',
    rfqId: 1,
    supplierId: 202,
    supplier: { id: 202, name: 'Beta Manufacturing' } as Quote['supplier'],
    status: 'withdrawn',
    currency: 'USD',
    totalMinor: 275000,
    leadTimeDays: 14,
    revisionNo: 2,
    incoterm: 'CIF',
    paymentTerms: '50% upfront',
    items: [
        {
            id: 'qi-3',
            quoteId: 'quote-beta',
            rfqItemId: 'line-1',
            currency: 'USD',
            quantity: 25,
            unitPriceMinor: 9000,
            lineSubtotalMinor: 225000,
        },
        {
            id: 'qi-4',
            quoteId: 'quote-beta',
            rfqItemId: 'line-extra',
            currency: 'USD',
            quantity: 5,
            unitPriceMinor: 10000,
            lineSubtotalMinor: 50000,
            note: 'Alt fixture',
        },
    ],
};

function toComparisonRow(quote: Quote, overrides?: Partial<QuoteComparisonRow>): QuoteComparisonRow {
    return {
        quoteId: quote.id,
        rfqId: quote.rfqId,
        supplier: quote.supplier,
        currency: quote.currency,
        totalPriceMinor: quote.totalMinor,
        leadTimeDays: quote.leadTimeDays,
        status: quote.status,
        attachmentsCount: quote.attachments?.length ?? 0,
        submittedAt: quote.submittedAt,
        scores: {
            composite: 0,
            price: 0,
            leadTime: 0,
            risk: 0,
            fit: 0,
            rank: 0,
            ...(overrides?.scores ?? {}),
        },
        quote,
        ...overrides,
    };
}

describe('QuoteCompareTable', () => {
    beforeEach(() => {
        useQuoteComparisonMock.mockReturnValue({
            data: [],
            isLoading: false,
            isFetching: false,
            isError: false,
            isSuccess: true,
        });
    });

    afterEach(() => {
        vi.clearAllMocks();
        navigateMock.mockReset();
    });

    it('prompts to select more quotes when fewer than two are provided', () => {
        render(
            <QuoteCompareTable
                open
                onOpenChange={onOpenChange}
                quotes={[alphaQuote]}
                rfqId="rfq-1"
                rfqItems={baseRfqItems}
                shortlistedQuoteIds={new Set()}
                selectedQuoteIds={new Set(['quote-alpha'])}
            />,
        );

        expect(screen.getByText(/Select at least two quotes/i)).toBeInTheDocument();
    });

    it('renders normalized comparison cards and RFQ line coverage', () => {
        const comparisonRows: QuoteComparisonRow[] = [
            toComparisonRow(alphaQuote, {
                scores: { composite: 0.9, price: 0.95, leadTime: 0.8, risk: 0.9, fit: 0.85, rank: 1 },
            }),
            toComparisonRow(betaQuote, {
                scores: { composite: 0.6, price: 0.55, leadTime: 0.4, risk: 0.45, fit: 0.35, rank: 2 },
            }),
        ];

        useQuoteComparisonMock.mockReturnValue({
            data: comparisonRows,
            isLoading: false,
            isFetching: false,
            isError: false,
            isSuccess: true,
        });

        render(
            <QuoteCompareTable
                open
                onOpenChange={onOpenChange}
                quotes={[alphaQuote, betaQuote]}
                rfqId="rfq-1"
                rfqItems={baseRfqItems}
                shortlistedQuoteIds={new Set(['quote-beta'])}
                selectedQuoteIds={new Set(['quote-alpha', 'quote-beta'])}
            />,
        );

        expect(screen.getAllByText('Alpha Fabrication').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Beta Manufacturing').length).toBeGreaterThan(0);
        expect(screen.getByText('Shortlisted')).toBeInTheDocument();
        expect(screen.getByText(/Rank #1/)).toBeInTheDocument();
        expect(screen.getAllByText(/Composite score/i).length).toBeGreaterThan(0);

        expect(screen.getByText('Line 1 · Bracket')).toBeInTheDocument();
        expect(screen.getByText(/Qty 25 ea/i)).toBeInTheDocument();
        expect(screen.getByText('Line 2 · Spacer')).toBeInTheDocument();
        expect(screen.getByText('Line — · Line line-extra')).toBeInTheDocument();

        const grandTotals = screen.getAllByTestId('money-cell-Grand total');
        expect(grandTotals).toHaveLength(2);
        expect(grandTotals[0]).toHaveTextContent('125000');
        expect(grandTotals[1]).toHaveTextContent('275000');

        expect(screen.getByText(/Low price outlier/i)).toBeInTheDocument();
        expect(screen.getByText(/High price outlier/i)).toBeInTheDocument();
        expect(screen.getByText(/Fast lead outlier/i)).toBeInTheDocument();
        expect(screen.getByText(/Slow lead outlier/i)).toBeInTheDocument();
    });

    it('surfaces commercial metadata for each quote', () => {
        const comparisonRows: QuoteComparisonRow[] = [
            toComparisonRow(alphaQuote, {
                scores: { composite: 0.9, price: 0.95, leadTime: 0.8, risk: 0.9, fit: 0.85, rank: 1 },
            }),
            toComparisonRow(betaQuote, {
                scores: { composite: 0.6, price: 0.55, leadTime: 0.4, risk: 0.45, fit: 0.35, rank: 2 },
            }),
        ];

        useQuoteComparisonMock.mockReturnValue({
            data: comparisonRows,
            isLoading: false,
            isFetching: false,
            isError: false,
            isSuccess: true,
        });

        render(
            <QuoteCompareTable
                open
                onOpenChange={onOpenChange}
                quotes={[alphaQuote, betaQuote]}
                rfqId="rfq-1"
                rfqItems={baseRfqItems}
                shortlistedQuoteIds={new Set(['quote-beta'])}
                selectedQuoteIds={new Set(['quote-alpha', 'quote-beta'])}
            />,
        );

        expect(
            screen.getByText((_, node) => (node?.textContent ?? '').trim() === 'Incoterm: FOB', {
                selector: 'div',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText((_, node) => (node?.textContent ?? '').trim() === 'Incoterm: CIF', {
                selector: 'div',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText((_, node) => (node?.textContent ?? '').trim() === 'Payment terms: Net 30', {
                selector: 'div',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText((_, node) => (node?.textContent ?? '').trim() === 'Payment terms: 50% upfront', {
                selector: 'div',
            }),
        ).toBeInTheDocument();
    });

    it('filters the comparison list with the new controls', async () => {
        const user = userEvent.setup();

        const comparisonRows: QuoteComparisonRow[] = [
            toComparisonRow(alphaQuote, {
                scores: { composite: 0.92, price: 0.9, leadTime: 0.8, risk: 0.88, fit: 0.82, rank: 1 },
            }),
            toComparisonRow(betaQuote, {
                scores: { composite: 0.5, price: 0.45, leadTime: 0.55, risk: 0.4, fit: 0.5, rank: 4 },
            }),
        ];

        useQuoteComparisonMock.mockReturnValue({
            data: comparisonRows,
            isLoading: false,
            isFetching: false,
            isError: false,
            isSuccess: true,
        });

        render(
            <QuoteCompareTable
                open
                onOpenChange={onOpenChange}
                quotes={[alphaQuote, betaQuote]}
                rfqId="rfq-1"
                rfqItems={baseRfqItems}
                shortlistedQuoteIds={new Set(['quote-beta'])}
                selectedQuoteIds={new Set(['quote-alpha', 'quote-beta'])}
            />,
        );

        const searchInput = screen.getByPlaceholderText(/Filter suppliers by name/i);
        await user.type(searchInput, 'Gamma');
        expect(screen.getByText(/No quotes match the current filters/i)).toBeInTheDocument();

        await user.clear(searchInput);
        await user.type(searchInput, 'Beta');
        expect(screen.queryByText('Alpha Fabrication')).not.toBeInTheDocument();

        await user.clear(searchInput);

        const shortlistButton = screen.getByRole('button', { name: /Shortlist only/i });
        await user.click(shortlistButton);
        expect(screen.queryByText('Alpha Fabrication')).not.toBeInTheDocument();

        const resetButton = await screen.findByRole('button', { name: /Reset filters/i });
        await user.click(resetButton);
        expect(screen.getAllByText('Alpha Fabrication').length).toBeGreaterThan(0);
    });

    it('offers a CTA to launch the award flow with selected quote context', async () => {
        const user = userEvent.setup();

        const comparisonRows: QuoteComparisonRow[] = [
            toComparisonRow(alphaQuote, {
                scores: { composite: 0.9, price: 0.95, leadTime: 0.8, risk: 0.9, fit: 0.85, rank: 1 },
            }),
            toComparisonRow(betaQuote, {
                scores: { composite: 0.6, price: 0.55, leadTime: 0.4, risk: 0.45, fit: 0.35, rank: 2 },
            }),
        ];

        useQuoteComparisonMock.mockReturnValue({
            data: comparisonRows,
            isLoading: false,
            isFetching: false,
            isError: false,
            isSuccess: true,
        });

        render(
            <QuoteCompareTable
                open
                onOpenChange={onOpenChange}
                quotes={[alphaQuote, betaQuote]}
                rfqId="rfq-1"
                rfqItems={baseRfqItems}
                shortlistedQuoteIds={new Set(['quote-beta'])}
                selectedQuoteIds={new Set(['quote-alpha', 'quote-beta'])}
            />,
        );

        const cta = screen.getByRole('button', { name: /Review & award/i });
        expect(cta).toBeEnabled();

        await user.click(cta);

        expect(navigateMock).toHaveBeenCalledWith('/app/rfqs/rfq-1/awards', {
            state: { quoteIds: ['quote-alpha', 'quote-beta'], source: 'compare' },
        });
    });
});
