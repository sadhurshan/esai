Supplier-Authored Invoicing

- Spec & Alignment
    - Extend REQUIREMENTS_FULL.md + invoicing.md describing supplier-authored flow: statuses (draft, submitted, buyer_review, approved, rejected, paid), field requirements, document rules, and notifications.
    - Capture open points (attachment limits, change requests) with TODO callouts until stakeholders confirm.

- Data & Domain
    - [x] Database migration: add created_by_type, supplier_company_id, submitted_at, reviewed_at, reviewed_by_id, status enum, and audit timestamps; index supplier_company_id + status, backfill existing records as buyer-authored.
    - [x] Update Eloquent models + factories to enforce company scoping, polymorphic ownership, and soft deletes.

- Backend Actions
    - Supplier endpoints (Inertia/API) for list/detail/create/update/submit invoices, guarded by policies referencing BelongsToCompany.
    - [x] Buyer review endpoints: approve, reject, request changes, add review notes; ensure transition validation and audit logging.
    - [x] Document uploads reuse existing virus-scan pipeline; queue notifications + webhooks per notification spec.

- Policies & Validation
    - [x] New FormRequest classes per action (supplier draft, supplier submit, buyer review) with spec-compliant validation.
    - [x] Policies: suppliers can CRUD draft invoices tied to their PO; only buyers may approve/reject; everyone must stay in tenant scope.

- Buyer Experience
    - [x] Update invoices list/detail pages to highlight supplier submissions, filter by status, and show review controls.
    - [x] Embed review actions (Approve, Reject, Request changes) with comment capture, timeline entries, and linked PO badge updates.

- Supplier Experience
    - Build supplier invoices pages (list, detail, create/edit wizard) under resources/js/pages/suppliers/invoices; include line entry, tax, attachments, and submission confirmation.
        - [x] Supplier invoice list view with filters, cursor pagination, and feature gating.
        - [x] Supplier invoice detail experience with buyer feedback + attachments.
        - [x] Draft/create wizard with line entry, tax, uploads, and submission confirmation.
    - [x] Provide visibility into status, buyer feedback, and resubmission flow if rejected.

- Integration Points
    - [x] Tie invoice timelines to PO timelines and receiving records to keep three-way matching intact.
    - [x] Update billing/feature gating so only plans with “supplier invoicing” expose UI/API; include metrics hooks for entitlements.

- Testing & QA
    - [x] Pest feature tests: supplier create/submit, unauthorized access, buyer approve/reject, audit log assertions (see [tests/Feature/Api/Supplier/SupplierInvoiceTest.php](tests/Feature/Api/Supplier/SupplierInvoiceTest.php)).
    - Frontend tests for new supplier forms and buyer review UI; Playwright flows covering draft→submit→approve and rejection cycle.
        - [x] Added Playwright smoke coverage ensuring supplier and buyer invoice dashboards render with filters ([tests/e2e/supplier-invoices.spec.ts](tests/e2e/supplier-invoices.spec.ts)).
    - [x] Seeder updates to generate sample supplier invoices for Storybook/Playwright scenarios ([database/seeders/DevTenantSeeder.php](database/seeders/DevTenantSeeder.php)).

- Rollout
    - [x] Migration script deployment plan with rollback strategy; seed initial permissions ([docs/launch_ready_cleanup/supplier-invoicing-rollout.md](docs/launch_ready_cleanup/supplier-invoicing-rollout.md)).
    - [x] Feature flag for gradual enablement; documentation updates (copilot-progress, changelog) and enablement guide for customers ([docs/launch_ready_cleanup/supplier-invoicing-enablement.md](docs/launch_ready_cleanup/supplier-invoicing-enablement.md), [CHANGELOG.md](../CHANGELOG.md), [docs/copilot-progress.md](docs/copilot-progress.md)).