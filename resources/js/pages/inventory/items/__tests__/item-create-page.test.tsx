import { waitFor } from '@testing-library/dom';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createContext, useContext, type PropsWithChildren } from 'react';
import { HelmetProvider } from 'react-helmet-async';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { publishToast } from '@/components/ui/use-toast';
import { ItemCreatePage } from '../item-create-page';

vi.mock('@/contexts/auth-context', () => ({
    useAuth: () => ({
        hasFeature: () => true,
        state: { status: 'ready' },
    }),
}));

const mutateAsyncMock = vi.fn();

vi.mock('@/hooks/api/inventory/use-create-item', () => ({
    useCreateItem: () => ({
        mutateAsync: mutateAsyncMock,
        isPending: false,
    }),
}));

vi.mock('@/hooks/api/inventory/use-locations', () => ({
    useLocations: () => ({
        data: {
            items: [
                {
                    id: 'loc-1',
                    name: 'Receiving',
                    siteName: 'Austin',
                },
            ],
        },
        isLoading: false,
    }),
}));

vi.mock('@/components/ui/select', () => {
    const SelectCtx = createContext<{
        onValueChange?: (value: string) => void;
    }>({});

    const Select = ({
        onValueChange,
        children,
    }: PropsWithChildren<{
        value?: string;
        onValueChange: (value: string) => void;
    }>) => (
        <SelectCtx.Provider value={{ onValueChange }}>
            {children}
        </SelectCtx.Provider>
    );
    const SelectTrigger = ({ children }: PropsWithChildren) => (
        <div>{children}</div>
    );
    const SelectContent = ({ children }: PropsWithChildren) => (
        <div>{children}</div>
    );
    const SelectValue = ({ placeholder }: { placeholder?: string }) => (
        <span>{placeholder ?? ''}</span>
    );
    const SelectItem = ({
        value,
        children,
    }: PropsWithChildren<{ value: string }>) => {
        const ctx = useContext(SelectCtx);
        return (
            <button
                type="button"
                data-testid={`select-item-${value}`}
                onClick={() => ctx.onValueChange?.(value)}
            >
                {children}
            </button>
        );
    };

    return {
        Select,
        SelectTrigger,
        SelectContent,
        SelectValue,
        SelectItem,
    };
});

vi.mock('@/components/ui/use-toast', () => ({
    publishToast: vi.fn(),
}));

const publishToastMock = vi.mocked(publishToast);

function renderPage() {
    return render(
        <HelmetProvider>
            <MemoryRouter initialEntries={['/app/inventory/items/new']}>
                <ItemCreatePage />
            </MemoryRouter>
        </HelmetProvider>,
    );
}

describe('ItemCreatePage', () => {
    beforeEach(() => {
        mutateAsyncMock.mockReset();
        publishToastMock.mockReset();
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    it('displays validation errors when required fields are blank', async () => {
        const user = userEvent.setup();

        renderPage();

        await user.click(screen.getByRole('button', { name: /save item/i }));

        expect(await screen.findByText('SKU is required')).toBeInTheDocument();
        expect(screen.getByText('Name is required')).toBeInTheDocument();
        expect(screen.getByText('Default UoM is required')).toBeInTheDocument();
        expect(mutateAsyncMock).not.toHaveBeenCalled();
    });

    it('submits trimmed values when the form is valid', async () => {
        const user = userEvent.setup();
        mutateAsyncMock.mockResolvedValue({ id: 'item-123' });

        renderPage();

        await user.type(screen.getByLabelText('SKU'), '  SKU-55  ');
        await user.type(screen.getByLabelText('Name'), '  Bracket  ');
        await user.type(screen.getByLabelText('Default UoM'), '  EA  ');
        await user.type(screen.getByLabelText('Category'), ' Hardware ');
        await user.type(
            screen.getByLabelText('Description'),
            ' Bracket for assemblies ',
        );

        await user.click(screen.getByTestId('select-item-loc-1'));

        await user.click(screen.getByRole('button', { name: /save item/i }));

        await waitFor(() => expect(mutateAsyncMock).toHaveBeenCalled());
        expect(mutateAsyncMock).toHaveBeenCalledWith({
            sku: 'SKU-55',
            name: 'Bracket',
            uom: 'EA',
            category: 'Hardware',
            description: 'Bracket for assemblies',
            minStock: undefined,
            reorderQty: undefined,
            leadTimeDays: undefined,
            defaultLocationId: 'loc-1',
            active: true,
        });
    });
});
