# Database Plan Summary (Derived from §26)

This document distills the database guidance in `/docs/REQUIREMENTS_FULL.md` §26. Treat the PDF as the source of truth; use this summary to keep migrations, factories, and model code aligned with the approved schema.

## Conventions & Multitenancy
- Storage engine `InnoDB`, charset/collation `utf8mb4` / `utf8mb4_unicode_ci`.
- Tables use plural `snake_case`; primary keys are auto-increment `BIGINT UNSIGNED id`.
- Foreign keys follow the `{singular}_id` pattern and require indexed columns.
- Every tenant-owned record includes `company_id BIGINT UNSIGNED` → `companies.id`; platform-wide rows (e.g., platform admins, global documents) explicitly allow `company_id` to be `NULL`.
- Business aggregates expose `created_at`, `updated_at`, and `deleted_at` (soft deletes). System tables that must persist (e.g., billing ledger) remain hard delete.
- Enum values are implemented with Laravel backed enums or constrained strings exactly as listed in §26.
- All create/update/delete actions emit records in `audit_logs` capturing `before`/`after` payloads and contextual metadata.
- File binaries live in S3/local disks per module limits; metadata and polymorphic bindings live in the `documents` table.

## Migration Order (Exact Sequence)
1. `companies`, `users`, `company_user`
2. Laravel Cashier billing tables (published via `php artisan vendor:publish --tag=cashier-migrations`)
3. `suppliers`, `supplier_documents`, `documents`
4. `rfqs`, `rfq_items`, `rfq_invitations`, `rfq_clarifications`
5. `quotes`, `quote_items`
6. `purchase_orders`, `po_lines`, `po_change_orders`, `orders`
7. `grns`, `grn_lines`, `ncrs`
8. `invoices`, `invoice_lines`, `invoice_matches`
9. `notifications`, `user_notification_prefs`
10. `api_keys`, `webhooks`
11. `usage_snapshots`, `audit_logs`, `retention_holds`, `company_plan_overrides`

Seeders follow each domain group (see §26.15) to ensure fixtures stay in sync with the schema.

## Table Summaries & Key Indexes

### Core Platform
- **companies**: tenant registry with `name`, `slug`, `status`, `region`, owner linkage, usage counters, Stripe billing references, and `trial_ends_at`. Soft deletes enabled. Indexes: `(status)`, `(plan_code)`, `(owner_user_id)`.
- **users**: platform identities keyed by optional `company_id`, `role`, auth credentials, `last_login_at`, soft deletes. Indexes: `(company_id, role)`.
- **company_user**: multi-org membership mapping `company_id`, `user_id`, `role`. Unique composite `(company_id, user_id)` with supporting indexes on both FKs.
- **Billing (Cashier)**: adopt Cashier tables (subscriptions, subscription_items, etc.) and extend with `company_id` where tenancy is required. Standard indexes provided by Cashier plus any additional tenant scopes.

### Directory & Supplier
- **suppliers**: tenant-filtered supplier directory with contact fields, `status`, capability JSON, rating averages, soft deletes. Indexes: `(company_id, status)` and `FULLTEXT(name, city, capabilities)`.
- **supplier_documents**: compliance artifacts linking suppliers to `documents`, tracking `type`, `expires_at`, `status`. Indexes: `(supplier_id, type)` and `(expires_at)`.

### Sourcing: RFQs, Quotes, Awards
- **rfqs**: sourcing headers with buyer `company_id`, author, commercial fields (`type`, `material`, `method`, `incoterm`, `currency`), lifecycle timestamps (`publish_at`, `due_at`, `close_at`), status enum, versioning, soft deletes. Indexes: `(company_id, status, due_at)` plus `FULLTEXT(title)`.
- **rfq_items**: line-level specs with `rfq_id`, `line_no`, `part_name`, `spec`, `quantity`, `uom`, optional `target_price`. Unique `(rfq_id, line_no)`.
- **rfq_invitations**: supplier invitations with `rfq_id`, `supplier_id`, inviter, status enum. Unique `(rfq_id, supplier_id)`.
- **rfq_clarifications**: RFQ Q&A/amendments with `kind`, `message`, optional `attachment_id`, `rfq_version`, timestamps. Index `(rfq_id, kind)`.
- **quotes**: supplier responses scoped by buyer `company_id`, linking to `rfq_id`, `supplier_id`, submitter, money fields (`currency`, `unit_price`, `min_order_qty`), `lead_time_days`, `note`, status enum with revisioning, soft deletes. Unique `(rfq_id, supplier_id, revision_no)`; index `(rfq_id, supplier_id, status)`.
- **quote_items**: quote line details mapping `quote_id` to `rfq_item_id`, pricing, lead time, optional note. Unique `(quote_id, rfq_item_id)`.

### Purchasing: POs, Change Orders, Orders
- **purchase_orders**: buyer `company_id`, linked `rfq_id`/`quote_id`, unique `po_number`, commercial fields (`currency`, `incoterm`, `payment_terms`, `tax_percent`), status, revision metadata, optional PDF document, soft deletes. Indexes: `(company_id, status)` and `(rfq_id, quote_id)`.
- **po_lines**: purchase order lines linking to `purchase_order_id`, optional `rfq_item_id`, `line_no`, description, quantity, `uom`, `unit_price`, optional `delivery_date`. Unique `(purchase_order_id, line_no)`.
- **po_change_orders**: change proposals referencing `purchase_order_id`, proposer, `reason`, JSON diff, status enum, `po_revision_no`.
- **orders**: execution tracker tying back to `purchase_order_id`, `company_id`, `supplier_id`, status enum, `tracking_number`, timeline JSON, timestamps. Indexes: `(company_id, status)` and `(supplier_id)`.

### Receiving & Quality
- **grns**: goods receipt headers with `company_id`, `purchase_order_id`, receiver, `received_at`, optional `note`.
- **grn_lines**: receipt lines linking `grn_id` and `po_line_id`, quantities (`received_qty`, `accepted_qty`, `rejected_qty`), inspection notes, `ncr_flag`. Unique `(grn_id, po_line_id)`.
- **ncrs**: non-conformance records referencing `company_id`, `po_line_id`, raiser, status enum, `reason`, attachment JSON, timestamps.

### Invoicing & Match
- **invoices**: buyer `company_id`, `purchase_order_id`, `supplier_id`, invoice metadata (`invoice_number`, `currency`, monetary fields), status enum, optional `document_id`, soft deletes. Indexes: `(company_id, status)` and `(supplier_id)`.
- **invoice_lines**: detail lines joining `invoice_id` with optional `po_line_id`, description, quantity, `uom`, `unit_price`.
- **invoice_matches**: three-way match result linking `invoice_id`, `po_id`, optional `grn_id`, match enum, details JSON, timestamps.

### Documents & Media
- **documents**: polymorphic attachments with optional `company_id`, `documentable_type/id`, `kind`, storage `path`, `filename`, `mime`, `size_bytes`, `hash_sha256`, version number, soft deletes. Indexes: `(documentable_type, documentable_id)` and `(company_id, kind)`.

### Notifications & Preferences
- **notifications**: user-facing feed entries with `company_id`, `user_id`, `type`, `title`, `body`, entity reference, `channel`, `read_at`, `meta` JSON. Index `(user_id, read_at)` per §26.12.
- **user_notification_prefs**: per-user event settings with `user_id`, `event_type`, `channel`, `digest`. Unique `(user_id, event_type)`.

### API & Webhooks
- **api_keys**: tenant API credentials with `company_id`, `name`, `token_hash`, JSON `scopes`, usage timestamps, `revoked_at`, creator.
- **webhooks**: outbound subscriptions with `company_id`, target `url`, `event_filters` JSON, shared secret, `active` flag.

### Analytics & Governance
- **usage_snapshots**: daily usage metrics keyed by `company_id`, `date`, counts for RFQs, quotes, POs, storage. Unique `(company_id, date)`.
- **audit_logs**: activity ledger with optional `company_id`/`user_id`, target entity type/id, action enum, `before`/`after` JSON, `ip`, `ua`, timestamp. Indexes: `(company_id, entity_type, entity_id)` and `(action, created_at)`.
- **retention_holds**: legal hold flags tying `company_id` to entity references, `reason`, `active` flag, timestamps. Unique `(company_id, entity_type, entity_id, active)`.
- **company_plan_overrides**: per-tenant entitlements override with `company_id`, `key`, `value`, `reason`, `created_by`. Unique `(company_id, key)`.

## Indexing, Cascades & Acceptance
- Implement the composite and FULLTEXT indexes called out above plus §26.12 (e.g., quotes `(rfq_id, supplier_id, status)`, suppliers FULLTEXT, notifications `(user_id, read_at)`).
- Follow §26.13 cascade guidance: cascade children (e.g., RFQ → rfq_items) where safe, but prefer soft deletes for legal artifacts (invoices, POs).
- Enforce §26.16 acceptance checks: FK integrity, tenant scoping, soft deletes, unique constraints, document link validity, audit emission, retention policies, and Cashier readiness.

Keep migrations, factories, and tests synchronized with this plan; deviations require explicit updates to §26 of the PDF.
