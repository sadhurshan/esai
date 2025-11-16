# Prompt for Copilot: Orders Module (Supplier Sales Orders & Fulfillment Tracking)

## Goal
Introduce an **Orders** module that represents the supplier-facing mirror of buyer POs: when a buyer **creates/sends a PO**, the supplier sees a corresponding **Sales Order (SO)**. Suppliers can **accept/decline**, **partially fulfill**, and **ship** items; buyers can **track order status** and shipment events. Keep it minimal but production-ready and consistent with existing PO/Invoice/GRN flows.

Use **TS SDK + React Query**, **react-hook-form + zod**, **Tailwind + shadcn/ui**, existing **Auth/ApiClient** providers, and plan/role gating. Supplier actions require `company.supplier_status = 'approved'`.

---

## 1) Data & Status Model

**Sales Order (SO)**
- `id`, `so_number`, `po_id` (foreign to buyer PO), `buyer_company_id`, `supplier_company_id`
- `status`: `draft | pending_ack | accepted | partially_fulfilled | fulfilled | cancelled`
- `currency`, `totals`, `issue_date`, `due_date?`, `notes?`
- `shipping`: ship-to, incoterms, carrier preferences (read-only from PO header)

**SO Line**
- `id`, `so_id`, `po_line_id`, `item_id/sku`, `description`, `uom`, `qty_ordered`, `qty_allocated`, `qty_shipped`, `unit_price_minor`, `currency`

**Shipment**
- `id`, `so_id`, `shipment_no`, `status`: `pending | in_transit | delivered | cancelled`
- `carrier`, `tracking_number`, `shipped_at`, `delivered_at?`
- `lines`: [{ `so_line_id`, `qty_shipped` }]

**Acknowledgement**
- `decision`: `accept` or `decline`, `reason?`, `acknowledged_at`

---

## 2) Backend Contracts (SDK)

Map to your OpenAPI (names may vary).

- `useSupplierOrders(params)` → GET `/supplier/orders` with filters: status, buyer, date range.
- `useSupplierOrder(id)` → GET `/supplier/orders/:id` (header + lines + shipments + audit timeline).
- `useAckOrder()` → POST `/supplier/orders/:id/ack` with `{ decision: "accept"|"decline", reason? }` → transitions: `pending_ack → accepted|cancelled`.
- `useCreateShipment()` → POST `/supplier/orders/:id/shipments` with `{ carrier, tracking_number, shipped_at, lines:[{ so_line_id, qty_shipped }] }`.
- `useUpdateShipmentStatus()` → POST `/supplier/shipments/:id/status` with `{ status: "in_transit"|"delivered" }` and `delivered_at` if delivered.
- (Optional) `useCancelOrder()` → POST `/supplier/orders/:id/cancel` (guarded; only before any shipment).
- Buyer tracking:
  - `useBuyerOrders(params)` → GET `/buyer/orders` (proxy of own POs with shipment rollups).
  - `useBuyerOrder(id)` → GET `/buyer/orders/:id` (view-only SO mirror + shipments).

All mutations: invalidate (`supplierOrders`, `supplierOrder`, `buyerOrders`, `buyerOrder`, `po`). Surface 401/402/403/422 with toasts + `PlanUpgradeBanner`.

---

## 3) Routes & Files

**Supplier workspace**
```tsx
<Route element={<RequireAuth><AppLayout/></RequireAuth>}>
  <Route path="/app/supplier/orders" element={<SupplierOrderListPage/>} />
  <Route path="/app/supplier/orders/:soId" element={<SupplierOrderDetailPage/>} />
</Route>
```

**Buyer tracking**
```tsx
<Route element={<RequireAuth><AppLayout/></RequireAuth>}>
  <Route path="/app/orders" element={<BuyerOrderListPage/>} />
  <Route path="/app/orders/:soId" element={<BuyerOrderDetailPage/>} />
</Route>
```

**Create files**
```
resources/js/pages/orders/supplier-order-list-page.tsx
resources/js/pages/orders/supplier-order-detail-page.tsx
resources/js/pages/orders/buyer-order-list-page.tsx
resources/js/pages/orders/buyer-order-detail-page.tsx

resources/js/components/orders/order-status-badge.tsx
resources/js/components/orders/shipment-create-dialog.tsx
resources/js/components/orders/shipment-status-chip.tsx
resources/js/components/orders/order-lines-table.tsx
resources/js/components/orders/order-timeline.tsx

resources/js/hooks/api/orders/use-supplier-orders.ts
resources/js/hooks/api/orders/use-supplier-order.ts
resources/js/hooks/api/orders/use-ack-order.ts
resources/js/hooks/api/orders/use-create-shipment.ts
resources/js/hooks/api/orders/use-update-shipment-status.ts

resources/js/hooks/api/orders/use-buyer-orders.ts
resources/js/hooks/api/orders/use-buyer-order.ts
```

---

## 4) Supplier UI

### SupplierOrderListPage
- Filters: status (chips), buyer, date range.
- Columns: SO #, Buyer, Issue Date, Currency, Total, Fulfillment %, Status, Actions.

### SupplierOrderDetailPage
- Header: SO #, Buyer chip, `OrderStatusBadge`, totals, issue date.
- Tabs:
  1) **Lines** (`OrderLinesTable`): show qty ordered/allocated/shipped; totals footer.
  2) **Shipments**: list shipments; button **Create Shipment** → `ShipmentCreateDialog`.
  3) **Timeline**: ack/ship/deliver events.
- Actions:
  - **Acknowledge** (accept/decline) if status `pending_ack`.
  - **Create Shipment**: choose lines + quantities (enforce ≤ remaining), carrier + tracking, `shipped_at`. On save, toast + update fulfillment.
  - **Mark In Transit** / **Mark Delivered** via `useUpdateShipmentStatus`.

Validation:
- Prevent shipping > remaining.
- Carrier + tracking required on create (or allow “manual” carrier).
- Delivered requires `delivered_at`.

---

## 5) Buyer UI (Tracking)

### BuyerOrderListPage
- Mirrors POs but shows fulfillment rollup: Fulfillment %, Shipments count, Last event.
- Filters: supplier, status (derived), date.

### BuyerOrderDetailPage
- Read-only view of SO header/lines.
- Shipments tab: tracking numbers with outbound links; status chips (in transit / delivered).
- Timeline (read-only).

---

## 6) Status & Business Rules

- **SO lifecycle**: `pending_ack → accepted → (partially_fulfilled)* → fulfilled`. Decline/cancel stops shipments.
- **Partial shipments** allowed; fulfillment % = shipped_qty / ordered_qty.
- **Sync with PO**: when shipments mark delivered, buyer GRN flow may be triggered separately (you already have GRN). This module does **not** auto-post GRN, only informs buyer.
- **Permissions**: supplier actions require `supplier_status='approved'`; buyer pages require membership in buyer tenant.
- **Numbering**: respect document numbering helpers for SO `so_number`.
- **Notifications**: optional to emit events on ack/ship/deliver (hooks exist in Notifications module).

---

## 7) Components & UX

- `OrderStatusBadge`: maps lifecycles; colors accessible.
- `ShipmentCreateDialog`: RHF + zod; multi-line picker with remaining qty hints; date/time pickers.
- Tables: sticky headers, responsive, skeleton/empty states.
- Tooltips for fulfillment %, with numerator/denominator breakdown.

---

## 8) Tests

- Hooks: unit tests for `useAckOrder`, `useCreateShipment`, `useUpdateShipmentStatus` (mock SDK).
- Pages: render tests for acknowledge + create shipment happy path; prevent over-ship.
- Cross-module: ensure buyer tracking reflects supplier actions.

---

## 9) Acceptance Criteria

- Supplier can view orders (SO) sourced from buyer POs, acknowledge, and create shipments.
- Partial shipments supported; status/fulfillment updates correctly.
- Buyer can track orders and shipments read-only.
- Permissions enforced; supplier features available only when approved.
- All API calls via TS SDK; errors handled; responsive UI with skeleton/empty/error states.
