import { waitFor } from '@testing-library/dom';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createContext, useContext, type PropsWithChildren } from 'react';
import { HelmetProvider } from 'react-helmet-async';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { publishToast } from '@/components/ui/use-toast';
import { validateMovementStock } from '@/lib/inventory-stock-validations';
import { MovementCreatePage } from '../movement-create-page';

vi.mock('@/contexts/auth-context', () => ({
    useAuth: () => ({
        hasFeature: () => true,
        state: { status: 'ready' },
    }),
}));

vi.mock('@/hooks/api/inventory/use-items', () => ({
    useItems: () => ({
        data: {
            items: [
                {
                    id: 'item-1',
                    sku: 'SKU-1',
                    name: 'Widget',
                    defaultUom: 'EA',
                },
            ],
        },
        isLoading: false,
    }),
}));

vi.mock('@/hooks/api/inventory/use-locations', () => ({
    useLocations: () => ({
        data: {
            items: [
                {
                    id: 'loc-a',
                    name: 'Line 1',
                    isDefaultReceiving: true,
                },
            ],
        },
        isLoading: false,
    }),
}));

const mutateAsyncMock = vi.fn();

vi.mock('@/hooks/api/inventory/use-create-movement', () => ({
    useCreateMovement: () => ({
        mutateAsync: mutateAsyncMock,
        isPending: false,
    }),
}));

type MockMovementEditorProps = {
    form: {
        setValue: (path: string, value: unknown, options?: unknown) => void;
    };
    defaultDestinationId?: string | null;
};

vi.mock('@/components/inventory/movement-line-editor', () => ({
    MovementLineEditor: (props: MockMovementEditorProps) => (
        <div>
            <button
                type="button"
                data-testid="clear-lines"
                onClick={() => props.form.setValue('lines', [])}
            >
                Clear lines
            </button>
            <button
                type="button"
                data-testid="seed-transfer-line"
                onClick={() =>
                    props.form.setValue('lines', [
                        {
                            itemId: 'item-1',
                            qty: 1,
                            fromLocationId: 'loc-a',
                            toLocationId: props.defaultDestinationId ?? 'loc-a',
                            uom: 'EA',
                            reason: null,
                        },
                    ])
                }
            >
                Seed transfer line
            </button>
        </div>
    ),
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

vi.mock('@/lib/inventory-stock-validations', () => ({
    validateMovementStock: vi.fn(),
}));

const publishToastMock = vi.mocked(publishToast);
const validateMovementStockMock = vi.mocked(validateMovementStock);

function renderPage() {
    return render(
        <HelmetProvider>
            <MemoryRouter initialEntries={['/app/inventory/movements/new']}>
                <MovementCreatePage />
            </MemoryRouter>
        </HelmetProvider>,
    );
}

describe('MovementCreatePage', () => {
    beforeEach(() => {
        mutateAsyncMock.mockReset();
        publishToastMock.mockReset();
        validateMovementStockMock.mockReset();
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    it('requires at least one movement line before submission', async () => {
        const user = userEvent.setup();
        validateMovementStockMock.mockReturnValue([]);

        renderPage();

        await user.click(screen.getByTestId('clear-lines'));
        await user.click(
            screen.getByRole('button', { name: /post movement/i }),
        );

        expect(
            await screen.findByText('Add at least one line'),
        ).toBeInTheDocument();
        expect(mutateAsyncMock).not.toHaveBeenCalled();
    });

    it('prevents transfers when the source and destination match', async () => {
        const user = userEvent.setup();
        validateMovementStockMock.mockReturnValue([
            {
                index: 0,
                message: 'Source and destination cannot be the same.',
            },
        ]);

        renderPage();

        await user.click(screen.getByTestId('select-item-TRANSFER'));

        await user.click(screen.getByTestId('seed-transfer-line'));
        await user.click(
            screen.getByRole('button', { name: /post movement/i }),
        );

        await waitFor(() => {
            expect(publishToastMock).toHaveBeenCalled();
        });
        expect(publishToastMock.mock.calls[0]?.[0]).toMatchObject({
            variant: 'destructive',
            title: 'Insufficient stock',
        });

        expect(mutateAsyncMock).not.toHaveBeenCalled();
        expect(validateMovementStockMock).toHaveBeenCalledWith(
            expect.objectContaining({
                movementType: 'TRANSFER',
            }),
        );
    });
});
