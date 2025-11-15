# Prompt for Copilot: PO Sending, Supplier Acknowledgement & Basic Invoice Handoff

## Goal
Extend the PO workflow so buyers can **send POs to suppliers**, suppliers can **acknowledge or decline**, and finance can perform a **basic invoice handoff** (create/receive invoices tied to POs). Keep it minimal but production-viable. Use **TS SDK + React Query**, **react-hook-form + zod**, **Tailwind + shadcn/ui**, existing **Auth/ApiClient** providers, and respect plan/role gating.

---

## 1) Routes & Files

**Buyer routes**
```tsx
<Route element={<RequireAuth><AppLayout/></RequireAuth>}>
  <Route path="/app/pos" element={<PoListPage />} />
  <Route path="/app/pos/:poId" element={<PoDetailPage />} />
  <Route path="/app/invoices" element={<InvoiceListPage />} />
  <Route path="/app/invoices/:invoiceId" element={<InvoiceDetailPage />} />
</Route>
```

**Supplier routes** (supplier portal or supplier mode)
```tsx
<Route element={<RequireAuth><AppLayout/></RequireAuth>}>
  <Route path="/app/suppliers/pos/:poId" element={<SupplierPoDetailPage />} />
</Route>
```

**Create files**
```
resources/js/components/pos/send-po-dialog.tsx
resources/js/components/pos/ack-status-chip.tsx
resources/js/components/pos/po-activity-timeline.tsx

resources/js/pages/pos/po-detail-page.tsx        // extend with send/ack UI
resources/js/pages/pos/po-list-page.tsx           // extend with ack filters

resources/js/pages/invoices/invoice-list-page.tsx
resources/js/pages/invoices/invoice-detail-page.tsx
resources/js/pages/suppliers/supplier-po-detail-page.tsx

resources/js/hooks/api/pos/use-send-po.ts
resources/js/hooks/api/pos/use-ack-po.ts          // supplier ack/decline
resources/js/hooks/api/pos/use-events.ts          // timeline events
resources/js/hooks/api/invoices/use-invoices.ts
resources/js/hooks/api/invoices/use-invoice.ts
resources/js/hooks/api/invoices/use-create-invoice.ts
resources/js/hooks/api/invoices/use-attach-invoice-file.ts
```

---

## 2) Backend integration (SDK Hooks)

Implement hooks against the OpenAPI TS SDK:

- **PO sending**
  - `useSendPo()` → POST `/pos/:poId/send` with `{ channel: "email" | "webhook", to?: string[], cc?: string[], message?: string }`.
  - Returns delivery id(s). Backend logs delivery event; sets PO status to `sent`.

- **Supplier acknowledgement**
  - `useAckPo()` → POST `/pos/:poId/ack` with `{ decision: "acknowledged" | "declined", reason?: string }` (supplier role).
  - Transitions: `sent → acknowledged` or `sent → declined`. Write audit event.

- **Events (timeline)**
  - `usePoEvents(poId)` → GET `/pos/:poId/events` (created, recalculated, sent, delivery status, supplier ack/decline, invoice posted).

- **Invoices (minimal)**
  - `useInvoices(params)` → list invoices (filters: status, supplier, date range).
  - `useInvoice(id)` → fetch invoice details with lines & attachments.
  - `useCreateInvoice()` → POST `/invoices/from-po` with `{ po_id, invoice_number, invoice_date, currency, lines?: [{ po_line_id, qty_invoiced, unit_price_minor? }] }`.
  - `useAttachInvoiceFile()` → POST `/invoices/:id/attachments` (upload PDF).

All mutations must invalidate related queries (`po`, `pos`, `poEvents`, `invoices`, `invoice`) and surface 401/402/403/422 via toasts + `PlanUpgradeBanner`.

---

## 3) Buyer UI – Send PO & Track Status (PoDetailPage)

**Header CTA group**
- **Send PO** button → opens `SendPoDialog`:
  - Fields: channel (email/webhook), To/CC (email autocomplete), optional message.
  - On confirm → `useSendPo`. Success: toast + push "Sent" event to timeline.
- **Recalculate**, **Cancel**, **Export PDF** remain.

**Ack status display**
- `AckStatusChip` shows: `Draft`, `Sent`, `Acknowledged`, `Declined`.
- Tooltip for last delivery attempt + last supplier response time.

**Timeline**
- `PoActivityTimeline` lists events (delivered, bounced, supplier ack/decline, invoice posted). Use relative + absolute timestamps.

**List page filters**
- Add status filter `ack_status` (`draft/sent/acknowledged/declined`).

---

## 4) Supplier UI – Acknowledge or Decline (SupplierPoDetailPage)

- **Read-only PO header**: PO number, buyer, issue date, totals.
- **Lines table**: part, qty, unit price, extended, promised date.
- **Actions**:
  - **Acknowledge PO** (primary): calls `useAckPo({ decision: "acknowledged"})`.
  - **Decline** (secondary with confirm): `useAckPo({ decision: "declined", reason })`.
- On success: toast + redirect back (or stay) with updated chip. Disable actions after decision.

Role/plan gating: supplier role required; if not enabled, show 403/upgrade banner.

---

## 5) Basic Invoice Handoff

**Buyer side**
- From PoDetailPage, add **Create Invoice** button (if plan allows):
  - Opens a modal or route to `InvoiceCreateFromPo` (inline dialog is fine).
  - Form fields: invoice number (required), invoice date, currency (defaults to PO currency), line items table:
    - Pre-populate from PO lines with remaining uninvoiced qty.
    - Allow editing `qty_invoiced` (<= remaining). If unit price differs, highlight and require confirmation.
  - Submit → `useCreateInvoice({ po_id, ... })` then redirect to InvoiceDetailPage with success toast.
- **InvoiceDetailPage**
  - Header: invoice number, supplier, dates, totals, status (`draft/posted/paid` if supported).
  - Lines: show qty, price, extended, link back to PO line.
  - Attachments: upload PDF via `useAttachInvoiceFile`.
  - Timeline shows “Invoice created from PO” and file upload events.

**List page**
- `InvoiceListPage`: filters (status, supplier, date range), table columns (Invoice #, Supplier, Date, Currency, Total, Status). Pagination + skeleton + empty states.

---

## 6) Validation & Business Rules

- **PO send rules**: Only `draft` or `recalculated` can be sent. After send → status `sent`. Prevent re-send within N minutes unless `force=true` (future enhancement).
- **Acknowledgement**: Only `sent` POs can be ack/declined. One-time irreversible decision (unless buyer cancels and re-sends).
- **Currency & money**: Use `{ amount_minor, currency }`. Money display via shared formatter with FX tooltip if converted.
- **Partial invoicing**: Limit `qty_invoiced` to remaining on each PO line. Disallow over-invoicing.
- **Attachments**: PDFs only for invoice upload; enforce size limit per plan. Store privately with signed URLs.
- **Plan gating**: if `invoices_enabled=false`, hide invoice UI and show upgrade on direct hits. PO send allowed if `po_enabled=true`.

---

## 7) Components (shadcn/ui)

- `SendPoDialog`: Dialog, `Select` for channel, `Input` chips for To/CC, `Textarea` for message, submit/cancel.
- `AckStatusChip`: Badge with variants, tooltip.
- `PoActivityTimeline`: list with icons for each event (send, bounce, ack, decline, invoice).
- `InvoiceLineEditor` (inline in dialog): small table to select `qty_invoiced` per PO line.
- Reuse `MoneyCell`, `DataTable`, `Skeleton`, `Toast` patterns from earlier pages.

Accessibility: proper labels, focus traps, keyboard navigation.

---

## 8) Testing

- Hooks: unit tests for `useSendPo`, `useAckPo`, `useCreateInvoice` (mock SDK).
- Pages: render tests for buyer send flow and supplier ack flow.
- Validation tests: prevent over-invoicing; disallow send from invalid states.
- E2E (optional): auth → create PO (from awards) → send → supplier ack → create invoice.

---

## 9) Acceptance Criteria

- Buyer can **send a PO** (email/webhook) and see delivery events in the timeline.
- Supplier can **acknowledge** or **decline** a sent PO once; status chips update.
- Buyer can create a **basic invoice from a PO**, attach a PDF, and see it in invoice list/detail.
- All network calls use the TS SDK; 401/402/403/422 handled via global logic; plan gating enforced.
- UI is responsive, with skeleton/empty/error states and accessible dialogs.
- Tests cover core hooks and critical UI flows.
