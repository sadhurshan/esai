RFP (Project/Service) Creation & Supplier Proposals

**1. RFP entity (problem/objectives, scope, timeline, evaluation criteria, proposal format) — ✅**
- Added the tenant-scoped `rfps` table with the required narrative fields, lifecycle metadata, soft deletes, status enum, and AI assist placeholders (`database/migrations/2025_11_26_150000_create_rfps_table.php`).
- Introduced the domain model, enum, factory, policy, middleware, API controller, request validators, resources, and actions that let buyers author RFPs independent of RFQs (`app/Models/Rfp.php`, `app/Enums/RfpStatus.php`, `app/Http/Controllers/Api/RfpController.php`, `app/Http/Requests/Rfp/*`, `app/Http/Resources/RfpResource.php`, `app/Actions/Rfp/*`, `app/Policies/RfpPolicy.php`, `app/Http/Middleware/EnsureRfpAccess.php`).
- Exposed authenticated API routes plus RBAC permissions so the new RFP entity is fully reachable from the buyer experience (`routes/api.php`, `config/rbac.php`, `bootstrap/app.php`).

**2. Supplier proposal submission form (price, schedule, approach, attachments) — ✅**
- Spec: FR-2 acceptance criteria says “Given an RFP, when published, suppliers can submit proposals with attachments and structured fields (price, lead time, more).”
- Added the backend submission pipeline so suppliers can submit to the new `rfp_proposals` table using the JSON envelope + file uploads: `StoreRfpProposalRequest` enforces the FR-2 fields, `SubmitRfpProposalAction` persists proposals + attachments via the documents subsystem, `RfpProposalController@store` + `routes/api.php` expose `POST /api/rfps/{rfp}/proposals` under the supplier guard, and `RfpProposalPolicy` ensures only invited suppliers (or buyer admins) can submit.
- Implemented the supplier-facing React page + supporting hooks so invited vendors can upload documents and submit proposals without leaving the portal (`resources/js/pages/suppliers/rfps/supplier-rfp-proposal-page.tsx`, `resources/js/hooks/api/rfps/*`, new route `/app/suppliers/rfps/:rfpId/proposals/new`). The form enforces FR-2 pricing/schedule fields, handles 50 MB document uploads via the document service, and surfaces buyer context, plan gating, and submission states inside the supplier workspace.

**3. RFP status lifecycle (draft → published → in_review → awarded/no_award) — ✅**
- Wired the lifecycle transitions through a dedicated domain action that enforces the allowed path, stamps lifecycle timestamps, and emits audit entries (`app/Actions/Rfp/TransitionRfpStatusAction.php`).
- Added lifecycle endpoints + policy enforcement so buyers can publish, send to review, award, or close with no award via authenticated API routes (`app/Http/Controllers/Api/RfpController.php`, `routes/api.php`).
- Backed the flow with request/permission guards and regression tests that cover happy-path + invalid transitions to keep the four-step lifecycle spec-compliant (`app/Http/Middleware/EnsureRfpAccess.php`, `app/Policies/RfpPolicy.php`, `tests/Feature/Api/RfpControllerTest.php`).

**4. Comparison / review UI for multiple proposals — ✅**
- Added the buyer-facing comparison screen that hydrates `useRfp` + `useRfpProposals` and renders buyer context cards, summary highlights, and a sortable table so sourcing teams can evaluate narrative proposals side by side (`resources/js/pages/rfps/rfp-proposal-review-page.tsx`).
- Wired the route under the authenticated workspace so `/app/rfps/:rfpId/proposals` loads the new page, inherits breadcrumbs/layout, and reuses the existing plan-gating + EmptyState patterns (`resources/js/app-routes.tsx`).
- The UI highlights lowest-price and fastest-turn proposals, surfaces attachment counts, and falls back to skeletons or upgrade banners while respecting tenant feature flags, aligning with the spec’s FR-4 review experience.

**5. Related tables, endpoints, pages — ✅**
- Landed the tenant-scoped `rfps` + `rfp_proposals` migrations with lifecycle timestamps, indexes, and soft deletes so the persistence layer matches the spec (`database/migrations/2025_11_26_150000_create_rfps_table.php`, `database/migrations/2025_11_26_170000_create_rfp_proposals_table.php`).
- Implemented scoped models/factories + policy bindings and document storage so proposals stay tied to their buyer company while attachments flow through the documents subsystem (`app/Models/Rfp.php`, `app/Models/RfpProposal.php`, `app/Policies/RfpPolicy.php`, `app/Policies/RfpProposalPolicy.php`, `app/Actions/Rfp/SubmitRfpProposalAction.php`, `app/Providers/AppServiceProvider.php`).
- Exposed buyer + supplier APIs for publishing, reviewing, and submitting proposals via the dedicated controllers and JSON envelope, mirroring the REST contract in `/docs/REQUIREMENTS_FULL.md` (`app/Http/Controllers/Api/RfpController.php`, `app/Http/Controllers/Api/RfpProposalController.php`, `routes/api.php`, `tests/Feature/Api/RfpControllerTest.php`, `tests/Feature/Api/RfpProposalControllerTest.php`).
- Shipped the paired React workspaces for sourcing teams and suppliers so `/app/rfps/:rfpId/proposals` and `/app/suppliers/rfps/:rfpId/proposals/new` operate end-to-end with the new hooks and layouts (`resources/js/pages/rfps/rfp-proposal-review-page.tsx`, `resources/js/pages/suppliers/rfps/supplier-rfp-proposal-page.tsx`, `resources/js/app-routes.tsx`, `resources/js/hooks/api/rfps/*`).

**6. Missing status handling / comparison gaps / RFQ reuse concerns — ✅**
- Closed the lifecycle gap by introducing dedicated actions + policies that drive the four-step status flow with audit logging, mirroring the RFP-specific DTOs instead of overloading RFQ controllers (`app/Actions/Rfp/TransitionRfpStatusAction.php`, `app/Policies/RfpPolicy.php`, `app/Http/Controllers/Api/RfpController.php`).
- Delivered buyer + supplier review experiences tailored to narrative proposals rather than RFQ line items, covering the spec’s comparison requirements through the new React pages and hooks (`resources/js/pages/rfps/rfp-proposal-review-page.tsx`, `resources/js/pages/suppliers/rfps/supplier-rfp-proposal-page.tsx`, `resources/js/hooks/api/rfps/*`).
- Added regression coverage for both status transitions and proposal review/submit guards so the failure modes called out in the spec are enforced going forward (`tests/Feature/Api/RfpControllerTest.php`, `tests/Feature/Api/RfpProposalControllerTest.php`).

**Checklist status:** All items ✅ — RFP creation & supplier proposals are production-ready as of 2025‑11‑26.