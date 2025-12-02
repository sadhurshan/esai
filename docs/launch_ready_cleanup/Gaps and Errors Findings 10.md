1. ✅ `resources/js/sdk/api-helpers.ts` now merges envelope metadata into any existing `data.meta` instead of clobbering collection pagination keys, exposing the request-level meta under `meta.envelope` so hooks retain `next_cursor` / `prev_cursor` data.

2. ✅ `app/Http/Controllers/Api/DigitalTwin/SystemController.php` and `LocationController.php` resolve the authenticated user plus active company via the shared helpers, fail with the standard envelope when context is missing, and scope queries/mutations with the resolved tenant instead of blindly casting `$request->user()->company_id`.

3. ✅ Both Digital Twin index endpoints append `orderByDesc('id')` after `orderBy('name')`, producing deterministic cursor pagination so duplicate names no longer skip/duplicate rows across pages.

4. ✅ `app/Http/Controllers/Api/QuoteController::compare` now authenticates via `resolveRequestUser()`, derives the active company, refuses cross-tenant access with `{ status: 'error' }`, and then authorizes the RFQ before building the pricing matrix, closing the data-leak path.

5. ✅ `app/Http/Controllers/Api/AwardController::store` replaced the `abort_if` checks with helper-driven user/company resolution plus envelope-compliant failures, so multi-company users get consistent JSON responses and RFQ scoping before award creation.

6. ✅ `app/Http/Controllers/Api/InvoiceTotalsController.php` and `PoTotalsController.php` both call `resolveUserCompanyId()` to set the active tenant, enforce policies (including PO update authorization), and compare the resolved company id against the record, allowing legitimate multi-company recalculations to succeed.