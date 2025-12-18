Quote Comparison & Awarding (Line-Item, Split Awards)


Examine the quote comparison and awarding flows:

- ✅ Comparison view shows multiple quotes line-by-line with price, lead time, rating, tax, incoterm, and payment metadata surfaced in `resources/js/components/quotes/quote-compare-table.tsx` (comparison drawer) and `resources/js/pages/quotes/quote-detail-page.tsx`, backed by the `2025_12_06_010500_add_commercial_terms_to_quotes_table.php` migration plus `app/Http/Resources/QuoteResource.php`/`app/Actions/Quote/SubmitQuoteAction.php`. Coverage lives in `resources/js/components/quotes/__tests__/quote-compare-table.test.tsx`.
- ✅ Buyers can jump straight from the comparison drawer to the award workspace via the new "Review & award" CTA in `resources/js/components/quotes/quote-compare-table.tsx`, which routes to `resources/js/pages/awards/award-review-page.tsx` and carries the selected quote ids through router state (tested in `resources/js/components/quotes/__tests__/quote-compare-table.test.tsx`).
- ✅ The award workspace now hydrates `AwardLinePicker` with those router-state selections so preferred suppliers are preselected per line (see `resources/js/pages/awards/award-review-page.tsx` + `resources/js/components/awards/award-line-picker.tsx`, validated in `resources/js/pages/awards/__tests__/award-review-page.test.tsx`).
- ✅ Sorting/filtering of quotes on this screen (the comparison drawer now ships supplier search, score filters, shortlist-only toggle, status chips, and reset controls in `resources/js/components/quotes/quote-compare-table.tsx`).
- ✅ Outlier highlighting (very low/high prices or lead times) if implemented (see `resources/js/components/quotes/quote-compare-table.tsx` which now flags ±20% price/lead variances per RFQ line using the comparison payload).
- ✅ Awarding logic (implemented via `resources/js/pages/awards/award-review-page.tsx`, `AwardLinePicker`, and `app/Actions/Rfq/AwardLineItemsAction.php`):
    a. Award at line level.
    b. Split awards across multiple suppliers for different lines of the same RFQ.
- ✅ Non-awarded suppliers receiving notifications/regret communications (handled in `app/Actions/Rfq/AwardLineItemsAction::notifySuppliers()` with coverage in `tests/Feature/Api/RfqLineAwardFeatureTest.php`).
- ✅ Identify DB relations between RFQ, quotes, and awards/POs (see `database/migrations/2025_11_03_000400_create_quotes_tables.php`, `2025_11_11_090000_add_totals_to_quotes_and_purchase_orders.php`, `2025_11_10_140000_create_rfq_item_awards_table.php`, and `2025_11_10_140300_add_award_reference_to_po_lines_table.php`).
- ✅ Report gaps in line-level awards, missing UI wiring, or denial of service cases (see "Review — 2025-12-02" section below outlining comparison UI deficiencies, missing award CTA, and data-model holes).

## Review — 2025-12-03

**Comparison matrix**
- The drawer in `resources/js/components/quotes/quote-compare-table.tsx` is now bound to the normalized `GET /api/rfqs/{id}/quotes/compare` payload via `useQuoteComparison`, so buyers see supplier rank, composite/price/lead/rating scores, attachment counts, estimated tax, and the new incoterm/payment fields added in `app/Http/Resources/QuoteResource.php`. Those commercial terms are persisted via `database/migrations/2025_12_06_010500_add_commercial_terms_to_quotes_table.php` and wired through `app/Actions/Quote/SubmitQuoteAction.php`, `app/Http/Requests/Supplier/Quotes/StoreQuoteRequest.php`, and `app/Http/Requests/Buyer/Quotes/StoreQuoteRequest.php`. The detail view (`resources/js/pages/quotes/quote-detail-page.tsx`) mirrors the same metadata for consistency. The comparison drawer also now ships a “Review & award” CTA that hands selected quote IDs to the award workspace for immediate action.

**Sorting & filtering**
- Buyers can sort the grid by composite, price, lead time, or risk and now filter the list by supplier name, shortlist flag, normalized score brackets, and quote status. These toggles (plus a reset helper) live inline atop `resources/js/components/quotes/quote-compare-table.tsx`, satisfying FR-4’s “filters/toggles” requirement.

**Outlier cues**
- Implemented in `quote-compare-table.tsx` using a ±20% tolerance from the median unit price/lead per RFQ line. Each cell now renders “Low/High price” or “Fast/Slow lead” badges when values deviate, satisfying the FR-4 cue requirement.

**Awarding workflow**
- Line-level selection, persistence, and PO conversion are implemented in `resources/js/pages/awards/award-review-page.tsx`, `AwardLinePicker`, and `AwardSummaryCard`. The frontend writes through `useCreateAwards` / `RFQsApi.createAwards`, and `useCreatePo` hits `POST /api/pos/from-awards` so each supplier gets a grouped PO draft. When navigated from the comparison drawer, the award form now prefills each RFQ line with the preferred quote using router state, minimizing duplicate clicks.
- Backend `app/Actions/Rfq/AwardLineItemsAction.php` enforces RFQ eligibility, normalizes each `{rfq_item_id, quote_item_id, awarded_qty}` row, groups by supplier, and (optionally) generates `purchase_orders` with linked `po_lines`. Partial awards are supported by the per-line quantity input and `awarded_qty` column added in `database/migrations/2025_11_15_000100_add_awarded_qty_to_rfq_item_awards_table.php`.

**Split awards**
- Buyers can assign different lines to different suppliers because `rfq_item_awards` stores one row per RFQ line and groups winners per supplier when creating purchase orders (see `database/migrations/2025_11_10_140000_create_rfq_item_awards_table.php` and the supplier batching inside `AwardLineItemsAction`). The `Clear selection` + per-line quantity controls in `AwardLinePicker` let planners intentionally split coverage.

**Supplier communications**
- `AwardLineItemsAction::notifySuppliers()` emits `rfq_line_awarded` to winners (optionally including the PO metadata) and `rfq_line_lost` to losers. This behaviour is covered by `tests/Feature/Api/RfqLineAwardFeatureTest.php`, which asserts both notification types exist after awarding.

**Data model links**
- Quotes belong to RFQs and suppliers via `database/migrations/2025_11_03_000400_create_quotes_tables.php`, with monetary fields added in `2025_11_11_090000_add_totals_to_quotes_and_purchase_orders.php` and attachment/status clean-up in `2025_11_23_120000_align_quotes_schema_with_spec.php`.
- Awards bridge RFQ items, quotes, suppliers, and purchase orders (`rfq_item_awards` migration above), and `database/migrations/2025_11_10_140300_add_award_reference_to_po_lines_table.php` links PO lines back to the originating award for downstream receiving/invoicing reconciliation.

**Gaps / risks**
- None outstanding. Consider future enhancements like persisting comparison filter presets or allowing inline award quantity overrides before launching the award workspace.