Quotes – List, Detail, Submit/Withdraw & Revisions (React + TS)

Goal

Implement Quotes for both sides:

a. Buyer (your app’s logged-in user): view quotes for an RFQ, compare quotes, view revisions, mark candidates for award.
b. Supplier (acting in supplier workspace or impersonation): create/submit quote, revise, withdraw.

Use our TS SDK + React Query, react-hook-form + zod, Tailwind + shadcn/ui, and existing Auth/ApiClient providers. Respect plan/role gating with useAuth().

1. Routes & files

    a. Buyer routes
    <Route element={<RequireAuth><AppLayout/></RequireAuth>}>
        <Route path="/app/rfqs/:rfqId/quotes" element={<QuoteListPage />} />
        <Route path="/app/quotes/:quoteId" element={<QuoteDetailPage />} />
    </Route>

    b. Supplier routes (if you have a supplier workspace URL like /supplier/*; otherwise feature-flag a supplier mode inside the same app)
    <Route element={<RequireAuth><AppLayout/></RequireAuth>}>
        <Route path="/app/suppliers/rfqs/:rfqId/quotes/new" element={<SupplierQuoteCreatePage />} />
        <Route path="/app/suppliers/quotes/:quoteId" element={<SupplierQuoteEditPage />} />
    </Route>

    c. Create files
    resources/js/pages/quotes/quote-list-page.tsx
    resources/js/pages/quotes/quote-detail-page.tsx
    resources/js/pages/quotes/supplier-quote-create-page.tsx
    resources/js/pages/quotes/supplier-quote-edit-page.tsx

    resources/js/components/quotes/*
    - quote-status-badge.tsx
    - quote-line-editor.tsx
    - quote-compare-table.tsx
    - money-cell.tsx (shared money display)
    - delivery-leadtime-chip.tsx
    - withdraw-confirm-dialog.tsx

    resources/js/hooks/api/quotes/*
    - use-quotes.ts (list with filters)
    - use-quote.ts
    - use-create-quote.ts
    - use-submit-quote.ts
    - use-withdraw-quote.ts
    - use-revise-quote.ts
    - use-quote-lines.ts (CRUD)

2. Hooks (SDK-backed) – wire to your API

    a. useQuotes(params) → list quotes; filters: rfqId, supplierId, status, page, perPage, sort.
    b. useQuote(quoteId) → fetch a single quote (includes supplier, totals, lines, attachments, revision history).
    c. Mutations:
        - useCreateQuote() → create draft quote for RFQ (supplier role).
        - useSubmitQuote() → submit a draft → status submitted.
        - useWithdrawQuote() → withdraw a submitted quote (reason required) → status withdrawn.
        - useReviseQuote() → create a new revision (cloned from previous) → status submitted with incremented rev_no.
        - Line mutations: useAddQuoteLine, useUpdateQuoteLine, useDeleteQuoteLine.
    d. All mutations: invalidate related queries (quotes, quote, rfqQuotes) and surface 402/403/422 via toasts + PlanUpgradeBanner.

3. Buyer – Quote List & Detail

    a. QuoteListPage (/app/rfqs/:rfqId/quotes)
        - Filters: supplier, status (submitted,withdrawn,expired), price range, lead time range.
        - Table columns: Supplier, Total (Money), Currency, Lead Time, Submitted At, Revisions, Status, Actions.
        - Actions:
            i. Open detail
            ii. "Shortlist" toggle (local tag to prep award)
        - Compare mode:
            i. Multi-select rows → “Compare” → open QuoteCompareTable drawer with normalized columns (price per line, totals, lead times). Handle mixed currencies via your Money helpers (show converted to company currency with a tooltip for original).
    
    b. QuoteDetailPage (/app/quotes/:quoteId)
        - Header: Supplier name, status badge, total, currency, lead time; buttons:
            i. Buyer actions: “Shortlist”, “Mark for Award” (doesn’t award yet), “Export PDF” (stub).
        - Tabs:
            i. Overview: totals, incoterms/payment if present, submitted/updated timestamps.
            ii. Lines: table of quoted lines (part, qty, unit price, currency, extended, promised date). If UoM enabled, show original vs base UoM.
            iii. Attachments: list/download; supplier-uploaded docs.
            iv. Revisions: list of revs with diff highlights (total, lines changed).
            v. Timeline/Audit: submitted, revised, withdrawn events.

4. Supplier – Create / Edit / Submit / Withdraw

    a. SupplierQuoteCreatePage (/app/suppliers/rfqs/:rfqId/quotes/new)
        - Steps (simpler than RFQ wizard):
            i. Company & contact autofill (read-only).
            ii. Lines: For each RFQ line → input unit price (money), lead time, optional notes. Provide bulk actions (set same lead time/discount across lines).
            iii. Attachments: upload/specs.
            iv. Review & Submit: totals preview; button Submit Quote (calls useSubmitQuote).
        - Validate with zod: positive money, allowed currencies, lead time ≥ 0, promised date ≥ today if provided.
        - On success → redirect to buyer-side Quote Detail (or supplier view) with toast.

    b. SupplierQuoteEditPage (/app/suppliers/quotes/:quoteId)
        - If status = submitted: show Withdraw button (requires reason) → useWithdrawQuote.
        - Provide Revise button → prefill lines in an editor and call useReviseQuote on submit.
        - Lock editing for statuses that disallow changes.

5. UI bits

    a. QuoteStatusBadge: Draft / Submitted / Withdrawn / Expired.
    b. MoneyCell: Formats {amount_minor, currency} using company currency; tooltip with original and FX info if converted.
    c. QuoteCompareTable: columns for each selected quote; rows per RFQ line; shows unit price, extended, lead time; footer with totals.
    d. WithdrawConfirmDialog: text area for reason; disables confirm until provided.

All components: accessible (labels, aria), responsive, skeletons for loading.

6. Plan/role gating

    a. Buyer pages require buyer role; supplier pages require supplier role (or a feature flag like supplier_portal_enabled).
    b. If plan lacks quotes_enabled, hide menu entries and show upgrade banner on direct URL hit (handle HTTP 402 gracefully).

7. Types & validation

    a. Use SDK types for Quote/QuoteLine.
    b. zod schemas:
        - QuoteLineInput: { unit_price_minor:number, currency:string, lead_time_days:number, note?:string }.
        - Top-level quote: allowed currencies; consistent currency across lines unless backend supports mixed + per-line conversions.

8. Tests

    a. Hooks: unit tests for useSubmitQuote, useWithdrawQuote, useReviseQuote (mock SDK).
    b. Pages: basic render and action tests for create/submit and withdraw flows.
    c. Compare table: snapshot or DOM checks for aligned columns and totals.

9. Acceptance Criteria

    a. Buyer
        - /app/rfqs/:rfqId/quotes lists quotes with filters and compare mode.
        - /app/quotes/:quoteId shows detail with tabs, revision history and attachments.
    b. Supplier
        - Can create a draft, fill lines, attach files, and Submit.
        - Can Withdraw a submitted quote (requires reason).
        - Can Revise a quote, creating a new revision visible in buyer’s “Revisions”.
    c. All network calls through TS SDK; 401/402/403 handled via global handlers.
    d. Money/UoM displays are correct; FX tooltip shown when conversions applied.
    e. Responsive UI, skeleton/empty/error states implemented.



