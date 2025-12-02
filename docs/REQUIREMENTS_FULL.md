# **Project Requirements**

# Elements Supply AI

Version 1.0  
Prepared by: Upgraver Technologies (Pvt) Ltd  
Client: Elements Technik Limited (trading as “Elements Supply AI")

**Elements Supply AI** is a centralized, audit ready workspace that unifies all engineering documents, digital twins, maintenance manuals, parts catalogs, RFQ / RFP, quotes, orders, supplier risk, and ESG reporting. So operations, procurement, engineering, and sustainability teams work from a single source of truth. The SaaS marketing website will be the entry point, while an authenticated Supplier Portal powers procurement workflows and analytics. The platform is mobile friendly, SEO optimised, and designed to scale with integrations. Supplier authentication via blockchain is explicitly deferred to a later phase and will be covered in a separate proposal.

1. ## Core Problem We Solve

   1. Documents scattered across email, drives, ERPs, CMMS, and vendor portals  
   2. Low traceability from drawing \> quote \> PO \> delivery \> maintenance \> ESG proof  
   3. Delays and cost overruns due to missing specs, wrong parts, or poor supplier fit  
   4. Fragmented maintenance plans and spare pa forecasting, creating stockouts or excess stock  
   5. Rising compliance expectations (safety, certifications, and ESG)  
        
- Our solution: an AI and ML centralized platform where every asset/part, connecting all documents, decisions, and data streams end-to-end.

2. ## Modules at a Glance

   1. Document Control Hub: All files in one place with versioning, approvals, watermarks, traceable downloads, and audit trails.  
   2. Digital Twin Workspace: A living 3D/2D record per asset/part (specs, CAD, manuals, PM plans, sensors, service history).  
   3. Maintenance Library: In depth repair & maintenance manuals, interactive parts breakdowns, torque charts, exploded views, and checklists.  
   4. Sourcing & RFQ / RFP: CAD aware RFQs, supplier matching, quote comparison, open bidding, and PO creation.  
   5. Inventory & Spares Forecasting: AI/ML to set reorder points, predict lead time risk, and suggest “order by" dates.  
   6. Supplier Risk & ESG: Risk scoring, certificate tracking, corrective actions, and ready to share ESG / Scope-3 support docs.  
   7. ERP / CMMS Integration: Two-way sync with your systems; ingest sensor/condition data to drive PdM and spare pare kits.  
   8. Analytics & Copilot: Natural language actions (“Draft a PO", “Compare quotes", “Show ESG gaps") with explainable outputs.

3. ## Digital Twin

   1. ## Key capabilities

      1. Twin Packs (templates) by machine category (e.g., pumps, conveyors, gearboxes, hydraulic power units).  
      2. 3D/2D viewer (GLB/STEP/PDF drawings) with tagged parts. Click to open manuals, specs, or replacement part numbers.  
      3. RFQ/RFP from Twin: Auto attach current rev, tolerances, finishes, and recent QA notes.  
      4. Supplier Proposals to Twin: Quotes, process notes, QA certs, and warranty flow back to the twin.  
      5. Maintenance from Twin: PM schedules, work orders, spares consumed, failure events, and root cause analysis.  
      6. Exports: One click export of a Twin bundle (PDF datasheet \+ JSON \+ CAD) for audits or sharing with external partners.

   2. ## Business impact

      1. Eliminates version confusion and rework  
      2. Shortens quote cycles. Reduces quality escapes  
      3. Creates audit grade traceability for customer, ISO, and ESG demands

4. ## Maintenance Manuals & Asset Care

   1. Interactive manuals with step by step procedures, safety notes, tools & torque specs, and embedded parts diagrams.  
   2. Exploded parts views linked to part numbers and inventory availability. “Add to RFQ/PO" in one click.  
   3. Maintenance plans (daily/weekly/monthly/overhaul) with checklists and sign-off trails.  
   4. Downtime & cost capture (labor, parts, external services)  and “cost per asset" dashboards.

5. ## Inventory Forecasting: AI \+ ML

   1. **Data we use**

      1. Demand history (orders/usage), lead times, supplier performance  
      2. Twin attributes (material, finish, tolerance, criticality), planned shutdowns  
      3. Maintenance signals (PdM trends, upcoming overhauls)

   2. **How it works**

      1. For steady items: seasonal/time series models estimate demand.  
      2. For spare parts that are ordered only once in a while, we estimate how often orders happen and how big they are, then combine those to give a realistic forecast.  
      3. Feature rich ML adds supplier reliability, lead-time variance, and PdM signals to predict realistic safety stock and reorder timing.  
      4. The Copilot answers what ifs in plain English: “If we target 97% service level and switch to Supplier B, what’s the new safety stock and cash impact?"

   3. **Outputs**

      1. Reorder point, safety stock, suggested order quantity, order by date, and supplier choice recommendations plus 1-click RFQ/PO.

6. ## Supplier Risk & ESG

   1. Risk scoring blends on time delivery, defect/return rates, price/lead-time volatility, and service responsiveness.  
   2. Explainable badges (“High risk due to 3 late POs \+ missing ISO9001 \+ rising defect trend").  
   3. ESG workspace collects supplier attestations, certificates, and activity logs. Exports a Scope-3 support pack (evidence \+ calculations) for customers and auditors.  When a client asks “prove the suppliers are compliant and show the carbon impact," the platform can produce a single, audit-ready bundle in seconds.

	

7. ## Stakeholders & Personas

- Buyer Admin / Requester raises RFQs/RFPs, evaluates quotes, issues POs, tracks orders, uploads/consumes documents, marks payments.  
- Supplier Admin / Estimator maintains capabilities, receives RFQs, submits quotes with lead time and attachments. (Open bidding supported.)  
- Platform Admin does onboarding/verification, marketplace moderation, dispute support.  
- Finance (Buyer/Supplier) does invoice generation (auto/manual), payment status, reports generation.

8. ## Functional Requirements

   1. **FR-1 Supplier Discovery & Profiles**

1. Filters: capability, material, tolerance, finish, location, lead time range, rating threshold, certifications, industries.  
2. Sort: match score, lead time, rating, distance, price band.  
3. Profiles include capabilities, indicative lead time, rating, certificates, OTIF badge, MOQ, industries; sensitive  
4. edits require verification.  
5. AI Match ranks suppliers using capability fit \+ performance (lead time reliability, OTIF, win rate); shows rationale badges.

   2. **FR-2 RFQ / RFP Creation**

1. RFQ (fields): part specs, quantity, tolerance/finish, manufacturing method, delivery timeline, location, notes, CAD upload; option to make Open RFQ (Bidding).  
2. RFP (fields): problem/objectives, scope, timeline, evaluation criteria, proposal format.  
3. AI Assist: detect part type/materials, prefill fields, validate tolerances.  
4. If CAD parsing is uncertain, suggestions are flagged and fields remain user editable.  
   **Acceptance:**  
   Given an uploaded STEP file, the form auto-detects “Bracket L-Shaped" and “Aluminum," and requests quantity and delivery time before submission.

   3. **FR-3 Quote Intake (Supplier Side)**

1. Direct RFQs and Open RFQs appear in a unified inbox with deadlines/status.  
2. Quote form captures unit price, lead time, notes, and attachments (QA certs, datasheets, warranty terms, compliance).  
3. AI Quote Generation suggests pricing/lead time from history; suppliers can adjust.  
   **Acceptance:**  
   For RFQ \#1049, a supplier submits “USD 280, 5 days" and uploads Specs.pdf; buyer sees it alongside alternatives with timestamps.

   4. **FR-4 Quote Comparison & Award**

1. Buyer dashboard to compare quotes by price, lead time, rating, and notes; award and convert to next steps.  
2. Open RFQ clearly marks the bidding window and “makes your offer stand out".

   5. **FR-5 Purchase Orders (POs)**

1. Creation: both parties may generate POs; auto-creation on award \+ manual override.  
2. Approvals: buyer review/notifications; some cases require Elements team approval.  
3. Branding: PDF with Elements Supply AI) brand \+ buyer brand (“powered by Elements Supply AI").

   6. **FR-6 Invoicing & Payments (Tracking only)**

1. Invoice generation from PO (auto) with manual edits supported; supplier branding.  
2. Payment status tracking (Paid/Pending/Overdue), records per part/order. No online payment processing.

   7. **FR-7 Orders & Logistics**

1. Order states: Pending, Confirmed, In-Production, Shipped/In-Transit, Delivered, Cancelled; quantities, totals, timestamps.  
2. Buyer and supplier “Orders" views to track status history.

   8. **FR-8 Document & CAD Management**

1. Accepted file types: STEP/IGES/DWG/PDF and others; secure sharing with selected parties.  
2. Per order/project, store technical, commercial, QA, logistics, financial, and communication documents as listed by the client.

   9. **FR-9 Reporting & Dashboards**

1. Dashboard, advance analytics, supplier performance scorecards/risk alerts (by plan).  
2. Exportable reports (CSV/PDF): quote cycle times, win/loss, OTIF, invoice status, inventory forecast snapshots.

   10. **FR-10 Integrations**

1. One ERP/MRP integration available at certain plan tiers; generic import/export now, APIs extensible.

   11. **FR-11 Roles & Permissions**

1. Role matrix (examples): Buyer Admin/Requester, Supplier Admin/Estimator, Finance, Platform Admin with least-privilege permissions to RFQs, quotes, POs, invoices, orders, and documents.

   12. **FR-12 User & Company Lifecycle Management**

1. Company Registration and Access Flow

   1. When a new user registers, the system automatically creates:

      1. A user record (role \= owner).  
      2. A company record linked to that user. The company is initially treated as a buyer by default.  
      3. The company status \= pending\_verification until reviewed by a Platform Admin.  
2. After login, the owner sees both Buyer and Supplier dashboards (partially gated).

   1. Buyers can immediately perform buyer-side activities (create RFQs, invite suppliers, view quotes).  
   2. Supplier-side activities remain locked until the company is approved as a supplier.

3. Apply as Supplier Process

   1. The owner (or buyer admin) can click “Apply as Supplier” from Settings → Supplier Profile.  
   2. The application form collects additional supplier details (capabilities, materials, certifications, facilities, website, contact info).  
   3. Submitting the form creates a Supplier Application record (status \=pending\_review) and triggers an email/push notification to Platform Super Admins.  
   4. Admins review and approve or reject the application (KYC verification with uploaded documents).

4. Post-Approval Behavior

   1. Once approved (status \=approved), the company becomes dual-role: buyer \+ supplier.  
   2. The Supplier Profile appears in the public Supplier Directory and becomes eligible for RFQ invitations and open bidding.  
   3. If rejected (status \=rejected), the company cannot perform supplier actions or invite supplier roles.  
   4. Approval and status changes are logged in audit\_logs.

5. Role Behavior

   1. The original registrant (owner) has full rights to act as both Buyer Admin and Supplier Admin within their company.  
   2. Owners can invite additional users with specific roles: buyer\_admin, buyer\_requester, supplier\_admin, supplier\_estimator, finance.  
   3. If a company has not applied for or been approved as a supplier, the UI must hide or disable supplier-only menus and functions (Quote submission, Supplier Dashboard, etc.).

6. User Invitations & Roles:

   1. Company Admins can invite users via email and assign roles (Buyer Admin, Buyer Requester, Supplier Admin, Estimator, Finance).  
   2. Each invitation creates a pending user record with token expiration (48 hours).  
   3. Users can be deactivated, suspended, or reassigned without data loss.

7. Company Switching / Multi-Org Access:

   1. Certain users (consultants or service providers) may belong to multiple companies.  
   2. The UI provides a **“Switch Organization”** menu; permissions are scoped by the active tenant.

8. Domain Verification:

   1. Optional email-domain validation ensures all users under a company share a verified domain (e.g., *@[elementstechnik.com](http://elementstechnik.com)*).

9. Admin Verification and Directory Visibility

   1. Platform Admins must verify each company before supplier activities are enabled.  
   2. Only approved suppliers are listed in the Supplier Directory.  
   3. Buyers can search and invite only approved suppliers.

10. Database Additions

    1. companies.supplier\_status ENUM('none','pending','approved','rejected') DEFAULT 'none'  
    2. supplier\_applications (company\_id, submitted\_by, status, form\_json, reviewed\_by, reviewed\_at, notes)

11. Acceptance Tests

    1. AT-12.1 When a new user registers, a buyer company is created and awaits admin verification.  
    2. AT-12.2 When “Apply as Supplier” is submitted, a pending application appears for super admins.  
    3. AT-12.3 When an admin approves, the company is visible in the Supplier Directory and can submit quotes.  
    4. AT-12.4 When rejected or unapplied, supplier menus and actions are inaccessible.  
    5. **AT-12.5**: Until supplier approval, supplier menus are hidden/disabled and supplier-only endpoints return 403\.

12. Either add owner to users.role or state that “owner is stored in companies.owner\_user\_id and has both Buyer Admin \+ Supplier Admin privileges.”

    13. **FR-13 Billing and Subscription Plans (Stripe Integration)**

1. **Purpose:** Implement recurring billing, entitlements, and upgrade/downgrade workflows using **Laravel Cashier (Stripe)**.  
2. Plan Tiers

   

| Tier | Price | Intended Audience | Key Feature Limits |
| :---- | :---- | :---- | :---- |
| Starter | USD 2,400 / month | Single-site engineering firms or small OEMs | Up to 5 users, 10 RFQs per month, 3 GB storage, no ERP integration, basic analytics only. |
| Growth | USD 4,800 / month | Mid-size manufacturers | Up to 25 users, 100 RFQs per month, 25 GB storage, 1 ERP/CMMS integration, full reporting suite, custom document templates. |
| Enterprise | Custom quote | Large enterprises / multi-plant operations | Unlimited users & RFQs, unlimited storage (soft limits apply), multi-tenant groups, SSO (SAML/AD), priority support, dedicated instance option. |

   

   14. **FR-14 Feature Gating & Entitlements**

1. Each plan defines numeric limits (e.g., rfqs\_per\_month, users\_max, storage\_gb) stored in the plans table.  
2. Middleware EnsureSubscribed validates active subscription before allowing:

   1. RFQ creation, Quote submission, Document upload, or Integration access.  
3. When limits exceed thresholds, users see an upgrade prompt and can redirect to /pricing → Stripe Checkout.  
4. Stripe webhooks manage events invoice.payment\_succeeded, invoice.payment\_failed, and customer.subscription.updated.  
5. Grace period \= 7 days post failed payment → system enters “read-only” mode until payment resolves.

   15. **FR-15 Billing Portal & Invoices**

1. Each company may access /billing/portal (Stripe Customer Portal) to view history, update payment methods, download Stripe-generated invoices, or cancel.  
2. Subscription status displayed on the Account → Plan page (active, trialing, past\_due, cancelled).  
3. Admins can upgrade/downgrade without losing data; features lock/unlock automatically.

   16. **FR-16 Approvals Matrix and Delegations**

1. **Purpose:** Introduce configurable, hierarchical approvals for key transactions.

   1. Approval Levels: Up to five levels per company (defined by threshold rules or document type).  
   2. Supported Actions: RFQ publishing, PO creation, Change Orders, Invoice approvals, NCR closures.  
   3. Delegation: Approvers can assign temporary delegate with start/end date (“on leave” mode).  
   4. Tracking: Each approval/rejection records approver, timestamp, comments, and sequence.

   17. **FR-17 Dispute / Return Management**

1. Return Requests (RMA): Buyer can raise an RMA against a Delivered order with reason, photos, and proposed resolution.  
2. Credit Notes: Finance can issue credit against invoice with link to PO and GRN records.  
3. Workflow: Raised → Under Review → Approved/Rejected → Closed.  
4. Audit: All RMAs and credits are logged and reflected in supplier performance metrics.

   18. **FR-18 Global Search and Filtering**

1. Universal search bar spanning Suppliers, Parts, RFQs, POs, Invoices, and Documents.  
2. Advanced filters by status, date range, entity type, and tag.  
3. **Saved Searches** for frequently used queries.

   19. **FR-19 Notifications & Reminders**

1. Support recurring email and in-app notifications for:

   1. Upcoming RFQ deadlines  
   2. Late deliveries / shipments  
   3. Certificate expiry reminders  
   4. Overdue invoices  
2. Daily and weekly digest modes configurable per user.

   20. **FR-20 Data Export & Backup**

1. Admins can export complete company data (entities \+ documents metadata) as ZIP/JSON bundle.  
2. Automatic nightly backups to secure storage with 30-day retention.  
3. Export includes audit logs and plan metadata for compliance portability.

   21. **FR-21 Localization and Units**

1. Multi-language support (JSON translation files).  
2. Regional date formats and unit preferences (metric / imperial) stored per company.  
3. Currency formatting already covered under FR-9.6.

   22. **FR-22 Audit Trail User Interface**

1. Dedicated Audit Log screen (filter by entity, user, action, date).  
2. Export to CSV or PDF.  
3. “View Changes” modal showing before/after JSON diff for critical fields.

   23. **FR-23 Analytics & Performance KPIs**

1. Initial dashboards should include:

   1. RFQ Cycle Time (Average days from creation to award)  
   2. Supplier Response Rate (% RFQs quoted)  
   3. On-Time Delivery Rate  
   4. Quality Score (accepted vs rejected qty)  
   5. Spend by Supplier/Category per month  
   6. Forecast Accuracy (MAPE vs actual usage)  
2. Each metric is filterable by date range and company unit.

9. ## Platform Admin Console (System Admin Dashboard)

   Purpose: Internal console for platform operators to manage tenants, subscriptions, usage, entitlements, and audits.

   1. Scope

      1. Tenants (Companies): approve/suspend, view profile, owner, users, storage, regions.  
      2. Subscriptions (Stripe): plan, status, next invoice, payment history; upgrade/downgrade; override limits.  
      3. Usage & Entitlements: RFQs/month, storage used, active users, integrations; force refresh.  
      4. Audit Overview: cross-tenant audit feed with filtering and CSV export.  
      5. Optional: Impersonate tenant admin (read-only by default; write only for break-glass role).

   2. Data Model (new/updated)

      1. platform\_admins (id, user\_id, role enum: super|support, enabled\_bool)  
      2. companies add: status enum(active|suspended|pending), region, storage\_used\_mb, rfqs\_monthly\_used  
      3. company\_plan\_overrides (company\_id, key, value, reason, created\_by)  
      4. platform\_announcements (title, body\_md, visible\_from, visible\_to)

   3. UI

      1. Companies table → detail drawer (owner, plan, usage, actions).  
      2. Subscriptions tab → Stripe invoice list \+ “Open Billing Portal”.  
      3. Usage tab → cards: RFQs this month, storage, active users, API calls.  
      4. Audit tab → filterable log; Export CSV.  
      5. Actions: Suspend/Activate, Plan Override, Impersonate, Send Announcement.

   4. Permissions

      1. Role platform:super full access; platform:support read-only \+ announce.

   5. Jobs

      1. Nightly job to recompute usage (ComputeTenantUsageJob) and store snapshots.

   6. Acceptance Tests

      1. AT-20.1: Super admin can Suspend/Activate a company; status reflected in login & APIs.  
      2. AT-20.2: Plan override changes enforced immediately (e.g., rfqs\_per\_month \= 200).  
      3. AT-20.3: Audit grid filters by company, entity, date and exports CSV accurately.  
      4. AT-20.4: Impersonation issues a one-time token and logs start/stop events.  
      5. AT-20.5: Usage cards match snapshot table totals within ±1% for the day.

10. ## Must-add to avoid blockers

    

    1. Supplier Onboarding & KYC Verification

       1. Each supplier must maintain a **Company Profile** containing legal name, registration number, tax ID, address, country, phone, email, and website.  
       2. Suppliers upload compliance documents (e.g., ISO certificates, business registration, insurance certificates).  
       3. Each document has an **expiry date** and triggers an **automatic reminder** 180 days before expiry.  
       4. Admins can **approve or reject** suppliers after reviewing KYC documents; only *approved* suppliers can participate in RFQs or submit quotes.  
       5. A supplier’s approval status and document validity appear in search results and quote comparisons.

    2. RFQ Clarifications (Q\&A) and Amendments

       1. Every RFQ has a **Clarifications thread** visible to all invited suppliers (or public if “Open Bidding”).  
       2. Suppliers can post **questions**; buyers can **reply** or post **RFQ amendments** (e.g., specification changes or new attachments).  
       3. All clarifications and amendments are **timestamped** and **emailed** to participants.  
       4. Amendments increment an **RFQ version number** and log the reason for traceability.

    3. Quote Revisions and Withdrawals

       1. Suppliers may **edit or re-submit** a quote any number of times before the RFQ deadline.  
       2. Each revision creates a new **revision number** (v1, v2, v3 …) with previous revisions archived but readable by the buyer.  
       3. Suppliers can **withdraw** quotes before the deadline. The buyer sees the withdrawal timestamp and reason.

    4. Line-Item Awards and Split Awards

       1. Buyers can **award individual items** within an RFQ to different suppliers.  
       2. Each awarded line creates a separate **Purchase Order (PO)** referencing the parent RFQ.  
       3. Non-awarded suppliers automatically receive **“Regret Notice”** emails identifying which items they lost.

    5. RFQ Deadline and Lifecycle Management

       1. Each RFQ includes: **publish date, submission deadline, bid-opening date, close date**.  
       2. Buyers can **extend deadlines** with reason capture; system logs all changes.  
       3. Once the deadline passes, submissions lock automatically; no new or revised quotes accepted.  
       4. A **lifecycle timeline** displays RFQ creation → clarifications → closing → awards → POs.

    6. Multi-Currency and Tax Support

       1. RFQs, Quotes, POs, and Invoices store **currency code (ISO 4217\)** and **exchange rate note**.  
       2. Unit price and total amount fields are currency-specific.  
       3. Tax rate (%) and tax amount are captured per document; totals show *net, tax, gross*.  
       4. **Incoterms** (EXW, FOB, CIF, DDP etc.) and **Delivery Terms** fields appear in all commercial documents.

    7. PO Acknowledgement and Change Orders

       1. When a PO is issued, the supplier must **acknowledge** (Accept / Reject / Propose Change).  
       2. Proposed changes (e.g., revised price or lead time) create a **Change Order** record linked to the original PO with status history.  
       3. Once both parties approve the change, a new **PO Revision Number** is generated (PO-001-R1).

    8. Goods Receipt & Quality Inspection

       1. Buyers can record **Goods Receipt Notes (GRN)** per PO line (received qty, date, inspector).  
       2. Quality Inspection fields: *Accepted Qty, Rejected Qty, Defect Notes, Attachments*.  
       3. Rejected items flag the PO line as **“NCR Raised”** (Non-Conformance Report) for corrective action.  
       4. Partial receipts accumulate until the total ordered qty is fully received.

    9. Invoice Reconciliation (Three-Way Match)

       1. The system compares **PO amount, GRN received qty, and Invoice amount**.  
       2. Status values:  
          1. ✅ Matched (within tolerance)  
          2. ⚠️ Qty Mismatch  
          3. ⚠️ Price Mismatch  
          4. ❌ Unmatched  
       3. Discrepancies trigger a notification to Buyer Finance role for manual review.

    10. Audit Trail and Notifications

        1. All critical actions (create, update, delete, award, acknowledge, pay) generate **audit entries**: user, role, timestamp, entity, action, before/after values.  
        2. Emails and in-app notifications are dispatched for all workflow events (new RFQ, new quote, amendment, award, PO issue, invoice received, delivery update).  
        3. Admins can export the full audit log as CSV.

    11. Custom Document Templates & Branding

        1. Each company can design **custom templates** for RFQ, Quote, PO, Invoice, and Delivery Note PDFs.  
        2. Template editor allows uploading **company logo**, choosing **primary color**, adding **header/footer text**, and customizing **signature blocks**.  
        3. The template configuration is stored per company and applied automatically when documents are generated.  
        4. Buyers can **preview and download** sample documents to verify branding.  
        5. Admin can enforce a **standard layout** if required for multi-tenant consistency.

    12. Bulk Data Import via CSV

        1. Users can **import large data sets** (e.g., Suppliers, Materials, RFQ Items, Products, Inventory, Price Lists) using structured CSV files.  
        2. Each import screen provides a downloadable **template CSV** with column headers, sample values, and validation rules.  
        3. The import process performs **server-side validation** and generates a **row-by-row error report** (downloadable CSV or on-screen grid).  
        4. Successful rows commit automatically; failed rows can be corrected and re-uploaded.  
        5. All imports are logged with file name, timestamp, importer user, row count, success / fail count.

    13. Notifications and Access Control (Extension)

        1. Notification preferences (Email / In-App / Both) configurable per user and per event type.  
        2. Role-based access controls ensure:  
           1. **Buyers** see their own company’s RFQs, Quotes, POs, Invoices.

           2. **Suppliers** see only invited or open RFQs and their own quotes/POs.  
           3. **Finance roles** access Invoice and Payment modules only.  
           4. **Admins** view all records.

    14. Performance and Data Integrity

        1. All critical transactions use **DB transactions** to prevent partial writes.  
        2. Each entity includes **soft-delete** and **versioning** (revision number, revision reason).  
        3. File uploads are virus-scanned (stub hook ok) and stored with access-controlled URLs.  
        4. Audit and import logs have retention period configurable by Admin.

11. ## Development Guard Rails & General Functional Standards

    This section defines the **baseline development and interaction rules** that every feature, module, and component of Elements Supply AI must follow.

    These standards ensure that the system is launch-ready, maintainable, and consistent across all modules, while allowing fast iteration in early versions.

    1. Code Architecture Standards

       1. Framework Stack:

          1. Backend: Laravel 12 (MVC pattern, REST API endpoints, Livewire 3 optional for interactive forms)  
          2. Frontend: React Starter Kit with Tailwind CSS.  
          3. Database: MySQL.

       2. Componentization:

          1. Every UI element must be created as a reusable React component (e.g., InputField, SelectBox, CheckboxGroup, Table, Toast, Modal, FormCard).  
          2. Components should be generic, configurable, and stored in /resources/js/components/ui/.  
          3. Avoid inline or one-off code duplication.

       3. Folder Structure:

          1. /app/Models → Eloquent models  
          2. /app/Http/Controllers → API controllers  
          3. /resources/js/pages → React pages per module  
          4. /resources/js/components → Shared components  
          5. /resources/js/hooks → Reusable hooks for API calls, validation, toasts, etc.  
          6. /resources/js/services → Axios or Fetch wrappers for HTTP requests

       4. Coding Practices:

          1. Follow PSR-12 (Laravel) and Airbnb (React/JSX) style guides.  
          2. No hard-coded strings; use config constants or translation files.  
          3. All functions and props documented via JSDoc/PHPDoc.  
          4. Use environment variables for keys, endpoints, and secrets.

    2. Form Standards

       1. Validation:

          1. Every form must implement client-side and server-side validation.  
          2. Required fields marked with “\*”.  
          3. Invalid inputs show inline error messages directly beneath the field.  
          4. On submission, forms must re-check validation before sending the request.  
          5. Use shared validation hook (useFormValidation) for consistency.

       2. Error Display Section:

          1. Each form must include an error summary area at the top or bottom listing validation errors (especially useful for long forms).  
          2. Validation errors must scroll into view automatically.

       3. Loading & Submit States:

          1. All submit buttons disable during network calls and show a spinner.  
          2. Prevent double submissions or race conditions.

       4. Toast & Feedback:

          1. After every successful action, show a success toast.  
          2. For any failed action, show an error toast with the reason (from API response or fallback message).  
          3. Toasts should be globally available, positioned top-right, dismissible, and automatically disappear after 4–5 seconds.

       5. Accessibility:

          1. Forms must be navigable via keyboard.  
          2. Every field labeled with for and id attributes.  
          3. Focus returns to the first invalid field on failed submission.

    3. API & State Handling Standards

       1. HTTP Requests:

          1. Use a unified HTTP service (/resources/js/services/api.js) wrapping Axios/Fetch for consistent headers and error handling.  
          2. Include global interceptors for 401 (unauthorized), 404 (not found), and 500 (server) errors.  
          3. Implement exponential backoff on retry for transient errors.

       2. API Feedback:

          1. All API calls must return standardized JSON with status, message, and data.  
          2. Frontend must interpret these values for toast & UI states.  
          3. Log errors to the console only in dev mode.

       3. State Management:

          1. Use React Context or Zustand for global state (auth, user, company, notifications).  
          2. Keep module state isolated; no cross-module state pollution.

       4. Response Caching:

          1. Use Laravel cache for static lists (materials, incoterms, currencies).  
          2. In frontend, use SWR or query caching for recent data where appropriate.

    4. Notification & Communication Standards

       1. Push Notifications:

          1. Implement in-app notification center (bell icon) using WebSocket / Pusher / Laravel Echo.  
          2. Notifications must appear in real time without page reload.  
          3. Include type (info, success, warning, error), title, and description.

       2. Email Notifications:

          1. Each event triggers an email based on templates (RFQ Created, Quote Submitted, PO Awarded, Invoice Paid).  
          2. Emails must be responsive, branded, and stored as Blade templates.

       3. Smooth Experience:

          1. No duplicate alerts; mark read/unread with proper UX.  
          2. Push and email notifications must be consistent and synchronized (once per event).

    5. UI Behavior Guard Rails

       1. Every interactive element (button, icon, checkbox, link) must provide visual feedback (hover/focus/active).  
       2. All destructive actions (delete, cancel, reject) require confirmation modals.  
       3. Empty states must display meaningful illustration \+ CTA (e.g., “No RFQs yet – Create your first RFQ”).  
       4. Responsive Design: All screens must work seamlessly across ≥ 1280 px desktop, ≥ 768 px tablet, ≥ 360 px mobile.  
       5. Performance: Each page load \< 2 s on average Wi-Fi; lazy-load large data tables.  
       6. Tables: Sortable, paginated, with sticky headers and column filters.  
       7. Modals: ESC and outside-click must close them gracefully.  
       8. Tooltips: Use for truncations and help texts; must never block primary actions.  
       9. Timeline and Status Bars: All lifecycle changes (RFQ → Quote → PO → Delivery) visualized via consistent timeline component.

    6. Reusability & DRY Principle

       1. Shared Component Library: Maintain /resources/js/components/ui/ with all base UI elements (forms, modals, tables, cards).  
       2. Utility Functions: Common date/time, currency, and formatters stored in /utils/helpers.js.  
       3. Do Not Repeat: No duplicate code across modules; reuse and extend existing components.  
       4. Styling: Use Tailwind classes or component-level SCSS modules only – no inline CSS.

    7. Error Handling & Fallbacks

       1. Global Error Boundary: React error boundary to catch UI crashes and display user-friendly message.  
       2. Network Fallback: If offline, show “Connection lost” banner with auto-retry.  
       3. Graceful Fallbacks: If AI Assist or 3D Viewer is down, show “Feature temporarily unavailable.”  
       4. Empty API Response: Show placeholder cards instead of blank space.

    8. Development Priorities & Simplification

       1. Launch First, Harden Later: The primary goal is a working, usable MVP — not maximum security.  
       2. Security De-scoping (for now): Use standard Laravel auth, CSRF, and HTTPS only. Do not over-complicate with JWT rotation or zero-trust flows yet.  
       3. Focus on User Flow: Each module must work from start to finish without dead ends or broken links.  
       4. Performance Over Perfection: Prefer fast, readable code to over-engineered solutions.  
       5. Readable Code: Variable and function names must be self-explanatory. No abbreviations or obscure logic.  
       6. Testing Light: Basic feature tests and manual UAT for launch; full unit tests in later phase.

    9. Deployment & Environment Consistency

       1. Environment Variables: All configurations (API keys, URLs, Stripe IDs) in .env.  
       2. Build Consistency: CI/CD pipeline (auto-deploy to staging and production with branch triggers).  
       3. Logging: Centralized logs via Laravel Log \+ Monolog (file & Slack notifications for errors).  
       4. Monitoring: Basic uptime checks and error alerts enabled before launch.  
       5. Backup Automation: Nightly DB and file backups to secure storage.

    10. General User Experience Assurance

        1. Smooth Navigation: No page reloads for core actions – use AJAX or React state updates.  
        2. Progress Indicators: Show spinners or loading bars during long operations.  
        3. Undo Options: Provide undo for non-destructive actions (e.g., archive, mark read).  
        4. Consistent Terminology: Use standardized labels across all modules (“RFQ,” “PO,” “Invoice,” “Order”).  
        5. User Context: Breadcrumbs and page titles must always reflect the current location and entity ID.  
        6. Accessibility: All interactive elements must be reachable by keyboard and screen readers.  
        7. No Unexpected Navigation: Warn before navigating away with unsaved changes.

    11. Readiness Criteria

The application is considered “Launch Ready” only when:

1. All forms validate correctly with clear error messages.  
   2. All major actions show toasts (success/error/warning).  
      3. Notifications (pusher \+ email) work consistently.  
      4. CRUD operations function end-to-end without console errors.  
      5. Reusable components exist for every UI element.  
      6. Pages load in under 2 seconds on standard Wi-Fi.  
      7. No blocking security issues or data loss bugs exist.  
      8. QA sign-off from Buyer and Supplier roles is complete.  
           
         

12. ## AI  & ML Features

    1. **Design principles**

       1. Auditable by design. Every AI prompt, suggestion, and action is logged with user, time, and context so you can review who did what, when.  
       2. Human in the loop. AI proposes; users decide. Suggestions are always editable and reversible.  
       3. Graceful fallback. If AI is slow/unavailable, forms still work and show “hint unavailable."

    2. **RFQ Assist**

       1. Auto-prefill. Reads titles, descriptions, and attached files (PDF drawings, images, CAD filenames) to prefill fields like material, finish, process, quantity, and target lead time.  
       2. Gap & conflict checks. Flags missing items (e.g., tolerance not set) and conflicts (e.g., anodize \+ stainless) with clear, editable suggestions.  
       3. Why does it think so: Shows a brief rationale (e.g., “Detected ‘6061-T6’ from drawing note and file name").  
       4. User control. All fields remain editable; nothing is auto submitted without a person reviewing.

    3. **Supplier Matching**

       1. Fit over price. Ranks suppliers by capability, material, finish, location, and past outcomes (on time delivery, quality notes), plus other configured parameters (e.g., min order qty).  
       2. Rationale badges. Each match shows why it was picked (“5 similar jobs, avg 6-day lead time, 0 recent defects").  
       3. Filter aware. Respects user filters (country, certification) and plan rules.

    4. **Quote Assist**

       1. For suppliers: Suggests a price/lead time band based on similar past jobs on the platform, so suppliers can sanity check before submitting.  
       2. For buyers: Highlights outliers (too high/low) and calls out unusual lead times or MOQs with a short explanation.

    5. **Cost Band Estimator**

       1. Reasonable range: Estimates a target price band using process, material, region, and historical platform data.  
       2. Negotiation aid: Helps buyers spot inflated quotes and helps suppliers avoid under quoting.

    6. **CAD / Drawing Intelligence**

       1. Extracts basic hints from filenames and drawing text (material calls, finishes, common tolerance tags) to speed up RFQs.  
       2. Find similar parts: Surfaces “similar parts" previously quoted or ordered so teams can reuse the right vendors and reference prices.  
       3. Human check: Complex GD\&T still requires human review at launch.

    7. **Digital Twin Intelligence**

       1. Auto link: Quotes, process notes, QA certificates, and warranty terms are attached to the part/asset’s Twin automatically.  
       2. Action prompts: Suggests follow ups like warranty reminders, re-order of wear parts, or adding a supplier to the preferred list based on outcomes.

    8. **Forecasting & Inventory Insights**

       1. Lead time awareness: Builds supplier lead time variance into reorder points so recommendations reflect reality, not just averages.  
       2. Clear outputs: Proposes safety stock, order by dates, and suggested order quantities with a one line “why."  
       3. What ifs: Simple scenario testing (“If we switch to Supplier B, how do order by dates change?").

    9. **Supplier Risk (Predictive)**

       1. Multi signal view: Combines delivery variance, defect/return rates, and certificate/terms gaps to score risk.  
       2. Explainable badges: “Medium risk: 2 late POs, ISO9001 expired, defect trend."  
       3. Mitigation tips: Suggests actions (increase lead time, add secondary supplier, request cert update).

    10. **ESG Packs**

        1. One click evidence bundle: Assembles a Scope-3 support pack from the digital twin/BOM and supplier inputs: method used, emission factors referenced, basic calculations, and linked evidence.  
        2. Share ready. Exports PDF/CSV for customers and auditors; shows data sources and assumptions.

    11. **Copilot**

        1. Read & guide: Natural language answers to common questions:  
1. “Summarize quotes on RFQ-1049."  
2. “What’s overdue this week?"  
   2. Action with approval: Drafts operations that need sign off (RBAC enforced):  
1. “Draft a PO to Supplier C for 200 units at $275, 6-day lead time."  
   3. Traceable. All Copilot actions are logged; high-impact actions always require approval.

   12. **Learning Loop**

       1. Continuously improves: Feeds accepted quotes, delivery results, defects and cost bands, and forecasts.  
       2. Quality visible. Shows model accuracy (e.g., MAPE/MAE) and data freshness, so teams know when to trust a signal or collect more data.

13. ## Client Inputs & Resources Needed

    1. **Supplier Information**

       1. Supplier list (Excel is fine): name, country, contact, what they can do (processes), materials, finishes.  
       2. Certificates: PDFs like ISO or safety/environment certificates, with expiry dates.  
       3. Performance: on time delivery %, quality issues, notes.

    2. **Past Activity (to load sample data)**

       1. RFQs: titles/descriptions, due dates, attached files (drawings/CAD/PDFs).  
       2. Quotes received: supplier, price, lead time, date, any notes.  
       3. Purchase Orders (POs): PO number, items, promised date, terms.  
       4. Invoices: invoice number, amount, status (Paid/Pending/Overdue).  
       5. Shipments: tracking numbers and delivered dates.

    3. **Parts & Items (for ordering and forecasting)**

       1. Item list: part/SKU number, description, unit, preferred supplier, typical lead time.  
       2. Stock rules: minimum / maximum stock levels.  
       3. BOM: which parts belong to which product.

    4. **Drawings & Manuals**

       1. Files to store: CAD/drawings (STEP/IGES/DWG/PDF) and any repair / maintenance manuals.  
       2. Good file names help: e.g., BRACKET\_6061\_RevC.pdf. (Strictly no space)

    5. **Maintenance & Assets (for maintenance features)**

       1. Machine list: name, model, serial, location.  
       2. Planned checks: weekly/monthly tasks, checklists, safety notes.  
       3. Downtime history: when it stopped, why, how long, parts used.

    6. **ESG & Compliance**

       1. Policies or statements: supplier codes of conduct, environment policies.  
       2. Certificates & audits: related PDFs with expiry dates.  
       3. Carbon data: material weights, shipping distances, any calculation notes.

    7. **How to Send Us the Files**

       1. Preferred: shared drive link (or we’ll provide a secure folder).  
       2. Formats: Excel/CSV for lists; PDF/STEP/IGES/DWG/PNG/JPG for documents.  
       3. Clean data helps: remove duplicates and give each record a clear ID (e.g., supplier ID, RFQ ID).

14. ## Digital Twin Packs (3D) \- Client Inputs

    1. **What we need from you**

       1. Pack categories

          1. Examples: Pumps, Gearboxes, Conveyors, Hydraulic Power Units (HPU), Valves, Motors, Bearings, Blades/Knives, Custom Jigs/Fixtures.  
          2. For each category, confirm if it’s a standalone part or an assembly (with sub-parts).

       2. Representative items per category

          1. 2 \- 3 real examples per pack to model (the most common ones).  
          2. For each example: part number, name, short description, where it’s used.

       3. Geometry & measurements \- Provide any one of these (more is better)

          1. CAD/Drawing files (STEP/IGES/DWG/PDF).  
          2. Manufacturer datasheet (with dimensions).  
          3. Photos \+ a simple sketch with key dimensions (length/width/height, hole diameters, pitch, thickness, flange OD/ID, bolt circle, etc.).  
          4. Tolerances & surface finish if critical.

       4. Assembly/BOM

          1. Exploded view or list of sub-parts (name, part number, quantity, where it fits).  
          2. What sub-parts are wear parts (likely to be replaced) vs structural.

       5. Where the part belongs

          1. Which machine or line uses it (e.g., “HPU-0042 on Press Line A").  
          2. Any compatibility rules (e.g., “Blade type B only fits Chipper model X").

       6. Materials & finishes

          1. Material grade (e.g., 6061-T6, EN8), coating (e.g., black anodize), hardness/heat treatment.

       7. Variants

          1. Sizes or options (e.g., 25mm / 30mm bore; left/right-hand; voltage; thread type).

       8. Maintenance notes

          1. Basic PM/CM tasks (e.g., “Replace filter every 3 months," “Grease bearing weekly").  
          2. Safety notes, torque specs if you have them.

       9. Photos/labeling conventions

          1. A few clear photos (front, side, top; include a scale if possible).  
          2. How you name files/parts. Any specific pattern

    2. **Who does what (quick roles)**

       1. Client: choose categories, provide examples, upload files/measurements, confirm reviews.  
       2. Our team: model 3D/2D, map fields, build Twin Pack templates, set up RFQ/PO actions, and publish.

    

    We will not guess part details. The client provides categories, examples, and measurements. We convert them into easy to use 3D Twin Packs that let teams identify parts, attach documents, and create RFQs/POs in one click.

15. ## UI / UX Design Standards

    1. This section defines the complete visual language, layout structure, interaction principles, and accessibility rules for Elements Supply AI. These standards ensure that the user experience is consistent, efficient, and aligned with enterprise-grade (SAP-like) product design philosophies while remaining modern and lightweight.

    2. React Starter Kit Theme & Component Consistency

       The front-end implementation must strictly follow the existing React Starter Kit style and architecture provided with the Laravel 12 \+ React setup.

       1. Design Frameworks

          1. Primary UI framework: Tailwind CSS (for utility-based styling).  
          2. Component library: shadcn/ui (for forms, modals, toasts, and interactive components).  
          3. Icons: Iconify.  
          4. Layout engine: React Starter Kit default page and dashboard layouts.

       2. Implementation Rules

          1. Use Existing Layouts and Screens:

             1. Reuse the existing layouts, navigation, and authentication screens included in the React Starter Kit installation.  
             2. Extend or modify them only when functionally necessary.  
             3. When modified, maintain consistent spacing, typography, padding, and component structure as the

          2. Follow Established Visual Theme:

             1. Maintain the color palette, typography scale, border radius, shadows, and animations that ship with the Starter Kit.  
             2. Do not introduce third-party themes or excessive custom styling outside the Tailwind \+ shadcn/ui ecosystem.  
             3. Any new pages or components must visually blend seamlessly with existing ones.

          3. Component Reuse and Consistency:

             1. Use the shared components/ui/ directory for all reusable atoms and molecules (buttons, inputs, modals, cards, tables).  
             2. When creating new components, follow the naming conventions, prop structure, and class usage patterns of shadcn/ui.  
             3. Avoid inline CSS or ad-hoc Tailwind classes that break consistency.

          4. Layout Modifications:

             1. Minor modifications (e.g., sidebar links, dashboard cards) are permitted if required by functional changes.  
             2. All layout updates must retain the same **grid system, top bar height, sidebar width, and responsive breakpoints** used in the Starter Kit.

          5. Theming Rules:

             1. The default dark/light mode toggle (if present) should continue to function with all new pages.  
             2. All custom components must inherit theme variables (colors, fonts) from Tailwind’s configuration.

          6. Screen and Page Creation:

             1. When building new screens (e.g., RFQ, Quotes, Orders, Admin Console), use the existing layout wrappers (DashboardLayout, AuthLayout, MainLayout) for structural consistency.  
             2. Page headers, breadcrumbs, and action buttons must follow existing placement and alignment patterns.

          7. Accessibility and Responsiveness:

             1. All new UI elements must remain responsive across mobile, tablet, and desktop using the same breakpoint logic defined in the Starter Kit.  
             2. Maintain accessibility semantics (ARIA, tab navigation, focus outlines) from shadcn/ui defaults.

       3. Acceptance Tests

          1. AT-UI-01: All new screens use the same typography, spacing, and color palette as the Starter Kit baseline.  
          2. AT-UI-02: Modified layouts maintain consistent grid, sidebar, and toolbar structure.  
          3. AT-UI-03: No external CSS frameworks or conflicting themes introduced.  
          4. AT-UI-04: All components use shadcn/ui or are derived directly from its base styles.  
          5. AT-UI-05: UI remains responsive and consistent across devices (mobile/tablet/desktop).

    3. Design Philosophy

       1. Form Follows Function:

          1. The platform prioritizes information density, clarity, and traceability over decorative or playful design.  
          2. Every UI element should serve a functional purpose.

       2. SAP-Inspired Efficiency:

          1. Focus on business workflows—data tables, object pages, approval panels, timelines, and wizards.  
          2. Minimal animations, direct affordances (buttons, inputs, modals), and compact layouts.

       3. Consistency Over Customization:

          1. Use a **single shared component library** across all modules (RFQ, Quotes, POs, Documents, Analytics).  
          2. Avoid one-off styling; all pages inherit global design tokens.

       4. Predictability:

          1. Controls appear in the same place on every page.  
          2. Users should never need to guess how to perform an action.

       5. Enterprise Neutral:

          1. Colors and typography must remain professional and neutral to accommodate client branding overlays.

    4. Color System

| Category | Purpose | Default Color | Usage |
| :---- | :---- | :---- | :---- |
| Primary | Brand/Action | \#0E2230 | Buttons, links, icons, titles |
| Accent | Highlights/Active states | \#0073B1 | Selection, hover, focus |
| Success | Positive feedback | \#1A9E55 | Status: Paid, Delivered, OK |
| Warning | Attention required | \#E5A50A | Status: Pending, Overdue |
| Error | Critical or invalid | \#D92D20 | Validation errors, failed uploads |
| Background | Page | \#F5F7FA | Overall layout background |
| Card/Panel | Containers | \#FFFFFF | Tables, modals, forms |
| Borders | Dividers | \#E1E4E8 | Input outlines, table rows |
| Text Primary | Titles/labels | \#0E2230 | Headings, primary text |
| Text Secondary | Metadata/hints | \#6B7280 | Descriptions, timestamps |

       1. Use **only Tailwind CSS color tokens** mapped to these values.  
       2. High contrast (min. 4.5:1) is mandatory for all text/background pairs.  
       3. Dark mode optional, but ensure all colors can invert gracefully.

    5. Typography

       

| Element | Font Family | Weight | Size | Usage |
| :---- | :---- | :---- | :---- | :---- |
| Headings (H1–H3) | Manrope / Poppins | 600–700 | 24px–18px | Page & section titles |
| Body Text | Manrope | 400 | 16px | Default text |
| Small Text / Metadata | Manrope | 400 | 14px | Captions, labels |
| Button / Action Text | Manrope | 500 | 15px | Buttons, tags |

       

       1. Line height \= 1.5× font size.  
       2. Paragraph spacing \= 1.25× line height.  
       3. All text left-aligned, except numerical data (right-aligned).  
       4. Headings use consistent capitalization (Sentence case only).

    6. Layout System

       1. Grid

          1. 12-column responsive grid using Tailwind’s flex \+ grid utilities.  
          2. Max content width: 1440px, centered.  
          3. Gutter spacing: 24px desktop / 16px tablet / 8px mobile.  
          4. Padding inside sections: 32px top/bottom, 24px sides.

       2. Page Archetypes

          1. List Page (Table View)

             1. Sticky header with page title and primary actions (e.g., “Create RFQ”).  
             2. Filters and search bar top-aligned above the table.  
             3. Table: striped rows, hover highlight, 44–48px row height.  
             4. Pagination at bottom right.

          2. Object Page (Detail View)

             1. Header: key metadata (ID, title, status, supplier, total).  
             2. Tabs below header: Overview, Attachments, Timeline, Audit Log.  
             3. Right-side panel for quick actions (Approve, Print, Export).

          3. Wizard (Multi-Step Form)

             1. Stepper at top with “Next / Back” navigation.  
             2. Auto-save draft on step change.  
             3. Validation per step, not global.

          4. Dashboard (KPI/Analytics)

             1. 2–3 KPIs per row.  
             2. Chart style: minimalist (bar, line, pie only).  
             3. All widgets exportable as PNG or CSV.

          5. Timeline View

             1. Chronological status flow (e.g., Order: Pending → Shipped → Delivered).  
             2. Each event shows timestamp, user, role, and attachments.

    7. Components Library

       Each module must reuse components from a central library.

       Below outlines the full atomic component set.

       1. Atoms

          1. Buttons (primary, secondary, danger, ghost)  
          2. Icons (Iconify icon set, 16px/20px)  
          3. Inputs (text, number, date, select, file, textarea)  
          4. Checkbox / Toggle / Radio  
          5. Tooltip / Badge / Tag  
          6. Avatar / Initial bubble  
          7. Spinner / Skeleton loader

       2. Molecules

          1. Form group (label \+ input \+ helper text \+ error message)  
          2. Card (with header \+ content \+ footer)  
          3. Modal / Drawer  
          4. Notification Toast  
          5. Search bar  
          6. Tabs  
          7. Stepper (for wizard)  
          8. Empty state card (icon \+ text \+ CTA)  
          9. Table row (configurable columns)

       3. Organisms

          1. Data Table (sortable, paginated, with filter row)  
          2. Master-Detail Split Panel  
          3. Form Wizard (multi-step component)  
          4. Timeline View  
          5. Comment/Q\&A Thread  
          6. File Manager (grid \+ list toggle)  
          7. KPI Card  
          8. Audit Log Viewer

    8. Interaction & Behavior Rules

       1. Feedback & Loading

          1. All async actions show a spinner (centered or inline).  
          2. Toast appears for: success ✅, error ❌, warning ⚠, info ℹ.  
          3. Loading states use skeleton placeholders, not blank screens.

       2. Validation

          1. Inline error message below field: red text (\#D92D20).  
          2. Tooltip hints for formatting help.  
          3. “Next” or “Submit” disabled until validation passes.

       3. Hover & Focus States

          1. Hover: light blue background (\#E6EEF2).  
          2. Focus: 2px outline (\#0073B1).  
          3. Active: pressed effect (scale 0.98).

       4. Modals & Drawers

          1. Modal size: small (400px), medium (720px), large (1024px).  
          2. Always dismissible via “X” and ESC key.  
          3. No nested modals allowed (use drawers or tabs).

       5. Navigation & Breadcrumbs

          1. Top nav with product switcher and search.  
          2. Left sidebar: collapsible, icons \+ labels, persistent highlight on active route.  
          3. Breadcrumbs appear on every page (Home / RFQs / RFQ \#123).  
          4. Quick action toolbar (Export / New / Filter / More) top-right.

       6. Scroll & Pagination

          1. Virtual scroll for large tables (\>200 rows).  
          2. Pagination control bottom-right (Prev, Next, 1…10).  
          3. Infinite scroll allowed only for activity feeds.

       7. Notifications

          1. In-app notification bell (header) with unread count.  
          2. “View All” opens full history grouped by date.  
          3. Clicking a notification deep-links to its record.  
    9. Accessibility & Keyboard Navigation

       1. Every component must be operable by keyboard:  
          1. Tab \= focus next  
          2. Shift+Tab \= focus previous  
          3. Enter \= confirm  
          4. ESC \= cancel/dismiss  
       2. Use ARIA labels on all interactive elements.  
       3. Ensure focus order follows visual layout.  
       4. All icons require text equivalents (alt or aria-label).

    10. Empty States & Error Handling  
        

| Situation | Message Style | Example |
| :---- | :---- | :---- |
| No Data | Illustration \+ title \+ CTA | “No RFQs yet. Create your first RFQ.” |
| Validation Error | Inline red text \+ icon | “Delivery date cannot be earlier than today.” |
| System Error | Modal alert \+ retry button | “Something went wrong. Please try again.” |
| 404 / Access Denied | Full-page message \+ home link | “You don’t have permission to view this page.” |

        

    11. File Upload & Preview

        1. Accepted file types: .pdf, .docx, .xlsx, .stp, .iges, .dwg, .jpg, .png.  
        2. Max size: 50 MB per file.  
        3. Drag & drop zone with progress bar.  
        4. On hover: “View”, “Download”, and “Delete” icons.  
        5. PDF and images preview inline; CAD shows thumbnail \+ metadata.

    12. Mobile Design Standards

        1. Mobile menu as bottom navigation (Home, RFQs, Orders, Docs, Profile).  
        2. Use collapsible accordions instead of tables.  
        3. Floating “+” button for primary actions.  
        4. Swipe left to reveal contextual options (Edit, Delete).  
        5. Responsive touch area ≥44px.  
        6. Input forms auto-scroll to next field on submit.

    13. Theming & White Labeling

        1. Each company’s logo, colors, and footer text configurable under Settings → Branding.  
        2. PDF templates and emails automatically adapt to theme.  
        3. Default Elements Supply AI branding always retained in footer unless white-label license applied.

    14. Icons & Visual Language

        1. Use IconifyIcons (16px inline, 20px toolbar).  
        2. Status indicators:  
           1. 🟢 \= Success / Active  
           2. 🟠 \= Warning / Pending  
           3. 🔴 \= Error / Rejected  
           4. ⚪ \= Neutral / Draft  
        3. Avoid emoji or 3D icons in production UI (only internal mockups).

    15. Microinteractions & Motion

        1. Duration: 150–250ms max.  
        2. Easing: ease-in-out.  
        3. Use for hover, button press, expand/collapse, modal open/close.  
        4. Never animate data tables or core transactional elements.

    16. UX Writing & Label Standards

        1. Tone: professional, clear, neutral.  
        2. Avoid jargon; spell out abbreviations once (e.g., “PO (Purchase Order)”).  
        3. Button labels \= action verbs (“Create RFQ”, “Submit Quote”, “Download PDF”).  
        4. Error messages: human-readable, not technical.  
        5. Use title case for UI buttons and labels.

    17. Onboarding & Help

        1. Step-by-step guided onboarding for new users:  
           1. Highlight key modules (RFQ, PO, Docs).  
           2. Show sample data for demonstration.  
        2. “Help” icon opens side drawer with contextual documentation or link to Knowledge Base.  
        3. Chatbot (optional) provides contextual hints using natural language.

    18. Dashboard Design

        1. Minimal 3-row layout:  
           1. Row 1: KPIs (Spend, RFQs, Orders, On-time Delivery %)  
           2. Row 2: Open Tasks (Approvals, Pending Invoices)  
           3. Row 3: Recent Activity Feed  
        2. Use consistent widget sizes (300–400px width).  
        3. Allow drag-and-drop repositioning for Enterprise tier.

    19. User Feedback & Ratings

        1. After key actions (RFQ creation, Quote submission, Order closure), prompt short feedback modal:  
           1. “How was this process?” ★★★★☆  
           2. Optional comment field.  
        2. Aggregate UX metrics visible only to Platform Admin for continuous improvement.

    20. Design Governance

        1. All new pages/components must pass **UX Review Checklist**:  
           1. Alignment with spacing grid.  
           2. Accessibility test.  
           3. Component reuse compliance.  
           4. Consistent button placement.  
           5. Responsive validation.  
        2. Design system managed via version control.  
        3. Updates require version increment and changelog entry.

16. ## UI Development Instructions for Copilot

    This section defines the exact implementation rules and constraints for VS Code Copilot (and other AI-assisted code generation tools) when generating user interface components, screens, layouts, and elements for the Elements Supply AI application. The objective is to maintain a unified, professional UI consistent with the React Starter Kit, prevent redundant code, and ensure visual and behavioral consistency throughout the platform.

    1. General Guidelines

       1. Primary Stack

          1. Frontend framework: React (TypeScript)  
          2. Styling: Tailwind CSS  
          3. Component library: shadcn/ui  
          4. Icons: lconify

       2. Theme Consistency

          1. Always inherit **Tailwind configuration** (colors, spacing, typography, border-radius, shadows).  
          2. Avoid introducing new color codes or inline styles unless explicitly defined in Tailwind config.  
          3. Use **dark/light mode** toggles that already exist in the Starter Kit.

       3. Component Creation Rules

          1. Always prefer composition over duplication.  
          2. Reuse existing shadcn/ui components and pass props for variations.  
          3. Every form must use Form, Input, Select, and Button components from shadcn/ui.  
          4. Modals, alerts, tables, and toasts must also use standardized shadcn/ui components.  
          5. Place new shared components only under /ui/, and prefix with clear functional names.

    2. Code Generation Behavior

       1. Follow React Starter Kit structure and import existing layouts and base components.  
       2. Use TypeScript interfaces for props; no implicit any types.  
       3. Use functional components with hooks (useState, useEffect, useQuery, etc.).  
       4. Implement proper loading, empty, and error states using shared UI patterns.  
       5. Maintain accessibility (ARIA attributes, keyboard focus, semantic HTML).  
       6. Use Tailwind utility classes from the Starter Kit — never inline CSS or styled-components.  
       7. Generate reusable skeleton loaders and empty-state placeholders for all data-driven screens.  
       8. Keep UI text minimal and professional; follow tone defined in “UI/UX Writing Standards”.

    3. Visual and Interaction Consistency

       1. Page Layouts

          1. All new pages must use an existing layout (Dashboard, Auth, Main).  
          2. Primary actions (e.g., “Create RFQ”, “Submit Quote”) appear at the top-right of the header bar.  
          3. Secondary actions go under a “More” (⋮) menu.  
          4. Breadcrumbs appear at the top-left for navigation consistency.

       2. Tables and Lists

          1. Use a shared DataTable component for all tabular data.  
          2. Include pagination controls and bulk action support.  
          3. Default sorting: created\_at desc.

       3. Forms and Wizards

          1. Multi-step processes (e.g., RFQ creation, Quote submission) use Stepper components.  
          2. Show inline validation with Tailwind text-red-500 styles and error messages beneath fields.  
          3. Include success/error toasts on all submit actions.

       4. Toasts and Notifications

          1. All alerts, confirmations, and status messages must use the shared Toast component.  
          2. Toasts appear in the top-right and auto-dismiss after 4 seconds.

       5. Loading and Empty States

          1. Use skeleton loaders (gray shimmer placeholders) while fetching data.  
          2. Empty screens display an illustration \+ title \+ CTA button.

       6. Accessibility

          1. Ensure focus rings, keyboard tab navigation, and ARIA roles are present.  
          2. Buttons must be reachable and operable via keyboard.  
          3. Use readable contrast (WCAG AA+) for all text and icons.

    4. UI Code Structure & Standards

       1. Naming Rules

          1. Components: PascalCase (e.g., RfqForm.tsx)  
          2. Hooks: camelCase starting with use (e.g., useFetchQuotes.ts)  
          3. CSS utilities: kebab-case in Tailwind (e.g., bg-slate-100)  
          4. Routes: kebab-case (e.g., /rfqs, /purchase-orders)

       2. Code Quality

          1. Use ESLint \+ Prettier formatting.  
          2. Maintain clean imports and avoid dead code.  
          3. Enforce consistent prop order and indentation (2 spaces).  
          4. Avoid unnecessary console logs in production.

    5. UI Development Guard Rails

       1. No duplicated components across modules; all shared components live in /ui/.  
       2. Every interactive element must have hover/focus states.  
       3. Do not bypass layout wrappers; all pages must inherit from an approved layout.  
       4. Keep component size manageable (≤200 lines per file; split when needed).  
       5. No direct DOM manipulation — use React state/hooks.  
       6. Always use React Router for navigation (no window.location redirects).  
       7. Use axios or fetch from /services/api.ts — no ad-hoc HTTP calls.  
       8. When uncertain, prefer simplicity and follow existing Starter Kit patterns.

    6. Acceptance Tests

       1. AT-UI-06: All new screens visually match the React Starter Kit theme and shadcn/ui component style.  
       2. AT-UI-07: All actions use consistent spacing, fonts, and button placements.  
       3. AT-UI-08: No external CSS frameworks or inconsistent styling introduced.  
       4. AT-UI-09: Components compile without lint or type errors.  
       5. AT-UI-10: UI remains responsive and accessible at all breakpoints.

17. ## Notifications UX (Push \+ Email \+ Read State)

**Purpose:** Ensure real-time, reliable notifications with consistent read/unread state across channels.

1. Events (minimum)

   1. RFQ created/invitation; Quote submitted/withdrawn; Award/PO issued; GRN posted; Invoice status changed; Plan over-limit.

   2. Data Model

      1. notifications (id, company\_id, user\_id, type, title, body, entity\_type, entity\_id, channel enum: push|email|both, read\_at, meta json)  
      2. user\_notification\_prefs (user\_id, event\_type, channel, digest enum:none|daily|weekly)

   3. Realtime & Email

      1. Websocket via Echo/Pusher for push; queued mail for email; single event produces both when configured.

   4. UI

      1. Header bell with badge; Inbox panel (Today / Earlier) with Mark all as read.  
      2. Clicking an item deep-links to the record; read state toggles immediately (optimistic).

   5. Acceptance Tests

      1. AT-21.1: When a Quote is submitted, target buyer receives both push & email (if prefs=both) within 5s/2m respectively.  
      2. AT-21.2: Mark as read in the bell syncs with the Notifications page; page refresh persists.  
      3. AT-21.3: Changing preference to “daily digest” suppresses immediate emails and sends one summary at 18:00 local.  
      4. AT-21.4: Duplicate notifications are deduped per event id; never double-send.

18. ## Analytics Permissions & Data Guarding

**Purpose:** Ensure dashboards respect **role** and **plan**; prevent leakage across tenants or roles.

1. Access Rules

   1. Buyer Admin/Requester: sees only their company data.  
      2. Supplier roles: see only their own quotes, POs, on-time metrics for them (no buyer-internal spend).  
      3. Finance: financial dashboards only (AP/AR, invoice aging).  
      4. Platform Admin: cross-tenant system metrics only (never tenant PII unless impersonating).

   2. Plan Gating

      1. Starter: operational widgets only (counts, recent activity).  
      2. Growth: adds trend charts, supplier scorecards.  
      3. Enterprise: full suite \+ export \+ custom widgets; can schedule reports.

   3. Technical

      1. All analytics queries tenant-scoped by company\_id at repo/service layer.  
      2. PII masking in charts: supplier emails/usernames never shown—use display names or IDs.  
      3. Cached materialized views recomputed hourly; plan gating applied at query layer.

   4. UI

      1. Dashboard tiles show a lock icon with tooltip when gated by plan.  
      2. Export buttons hidden for non-eligible plans/roles.

   5. Acceptance Tests

      1. AT-24.1: Supplier viewing “Spend by Supplier” sees only their own totals or a gated screen.  
      2. AT-24.2: Starter plan cannot export CSV/PDF; Growth can; Enterprise can schedule.  
      3. AT-24.3: When plan upgrades, gated widgets unlock without logout; downgrade re-locks after webhook event.

19. ## Cross-Cutting Guard Rails & Consistency Rules

These rules apply to every module, page, API, and component. They exist to prevent duplication, reduce code size, enforce consistency, and guarantee smooth UX and reliable operations.

1. No Duplication (DRY) – Functions, Modules, Routes

   1. Rules

      1. Single Source of Truth: Any shared behavior (validation, toasts, date/price formatting, API calls) must live in a single reusable utility or component.  
         2. Route Uniqueness: Route names and paths must be unique. Routes must be declared in a central registry (backend \+ frontend) to prevent collisions.  
         3. Dedup Checks in CI: CI must fail on duplicated code \> 30 lines or 85% similarity.

      2. Enforcement

         1. Frontend: ESLint “no-duplicate-imports”, “no-duplicate-case”; JSCPD (or npx jscpd) for copy/paste detection.  
         2. Backend: PHPStan/Larastan \+ rector sets; Pest architectural tests to forbid controller-to-controller calls.

         3. Route registry files:

            1. Laravel: /routes/web.php, /routes/api.php with a route name prefix per module.  
            2. React: /resources/js/routes/index.ts exporting a ROUTES object (single source).

      3. Acceptance Tests

         1. AT-25.1: JSCPD score ≤ 3% duplication in PR.  
         2. AT-25.2: No route duplication (CI route linter passes).  
         3. AT-25.3: Shared helpers imported from /utils or /services, not re-implemented locally.

   2. Minimize Code & Keep It Simple

      1. Rules

         1. Prefer composition over inheritance in React components.  
         2. Keep files short: functions ≤ 50 lines; components ≤ 200 lines (split if larger).  
         3. Avoid premature abstractions; extract only when reused ≥2 places.

      2. Enforcement

         1. ESLint complexity cap ("complexity": \["error", 10\]), max-lines-per-function, max-lines-per-file.  
         2. PHP CS Fixer \+ Rector to simplify code.  
         3. CI fails if complexity or file length thresholds are exceeded.

      3. Acceptance Tests

         1. AT-25.4: Lint/format passes with zero warnings.  
         2. AT-25.5: No component exceeds agreed thresholds (CI gate).

   3. Launch-Ready Feature Criteria

      1. Rules

         1. Every feature must:

            1. a) Validate inputs (client \+ server)  
            2. b) Use shared toasts (success/error)  
            3. c) Update UI state immediately (optimistic or after re-fetch)  
            4. d) Create an audit entry  
            5. e) Include empty state & loading skeleton  
            6. f) Have at least one CSV export if relevant (tables)

      2. Acceptance Tests

         1. AT-25.6: For each feature PR, checklist includes validation, toast, state update, audit, skeleton, empty state. QA blocks merge if any missing.

   4. Notifications: Push \+ Email (Right User, Right Time)

      1. Rules

         1. All business events emit a single domain event → dispatcher fan-out to push/email per user prefs.  
         2. No double sends: dedupe by event id \+ recipient.  
         3. All notifications deep-link to the entity.

      2. Enforcement

         1. Central NotificationService only; no direct mail/push in feature code.  
         2. “Mark as read” syncs bell \+ inbox \+ mobile.

      3. Acceptance Tests

         1. AT-25.7: Event → push within 5s; email within 2m; read state consistent after refresh.  
         2. AT-25.8: Same event only notifies once per user.

   5. Smooth Authentication

      1. Rules

         1. Standard Laravel auth \+ CSRF; remember-me optional.  
         2. Sign-in \< 2 steps; error messages human-readable.  
         3. On sign-in, restore last organization \+ last visited module (if available).  
         4. 401/419 → unified re-auth dialog, preserves unsaved state if possible.

      2. Acceptance Tests

         1. AT-25.9: Re-auth returns user to the original page/action.  
         2. AT-25.10: Invalid credentials show clear inline error; no stack traces.

   6. State Updates & Data Freshness

      1. Rules

         1. Mutations must **optimistically update** UI (or re-fetch on success) and handle rollback on error.  
         2. Lists invalidate caches of affected queries (RFQs, Quotes, POs).  
         3. Detail pages refresh header stats after actions.

      2. Enforcement

         1. Use a standard useMutation/useQuery wrapper (SWR/React Query pattern or custom hooks).  
         2. No manual setState against stale copies; always go through the query cache.

      3. Acceptance Tests

         1. AT-25.15: After create/update/delete, the list and detail reflect changes without full page reload.  
         2. AT-25.16: Failed mutation rolls back optimistic UI and shows error toast.

   7. Consistency Across Pages/Components

      1. Rules

         1. All pages use shared UI tokens (colors, spacing, typography) and standard page archetypes (List, Object, Wizard, Timeline, Dashboard).  
         2. Primary actions placed top-right; secondary under “⋮”.  
         3. Confirmation modals for destructive actions; same copy system-wide.

      2. Acceptance Tests

         1. AT-25.17: Heuristic scan — action placements are consistent on RFQ/Quote/PO/Invoice pages.  
         2. AT-25.18: Button labels follow verb style (“Create RFQ”, “Submit Quote”, “Download PDF”).

   8. Routing & Naming Conventions

      1. Rules

         1. Backend routes: kebab-case, versioned for API (e.g., /api/v1/rfqs).  
         2. Frontend routes: centralized ROUTES object with typed params.  
         3. Naming: Models (Singular, PascalCase), tables (snake\_case plural), components (PascalCase), files (kebab-case).

      2. Acceptance Tests

         1. AT-25.19: /api/v1/\* only; no ad-hoc or unversioned APIs.  
         2. AT-25.20: Route params are validated (numeric ids, UUIDs) at controller request level.

   9. Error Handling & Observability

      1. Rules

         1. All API responses: { status, message, data } with appropriate HTTP codes.  
         2. Global error boundary in React; friendly fallback screens.  
         3. Log levels: error/warn/info; P1 alerts ping Slack/Email.

      2. Acceptance Tests

         1. AT-25.21: 4xx shows inline messages; 5xx shows retry & support link.  
         2. AT-25.22: Audit created for all create/update/delete (before/after snapshot).

   10. HTTP Status & Error Envelope

       1. All API endpoints must return a uniform response envelope:  
          {  
            "status": "success" | "error",  
            "message": "Human-readable explanation",  
            "data": { },  
            "errors": {  
              "field\_name": \["Error message…"\]  
            }  
          }  
        


| Code | Symbolic Name | Meaning |
| :---- | :---- | :---- |
| 200 | OK | Successful operation |
| 201 | Created | Resource created |
| 204 | NoContent | Deletion or empty response |
| 400 | BadRequest | Malformed input |
| 401 | Unauthorized | Auth required |
| 403 | Forbidden | Access denied |
| 404 | NotFound | Resource missing |
| 409 | DuplicateEntity | Conflict / already exists |
| 422 | ValidationError | Field-level failures |
| 429 | RateLimited | Too many requests |
| 500 | ServerError | Unhandled exception |

   11. Pagination Defaults

       1. All list endpoints must support the standard pagination contract:  
          ?page=1\&per\_page=25  
       2. Default: page=1, per\_page=25  
       3. Maximum: per\_page=100  
       4. Response Envelope: { data:\[\], meta:{ total, page, per\_page, last\_page } }  
       5. Sort Order: default created\_at DESC; must accept sort\_by and direction parameters.  
       6. Empty states and loaders should handle pagination gracefully in UI.

   12. Search Indexes (FULLTEXT Columns)

       1. Copilot must create FULLTEXT indexes and search scopes on these columns:  
          

| Table | Columns | Notes |
| :---- | :---- | :---- |
| suppliers | name, capabilities | Used in Directory search & filters |
| rfqs | title, material, method | Buyer global search |
| documents | filename, mime | Document manager lookup |
| quotes | note | Quick filter inside RFQ detail |
| audit\_logs | entity\_type, action | Admin console search |
| notifications | title, body | Inbox search |

          

       2. Search queries must be tenant-scoped (company\_id) and safe from SQL injection by using Laravel Query Builder search scopes.

   13. Email & Push Notification Templates

       1. All notification events use pre-defined Blade templates with matching subject lines.  
       2. Templates reside in resources/views/emails/\<module\>/.  
          

| Event | Template File | Email Subject | Push Title |
| :---- | :---- | :---- | :---- |
| RFQ Invitation Sent | emails/rfq/invitation.blade.php | New RFQ Invitation – {{ rfq.title }} | RFQ Invitation Received |
| Quote Received | emails/quote/received.blade.php | Quote Received for {{ rfq.title }} | New Supplier Quote Submitted |
| RFQ Awarded / PO Issued | emails/po/awarded.blade.php | Purchase Order {{ po.po\_number }} Awarded | PO Awarded to You |
| Invoice Paid | emails/invoice/paid.blade.php | Invoice {{ invoice.invoice\_number }} Marked Paid | Payment Received |
| Plan Over Limit | emails/billing/overlimit.blade.php | Plan Limit Reached – Action Required | Usage Limit Warning |
| System Announcement | emails/platform/announcement.blade.php | Important Notice from Elements Supply AI | Platform Announcement |

          

       3. Each email template must have a corresponding notification class and Blade view.

20. ## Shared Guard Rails (for Copilot & Devs)

    1. All new endpoints must return { status, message, data } and meaningful HTTP codes.  
    2. Every create/update/delete logs an audit record with before/after snapshot.  
    3. All screens use shared UI components (inputs, selects, tables, toasts, modals).  
    4. Every async action → spinner \+ toast (success/error).  
    5. Permissions checked in policy layer \+ route middleware; never only in the UI.  
    6. Add seeders & factories for happy-path demo data (admins, companies, keys, notifications).

20. ## Acceptance Test Checklists

    1. **Supplier Discovery & Profiles**

1. Given capability filters (process, material, location), when a buyer searches, then only matching, verified suppliers are listed.  
2. Given a supplier profile, when capabilities/certificates are updated, then changes require approval if flagged and appear in the audit log.  
3. Given a supplier card, when ‘Invite to RFQ’ is clicked, then the RFQ wizard opens prefilled with the supplier selected.  
4. 

   2. **RFQ / RFP Creation**

1. Given a CAD file (STEP/IGES) is uploaded, when the wizard runs, then part type/material suggestions display and required fields validate.  
2. Given an RFQ marked ‘Open’, when submitted, then it is visible to eligible suppliers until the bidding deadline.  
3. Given an RFP, when published, then suppliers can submit proposals with attachments and structured fields (price, lead time, more).

   3. **Quote Intake (Supplier)**

1. Given a supplier receives an RFQ, when opening, then the quote form shows price/lead time fields and attachment uploads.  
2. Given AI quote assist is enabled, when loading the form, then suggested price/lead time values populate with an edit option.  
3. Given a submission, when saved, then the buyer sees the quote in comparison view with timestamp and status.

   4. **Quote Comparison & Award**

1. Given at least two quotes, when viewing comparison, then price, lead time, rating, and notes columns appear sortable.  
2. Given a selected quote, when ‘Award’ is confirmed, then the PO creation flow triggers (auto or manual).  
3. Given an open RFQ deadline passes, when no quotes exist, then the RFQ auto closes and notifies the requester.

   5. **Purchase Orders**

1. Given an awarded quote, when auto PO is configured, then a PO PDF is generated with dual branding and unique number.  
2. Given manual PO mode, when drafted and submitted, then approvers receive notifications as per the approval matrix.  
3. Given a PO, when exported, then PDF and CSV formats are downloadable

   6. **Invoicing & Payment Tracking**

1. Given a PO exists, when ‘Generate Invoice’ is clicked, invoice data pre-fills from the PO and is editable before issue.  
2. Given an invoice status update, when marked Paid/Pending/Overdue, then order and finance dashboards reflect the new status.  
3. Given plan limits, when invoice count exceeds allowance, then the system blocks creation with an upgrade prompt

   7. **Orders & Logistics Tracking**

1. Given a PO is confirmed, when the supplier updates status to In Production or Shipped, then the order timeline shows the change and sends alerts.  
2. Given a tracking number is added, when saved, then it is visible to the buyer with a carrier link.  
3. Given a delivery is confirmed, when marked Delivered, then cycle time metrics update. 

   8. **Document & CAD Management**

1. Given a project/order, when a user uploads a document, then file type validation runs and permissions restrict access to authorised parties.  
2. Given a CAD file, when preview is requested, then a lightweight viewer or download is available.  
3. Given retention rules, when a project closes, then documents remain accessible per retention policy. 

   9. **Reporting & Dashboards**

1. Given user role and plan, when loading dashboards, then the correct widgets (spend, supplier performance, cycle times) appear.  
2. Given an export request, when CSV/PDF is chosen, then the file downloads with applied filters.  
3. Given period filters, when changed, then charts/tables refresh accordingly.

   10. **Integrations (Foundations)**

1. Given CSV import templates, when valid files are uploaded, then records are created or with error reporting for rejects.  
2. Given API tokens, when used, then endpoints authenticate and rate limits apply.  
3. When a given plan includes ERP/MRP integration, when connected, then data sync jobs are visible with logs.

   11. **Roles & Permissions**

1. Given RBAC rules, when a user without permission tries to access a module, then a denied message appears and is audited.  
2. Given a new role is assigned, when the user logs in, then navigation reflects allowed modules only.  
3. Given an audit requirement, when sensitive actions occur (award, cancel, delete), then audit entries record actor, time, and context.

21. ## End to End User Journey

    1. Upload drawings/CAD/manuals: platform creates or updates the Twin.  
    2. Need a spare part: From the manual/exploded view, add the part to RFQ/PO.  
    3. Supplier quotes arrive: platform ranks by fit, risk, lead time, and price.  
    4. Approve PO: goods received; QA docs stored on the digital twin.  
    5. Maintenance performed: spares consumed; downtime and costs logged.  
    6. Forecast updates safety stock: Copilot suggests “order-by" dates and supplier mix.  
    7. ESG pack compiles evidence for audits/customer requests.

22. ## Security, Governance, and Scale

    1. RBAC by role, project, and supplier  
    2. Approvals on every material action (min/max changes, PO creation, ESG release)  
    3. Full audit trails (who saw/downloaded/approved what, when)  
    4. API first architecture for ERP/CMMS integrations

23. ## Why We Stand Out

    1. Competitors focus on quoting or machining. We anchor everything: documents, sourcing, and maintenance, on a digital twin that lives beyond the purchase moment.  
    2. Manuals that drive transactions: Interactive manuals and exploded views link directly to parts lists, RFQs, and POs.   
    3. Maintenance integrated procurement: work orders automatically pre stage spares and suggest supplier switches before failure.  
    4. Explainable AI: Every recommendation (forecast, supplier, price band) shows why, with drill through to evidence (POs, QA docs, telemetry).  
    5. ESG by design: Supplier evidence collection, certificate aging alerts, and auto assembled ESG support packs, ready to share with customers.  
    6. Open architecture: Real ERP/CMMS, not just CSV imports, so the platform slots into existing operations without heavy change management.  
    7. Role based Copilot: Natural language commands that actually do work (draft RFQs/POs, compare quotes, generate ESG packs), with approvals and full audit trails.

24. ## Competitive Comparison

    **Legend:** ✔️ Yes | ◑ Partial/typical | X Not core / not typical 

| Capability | Protolabs | Xometry | SAP Ariba | GEP | Elements Supply AI |
| :---- | :---- | :---- | :---- | :---- | :---- |
| Central Document Control Hub (versioning, approvals, audit) | ✔️ | ✔️ | ✔️ | ✔️ | ✔️ |
| Digital Twin per asset/part (3D viewer, lifecycle) | X | X | X | ✔️ | ✔️ |
| Interactive Maintenance Manuals linked to parts & inventory | X | X | X | ✔️ | ✔️ |
| Exploded parts with click to RFQ/PO | X | X | ✔️ | ✔️ | ✔️ |
| Maintenance Plans | X | X | X | ◑ | ✔️ |
| CAD aware RFQ/RFP with supplier matching | ✔️ | ✔️ | ◑ | ✔️ | ✔️ |
| Quote comparison by fit/lead/price/risk with explanations | ✔️ | ✔️ | ✔️ | ✔️ | ✔️ |
| AI/ML Forecasting (safety stock, order by, lead time risk) | ◑ | ✔️ | ✔️ | ◑ | ✔️ |
| Supplier Risk (OTD, defects, term compliance) with badges | ◑ | ◑ | ✔️ | ✔️ | ✔️ |
| ESG Workspace (certs, Scope-3 support packs) | ✔️ | ✔️ | ◑ | ✔️ | ✔️ |
| ERP/CMMS two way sync (items, POs, WOs, costs) | ◑ | X | ✔️ | ✔️ | ✔️ |
| Role based Copilot that drafts RFQs/POs & ESG packs | X | ◑ | ◑ | ◑ | ✔️ |

# **26\. Database Plan (MySQL \+ Laravel 12\)**

This plan defines the canonical data model for Elements Supply AI.  
 All tables are **tenant-scoped** by `company_id` unless stated as platform-wide.

---

## **26.1 Conventions (Copilot must follow)**

* **Engine/Charset:** InnoDB, `utf8mb4`, `utf8mb4_unicode_ci`.

* **Naming:** tables \= `snake_case` plural; PK \= `id` (BIGINT UNSIGNED, AI); FKs \= `{singular}_id`.

* **Timestamps:** `created_at`, `updated_at`; soft deletes: `deleted_at` on business tables.

* **Multitenancy:** include `company_id` (BIGINT UNSIGNED, FK→`companies.id`) on all tenant data.

* **Enums:** use Laravel `enum()` (or string with CHECK) with constants in Eloquent models.

* **Auditing:** every create/update/delete emits an `audit_logs` row (before/after JSON).

* **Files:** store metadata in `documents` with object-morph; binaries in S3/local storage.

* **Indexes:** always index FKs; add composite indexes for common queries; add FULLTEXT where noted.

---

## **26.2 Core Platform Tables**

### **26.2.1 `companies` (platform tenant)**

* `id` BIGINT PK

* `name` VARCHAR(160) **unique**

* `slug` VARCHAR(160) **unique**

* `status` ENUM('pending','active','suspended')

* `region` VARCHAR(64)

* `owner_user_id` BIGINT FK→`users.id` NULL

* Usage: `rfqs_monthly_used` INT DEFAULT 0, `storage_used_mb` INT DEFAULT 0

* Billing: `stripe_id` VARCHAR(191) NULL, `plan_code` ENUM('starter','growth','enterprise') NULL

* `trial_ends_at` TIMESTAMP NULL

* Timestamps, Soft delete

**Indexes:** (`status`), (`plan_code`), (`owner_user_id`)

---

### **26.2.2 `users` (platform-wide)**

* `id` BIGINT PK

* `company_id` BIGINT FK→`companies.id` NULL *(platform admins have NULL)*

* `name` VARCHAR(160)

* `email` VARCHAR(191) **unique**

* `password` VARCHAR(255)

* `role` ENUM('buyer\_admin','buyer\_requester','supplier\_admin','supplier\_estimator','finance','platform\_super','platform\_support')

* `last_login_at` TIMESTAMP NULL

* Timestamps, Soft delete

**Indexes:** (`company_id`,`role`)

---

### **26.2.3 `company_user` (optional multi-org membership)**

* `id` BIGINT PK

* `company_id` BIGINT FK→`companies.id`

* `user_id` BIGINT FK→`users.id`

* `role` ENUM(…same as above…)

* Unique: (`company_id`,`user_id`)

* Timestamps

---

### **26.2.4 Billing (Laravel Cashier default)**

Create via `php artisan vendor:publish --tag=cashier-migrations`  
 Includes `subscriptions`, `subscription_items`, etc.  
 Link at **company level** (Company uses `Billable` trait).  
 Add `company_id` to `subscriptions` if you switch to company-centric billing.

---

## **26.3 Directory & Supplier**

### **26.3.1 `suppliers`**

* `id` BIGINT PK

* `company_id` BIGINT FK *(buyer’s tenant that manages directory entry; optional global)*

* `name` VARCHAR(160)

* `country` VARCHAR(2), `city` VARCHAR(120)

* `email` VARCHAR(191), `phone` VARCHAR(60)

* `website` VARCHAR(191) NULL

* `status` ENUM('pending','approved','rejected','suspended')

* `capabilities` JSON *(methods, materials, certs)*

* `rating_avg` DECIMAL(3,2) DEFAULT 0.00

* Timestamps, Soft delete

**Indexes:** (`company_id`,`status`), FULLTEXT(`name`,`city`,`capabilities`)

---

### **26.3.2 `supplier_documents`**

* `id` BIGINT PK

* `supplier_id` BIGINT FK

* `type` ENUM('iso','tax','insurance','registration','other')

* `document_id` BIGINT FK→`documents.id`

* `expires_at` DATE NULL

* `status` ENUM('valid','expiring','expired')

* Timestamps

**Indexes:** (`supplier_id`,`type`), (`expires_at`)

---

## **26.4 Sourcing: RFQ / Quotes / Awards**

### **26.4.1 `rfqs`**

* `id` BIGINT PK

* `company_id` BIGINT FK *(buyer)*

* `created_by` BIGINT FK→`users.id`

* `title` VARCHAR(200)

* `type` ENUM('ready\_made','manufacture')

* `material` VARCHAR(120) NULL

* `method` VARCHAR(120) NULL

* `tolerance_finish` VARCHAR(120) NULL

* `incoterm` VARCHAR(8) NULL

* `currency` CHAR(3) DEFAULT 'USD'

* `open_bidding` TINYINT(1) DEFAULT 0

* Lifecycle dates: `publish_at` DATETIME NULL, `due_at` DATETIME NULL, `close_at` DATETIME NULL

* `status` ENUM('draft','open','closed','awarded','cancelled') DEFAULT 'draft'

* `version` INT DEFAULT 1

* Timestamps, Soft delete

**Indexes:** (`company_id`,`status`,`due_at`), FULLTEXT(`title`)

---

### **26.4.2 `rfq_items`**

* `id` BIGINT PK

* `rfq_id` BIGINT FK

* `line_no` INT

* `part_name` VARCHAR(160)

* `spec` TEXT NULL

* `quantity` INT

* `uom` VARCHAR(16) DEFAULT 'pcs'

* `target_price` DECIMAL(12,2) NULL

* Unique: (`rfq_id`,`line_no`)

---

### **26.4.3 `rfq_invitations`**

* `id` BIGINT PK

* `rfq_id` BIGINT FK

* `supplier_id` BIGINT FK

* `invited_by` BIGINT FK→`users.id`

* `status` ENUM('pending','accepted','declined')

* Unique: (`rfq_id`,`supplier_id`)

---

### **26.4.4 `rfq_clarifications` *(Q\&A / amendments)***

* `id` BIGINT PK

* `rfq_id` BIGINT FK

* `user_id` BIGINT FK

* `kind` ENUM('question','answer','amendment')

* `message` TEXT

* `attachments_json` JSON (array of `{document_id, filename, mime, size_bytes, uploaded_by, uploaded_at}` linked to `documents.id`)

* `rfq_version` INT *(increment when amendment)*

* Timestamps

**Indexes:** (`rfq_id`,`kind`)

---

### **26.4.5 `quotes`**

* `id` BIGINT PK

* `company_id` BIGINT FK *(buyer for scoping)*

* `rfq_id` BIGINT FK

* `supplier_id` BIGINT FK

* `submitted_by` BIGINT FK→`users.id`

* Money: `currency` CHAR(3), `unit_price` DECIMAL(12,2), `min_order_qty` INT NULL

* `lead_time_days` INT

* `note` TEXT NULL

* `status` ENUM('draft','submitted','withdrawn','awarded','lost') DEFAULT 'submitted'

* `revision_no` INT DEFAULT 1

* Timestamps, Soft delete

**Unique:** (`rfq_id`,`supplier_id`,`revision_no`)  
 **Indexes:** (`rfq_id`,`supplier_id`,`status`)

---

### **26.4.6 `quote_items`**

* `id` BIGINT PK

* `quote_id` BIGINT FK

* `rfq_item_id` BIGINT FK

* `unit_price` DECIMAL(12,2)

* `lead_time_days` INT

* `note` VARCHAR(255) NULL

* Unique: (`quote_id`,`rfq_item_id`)

---

## **26.5 Purchasing: PO / Change Orders / Orders**

### **26.5.1 `purchase_orders`**

* `id` BIGINT PK

* `company_id` BIGINT FK *(buyer)*

* `rfq_id` BIGINT FK

* `quote_id` BIGINT FK

* `po_number` VARCHAR(40) **unique**

* Commercials: `currency` CHAR(3), `incoterm` VARCHAR(8) NULL, `tax_percent` DECIMAL(5,2) NULL

* `status` ENUM('draft','sent','acknowledged','confirmed','cancelled') DEFAULT 'sent'

* `revision_no` INT DEFAULT 0

* `pdf_document_id` BIGINT FK→`documents.id` NULL

* Timestamps, Soft delete

**Indexes:** (`company_id`,`status`), (`rfq_id`,`quote_id`)

---

### **26.5.2 `po_lines`**

* `id` BIGINT PK

* `purchase_order_id` BIGINT FK

* `rfq_item_id` BIGINT FK NULL *(for mapping to RFQ)*

* `line_no` INT

* `description` VARCHAR(200)

* `quantity` INT

* `uom` VARCHAR(16)

* `unit_price` DECIMAL(12,2)

* `delivery_date` DATE NULL

* Unique: (`purchase_order_id`,`line_no`)

---

### **26.5.3 `po_change_orders`**

* `id` BIGINT PK

* `purchase_order_id` BIGINT FK

* `proposed_by_user_id` BIGINT FK

* `reason` VARCHAR(255)

* `changes_json` JSON *(list of field changes)*

* `status` ENUM('proposed','accepted','rejected') DEFAULT 'proposed'

* `po_revision_no` INT *(applied revision number when accepted)*

* Timestamps

---

### **26.5.4 `orders` (execution status)**

* `id` BIGINT PK

* `company_id` BIGINT FK

* `purchase_order_id` BIGINT FK

* `supplier_id` BIGINT FK

* `status` ENUM('pending','in\_production','in\_transit','delivered','cancelled') DEFAULT 'pending'

* `tracking_number` VARCHAR(80) NULL

* `timeline` JSON *(array of {status, at, user\_id, note})*

* Timestamps

**Indexes:** (`company_id`,`status`), (`supplier_id`)

---

## **26.6 Receiving & Quality**

### **26.6.1 `grns` *(Goods Receipt Notes)***

* `id` BIGINT PK

* `company_id` BIGINT FK

* `purchase_order_id` BIGINT FK

* `received_by` BIGINT FK→`users.id`

* `received_at` DATETIME

* `note` VARCHAR(255) NULL

* Timestamps

### **26.6.2 `grn_lines`**

* `id` BIGINT PK

* `grn_id` BIGINT FK

* `po_line_id` BIGINT FK

* `received_qty` INT

* `accepted_qty` INT

* `rejected_qty` INT

* `inspection_note` VARCHAR(255) NULL

* `ncr_flag` TINYINT(1) DEFAULT 0

* Unique: (`grn_id`,`po_line_id`)

---

### **26.6.3 `ncrs` *(Non-Conformance Reports)***

* `id` BIGINT PK

* `company_id` BIGINT FK

* `po_line_id` BIGINT FK

* `raised_by` BIGINT FK→`users.id`

* `status` ENUM('raised','in\_review','corrective\_action','verified','closed') DEFAULT 'raised'

* `reason` VARCHAR(255)

* `documents_json` JSON NULL

* Timestamps

---

## **26.7 Invoicing & Match**

### **26.7.1 `invoices`**

* `id` BIGINT PK

* `company_id` BIGINT FK

* `purchase_order_id` BIGINT FK

* `supplier_id` BIGINT FK

* `invoice_number` VARCHAR(60)

* `currency` CHAR(3)

* `subtotal` DECIMAL(12,2)

* `tax_amount` DECIMAL(12,2) DEFAULT 0

* `total` DECIMAL(12,2)

* `status` ENUM('pending','paid','overdue','disputed') DEFAULT 'pending'

* `document_id` BIGINT FK→`documents.id` NULL

* Timestamps, Soft delete

**Indexes:** (`company_id`,`status`), (`supplier_id`)

---

### **26.7.2 `invoice_lines`**

* `id` BIGINT PK

* `invoice_id` BIGINT FK

* `po_line_id` BIGINT FK NULL

* `description` VARCHAR(200)

* `quantity` INT

* `uom` VARCHAR(16)

* `unit_price` DECIMAL(12,2)

---

### **26.7.3 `invoice_matches`**

* `id` BIGINT PK

* `invoice_id` BIGINT FK

* `po_id` BIGINT FK

* `grn_id` BIGINT FK NULL

* `result` ENUM('matched','qty\_mismatch','price\_mismatch','unmatched')

* `details` JSON

* Timestamps

---

## **26.8 Documents & Media**

### **26.8.1 `documents` *(polymorphic)***

* `id` BIGINT PK

* `company_id` BIGINT FK NULL *(platform docs can be NULL)*

* Polymorph: `documentable_type` VARCHAR(160), `documentable_id` BIGINT

* `kind` ENUM('rfq','quote','po','invoice','grn','ncr','supplier','template','other')

* `path` VARCHAR(255)

* `filename` VARCHAR(191)

* `mime` VARCHAR(120)

* `size_bytes` BIGINT

* `hash_sha256` CHAR(64)

* `version` INT DEFAULT 1

* Timestamps, Soft delete

**Indexes:** (`documentable_type`,`documentable_id`), (`company_id`,`kind`)

---

## **26.9 Notifications & Preferences**

### **26.9.1 `notifications`**

* `id` BIGINT PK

* `company_id` BIGINT FK

* `user_id` BIGINT FK

* `type` VARCHAR(120) *(event key)*

* `title` VARCHAR(160), `body` TEXT

* `entity_type` VARCHAR(160), `entity_id` BIGINT

* `channel` ENUM('push','email','both') DEFAULT 'both'

* `read_at` DATETIME NULL

* `meta` JSON

* Timestamps

### **26.9.2 `user_notification_prefs`**

* `id` BIGINT PK

* `user_id` BIGINT FK

* `event_type` VARCHAR(120)

* `channel` ENUM('push','email','both','none') DEFAULT 'both'

* `digest` ENUM('none','daily','weekly') DEFAULT 'none'

* Unique: (`user_id`,`event_type`)

---

## **26.10 API & Webhooks**

### **26.10.1 `api_keys`**

* `id` BIGINT PK

* `company_id` BIGINT FK

* `name` VARCHAR(120)

* `token_hash` CHAR(64)

* `scopes` JSON

* `last_used_at` DATETIME NULL

* `revoked_at` DATETIME NULL

* `created_by` BIGINT FK→`users.id`

* Timestamps

### **26.10.2 `webhooks`**

* `id` BIGINT PK

* `company_id` BIGINT FK

* `url` VARCHAR(255)

* `event_filters` JSON

* `secret` VARCHAR(191)

* `active` TINYINT(1) DEFAULT 1

* Timestamps

---

## **26.11 Analytics & Governance**

### **26.11.1 `usage_snapshots`**

* `id` BIGINT PK

* `company_id` BIGINT FK

* `date` DATE

* `rfqs_count` INT

* `quotes_count` INT

* `pos_count` INT

* `storage_used_mb` INT

* Unique: (`company_id`,`date`)

### **26.11.2 `audit_logs`**

* `id` BIGINT PK

* `company_id` BIGINT FK NULL *(platform actions may be NULL)*

* `user_id` BIGINT FK NULL

* `entity_type` VARCHAR(160), `entity_id` BIGINT

* `action` ENUM('create','update','delete','award','acknowledge','status\_change','login','impersonate')

* `before` JSON NULL, `after` JSON NULL

* `ip` VARCHAR(64), `ua` VARCHAR(255)

* `created_at` TIMESTAMP

**Indexes:** (`company_id`,`entity_type`,`entity_id`), (`action`,`created_at`)

---

### **26.11.3 `retention_holds`**

* `id` BIGINT PK

* `company_id` BIGINT FK

* `entity_type` VARCHAR(160), `entity_id` BIGINT

* `reason` VARCHAR(255)

* `active` TINYINT(1) DEFAULT 1

* Timestamps

* Unique: (`company_id`,`entity_type`,`entity_id`,`active`)

---

### **26.11.4 `company_plan_overrides`**

* `id` BIGINT PK

* `company_id` BIGINT FK

* `key` VARCHAR(80) *(e.g., rfqs\_per\_month, users\_max, storage\_gb)*

* `value` VARCHAR(80)

* `reason` VARCHAR(255)

* `created_by` BIGINT FK→`users.id`

* Timestamps

* Unique: (`company_id`,`key`)

---

## **26.12 Indexing & Search Guidance**

* Always index (`company_id`, `status`) on transactional tables.

* Add FULLTEXT on `suppliers.name`, `suppliers.capabilities`, `rfqs.title`.

* Composite examples:

  * `quotes (rfq_id, supplier_id, status)`

  * `purchase_orders (company_id, status)`

  * `orders (supplier_id, status)`

  * `notifications (user_id, read_at)`

* For analytics speed, consider **materialized views** (or summary tables) for monthly counts.

---

## **26.13 Cascade & Referential Rules**

* **ON DELETE CASCADE** from parent → children where safe:

  * `rfqs` → `rfq_items`, `rfq_invitations`, `rfq_clarifications`, `quotes` (or soft-delete preferred)

  * `quotes` → `quote_items`

  * `purchase_orders` → `po_lines`, `po_change_orders`, `orders`

  * `grns` → `grn_lines`

  * `invoices` → `invoice_lines`, `invoice_matches`

* **Restrict** when legal record must persist (e.g., invoices). Prefer **soft delete** for business entities.

---

## **26.14 Migration Order (generate in this sequence)**

1. `companies`, `users`, `company_user`

2. Cashier billing tables

3. `Suppliers`, `supplier_applications,` `supplier_documents`, `documents`

4. `rfqs`, `rfq_items`, `rfq_invitations`, `rfq_clarifications`

5. `quotes`, `quote_items`

6. `purchase_orders`, `po_lines`, `po_change_orders`, `orders`

7. `grns`, `grn_lines`, `ncrs`

8. `invoices`, `invoice_lines`, `invoice_matches`

9. `notifications`, `user_notification_prefs`

10. `api_keys`, `webhooks`

11. `usage_snapshots`, `audit_logs`, `retention_holds`, `company_plan_overrides`

*(Copilot: create factories/seeders immediately after each domain group.)*

---

## **26.15 Seeders (minimum)**

* `CompanySeeder` (1 demo tenant \+ plan\_code)

* `UserSeeder` (buyer admin, supplier estimator, finance)

* `SupplierSeeder` (8 suppliers with capabilities JSON)

* `RfqSampleSeeder` (3 RFQs, items, invitations)

* `QuoteSampleSeeder` (quotes from 3 suppliers, with items)

* `PoSampleSeeder` (1 PO \+ lines \+ order timeline)

* `GrnInvoiceSeeder` (1 GRN with partial/accepted/rejected; 1 Invoice \+ match)

* `NotificationSeeder` (recent events)

* `ApiKeySeeder` (scoped key)

---

## **26.16 Acceptance Checks (DB)**

* AC-DB-01: All FK constraints valid; inserts/updates fail on invalid FK.

* AC-DB-02: Multitenancy enforced — **no** row without correct `company_id` on tenant tables.

* AC-DB-03: Soft deletes present on RFQ/Quote/PO/Invoice/Supplier and cascade to children through application logic.

* AC-DB-04: Unique constraints prevent duplicates: `po_number`, (`rfq_id`,`line_no`), (`quote_id`,`rfq_item_id`), (`supplier_id`,`type` in docs), (`company_id`,`key` in overrides).

* AC-DB-05: Indexes exist for FK columns and composite lookups listed in §26.12.

* AC-DB-06: Document links valid; `documents.documentable_*` matches existing rows.

* AC-DB-07: Audit rows created on create/update/delete with non-empty `after` (and `before` when update/delete).

* AC-DB-08: Retention: records older than policy are marked archived or removed by job (test on sandbox clock).

* AC-DB-09: Cashier tables present; subscription for company can be created and queried.

---

## **26.17 Laravel Model Notes (for Copilot)**

* Add `BelongsToCompany` trait to tenant models to auto-scope by `company_id`.

* Define relationships:

  * `Rfq hasMany RfqItem, hasMany Quote, hasMany RfqClarification`

  * `Quote belongsTo Rfq, belongsTo Supplier, hasMany QuoteItem`

  * `PurchaseOrder belongsTo Rfq, belongsTo Quote, hasMany PoLine, hasMany PoChangeOrder, hasOne Order`

  * `Invoice belongsTo PurchaseOrder, hasMany InvoiceLine, hasOne InvoiceMatch`

  * `Document morphTo documentable`

* Use `casts` for JSON fields (`capabilities`, `timeline`, `changes_json`, `meta`).

## 26.18 Registration & Supplier Approval Amendments

Add the following data-model clarifications to support the buyer / supplier dual-role registration flow:

1. Company-level flags

   1. Extend companies with a field to record supplier approval state (supplier\_status ENUM('none','pending','approved','rejected')).  
   2. Add optional verified\_at, verified\_by, and is\_verified boolean to indicate platform admin verification.  
   3. Keep existing buyer behaviour unchanged – all new companies default to buyer status until supplier approval.

2. Supplier-application tracking

   1. Introduce a lightweight table supplier\_applications to store each company’s supplier application request, reviewer, timestamps, and JSON form payload.  
   2. One active pending record per company at a time.

3. User roles linkage

   1. Owners created at registration act as both buyer and potential supplier admins once the company is approved.  
   2. No schema change required for roles; just ensure role validation respects companies.supplier\_status.

4. Visibility rules

   1. Supplier Directory queries must return only companies with supplier\_status \= 'approved' and is\_verified \= 1\.  
   2. Pending or rejected suppliers are excluded from invitations and RFQ listings.

5. Auditing and workflow

   1. All supplier-application submissions, approvals, and rejections must generate entries in audit\_logs (entity \= SupplierApplication).  
   2. Status transitions must follow the same retention and soft-delete policies as other transactional entities.

6. Notification hooks

   1. Queue notifications on supplier-application create, approve, and reject events (email \+ push to owner).  
   2. Use the existing notifications schema; no new table required.

## 

## Approval

By approving via email, the client acknowledges that they have reviewed and agreed to the requirements outlined in this document. The client affirms that all details provided accurately reflect their needs and expectations, and they authorize the service provider to proceed with the project based on the specified requirements.

