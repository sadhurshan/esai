# Acceptance Smoke Tests

Quick-run checks to validate each epic after deploys. These derive from `/docs/REQUIREMENTS_FULL.md` §26 and companion specs; expand into full suites in Pest and Playwright as we automate more coverage.

## Supplier Discovery & Directory
- [ ] DB: `suppliers` and `supplier_documents` seeded with tenant-scoped rows tied to `company_id`.
- [ ] API: `GET /api/suppliers` returns `{ status: 'success', data.items: [], meta.next_cursor }` with capability filters functioning.
- [ ] UI: `/suppliers` renders with Tailwind/shadcn table, search/filter controls, and empty-state illustration.

## RFQ / RFP Management
- [ ] DB: `rfqs`, `rfq_items`, `rfq_invitations`, `rfq_clarifications` created with expected enums and versioning.
- [ ] API: `POST /api/rfqs` stores CAD metadata and emits audit log; cursor listing respects tab filters.
- [ ] UI: `/rfq` index loads skeleton, query-string filters, and “New RFQ” flow opens with required form fields.

## Quote Intake & Comparison
- [ ] DB: `quotes` + `quote_items` revisions recorded with unique `(rfq_id, supplier_id, revision_no)`.
- [ ] API: `POST /api/rfqs/{id}/quotes` accepts attachment, queues notification, and response uses envelope.
- [ ] UI: RFQ detail page renders quote comparison grid with badge statuses and download links.

## Awarding & Split Decisions
- [ ] DB: `purchase_orders.quote_id` matches awarded quote, with revision number updated.
- [ ] API: Award action endpoint returns success envelope and triggers audit entry.
- [ ] UI: Award modal confirms selected suppliers and displays split alloc totals.

## Purchase Orders & Change Orders
- [ ] DB: `purchase_orders`, `po_lines`, `po_change_orders` persisted with soft deletes and change JSON payload.
- [ ] API: `POST /api/purchase-orders/{id}/change-orders` queues approval workflow and returns cursor meta when listing.
- [ ] UI: `/orders` PO detail shows timeline, change history drawer, and print-ready PDF link.
- [ ] UI: Buyer PO detail exposes a Change Orders tab showing proposed revisions with approve/reject actions.
- [ ] API/UI: Approving a change order increments `purchase_orders.revision_no` and updates the change order status.

## Order Execution & Tracking
- [ ] DB: `orders.timeline` JSON stores status transitions with actor IDs.
- [ ] API: `PATCH /api/orders/{id}` advances status and enforces supplier/buyer authorization.
- [ ] UI: Order board reflects status pills and displays tracking number.

## Receiving, Inspection & NCRs
- [ ] DB: `grns`, `grn_lines`, `ncrs` linked via FKs with soft deletes off.
- [ ] API: `POST /api/grns` increments received quantities and sets NCR flag.
- [ ] UI: Receiving screen captures accepted/rejected quantities and raises NCR modal.

## Invoicing & Three-Way Match
- [ ] DB: `invoices`, `invoice_lines`, `invoice_matches` persisted with audit row on match.
- [ ] API: `POST /api/invoices` syncs totals and triggers match job.
- [ ] UI: Invoice summary renders three-way match badge and download link.

## Document & CAD Control
- [ ] DB: `documents` rows created with correct polymorphic bindings and hashed metadata.
- [ ] API: `GET /api/files/{type}` returns signed URL via FileController envelope.
- [ ] UI: Document library preview works with skeleton state and pagination cursors.

## Analytics & Operational Dashboards
- [ ] DB: `usage_snapshots` nightly job populates latest metrics for tenant.
- [ ] API: Analytics endpoint respects plan gates and returns summarized datasets.
- [ ] UI: Dashboard cards render charts with accessible labels and loading skeletons.

## Integrations & Webhooks
- [ ] DB: `api_keys` and `webhooks` rows scoped by company with active flags.
- [ ] API: Key rotation endpoint returns hashed token once; webhook test ping yields 200.
- [ ] UI: Integration settings screen lists keys/hooks with copy + revoke actions.

## Notifications & Preferences
- [ ] DB: `notifications` and `user_notification_prefs` seeded for tenant users.
- [ ] API: Notification feed endpoint uses cursor meta and marks `read_at` timestamps.
- [ ] UI: Bell dropdown shows unread count and preference form toggles channels.

## Company & User Administration
- [ ] DB: `companies`, `users`, `company_user` rows created with plan codes and soft deletes.
- [ ] API: Invite endpoint issues signed link and enforces role limits.
- [ ] UI: Admin console lists tenants with status badges and impersonate guard.

## Billing, Plans & Entitlements
- [ ] DB: Cashier tables migrated; `company_plan_overrides` entries present for test tenant.
- [ ] API: Billing webhook stub logs events; middleware gates plan-locked routes.
- [ ] UI: Billing settings renders plan cards and upcoming invoice preview.

## Approvals Matrix & Delegations
- [ ] DB: Approval rules stored per company (see deep spec) with cascade to delegated approvers.
- [ ] API: Approval decision endpoint persists audit row and advances workflow.
- [ ] UI: Approval queue displays pending items with bulk actions.

## Global Search & Indexing
- [ ] DB: FULLTEXT indexes in place on `suppliers` and `rfqs`; search analytics track usage.
- [ ] API: `/api/search` enforces tenant scope and returns ranked hits.
- [ ] UI: Command palette surfaces cross-module results with keyboard navigation.

## Localization, Units & Currency
- [ ] DB: Currency and unit preferences stored per tenant.
- [ ] API: Responses format monetary values per locale (tests verify USD/EUR at least).
- [ ] UI: Settings page toggles locale and previews measurement/unit changes.

## Audit Trails & Governance
- [ ] DB: `audit_logs` populated on CRUD plus impersonation events; `retention_holds` applied on protected rows.
- [ ] API: Audit export endpoint streams CSV with scoped cursor pagination.
- [ ] UI: Audit console renders filterable list with diff viewer.

## AI & Copilot Assistants
- [ ] DB: Prompt history stored per tenant (see AI deep spec) with soft deletes.
- [ ] API: Copilot action endpoint records request/response metadata for analytics.
- [ ] UI: Copilot sidebar launches contextual suggestions and respects entitlements.

## Platform Admin Console & Support
- [ ] DB: Platform-level `users` (company_id NULL) seeded with super/support roles.
- [ ] API: Health check returns `{ status: 'success', data: { healthy: true } }`.
- [ ] UI: Admin console renders tenant list, plan usage, and broadcast banner controls.
