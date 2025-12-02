RFQ Creation & Status Lifecycle

**1. Audit the RFQ creation and lifecycle**
- Backend entry points live entirely in `app/Http/Controllers/Api/RFQController.php`, with creation → publish → close/cancel covered plus audit logging via `AuditLogger` and version bumps via `RfqVersionService`.
- Frontend creation wizard is consolidated under `resources/js/pages/rfqs/rfq-create-wizard.tsx`, and detail management happens in `rfq-detail-page.tsx` with shared controls in `resources/js/components/rfqs`. Lifecycle is therefore split between those two surfaces and the award flows (`app/Actions/Rfq/*`).

**2. RFQ creation form coverage**
- Wizard captures basics, suppliers, attachments, and line-level specs/quantities (`rfq-create-wizard.tsx`), matching the spec requirements for methods/materials/tolerance/finish/UoM/deadlines.
- ✅ Currency, payment terms, and tax percent now live in the commercial terms step, serialize through `use-create-rfq`, and persist via RFQ meta so both UI and backend stay aligned.

**3. Open vs invited RFQs**
- Creation payload sets `open_bidding` (boolean) and supplier IDs, and backend invite handling is in `InviteSuppliersToRfqAction` + `RfqInvitationController`.
- Supplier visibility rules in `RFQController::index()` correctly scope open RFQs vs invitations. ✅ Covered, though UI still allows inviting suppliers while status is `awarded` (see item 5).

**4. Status & state machine**
- Model canonical statuses are `draft|open|closed|awarded|cancelled` (`app/Models/RFQ.php`). Publish/close/cancel endpoints enforce the expected transitions, and `ManagesRfqAwardState` flips to `awarded` once every line has an award.
- Missing clarifications phase: spec calls out a distinct "clarifications" status but implementation keeps RFQs `open` while clarifications are logged (`RfqClarificationService`). Decide whether to introduce that status or update the spec.
- ✅ Frontend now treats both `draft` and legacy `awaiting` statuses (`RfqActionBar`, `RfqStatusBadge`), so publish CTAs and badges render correctly when the API returns `draft`.

**5. Transition enforcement (backend & UI)**
- ✅ Backend: `RFQController@update` now rejects edits once an RFQ leaves `draft`, nudging buyers to the amendment workflow for published or closed documents.
- ✅ UI: `RfqActionBar` only enables Publish/Edit while status is `draft/awaiting`, and supplier invites are restricted to `draft` or `open` so awarded/closed RFQs can’t keep inviting. Remaining gaps: disable direct edits once amendments exist, and mirror any backend policy changes.

**6. DB/models/services/UI consistency**
- ✅ Legacy `useCreateRFQ.ts` now re-exports the spec-compliant hook, so nothing references the deprecated `item_name`/`awaiting` fields.
- ✅ Payment terms and tax percent are serialized through `use-create-rfq`, stored in the RFQ `meta` payload, exposed by `RFQResource`, and rendered on `rfq-detail-page.tsx`, eliminating the previous meta gap.

**7. Unused statuses / illegal flows**
- `awaiting` is a dead status kept only for legacy transforms; no backend code emits it. Client components relying on it are now broken.
- ✅ `cancel` endpoint now rejects closed RFQs (see `tests/Feature/Api/RfqCancelTest.php`). Still need to decide whether partial awards should reopen RFQs or enforce a stricter state machine.
- ✅ Status assertions now live in `tests/Feature/Api/RfqLifecycleStatusTest.php`, covering draft→open, open→closed/cancelled, and the award flow (including partial-award behavior) so illegal transitions surface quickly.