# Domain Model (Summary)
Tenant‑scoped by `company_id`.

**Core:** Company, User (+company_user), Supplier (+supplier_documents), RFQ (+items, invitations, clarifications with version), Quote (+items, revision), PO (+lines, change_orders), Order (timeline), GRN (+lines), NCR, Invoice (+lines, match), Documents (polymorph), Notifications (+prefs), API Keys, Webhooks, UsageSnapshots, AuditLogs, RetentionHolds, CompanyPlanOverrides.

**Parts Catalog:** Part (company_id, part_number, name, uom, base_uom_id, spec, meta, soft deletes) plus PartTag (company_id, part_id, tag, normalized_tag, soft deletes) to power FR-18 tag filters and saved searches.

**Analytics Aggregates:** UsageSnapshot (company_id, date, rfqs_count, quotes_count, pos_count, storage_used_mb, soft deletes) populated nightly via `ComputeTenantUsageJob` so admin usage cards and plan enforcement rely on consistent historical data.

**Enums (examples):** user.role, supplier.status, rfq.status, purchase_order.status, order.status, invoice.status, invoice_match.result.

**Indexes:** FULLTEXT suppliers(name,capabilities), rfqs(title); composite per table; always index FKs. Soft delete business entities.

**Relationships:** RFQ 1‑* Items/Invitations/Clarifications/Quotes; Quote 1‑* Items; PO 1‑* Lines/ChangeOrders and 1‑1 Order; GRN 1‑* Lines; Invoice 1‑* Lines and 1‑1 Match; Document morphs.
