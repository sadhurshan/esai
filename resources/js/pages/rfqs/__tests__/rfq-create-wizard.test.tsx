import { render } from '@testing-library/react';
import { screen, waitFor, within } from '@testing-library/dom';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { HelmetProvider } from 'react-helmet-async';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

import { RfqCreateWizard } from '../rfq-create-wizard';

const createRfqMock = vi.fn();
const inviteSuppliersMock = vi.fn();
const publishRfqMock = vi.fn();
const uploadAttachmentMock = vi.fn();

beforeAll(() => {
    if (!Element.prototype.hasPointerCapture) {
        Element.prototype.hasPointerCapture = () => false;
    }
    if (!Element.prototype.setPointerCapture) {
        Element.prototype.setPointerCapture = () => {};
    }
    if (!Element.prototype.releasePointerCapture) {
        Element.prototype.releasePointerCapture = () => {};
    }
    if (!Element.prototype.scrollIntoView) {
        Element.prototype.scrollIntoView = () => {};
    }
});

beforeEach(() => {
    window.localStorage.clear();
    window.sessionStorage.clear();
});

vi.mock('@/hooks/api/rfqs', () => ({
    useCreateRfq: () => ({
        mutateAsync: createRfqMock,
        isPending: false,
    }),
    useInviteSuppliers: () => ({
        mutateAsync: inviteSuppliersMock,
        isPending: false,
    }),
    usePublishRfq: () => ({
        mutateAsync: publishRfqMock,
        isPending: false,
    }),
    useUploadAttachment: () => ({
        mutateAsync: uploadAttachmentMock,
        isPending: false,
    }),
}));

vi.mock('@/hooks/api/use-money-settings', () => ({
    useMoneySettings: () => ({
        data: {
            baseCurrency: { code: 'USD', name: 'US Dollar' },
            pricingCurrency: { code: 'USD', name: 'US Dollar' },
        },
        isLoading: false,
    }),
}));

vi.mock('@/hooks/api/useSuppliers', () => ({
    useSuppliers: () => ({
        data: {
            items: [
                {
                    id: 1,
                    companyId: 101,
                    name: 'Acme Fabrication',
                    status: 'approved',
                    capabilities: { methods: ['CNC machining'] },
                    ratingAvg: 4.8,
                    contact: {},
                    address: { city: 'Austin', country: 'USA' },
                    geo: {},
                },
            ],
        },
        isLoading: false,
    }),
}));

vi.mock('@/hooks/api/use-uoms', () => ({
    useUoms: () => ({
        data: [
            {
                code: 'ea',
                name: 'Each',
                symbol: 'ea',
                dimension: 'quantity',
                siBase: true,
            },
            {
                code: 'set',
                name: 'Set',
                symbol: 'set',
                dimension: 'quantity',
                siBase: false,
            },
        ],
        isLoading: false,
        isError: false,
    }),
}));

vi.mock('@/hooks/use-uom-conversion-helper', () => ({
    useBaseUomQuantity: () => ({
        baseUomLabel: 'EA',
        convertedLabel: null,
        isEnabled: false,
        isLoading: false,
    }),
}));

vi.mock('@/contexts/auth-context', () => ({
    useAuth: () => ({
        hasFeature: () => true,
        state: {
            featureFlags: {
                'rfqs.create': true,
                'rfqs.publish': true,
                'rfqs.suppliers.invite': true,
                'rfqs.attachments.manage': true,
                'suppliers.directory.browse': true,
            },
        },
    }),
}));

describe('RfqCreateWizard', () => {
    afterEach(() => {
        vi.clearAllMocks();
        createRfqMock.mockReset();
        inviteSuppliersMock.mockReset();
        publishRfqMock.mockReset();
        uploadAttachmentMock.mockReset();
        window.localStorage.clear();
    });

    function renderWizard() {
        return render(
            <HelmetProvider>
                <MemoryRouter initialEntries={['/app/rfqs/new']}>
                    <RfqCreateWizard />
                </MemoryRouter>
            </HelmetProvider>,
        );
    }

    it('displays validation errors when basics step is incomplete', async () => {
        const user = userEvent.setup();
        renderWizard();

        await user.click(screen.getByRole('button', { name: /next/i }));

        await waitFor(() => {
            expect(screen.getByText(/Step 1 of 6/i)).toBeInTheDocument();
        });

        const basicsIndicator = screen.getByText('1. Basics');
        const linesIndicator = screen.getByText('2. Lines');

        expect(basicsIndicator.className).toContain('font-semibold');
        expect(linesIndicator.className).not.toContain('font-semibold');
        expect(createRfqMock).not.toHaveBeenCalled();
    });

    it('requires a line item description before advancing past the lines step', async () => {
        const user = userEvent.setup();
        renderWizard();

        await user.type(screen.getByLabelText('Title'), 'Bracket RFQ');
        await user.type(screen.getByLabelText('Delivery location'), 'Elements Supply · Austin, TX');

        await user.click(screen.getByRole('button', { name: /next/i }));

        expect(screen.getByText('Line items')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /next/i }));

        expect(await screen.findByText('Part name is required.')).toBeInTheDocument();
        expect(await screen.findByText('Manufacturing method is required.')).toBeInTheDocument();
        expect(await screen.findByText('Material is required.')).toBeInTheDocument();
        expect(createRfqMock).not.toHaveBeenCalled();
    });

    it('uploads attachments and publishes when finishing the wizard', async () => {
        const user = userEvent.setup();
        createRfqMock.mockResolvedValue({ data: { id: 'rfq-999' } });
        inviteSuppliersMock.mockResolvedValue({ invited: 1, responses: [] });
        publishRfqMock.mockResolvedValue({});
        uploadAttachmentMock.mockResolvedValue({});

        const { container } = renderWizard();

        await user.type(screen.getByLabelText('Title'), 'Bracket RFQ');
        await user.type(screen.getByLabelText('Delivery location'), 'Elements Supply · Austin, TX');

        await user.click(screen.getByRole('button', { name: /next/i }));

        await user.type(screen.getByLabelText(/Part \/ description/i), 'Bracket 001');
        await user.type(screen.getByLabelText('Manufacturing method'), 'CNC machining');
        await user.type(screen.getByLabelText('Material'), '6061-T6 Aluminum');
        await user.type(screen.getByLabelText('Required date'), '2100-01-05');

        await user.click(screen.getByRole('button', { name: /next/i }));

        const browseButton = screen.getByRole('button', { name: /browse directory/i });
        await user.click(browseButton);

        const directoryDialog = await screen.findByRole('dialog', { name: /browse supplier directory/i });
        const addButton = within(directoryDialog).getByRole('button', { name: /^add$/i });
        await user.click(addButton);

        await waitFor(() => {
            expect(screen.getByText('Acme Fabrication')).toBeInTheDocument();
        });

        await user.click(screen.getByRole('button', { name: /next/i }));

        await user.type(screen.getByLabelText('Publish date'), '2100-01-01T10:00');
        await user.type(screen.getByLabelText('Quote submission deadline'), '2100-01-10T10:00');
        await user.type(screen.getByLabelText('Payment terms (optional)'), 'Net 45');
        await user.type(screen.getByLabelText('Estimated tax rate (optional)'), '8.75');

        await user.click(screen.getByRole('button', { name: /next/i }));

        const fileInput = container.querySelector('input[type="file"]') as HTMLInputElement;
        const file = new File(['spec'], 'spec.pdf', { type: 'application/pdf' });
        await user.upload(fileInput, file);

        await user.click(screen.getByRole('button', { name: /next/i }));

        const publishCheckbox = screen.getByLabelText('Publish immediately after creation') as HTMLInputElement;
        expect(publishCheckbox).not.toBeDisabled();
        await user.click(publishCheckbox);
        expect(publishCheckbox).toBeChecked();

        const removeItemSpy = vi.spyOn(Object.getPrototypeOf(window.localStorage), 'removeItem');

        await user.click(screen.getByRole('button', { name: /Finish & create RFQ/i }));

        await waitFor(() => {
            expect(createRfqMock).toHaveBeenCalled();
        });

        expect(createRfqMock).toHaveBeenCalled();
        const payload = createRfqMock.mock.calls[0]?.[0];
        expect(payload).toMatchObject({
            title: 'Bracket RFQ',
            method: 'cnc',
            deliveryLocation: 'Elements Supply · Austin, TX',
            currency: 'USD',
            paymentTerms: 'Net 45',
            taxPercent: 8.75,
        });
        expect(payload?.items?.[0]).toMatchObject({
            partNumber: 'Bracket 001',
            requiredDate: '2100-01-05',
        });
        expect(inviteSuppliersMock).toHaveBeenCalledWith({
            rfqId: 'rfq-999',
            supplierIds: ['1'],
        });
        expect(uploadAttachmentMock).toHaveBeenCalledWith({
            rfqId: 'rfq-999',
            file,
        });
        expect(publishRfqMock).toHaveBeenCalledWith(
            expect.objectContaining({
                rfqId: 'rfq-999',
                notifySuppliers: true,
                dueAt: expect.any(Date),
                publishAt: expect.any(Date),
            }),
        );
        const publishArgs = publishRfqMock.mock.calls[0]?.[0];
        expect(publishArgs?.dueAt?.toISOString()).toBe(new Date('2100-01-10T10:00').toISOString());
        expect(publishArgs?.publishAt?.toISOString()).toBe(new Date('2100-01-01T10:00').toISOString());
        expect(removeItemSpy).toHaveBeenCalledWith('esai.rfq-wizard-state');

        removeItemSpy.mockRestore();
    }, 15_000);
});
