1. ✅ Critical – Tenant scope trait never implemented: Added `App\Support\CompanyContext`, the shared `BelongsToCompany` concern, and a new `CompanyScopedModel` base so every tenant model now auto-scopes queries/creates by `company_id` (applied across RFQ, PO, Invoice, supplier, tax, etc. models per §26.17).

2. ✅ High – Award line APIs bypass tenant context + envelope: `AwardLineController` now resolves the active company via `requireCompanyContext()`, scopes RFQ + award lookups by that id, authorizes with Gate, and returns standard `ok()/fail()` envelopes for both store and destroy actions.

3. ✅ High – Clarification controller ignores tenant scoping and envelope: Every action in `RfqClarificationController` now enforces the resolved company context, rejects RFQs outside that tenant, replaces `abort()` calls with `fail()`, and returns `ok()` envelopes (201 for create flows) so `meta.request_id` is preserved.

4. ✅ High – RFQ award bulk action cross-tenant: `RfqAwardController::awardLines()` now requires the active company context, ensures it matches the RFQ before authorizing, and returns `fail('RFQ not found', 404)` on mismatch to block cross-tenant awards.

5. ✅ Medium – Supplier application listing ignores context + pagination: `SupplierApplicationController::index()` now uses `requireCompanyContext()`, scopes records by that company id, and returns a cursor-paginated resource envelope (with `meta` cursor data) instead of an unpaginated collection.

6. ✅ Medium – RFQ attachment upload bypasses envelope/meta: `RfqAttachmentController::store()` now responds via `$this->ok(...)->setStatusCode(201)` so every upload includes the standard envelope and `X-Request-Id` metadata.

7. ✅ High (Frontend) – Clarification UI can neither upload nor display attachments: `clarification-thread.tsx` now supports file selection/removal with user messaging about virus scanning, shows selected files per submission, and renders download links (with filenames + sizes) for existing clarification attachments.

8. ✅ High (Frontend) – Clarification mutations send the wrong payload: `use-rfq-clarifications` now submits multipart form-data (message + attachments[]) through the authenticated fetch pipeline, and the detail page threads pass the structured payload straight through so backend requests satisfy `StoreRfqQuestion/Answer`.