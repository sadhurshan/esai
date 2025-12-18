import type { ReactNode } from 'react';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HelmetProvider } from 'react-helmet-async';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { SupplierQuoteCreatePage } from '../supplier-quote-create-page';
import { SupplierQuoteEditPage } from '../supplier-quote-edit-page';
import type { Quote, RfqItem } from '@/sdk';

const mockNavigate = vi.fn();
const mockParams: Record<string, string | undefined> = {};

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
        formatMoney: (value: number | string | null | undefined) => `$${value ?? '—'}`,
        formatDate: () => '2024-01-01',
    }),
}));

vi.mock('@/hooks/api/settings', () => ({
    useNumberingSettings: () => ({
        data: {
            quote: { prefix: 'QT-', seqLen: 4, next: 42, reset: 'never' },
        },
        isLoading: false,
        isError: false,
    }),
}));

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
    return {
        ...actual,
        useNavigate: () => mockNavigate,
        useParams: () => mockParams,
    };
});

vi.mock('@/components/plan-upgrade-banner', () => ({
    PlanUpgradeBanner: () => <div data-testid="plan-upgrade-banner" />,
}));

vi.mock('@/components/quotes/money-cell', () => ({
    MoneyCell: ({ amountMinor, currency, label }: { amountMinor?: number | null; currency?: string | null; label?: string }) => (
        <div data-testid={`money-cell-${label ?? 'total'}`}>
            {label ?? 'Total'}: {amountMinor ?? 0} {currency ?? 'USD'}
        </div>
    ),
}));

vi.mock('@/components/quotes/quote-status-badge', () => ({
    QuoteStatusBadge: ({ status }: { status: string }) => <span data-testid="status">{status}</span>,
}));

type PublishToastArgs = [payload: unknown];

const publishToastMock = vi.fn();
vi.mock('@/components/ui/use-toast', () => ({
    publishToast: (...args: PublishToastArgs) => publishToastMock(...args),
}));

vi.mock('@/components/ui/dialog', () => ({
    Dialog: ({ open, children }: { open: boolean; children: ReactNode }) => (
        <div data-testid="dialog" data-open={open}>
            {open ? children : null}
        </div>
    ),
    DialogContent: ({ children }: { children: ReactNode }) => <div>{children}</div>,
    DialogDescription: ({ children }: { children: ReactNode }) => <p>{children}</p>,
    DialogFooter: ({ children }: { children: ReactNode }) => <div>{children}</div>,
    DialogHeader: ({ children }: { children: ReactNode }) => <div>{children}</div>,
    DialogTitle: ({ children }: { children: ReactNode }) => <h2>{children}</h2>,
}));

vi.mock('@/contexts/auth-context', () => ({
    useAuth: () => ({
        hasFeature: () => true,
        notifyPlanLimit: vi.fn(),
        state: {
            status: 'authenticated',
            user: { role: 'supplier', name: 'Supplier User', email: 'supplier@example.com' },
            company: { name: 'Supplier Co' },
        },
    }),
}));

const rfqQueryResult = {
    data: { number: 'RFQ-42' },
    isLoading: false,
    isError: false,
};
type UseRfqArgs = [rfqId?: string | null, options?: unknown];

const useRfqMock = vi.fn((...args: UseRfqArgs) => {
    void args;
    return rfqQueryResult;
});
vi.mock('@/hooks/api/rfqs/use-rfq', () => ({
    useRfq: (...args: UseRfqArgs) => useRfqMock(...args),
}));

const baseRfqLines: RfqItem[] = [
    { id: 'line-1', lineNo: 1, partName: 'Bracket', spec: '6061-T6', quantity: 5, uom: 'ea' },
];
const rfqLinesResult = { items: baseRfqLines, isLoading: false };
vi.mock('@/hooks/api/rfqs/use-rfq-lines', () => ({
    useRfqLines: () => rfqLinesResult,
}));

const moneySettingsResult = { data: { pricingCurrency: { code: 'USD', name: 'US Dollar', minorUnit: 2 } } };
vi.mock('@/hooks/api/use-money-settings', () => ({
    useMoneySettings: () => moneySettingsResult,
}));

const createQuoteMutate = vi.fn();
vi.mock('@/hooks/api/quotes/use-create-quote', () => ({
    useCreateQuote: () => ({ mutateAsync: createQuoteMutate, isPending: false }),
}));

const submitQuoteMutate = vi.fn();
vi.mock('@/hooks/api/quotes/use-submit-quote', () => ({
    useSubmitQuote: () => ({ mutateAsync: submitQuoteMutate, isPending: false }),
}));

const reviseQuoteMutate = vi.fn();
vi.mock('@/hooks/api/quotes/use-revise-quote', () => ({
    useReviseQuote: () => ({ mutateAsync: reviseQuoteMutate, isPending: false }),
}));

const withdrawQuoteMutate = vi.fn();
vi.mock('@/hooks/api/quotes/use-withdraw-quote', () => ({
    useWithdrawQuote: () => ({ mutateAsync: withdrawQuoteMutate, isPending: false }),
}));

const updateLineMutate = vi.fn();
vi.mock('@/hooks/api/quotes/use-quote-lines', () => ({
    useQuoteLines: () => ({
        updateLine: {
            mutateAsync: updateLineMutate,
            isPending: false,
        },
    }),
}));

const quoteQueryResult = {
    data: {
        quote: null as Quote | null,
    },
    isLoading: false,
    isError: false,
};
type UseQuoteArgs = [quoteId?: string | null, options?: unknown];

const useQuoteMock = vi.fn((...args: UseQuoteArgs) => {
    void args;
    return quoteQueryResult;
});
vi.mock('@/hooks/api/quotes/use-quote', () => ({
    useQuote: (...args: UseQuoteArgs) => useQuoteMock(...args),
}));

const sampleQuote: Quote = {
    id: 'quote-123',
    rfqId: 321,
    supplierId: 900,
    status: 'submitted',
    currency: 'USD',
    totalMinor: 150000,
    leadTimeDays: 12,
    note: 'Initial submission',
    revisionNo: 1,
    supplier: { id: 900, name: 'Alpha Industries' } as Quote['supplier'],
    items: [
        {
            id: 'quote-item-1',
            quoteId: 'quote-123',
            rfqItemId: 'line-1',
            currency: 'USD',
            quantity: 5,
            unitPriceMinor: 2500,
            lineSubtotalMinor: 12500,
            leadTimeDays: 14,
            note: 'Packed in foam',
        },
    ],
    attachments: [
        {
            id: 'att-1',
            filename: 'quote.pdf',
            mime: 'application/pdf',
        },
    ],
};

function renderWithHelmet(node: ReactNode) {
    return render(<HelmetProvider>{node}</HelmetProvider>);
}

describe('Supplier quote pages', () => {
    afterEach(() => {
        vi.clearAllMocks();
        mockNavigate.mockReset();
        mockParams.rfqId = undefined;
        mockParams.quoteId = undefined;
        quoteQueryResult.data.quote = null;
    });

    it('submits a supplier quote from the create page and routes to buyer detail', async () => {
        mockParams.rfqId = 'rfq-123';
        createQuoteMutate.mockResolvedValue({
            id: 'quote-result',
            rfqId: 321,
            supplierId: 901,
            status: 'draft',
            currency: 'USD',
            totalMinor: 0,
        } as Quote);
        submitQuoteMutate.mockResolvedValue({});

        const user = userEvent.setup();
        renderWithHelmet(<SupplierQuoteCreatePage />);

        const incotermInput = await screen.findByLabelText('Incoterm (optional)');
        await user.type(incotermInput, 'fob ');
        const paymentTermsInput = screen.getByLabelText('Payment terms (optional)');
        await user.type(paymentTermsInput, ' Net 30');

        const unitPriceInput = await screen.findByLabelText('Unit price (USD)');
        await user.type(unitPriceInput, '12.5');
        const leadTimeInput = screen.getByLabelText('Lead time (days)');
        await user.type(leadTimeInput, '10');

        await user.click(screen.getByRole('button', { name: 'Submit quote' }));

        await waitFor(() => {
            expect(createQuoteMutate).toHaveBeenCalled();
            expect(submitQuoteMutate).toHaveBeenCalled();
        });

        const payload = createQuoteMutate.mock.calls[0]?.[0];
        expect(payload).toMatchObject({ rfqId: 'rfq-123', currency: 'USD', incoterm: 'FOB', paymentTerms: 'Net 30' });
        expect(payload.items[0]).toMatchObject({ rfqItemId: 'line-1', unitPriceMinor: 1250, leadTimeDays: 10 });

        expect(submitQuoteMutate).toHaveBeenCalledWith({ quoteId: 'quote-result', rfqId: 321 });
        expect(mockNavigate).toHaveBeenCalledWith('/app/supplier/quotes/quote-result');
    });

    it('collects a withdraw reason on the edit page before calling the mutation', async () => {
        mockParams.quoteId = 'quote-123';
        quoteQueryResult.data.quote = sampleQuote;
        withdrawQuoteMutate.mockResolvedValue({});

        const user = userEvent.setup();
        renderWithHelmet(<SupplierQuoteEditPage />);

        const withdrawAction = await screen.findByRole('button', { name: 'Withdraw quote' });
        await user.click(withdrawAction);

        const dialog = screen.getByTestId('dialog');
        const reasonInput = await within(dialog).findByLabelText('Reason');
        await user.type(reasonInput, 'Pricing expired after alloy surcharge.');

        const confirmButton = within(dialog).getByRole('button', { name: 'Withdraw quote' });
        await user.click(confirmButton);

        await waitFor(() => {
            expect(withdrawQuoteMutate).toHaveBeenCalledWith({ quoteId: 'quote-123', rfqId: 321, reason: expect.stringContaining('Pricing expired') });
        });

        expect(publishToastMock).toHaveBeenCalledWith(
            expect.objectContaining({ title: 'Quote withdrawn', variant: 'success' }),
        );
        expect(mockNavigate).not.toHaveBeenCalled();
    });
});
