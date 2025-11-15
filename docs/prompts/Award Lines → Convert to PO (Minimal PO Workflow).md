Award Lines → Convert to PO (Minimal PO Workflow)

## Goal
Enable buyers to **award RFQ line(s)** to a selected quote/supplier and **convert the award into a Purchase Order**. Build minimal but production-viable PO views and actions. Use our **TS SDK + React Query**, **react-hook-form + zod**, **Tailwind + shadcn/ui**, and existing **Auth/ApiClient** providers. Respect plan/role gating.

---

## 1) Routes & Files

**Buyer routes**
```tsx
<Route element={<RequireAuth><AppLayout/></RequireAuth>}>
  <Route path="/app/rfqs/:rfqId/awards" element={<AwardReviewPage />} />
  <Route path="/app/pos" element={<PoListPage />} />
  <Route path="/app/pos/:poId" element={<PoDetailPage />} />
</Route>
```

**Create files**
```
resources/js/pages/awards/award-review-page.tsx
resources/js/pages/pos/po-list-page.tsx
resources/js/pages/pos/po-detail-page.tsx

resources/js/components/awards/*
  - award-line-picker.tsx
  - award-summary-card.tsx
  - convert-to-po-dialog.tsx

resources/js/components/pos/*
  - po-status-badge.tsx
  - po-line-table.tsx
  - po-header-card.tsx

resources/js/hooks/api/awards/*
  - use-rfq-award-candidates.ts (quotes + lines flattened for picking)
  - use-create-awards.ts
  - use-delete-award.ts

resources/js/hooks/api/pos/*
  - use-pos.ts (list)
  - use-po.ts (detail)
  - use-create-po.ts (from award-lines)
  - use-recalc-po.ts
  - use-cancel-po.ts (optional)
```

---

## 2) Backend integration (SDK Hooks)

Implement hooks against our OpenAPI-generated SDK:

- **Awards**
  - `useRfqAwardCandidates(rfqId)` → loads RFQ lines, shortlisted quotes, best price per line, currency info.
  - `useCreateAwards()` → POST `/awards` with `{ rfq_id, items:[{ rfq_line_id, quote_id, awarded_qty }] }`. Server closes RFQ lines as awarded.
  - `useDeleteAward()` → DELETE `/awards/:id` (reopens line if no other award exists).

- **POs**
  - `usePos(params)` → GET POs with filters: status, supplier, date range.
  - `usePo(poId)` → GET PO detail (header, supplier, ship/bill to, totals, lines).
  - `useCreatePo()` → POST `/pos/from-awards` with selected award ids → returns `{ po_id }`.
  - `useRecalcPo()` → POST `/pos/:poId/recalculate`.
  - `useCancelPo()` → POST `/pos/:poId/cancel` (optional if supported).

All mutations should invalidate relevant queries (`rfq`, `awards`, `quotes`, `pos`, `po`) and surface 402/403/422 via toasts + `PlanUpgradeBanner`.

---

## 3) AwardReviewPage (`/app/rfqs/:rfqId/awards`)

**Header**
- RFQ breadcrumb, RFQ title/number, status badge.
- CTA: **Convert to PO** (disabled until at least one award exists).

**Body layout**
- **Left pane: AwardLinePicker**
  - For each RFQ line: show candidate quotes (supplier, unit price money, currency, lead time).
  - Radio/select to choose winning quote for the line.
  - Quantity control (default = RFQ qty, allow partial if backend supports).
  - Show **MoneyCell** with converted company currency (tooltip original currency/FX).
- **Right pane: AwardSummaryCard**
  - Selected supplier(s), subtotals per supplier, currencies, lead time ranges.
  - Validation errors (e.g., mixed currency per PO if not allowed; missing selections).
  - Button: **Create Awards** → `useCreateAwards` (persist picks).
  - When persisted, show a success toast and enable **Convert to PO**.

**States**
- Loading skeletons, empty state when no shortlisted quotes.
- Error handling (toasts) with retry.
- Plan gating: hide page if `po_enabled=false` but allow awards; converting to PO should return 402 → raise upgrade banner.

---

## 4) Convert-to-PO flow

**ConvertToPoDialog**
- Lists suppliers implied by awards (group awards by supplier).
- Option A (simplest, recommended): **1 supplier → 1 PO**, multiple suppliers → **generate 1 PO per supplier** in a single action.
- Show header fields preview: Supplier, Ship-To, Bill-To (read from company profile), currency (company currency), payment terms.
- Confirm → `useCreatePo({ award_ids: [...] })`. On success: redirect to first created PO detail with success toast.

---

## 5) PO Pages

**PoListPage (`/app/pos`)**
- Filters: status (`draft/sent/acknowledged/cancelled`), supplier, date range.
- Table columns: PO No, Supplier, Issue Date, Currency, Total, Status, Actions (open detail).
- Pagination, loading skeleton, empty state.

**PoDetailPage (`/app/pos/:poId`)**
- **HeaderCard**: PO number, supplier chip, status badge, totals (Money), issue date.
- Actions:
  - **Recalculate** → `useRecalcPo` (re-price/taxes/discounts).
  - **Send to supplier** (stub or integrate email/webhook later).
  - **Cancel PO** (optional) → `useCancelPo` with confirm dialog.
  - **Export PDF** (stub).
- **Lines Table**:
  - Columns: Part/Description, Qty + UoM, Unit Price (Money), Tax, Extended (Money), Promised date/Lead time.
  - Totals footer (subtotal, tax, grand total).
- **Meta**:
  - Ship/Bill-To addresses, payment terms, incoterms if present.

**States/A11y**
- Skeletons, empty, error toasts; keyboard focus management on dialogs; responsive layout.

---

## 6) Validation & Business Rules

- **Currency**: If POs must be single-currency, prevent mixed supplier-currency groupings at convert time and show guidance.
- **Money**: Use `{amount_minor, currency}`; display formatted money with tooltip for FX conversion.
- **UoM**: Lines display in base UoM; if RFQ/Quote used alternative UoM, show both (small caption).
- **Quantities**: Enforce positive integer, not exceeding available RFQ line qty (if partial awards allowed).
- **Plan/roles**: Buyer role required; supplier cannot access buyer PO pages.

---

## 7) Testing

- Hooks: unit tests for `useCreateAwards`, `useCreatePo`, `useRecalcPo` (mock SDK).
- Pages: render tests verifying selection → create awards → convert to PO happy path.
- Edge tests: mixed currency prevention, 402 upgrade handling, 403 policy errors.

---

## 8) Acceptance Criteria

- Buyer can select winning quotes per RFQ line and **persist awards**.
- **Convert to PO** creates POs (1 per supplier) and redirects to PO detail with success toast.
- PO list shows newly created PO(s) with correct totals/status.
- PO detail allows **recalculate** (and optional cancel), displays header, lines, totals, and meta.
- All network calls via TS SDK; errors handled globally; plan gating enforced.
- UI is responsive, with skeleton/empty/error states and accessible dialogs.

---

**Next up after this feature:** PO sending & supplier acknowledgement + basic invoice handoff.
