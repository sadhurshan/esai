
# Requirements Companion — Elements Supply AI

This living document mirrors `/docs/ProjectRequirements.pdf` and should be read alongside the PDF. Always treat the PDF as canonical; use the outline and checklists below to keep Copilot responses aligned with the approved scope.

## Section 26 Outline
- [26 Database Plan (MySQL + Laravel 12)](#26-database-plan-mysql--laravel-12)
	- [26.1 Conventions (Copilot must follow)](#261-conventions-copilot-must-follow)
	- [26.2 Core Platform Tables](#262-core-platform-tables)
		- [26.2.1 companies (tenant registry)](#2621-companies-tenant-registry)
		- [26.2.2 users (platform identities)](#2622-users-platform-identities)
		- [26.2.3 company_user (multi-org membership)](#2623-company_user-multi-org-membership)
		- [26.2.4 Billing (Laravel Cashier baseline)](#2624-billing-laravel-cashier-baseline)
	- [26.3 Directory & Supplier](#263-directory--supplier)
	- [26.4 Sourcing: RFQs, Quotes, Awards](#264-sourcing-rfqs-quotes-awards)
	- [26.5 Purchasing: POs, Change Orders, Orders](#265-purchasing-pos-change-orders-orders)
	- [26.6 Receiving & Quality](#266-receiving--quality)
	- [26.7 Invoicing & Match](#267-invoicing--match)
	- [26.8 Documents & Media](#268-documents--media)
	- [26.9 Notifications & Preferences](#269-notifications--preferences)
	- [26.10 API & Webhooks](#2610-api--webhooks)
	- [26.11 Analytics & Governance](#2611-analytics--governance)
	- [26.12 Indexing & Search Guidance](#2612-indexing--search-guidance)
	- [26.13 Cascade & Referential Rules](#2613-cascade--referential-rules)
	- [26.14 Migration Order](#2614-migration-order)
	- [26.15 Seeders (minimum)](#2615-seeders-minimum)
	- [26.16 Acceptance Checks (DB)](#2616-acceptance-checks-db)
	- [26.17 Laravel Model Notes](#2617-laravel-model-notes)

### 26 Database Plan (MySQL + Laravel 12)
Section 26 defines the authoritative data blueprint: conventions, tenancy rules, module tables, indexing, and delivery checklists. The detailed column and index summaries are captured in `/docs/DB_PLAN.md`; always reconcile code and migrations with §26 of the PDF.

#### 26.1 Conventions (Copilot must follow)
Engine/charset (InnoDB + utf8mb4), naming patterns, timestamps, soft deletes, multitenancy, enums, auditing, document storage, and indexing expectations.

### 26.2 Core Platform Tables
Tenant registry, platform identities, membership bridging, and Cashier billing scaffolding that every other module depends on.

#### 26.2.1 companies (tenant registry)
Defines tenant metadata, billing status, usage counters, and soft-delete lifecycle.

#### 26.2.2 users (platform identities)
Holds cross-tenant users, roles, auth credentials, and soft-delete flags with optional platform scope.

#### 26.2.3 company_user (multi-org membership)
Maps users to companies with role overrides and uniqueness constraints for shared org access.

#### 26.2.4 Billing (Laravel Cashier baseline)
Adopts Cashier migrations with company-level linkage; extend with company_id when gating subscriptions.

### 26.3 Directory & Supplier
Supplier directory tables (suppliers, supplier_documents) including FULLTEXT capability search and compliance document tracking.

### 26.4 Sourcing: RFQs, Quotes, Awards
RFQ master/detail, invitations, clarifications, and quote revisions underpinning sourcing workflows.

### 26.5 Purchasing: POs, Change Orders, Orders
Purchase order issuance, line items, change orders, and downstream order execution timelines.

### 26.6 Receiving & Quality
GRNs, inspection lines, and NCR tracking for non-conformance handling.

### 26.7 Invoicing & Match
Invoice headers, lines, and three-way match records including status enums and document attachments.

### 26.8 Documents & Media
Polymorphic document storage with versioning, hashing, and tenant-aware scopes.

### 26.9 Notifications & Preferences
User-facing notification feed and per-event channel preferences with digest settings.

### 26.10 API & Webhooks
Tenant API keys and outbound webhook subscriptions with scope and secret management.

### 26.11 Analytics & Governance
Usage snapshots, audit logging, retention holds, and company plan overrides for governance.

### 26.12 Indexing & Search Guidance
Composite, FULLTEXT, and cursor-friendly index requirements that all migrations must respect.

### 26.13 Cascade & Referential Rules
Delete/cascade strategy and soft-delete expectations for each aggregate.

### 26.14 Migration Order
Canonical migration sequencing for scaffolding databases and seed data safely.

### 26.15 Seeders (minimum)
Baseline tenant, supplier, sourcing, purchasing, fulfillment, invoice, notification, and API key fixtures.

### 26.16 Acceptance Checks (DB)
Database smoke criteria covering FK integrity, multitenancy enforcement, soft deletes, indexes, documents, auditing, retention, and Cashier readiness.

### 26.17 Laravel Model Notes
Model traits, relationships, and JSON casting guidance for aligning Eloquent with §26.

## Scope Traceability
Use this checklist to confirm every epic has matching features, migrations, tests, and docs before sign-off.

- [ ] Supplier Discovery & Directory
- [ ] RFQ / RFP Management
- [ ] Quote Intake & Comparison
- [ ] Awarding & Split Decisions
- [ ] Purchase Orders & Change Orders
- [ ] Order Execution & Tracking
- [ ] Receiving, Inspection & NCRs
- [ ] Invoicing & Three-Way Match
- [ ] Document & CAD Control
- [ ] Analytics & Operational Dashboards
- [ ] Integrations & Webhooks
- [ ] Notifications & Preferences
- [ ] Company & User Administration
- [ ] Billing, Plans & Entitlements
- [ ] Approvals Matrix & Delegations
- [ ] Global Search & Indexing
- [ ] Localization, Units & Currency
- [ ] Audit Trails & Governance
- [ ] AI & Copilot Assistants
- [ ] Platform Admin Console & Support
