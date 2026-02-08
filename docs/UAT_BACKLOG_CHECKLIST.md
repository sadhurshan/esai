# UAT Backlog Checklist

Generated from docs/Backlog - Elements Supply AI - Backlog v1.0.csv.
Update this file by running tools/uat/generate-uat-checklist.mjs from the repo root.

## [AUTH] Authentication, Accounts & Roles
- [ ] AUTH1 — AUTH1 — Register an account
  - Form: name, email, password.
  - Validation + error states.
  - Success logs in (or email-verify if enabled) and redirects to onboarding.
- [ ] AUTH2 — AUTH2 — Log in / Log out
  - Valid creds → session established.
  - Invalid creds → error shown.
  - Logout clears session and returns to public/login.
- [ ] AUTH3 — AUTH3 — Roles are enforced per company
  - Role gates applied on routes/components.
  - Unauthorized → 403 (or redirect) + friendly message.
- [ ] AUTH4 — AUTH4 — Multi-org switching
  - “Switch organisation” menu shows all memberships.
  - Switching scopes data/permissions immediately.
- [ ] AUTH5 — AUTH5 — Email verification & password reset
  - send verify email on signup; locked until verified; forgot-password email.
- [ ] AUTH6 — AUTH6 — Signup creates buyer company + owner membership (buyer or supplier-first)
  - On successful signup, create: company record membership record for the user with role owner
  - Set initial company state: supplier_status = none (until super admin approves via SUPA2) company_profile_completed_at = null (until buyer wizard completed via ONB1)
  - If signup choice = Buyer (or choice not provided): Redirect to buyer onboarding wizard /onboarding/company (ONB1) Buyer features remain gated until onboarding completion (ONB2)
  - If signup choice = Supplier: Mark company as supplier-first (store onboarding/start mode on company) Do not redirect to buyer onboarding wizard Redirect to Supplier setup entry point (e.g., “Complete Supplier Application” / SUPA1) Supplier remains not visible in supplier directory until approved + public + profile complete (SUPD1/SUPD4) No plan selection / payment prompts are triggered for supplier-first onboarding (handled by BIL9)
- [ ] AUTH7 — AUTH7 — Signup captures company name
  - company name collected on signup OR prefilled in B1 wizard.
- [ ] AUTH8 — AUTH8 — Verify company email domain
  - Settings allow adding a domain (e.g., example.com) and give a DNS TXT or verification email method.
  - Once verified, domain is marked verified on company record.
  - Verification status affects invite and join flows (see next stories).
- [ ] AUTH9 — AUTH9 — Restrict invitations to verified domain (optional)
  - Toggle in company settings: “Allow invites only for emails on verified domain(s)”.
  - When enabled, invites to external domains are blocked with clear error.
  - Owners/super admins can still override using a special flow if required (with audit entry).
- [ ] AUTH10 — AUTH10 — Join existing company by domain
  - On signup, if email domain matches a verified company, user is offered “Request to join <Company>” instead of auto-creating a new tenant.
  - A join request is sent to that company’s admins (I1/I3 integration).
  - Admins can approve/deny join; approval attaches user to company and notifies them.
  - If denied, user may still create a separate company (with clear explanation).
- [ ] AUTH11 — AUTH11 — Choose Buyer vs Supplier at signup
  - Signup form includes a required choice: “Start as Buyer” or “Start as Supplier”.
  - Buyer path follows existing flow (creates buyer company + owner membership; routes to buyer onboarding).
  - Supplier path creates a company flagged as supplier-first (supplier_status=pending; supplier_profile_completed_at is null) and routes to Supplier Onboarding/Apply flow.
  - The chosen start mode is stored on the company (e.g., company_type=buyer|supplier or onboarding_mode).
  - User is created as company owner; RBAC works as per AUTH3.

## [ONB] Buyer Company Onboarding
- [ ] ONB1 — ONB1 — Company wizard after signup
  - Redirect to /onboarding/company until completed.
  - Fields include legal name, country, address, optional tax/registration, industry, default currency.
  - Completing sets company_profile_completed_at.
- [ ] ONB2 — ONB2 — Guard buyer features until onboarding
  - Middleware blocks access; shows banner/redirect to onboarding.
  - Invited users into an existing company bypass the wizard.
- [ ] ONB3 — Supplier-first tenants bypass buyer onboarding + buyer feature gating
  - If company start mode = Supplier, do not redirect to /onboarding/company (buyer wizard).
  - Buyer-only routes/components are hidden or blocked with a clear message + CTA (e.g., “Switch to Buyer mode / Complete buyer onboarding”).
  - Supplier Onboarding/Apply flow remains accessible while supplier is pending approval.
  - If a Supplier-first company later enables Buyer mode, buyer onboarding wizard applies as normal (ONB1/ONB2).
- [ ] ONB4 — ONB4 — Edit company profile
  - Editable fields with validation.
  - Changes audit-logged (who/when).

## [SUPA] Supplier Application & Approval
- [ ] SUPA1 — SUPA1 — Apply as Supplier (company-level)
  - “Apply as Supplier” entry point.
  - Collect supplier profile (capabilities, materials, locations, MOQ, lead time, certifications/quality standards, ESG/tax/bank docs).
  - Creates Supplier Application with status pending.
  - Sets supplier_profile_completed_at.
- [ ] SUPA2 — SUPA2 — Admin reviews supplier applications
  - Review queue list + detail page with documents.
  # UAT Launch Checklist (Production-Ready)

  Use this launch-focused checklist for critical UAT coverage. It prioritizes core flows, permissions, data safety, and go-live readiness. Each item should be verified for both Buyer and Supplier roles where applicable.

  ## 1) Access, Authentication, and Tenant Isolation
  - [ ] Login, logout, and session persistence across refresh/navigation.
  - [ ] Password reset and email verification flows function end-to-end.
  - [ ] Role-based access control: disallowed pages/actions return 403 or show gated UI.
  - [ ] Company scoping: user cannot access data from another company (verify list + detail pages).
  - [ ] Org switching (if multi-membership): data/permissions change immediately after switch.

  ## 2) Signup Paths (Buyer vs Supplier-first)
  - [ ] Buyer signup creates company + owner membership; redirects to buyer onboarding.
  - [ ] Supplier-first signup creates company with supplier-first flags; no buyer onboarding.
  - [ ] Company name captured and persists on company profile.
  - [ ] Supplier-first billing is suppressed (no plan selection or Stripe prompts).

  ## 3) Buyer Onboarding & Gating
  - [ ] Buyer onboarding wizard required until completed; completion sets company_profile_completed_at.
  - [ ] Buyer features are blocked until onboarding is completed (clear CTA + redirect).
  - [ ] Supplier-first tenants are not blocked by buyer onboarding.

  ## 4) Supplier Application & Approval
  - [ ] Supplier application form saves draft and submits; required fields validate.
  - [ ] Submitted application becomes read-only until decision.
  - [ ] Admin review: approve/reject with reason and notification.
  - [ ] Approved suppliers can access supplier actions; rejected suppliers see reason + resubmission path.
  - [ ] Supplier directory visibility requires approved + public + profile completed.

  ## 5) Supplier Directory (Public)
  - [ ] Directory list shows only approved + public + profile-complete suppliers.
  - [ ] Search + filters return correct suppliers and respect pagination.
  - [ ] Supplier profile page renders and “Send RFQ” CTA works.

  ## 6) RFQ Lifecycle (Buyer)
  - [ ] Create RFQ (ready-made) and submit successfully.
  - [ ] Create RFQ with CAD upload; file validation and secure access enforced.
  - [ ] Direct vs open bidding selection works; deadlines enforced.
  - [ ] Draft RFQ saved and later published; status transitions correct.
  - [ ] RFQ detail shows attachments, notes, supplier responses, and “Compare Quotes”.

  ## 7) RFQ Responses & Quotes (Supplier)
  - [ ] Supplier can view received/open RFQs (only if approved).
  - [ ] Submit quote from RFQ detail; status updates to “Quoted”.
  - [ ] Decline quote path works with optional reason.
  - [ ] Buyer can view quote list and comparison grid; sort by price/lead time.
  - [ ] Buyer accepts a quote; status and audit trail updated.

  ## 8) Purchase Orders & Orders
  - [ ] Buyer can create PO from accepted quote; details are correct.
  - [ ] Supplier can view PO details and acknowledge (accept/decline).
  - [ ] Buyer sees acknowledgement status.
  - [ ] Order status updates propagate and are visible to both sides.

  ## 9) Documents & Attachments
  - [ ] RFQ/Quote attachments download only for authorized users.
  - [ ] Uploaded documents enforce size/type limits and store securely.
  - [ ] Attachment access is logged.

  ## 10) Invitations & Team Management
  - [ ] Invite user flow works; invite email token accepts and joins correct company.
  - [ ] Role changes are permission-gated and take effect immediately.
  - [ ] Supplier-role invites are blocked until supplier approval.

  ## 11) Notifications & Emails
  - [ ] Key notifications fire (RFQ sent, quote received, quote accepted, PO sent/ack).
  - [ ] Notification links deep-link to the correct record and are permission-scoped.

  ## 12) UI/UX & Navigation
  - [ ] Sidebar shows correct menus for Buyer, Supplier, Admin (approval-gated).
  - [ ] Empty states show clear CTAs.
  - [ ] All pages reachable via navigation; no dead links.

  ## 13) Audit, Logs, and Compliance
  - [ ] Create/update/status-change actions generate audit entries.
  - [ ] Audit log view is accessible to authorized roles and filters correctly.

  ## 14) Performance, Errors, and Resilience
  - [ ] Key pages load without console errors; errors show friendly UI messages.
  - [ ] Form validation errors render inline and block submission.
  - [ ] File upload failures and network errors display clear guidance.

  ## 15) Launch Readiness Sanity
  - [ ] Production environment configuration set (URLs, queues, mail, storage).
  - [ ] Health endpoints green and background jobs running.
  - [ ] Data seed or demo data verified; no cross-tenant data leakage.
  - Comparison grid: supplier, price, lead time, notes, files.

  - Sorting by price/lead-time.
