# Domain Model (Summary)
Tenant‑scoped by `company_id`.

**Core:** Company, User (+company_user), Supplier (+supplier_documents), RFQ (+items, invitations, clarifications with version), Quote (+items, revision), PO (+lines, change_orders), Order (timeline), GRN (+lines), NCR, Invoice (+lines, match), Documents (polymorph), Notifications (+prefs), API Keys, Webhooks, UsageSnapshots, AuditLogs, RetentionHolds, CompanyPlanOverrides.

**Enums (examples):** user.role, supplier.status, rfq.status, purchase_order.status, order.status, invoice.status, invoice_match.result.

**Indexes:** FULLTEXT suppliers(name,capabilities), rfqs(title); composite per table; always index FKs. Soft delete business entities.

**Relationships:** RFQ 1‑* Items/Invitations/Clarifications/Quotes; Quote 1‑* Items; PO 1‑* Lines/ChangeOrders and 1‑1 Order; GRN 1‑* Lines; Invoice 1‑* Lines and 1‑1 Match; Document morphs.
