# Prompt for Copilot: Inventory – Item Master, Stock Movements & Low‑Stock Alerts

## Goal
Implement a minimal but production‑ready **Inventory** module so operations can manage the **Item Master**, track **on‑hand** via stock **Movements** (Receipt / Issue / Transfer / Adjust), and surface **Low‑Stock Alerts**. Use **TS SDK + React Query**, **react-hook-form + zod**, **Tailwind + shadcn/ui**, our existing **Auth/ApiClient** providers, and enforce **plan/role gating**.

---

## 1) Routes & Files

```tsx
<Route element={<RequireAuth><AppLayout/></RequireAuth>}>
  {/* Inventory */}
  <Route path="/app/inventory/items" element={<ItemListPage />} />
  <Route path="/app/inventory/items/new" element={<ItemCreatePage />} />
  <Route path="/app/inventory/items/:itemId" element={<ItemDetailPage />} />

  <Route path="/app/inventory/movements" element={<MovementListPage />} />
  <Route path="/app/inventory/movements/new" element={<MovementCreatePage />} />
  <Route path="/app/inventory/movements/:moveId" element={<MovementDetailPage />} />

  {/* Alerts */}
  <Route path="/app/inventory/alerts" element={<LowStockAlertPage />} />
</Route>
```

**Create files**
```
resources/js/pages/inventory/items/item-list-page.tsx
resources/js/pages/inventory/items/item-detail-page.tsx
resources/js/pages/inventory/items/item-create-page.tsx

resources/js/pages/inventory/movements/movement-list-page.tsx
resources/js/pages/inventory/movements/movement-detail-page.tsx
resources/js/pages/inventory/movements/movement-create-page.tsx

resources/js/pages/inventory/alerts/low-stock-alert-page.tsx

resources/js/components/inventory/item-status-chip.tsx
resources/js/components/inventory/reorder-editor.tsx
resources/js/components/inventory/movement-line-editor.tsx
resources/js/components/inventory/location-select.tsx
resources/js/components/inventory/stock-badge.tsx

resources/js/hooks/api/inventory/use-items.ts
resources/js/hooks/api/inventory/use-item.ts
resources/js/hooks/api/inventory/use-create-item.ts
resources/js/hooks/api/inventory/use-update-item.ts

resources/js/hooks/api/inventory/use-movements.ts
resources/js/hooks/api/inventory/use-movement.ts
resources/js/hooks/api/inventory/use-create-movement.ts

resources/js/hooks/api/inventory/use-low-stock.ts
resources/js/hooks/api/inventory/use-locations.ts   // sites / bins
resources/js/hooks/api/inventory/use-uoms.ts        // optional
```
Keep folders consistent with your codebase.

---

## 2) Backend Integration (SDK Hooks)

Wire to the OpenAPI TS SDK (names can vary; map accordingly):

- **Items**
  - `useItems(params)` → list (filters: sku, name, category, status, site).
  - `useItem(itemId)` → item detail (attributes, default UoM, reorder rules, on‑hand by location, attachments).
  - `useCreateItem()` → POST `/inventory/items` with `{ sku, name, uom, category?, min_stock?, reorder_qty?, lead_time_days?, active }`.
  - `useUpdateItem()` → PATCH `/inventory/items/:id`.

- **Movements**
  - `useMovements(params)` → list movements (filters: type, item, location, date).
  - `useMovement(moveId)` → movement detail.
  - `useCreateMovement()` → POST `/inventory/movements` with payload:
    ```ts
    {
      type: "RECEIPT" | "ISSUE" | "TRANSFER" | "ADJUST",
      lines: [{
        item_id: string,
        qty: number,
        uom?: string,
        from_location_id?: string,   // for ISSUE / TRANSFER
        to_location_id?: string,     // for RECEIPT / TRANSFER
        reason?: string              // for ADJUST
      }],
      reference?: { source?: "PO" | "SO" | "MANUAL", id?: string },
      moved_at: string // ISO
    }
    ```
  - Server must update stock ledger and per‑location on‑hand. Return new balances.

- **Low‑Stock**
  - `useLowStock(params)` → GET items below `min_stock` (optionally by location).
  - Optional: `useSubscribeLowStock()` → POST webhook/email subscriptions.

Invalidate related queries after mutations (`items`, `item`, `movements`, `lowStock`). Surface 401/402/403/422 via toasts + `PlanUpgradeBanner`.

---

## 3) UI – Item Master

### ItemListPage
- Filters: text (sku/name), category, active, below‑min only.
- Table: SKU, Name, Category, Default UoM, On‑Hand (total), Sites (count), Status (active/inactive).
- Actions: open detail, quick toggle active.

### ItemDetailPage
- Header: SKU + name, status chip, stock badge (total on‑hand).
- Tabs:
  1. **Overview**: attributes, category, default UoM.
  2. **Stock by Location**: table per site/bin with on‑hand, reserved, available.
  3. **Reorder Rules**: show/edit `min_stock`, `reorder_qty`, `lead_time_days` (use `ReorderEditor`).
  4. **Attachments**: upload/download (if endpoints exist).
  5. **Movements**: recent ledger entries for the item.
- Save via `useUpdateItem`. Validation with `zod` (e.g., min_stock ≥ 0).

### ItemCreatePage
- Form: sku (required, unique), name, default UoM, category, min_stock, reorder_qty, lead_time_days, active.
- On save → redirect to detail + toast.

---

## 4) UI – Movements

### MovementCreatePage
- Step 1: **Type selector** (Receipt / Issue / Transfer / Adjust).
- Step 2: **MovementLineEditor**:
  - Add multiple lines; each line has item select (autocomplete), qty, UoM, location(s), reason (for ADJUST).
  - For TRANSFER: require both `from_location` and `to_location`. Prevent same location.
  - For ISSUE: require `from_location`.
  - For RECEIPT: require `to_location` (preselect default receiving bin).
- Validation:
  - qty > 0; integer if your items are discrete.
  - For ISSUE/TRANSFER, block qty > available (unless role has override → show warning chip).
- Submit → `useCreateMovement` → success toast + redirect to MovementDetailPage.

### MovementListPage
- Filters: type, item, location, date range.
- Table: Movement #, Type, Lines (count), From → To, Moved At, Reference, Actions.
- Empty/skeleton states; pagination.

### MovementDetailPage
- Header: Movement #, Type, Moved At, From/To summary.
- Lines: read‑only; show resulting balances where available.
- Timeline (optional): created, posted, edited.

---

## 5) UI – Low‑Stock Alerts

### LowStockAlertPage
- Filters: site/location, category.
- Card/grid or table: Item, On‑Hand, Min, Reorder Qty, Lead Time, Suggested Reorder Date.
- **Actions**:
  - “Create RFQ” shortcut → deep link to RFQ wizard prefilled with selected items.
  - (Optional) Subscribe to alerts (email/webhook) if backend supports.
- Empty/skeleton states.

---

## 6) Validation & Business Rules

- **UoM**: Display default UoM; if alt UoM entered, show converted preview; send both raw/normalized as your API expects.
- **Stock integrity**: Prevent negative on‑hand on ISSUE/TRANSFER unless override; show warning and require confirmation.
- **Locations**: Support Sites / Bins; if only one site, hide site selector to simplify.
- **Reorder**: `min_stock` and `reorder_qty` must be ≥ 0. Suggested reorder date may be today + `lead_time_days` for below‑min items.
- **Plan gating**: Inventory screens require `inventory_enabled`. On HTTP 402 → upgrade banner.

---

## 7) Components & UX

- `item-status-chip`, `stock-badge`, `reorder-editor`, `movement-line-editor`, `location-select`.
- Reuse `MoneyCell` where prices display (optional).
- Full a11y: labels, aria‑describedby for validation, keyboardable dialogs.
- Responsive layouts; use skeletons and empty states.

---

## 8) Testing

- Hooks: unit tests for `useCreateItem`, `useUpdateItem`, `useCreateMovement`, `useLowStock` (mock SDK).
- Pages: render tests for MovementCreate (validation, TRANSFER rules), ItemCreate (required fields).
- Edge cases: prevent negative stock, enforce transfer location rules, 402 plan flow.

---

## 9) Acceptance Criteria

- Item master: create/update items; view stock by location; manage reorder rules.
- Movements: create Receipt/Issue/Transfer/Adjust; ledger and balances update server‑side.
- Low‑stock page shows items below min; can navigate to RFQ wizard with prefilled items.
- All API calls via TS SDK; errors handled globally; plan gating enforced.
- UI responsive with proper loading/empty/error states and accessible controls.
