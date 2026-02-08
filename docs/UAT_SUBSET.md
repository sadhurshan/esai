# UAT Subset

Generated from docs/UAT_BACKLOG_CHECKLIST.md.
Generated: 2026-01-30T05:25:19.325Z
Selected: 20 of 388 pending item(s).

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
  - Approve → company supplier_status=approved, supplier_approved_at set; optional “List publicly” toggle.
  - Reject → supplier_status=rejected + reason + notification.
- [ ] SUPA3 — SUPA3 — Block supplier features until approved
  - Middleware on supplier endpoints (RFQ respond, quotes, PO receive).
  - UI disables supplier actions with explanation banner.
- [ ] SUPA4 — SUPA4 — Supplier directory visibility toggle
  - Toggle only if approved.
  - Directory uses approved + public + profile_completed filter.
- [ ] SUPA5 — SUPA5 — Supplier-first post-login status + “Complete Supplier Application” CTA
  - After login, supplier-first companies see a persistent status banner/card: Not submitted / Submitted / Under review / Approved / Rejected.
  - Primary CTA: “Complete Supplier Application” → supplier application flow (reuses SUPA1 form/fields).
  - If Submitted/Under review: show “Please wait for approval” message + what’s allowed vs blocked.
  - If Rejected: show reason and CTA “Update & Resubmit”.
  - Supplier actions remain blocked until approved (SUPA3); directory listing remains hidden until approved + public + profile complete (SUPD1/SUPD4).

