import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { ShipmentCreateDialog } from '@/components/orders/shipment-create-dialog';
import type { SalesOrderLine } from '@/types/orders';

describe('ShipmentCreateDialog', () => {
    const lines: SalesOrderLine[] = [
        {
            id: 1,
            soLineId: 1,
            description: 'Widget',
            qtyOrdered: 10,
            qtyShipped: 8,
            uom: 'EA',
        },
    ];

    it('prevents shipping more than the remaining quantity', async () => {
        const user = userEvent.setup();
        const onSubmit = vi.fn();

        render(
            <ShipmentCreateDialog
                open
                onOpenChange={() => {}}
                lines={lines}
                onSubmit={onSubmit}
                isSubmitting={false}
            />,
        );

        await user.type(screen.getByLabelText(/Carrier/i), 'DHL');
        await user.type(screen.getByLabelText(/Tracking number/i), '1Z999');

        const shipQtyInput = screen.getByRole('spinbutton', {
            name: /ship quantity for line #1/i,
        });
        await user.clear(shipQtyInput);
        await user.type(shipQtyInput, '3');

        await user.click(screen.getByRole('button', { name: /Create shipment/i }));

        expect(await screen.findByText(/Line #1 only has 2 units remaining/i)).toBeInTheDocument();
        expect(onSubmit).not.toHaveBeenCalled();

        await user.clear(shipQtyInput);
        await user.type(shipQtyInput, '2');

        await user.click(screen.getByRole('button', { name: /Create shipment/i }));

        expect(onSubmit).toHaveBeenCalledWith(
            expect.objectContaining({
                carrier: 'DHL',
                trackingNumber: '1Z999',
                lines: [{ soLineId: 1, qtyShipped: 2 }],
            }),
        );
    });
});
