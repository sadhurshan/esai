import type { ReactNode } from 'react';
import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { QuoteCompareTable } from '../quote-compare-table';
import type { Quote, RfqItem } from '@/sdk';

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
    totalMinor: 150000,
    leadTimeDays: 7,
    revisionNo: 1,
    items: [
        {
            id: 'qi-1',
            quoteId: 'quote-alpha',
            rfqItemId: 'line-1',
            currency: 'USD',
            quantity: 25,
            unitPriceMinor: 5000,
            lineSubtotalMinor: 125000,
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
    status: 'submitted',
    currency: 'USD',
    totalMinor: 200000,
    leadTimeDays: 14,
    revisionNo: 2,
    items: [
        {
            id: 'qi-3',
            quoteId: 'quote-beta',
            rfqItemId: 'line-1',
            currency: 'USD',
            quantity: 25,
            unitPriceMinor: 6000,
            lineSubtotalMinor: 150000,
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

describe('QuoteCompareTable', () => {
    afterEach(() => {
        vi.clearAllMocks();
    });

    it('prompts to select more quotes when fewer than two are provided', () => {
        render(
            <QuoteCompareTable open onOpenChange={onOpenChange} quotes={[alphaQuote]} rfqItems={baseRfqItems} shortlistedQuoteIds={new Set()}
            />,
        );

        expect(screen.getByText(/Select at least two quotes/i)).toBeInTheDocument();
    });

    it('renders line comparisons with RFQ metadata and quote fallbacks', () => {
        render(
            <QuoteCompareTable
                open
                onOpenChange={onOpenChange}
                quotes={[alphaQuote, betaQuote]}
                rfqItems={baseRfqItems}
                shortlistedQuoteIds={new Set(['quote-beta'])}
            />,
        );

        expect(screen.getAllByText('Alpha Fabrication').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Beta Manufacturing').length).toBeGreaterThan(0);
        expect(screen.getByText('Shortlisted')).toBeInTheDocument();

        expect(screen.getByText('Line 1 · Bracket')).toBeInTheDocument();
        expect(screen.getByText(/Qty 25 ea/i)).toBeInTheDocument();
        expect(screen.getByText('Line 2 · Spacer')).toBeInTheDocument();
        expect(screen.getByText('Line — · Line line-extra')).toBeInTheDocument();

        const grandTotals = screen.getAllByTestId('money-cell-Grand total');
        expect(grandTotals).toHaveLength(2);
        expect(grandTotals[0]).toHaveTextContent('150000');
        expect(grandTotals[1]).toHaveTextContent('200000');
    });
});
