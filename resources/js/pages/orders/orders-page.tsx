import { BuyerOrderListPage } from './buyer-order-list-page';

/**
 * Backwards-compatible entry point used by older imports that expected `OrdersPage`.
 * We now render the full buyer orders experience so navigation links and tests
 * referencing `OrdersPage` pick up the real module instead of a placeholder.
 */
export function OrdersPage() {
    return <BuyerOrderListPage />;
}
