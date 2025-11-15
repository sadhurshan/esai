# Prompt for Copilot: Goods Receipt (GRN) & 3‑Way Match (PO ↔ Receipt ↔ Invoice) + Credit Notes

## Goal
Add **receiving** and **reconciliation** so operations can record Goods Receipt Notes (GRNs), finance can **3‑way match** PO vs. Receipt vs. Invoice, and issue **Credit Notes** for discrepancies. Keep it minimal but production‑viable. Use **TS SDK + React Query**, **react-hook-form + zod**, **Tailwind + shadcn/ui**, existing **Auth/ApiClient** providers, and respect plan/role gating.

---

## 1) Routes & Files

```tsx
<Route element={<RequireAuth><AppLayout/></RequireAuth>}>
  {/* Receiving */}
  <Route path="/app/receiving" element={<ReceivingListPage />} />
  <Route path="/app/receiving/new" element={<ReceivingCreatePage />} />
  <Route path="/app/receiving/:grnId" element={<ReceivingDetailPage />} />

  {/* Matching & Finance */}
  <Route path="/app/matching" element={<MatchWorkbenchPage />} />

  {/* Credit Notes */}
  <Route path="/app/credit-notes" element={<CreditNoteListPage />} />
  <Route path="/app/credit-notes/:creditId" element={<CreditNoteDetailPage />} />
</Route>
```

**Create files**
```
resources/js/pages/receiving/receiving-list-page.tsx
resources/js/pages/receiving/receiving-create-page.tsx
resources/js/pages/receiving/receiving-detail-page.tsx

resources/js/pages/matching/match-workbench-page.tsx

resources/js/pages/credits/credit-note-list-page.tsx
resources/js/pages/credits/credit-note-detail-page.tsx

resources/js/components/receiving/grn-line-editor.tsx
resources/js/components/receiving/grn-status-badge.tsx
resources/js/components/matching/match-summary-card.tsx
resources/js/components/matching/discrepancy-badge.tsx
resources/js/components/credits/credit-line-editor.tsx

resources/js/hooks/api/receiving/use-grns.ts
resources/js/hooks/api/receiving/use-grn.ts
resources/js/hooks/api/receiving/use-create-grn.ts
resources/js/hooks/api/receiving/use-attach-grn-file.ts

resources/js/hooks/api/matching/use-match-candidates.ts
resources/js/hooks/api/matching/use-3way-match.ts

resources/js/hooks/api/credits/use-credit-notes.ts
resources/js/hooks/api/credits/use-credit-note.ts
resources/js/hooks/api/credits/use-create-credit-note.ts
resources/js/hooks/api/credits/use-attach-credit-file.ts
```

---

## 2) Backend integration (SDK Hooks)

Implement hooks against the OpenAPI TS SDK (names may vary; map to your endpoints):

- **Receiving (GRN)**
  - `useGrns(params)` → GET list: filters `status`, `poId`, `supplier`, `date range`.
  - `useGrn(grnId)` → GET GRN detail (header, lines, attachments, status).
  - `useCreateGrn()` → POST `/grns` with `{ po_id, lines:[{ po_line_id, qty_received, uom? }], received_at, notes? }`.
  - `useAttachGrnFile()` → POST `/grns/:id/attachments` (upload scan/photo).
  - Status flow: `draft → posted`. Posting should update **received qty** on PO lines (server‑side).

- **3‑Way Match**
  - `useMatchCandidates(params)` → GET rollup dataset for workbench: join **PO**, **Receipts**, **Invoices** by supplier/PO.
  - `use3WayMatch()` → POST `/matching/resolve` with decisions: `{ invoice_id, po_id, grn_ids:[], resolutions:[ { type:"price|qty|uom", status:"accept|reject|credit", notes? } ] }`.
  - Server records decisions and emits audit events.

- **Credit Notes**
  - `useCreditNotes(params)` → list credit notes (filters `status`, `supplier`, `date`).
  - `useCreditNote(id)` → detail with lines, references to invoice/po lines.
  - `useCreateCreditNote()` → POST `/credits/from-invoice` with `{ invoice_id, reason, lines:[{ invoice_line_id, qty, unit_price_minor? }] }`.
  - `useAttachCreditFile()` → POST `/credits/:id/attachments` (PDF).

All mutations invalidate relevant queries (`po`, `invoice`, `grn`, `matching`, `credits`) and surface 401/402/403/422 via toasts + `PlanUpgradeBanner`.

---

## 3) Receiving UI

### ReceivingListPage (`/app/receiving`)
- Filters: status (`draft/posted`), supplier, PO, date range.
- Table: GRN #, PO #, Supplier, Received At, Lines (count), Status, Actions.
- Pagination, skeleton, empty state.

### ReceivingCreatePage (`/app/receiving/new`)
- **Select PO** (autocomplete) → loads PO header + lines not fully received.
- **GRN lines table** using `GrnLineEditor`:
  - For each PO line show: Part/Desc, Ordered Qty, Previously Received, **Qty Received (input)**, UoM.
  - Validation: qty_received ≥ 0 and ≤ remaining; UoM conversions if enabled.
- **Attachments**: upload delivery note/photo via `useAttachGrnFile` after draft created.
- Actions: **Save Draft** → `useCreateGrn` (draft), **Post GRN** (finalize) → server updates PO received qty.
- Success: toast + redirect to GRN detail.

### ReceivingDetailPage
- Header: GRN #, PO #, Supplier, Received At, Status badge.
- Lines: read‑only table with totals; show over/short flags if any.
- Attachments list; download; upload more (if allowed).
- Timeline (optional): created, posted, files added.

---

## 4) 3‑Way Match Workbench (`/app/matching`)

- **Data grid** grouped by PO (or Supplier → PO):
  - Columns per PO: PO Total, **Received** (sum of GRNs), **Invoiced**, **Variance** (qty/price), Status chip.
- **Row expand** → line‑level view with discrepancy badges:
  - Price variance, Qty variance, UoM mismatch.
- **Right drawer: MatchSummaryCard**
  - Shows detected variances and lets user **Resolve** each:
    - Accept variance (write‑off), Request **Credit Note**, or Mark as **Pending**.
  - Submit → `use3WayMatch` → success toast, refresh rollup.
- **Filters**: supplier, status (`clean/variance/pending/resolved`), date range.

UX: skeletons, error toasts, accessible keyboard navigation.

---

## 5) Credit Notes

### CreditNoteListPage
- Filters: status (`draft/posted`), supplier, date range.
- Table: Credit #, Supplier, Date, Currency, Total, Status.

### CreditNoteDetailPage
- Header: Credit #, Supplier, Totals, Status.
- Lines table using `CreditLineEditor`:
  - Defaulted from invoice lines with variance (if launched from workbench).
  - Validate qty ≤ invoiced − already‑credited.
- Attachments tab: upload PDF via `useAttachCreditFile`.
- Actions: **Post Credit Note** (finalize), **Export PDF** (stub).

---

## 6) Validation & Business Rules

- **Receiving**
  - Cannot receive more than remaining unless role has override; if override, flag as variance.
  - Respect UoM/base UoM conversions. Store raw + normalized qty.
- **3‑Way Match**
  - Variance = (Invoiced − Received) for qty, and (Invoice Unit Price − PO Unit Price) for price.
  - Mixed currency: normalize via company currency for summaries; retain per‑line currency for detail.
  - Decisions immutable after posting; allow reopen only with admin role (future).
- **Credits**
  - Credit currency must match invoice currency (simplest). If not, block and explain.
  - Totals must equal sum of credit lines; negative amounts disallowed.
- **Plan gating**
  - Receiving requires `inventory_enabled`; Matching & Credits require `finance_enabled`.
  - On HTTP 402 from any action, show upgrade banner and keep user’s form state.

---

## 7) Components

- `GrnLineEditor` – inputs with per‑line validation and remaining qty hint.
- `GrnStatusBadge` – Draft/Posted.
- `DiscrepancyBadge` – Price/QTY/UoM types.
- `MatchSummaryCard` – variance list + resolution radio/select + notes.
- `CreditLineEditor` – lines with qty, price, extended money, and per‑line remaining to credit.
- Reuse `MoneyCell`, `DataTable`, `Skeleton`, `Toast`, `FileUploader` patterns.

Accessibility: form labels, aria‑describedby for validation text, focus traps in drawers/dialogs.

---

## 8) Testing

- Hooks: unit tests for `useCreateGrn`, `use3WayMatch`, `useCreateCreditNote` (mock SDK).
- Pages: render tests for receiving (qty validation) and matching (resolution submit).
- Edge cases: over‑receipts blocked; mixed currency blocked in credits; 402 upgrade flow preserved.
- (Optional) E2E happy path: PO → GRN → Invoice → Match → Credit.

---

## 9) Acceptance Criteria

- Users can create/post a **GRN** from a PO; PO received quantities update.
- **Match Workbench** summarizes PO vs Receipt vs Invoice; variances can be resolved or sent to Credit.
- Users can create and post **Credit Notes** (from invoice variances), with PDF attachments.
- All network calls go through the TS SDK; errors handled globally; plan gating enforced.
- UI is responsive with skeleton/empty/error states; a11y respected.
