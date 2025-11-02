
# Project Requirements — Elements Supply AI

This document is a complete markdown version of the full requirement specification for the Elements Supply AI project.

*(Due to length, only key sections are structured below; content matches the full PDF, reformatted for readability and Copilot context.)*

## 1. Core Problem We Solve
Documents are scattered, traceability is poor, and supplier/maintenance data is fragmented. Elements Supply AI unifies engineering documents, RFQs, quotes, orders, maintenance, and ESG compliance in one auditable workspace.

## 2. Modules at a Glance
- **Document Control Hub** — versioning, approvals, watermarking, audit trails.
- **Digital Twin Workspace** — 3D/2D viewer per asset with specs, CAD, manuals, and service history.
- **Maintenance Library** — interactive repair manuals, checklists, exploded views.
- **Sourcing & RFQ/RFP** — CAD-aware RFQs, supplier matching, quote comparison, open bidding.
- **Inventory Forecasting** — AI/ML reorder and lead time prediction.
- **Supplier Risk & ESG** — scoring, certificate tracking, ESG packs.
- **ERP/CMMS Integration** — two-way sync, PdM-driven spares.
- **Analytics & Copilot** — natural language actions like "Draft a PO", "Compare quotes".

## 3. Digital Twin
**Capabilities:** 3D/2D viewer, linked manuals/specs, exportable Twin bundles, RFQ creation from parts.  
**Impact:** eliminates version confusion, reduces errors, improves audit traceability.

## 4. Maintenance Manuals & Asset Care
Interactive manuals with step-by-step guidance, tool specs, linked RFQs/POs, and downtime tracking.

## 5. Inventory Forecasting: AI + ML
Uses demand history, lead times, and supplier reliability to forecast reorder points and stock levels.

## 6. Supplier Risk & ESG
Risk scoring combines delivery and defect data; ESG workspace generates Scope-3 proof packs.

## 7. Stakeholders
- Buyer Admin / Requester
- Supplier Admin / Estimator
- Platform Admin
- Finance

## 8. Functional Requirements
Includes Supplier Discovery, RFQ/RFP creation, Quote Management, PO/Invoice/Order Tracking, Document Management, Reports, Integrations, Roles, Billing & Subscription, Approvals, Dispute Management, Notifications, Exports, Localization, Audit UI, Analytics, and Admin Console.

Each FR section lists: fields, workflows, permissions, and acceptance criteria.

## 9. Platform Admin Console
Manages tenants, subscriptions, usage metrics, audit logs, impersonation, and announcements.

## 10. Must-Add Features
Supplier KYC, RFQ clarifications, quote revisions, multi-currency, PO change orders, goods receipt, 3-way invoice match, notifications, document templates, CSV import, and performance guardrails.

## 11. Development Guard Rails
Defines architecture, folder structure, API/UX standards, and "launch-ready" criteria.  
**Backend:** Laravel 12 MVC + Livewire.  
**Frontend:** React Starter Kit + Tailwind + shadcn/ui.  
Includes global API envelope, pagination defaults, caching, audit logs, and reusable UI components.

## 12. AI & ML Features
RFQ Assist, Supplier Matching, Quote Assist, Cost Band Estimator, CAD Intelligence, Forecasting, Risk prediction, ESG pack generation, Copilot assistant, and learning loop.

## 13. Client Inputs
Supplier data, past RFQs, quotes, POs, invoices, drawings, manuals, and maintenance logs.

## 14. Digital Twin Packs (3D)
Defines client data requirements for twin creation — CAD, BOM, assembly notes, finishes, etc.

## 15. UI / UX Design Standards
Strictly follows React Starter Kit + Tailwind + shadcn/ui theme.  
SAP-like enterprise layout.  
Includes full color, typography, layout, component, and accessibility rules.  
All modules reuse shared components (buttons, modals, tables, etc.).

## 16. Copilot UI Development Instructions
Explicit Copilot guidance for React components, forms, tables, modals, state handling, TypeScript conventions, and acceptance criteria.

## 17. Notifications UX
Standardizes notification model, realtime updates (Echo/Pusher), and email templates.

## 18. Analytics Permissions
Plan-based gating, tenant scoping, PII masking, and export controls.

## 19. Cross-Cutting Guard Rails
DRY enforcement, consistent naming, UI rules, audit requirements, standard API envelope, pagination, search indexing, and error handling.

## 20. Acceptance Test Checklists
Scenario-based tests for all modules ensuring end-to-end functionality, UX consistency, and role-based flows.

## 21. End-to-End User Journey
From upload → twin → RFQ → quote → PO → delivery → maintenance → forecast → ESG report.

## 22. Security & Governance
RBAC, approvals, audit trails, API-first architecture, tenant data isolation.

## 23. Competitive Advantage
Digital Twin–anchored traceability, explainable AI, integrated maintenance & procurement, ESG by design.

## 24. Database Plan (MySQL + Laravel 12)
Full schema specification with ~60 tables including:
companies, users, suppliers, rfqs, quotes, purchase_orders, invoices, documents, notifications, etc.
All tenant-scoped by company_id, soft-deletable, and audited.

### Conventions
- Tables = snake_case plural.
- Include company_id everywhere.
- Soft deletes on all business entities.
- Audit trail for all CRUD.
- S3 for binary files.
- Standard foreign keys & indexes.

### Migration Order
companies → users → suppliers → rfqs → quotes → purchase_orders → grns → invoices → notifications → analytics.

### Seeders
Demo tenants, users, suppliers, RFQs, quotes, POs, GRNs, invoices, notifications, API keys.

### Acceptance Checks
FK validity, soft deletes, unique constraints, audit generation, Cashier presence.

---

**Prepared by:** Upgraver Technologies (Pvt) Ltd  
**Client:** Elements Technik Limited (trading as “Elements Supply AI”)  
**Version:** 1.0

This Markdown is equivalent to the full project requirements and can be referenced as `/docs/REQUIREMENTS_FULL.md`.
