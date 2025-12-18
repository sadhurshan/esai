Here’s the implementation plan to let an owner operate both as buyer and supplier without breaking the tenant model:

- Data model
    - [x] Add supplier_capable (boolean) plus default_supplier_id to users so we know who can switch personas and which supplier record they control.
    - [x] Introduce supplier_contacts (or reuse existing supplier relation if available) that links user_id, company_id (buyer tenant), and supplier_id. This is the pivot we’ll hydrate when a buyer invites that user’s supplier identity. All queries stay tenant-scoped via company_id.
    - [x] Backfill existing owners who should be supplier-capable and create migration/seed logic to generate the mapping on invite.

- Auth/session plumbing
    - [x] Extend the auth bootstrap (useAuth, Fortify responses) so each login returns personas[] entries like { type: 'buyer', company_id } and { type: 'supplier', company_id, supplier_id }.
    - [x] Track activePersona in AuthContext; default to buyer, but allow switching when multiple personas exist.
    - [x] Ensure access policies consult activePersona before evaluating permissions, so when in supplier mode we only authorize supplier_* actions scoped to the linked buyer tenant.

- Persona switcher UX
    - [x] Global header chip/dropdown that lists available personas (“Buyer · Jim Enterprise”, “Supplier · Jim Enterprise (invited by Acme)”).
    - Switching personas:
        - [x] Updates AuthContext (company id, roles, supplier_id).
        - [x] Resets client caches (React Query/Inertia) so no buyer data lingers.
        - [x] Navigates to the appropriate landing page (/app/rfqs list for suppliers, existing home for buyers).
    - [x] While in supplier mode, adjust nav to show only supplier-allowed routes (RFQs received, quotes, profile) and badge the header (“Acting as Supplier for Jim Enterprise”) to avoid confusion.

- Safeguards
    - [x] Middleware enforces that every request carries company_id from the active persona; supplier personas use the buyer’s company id while tagging acting_supplier_id for auditing.
    - [x] Audit logs store { user_id, persona_type, persona_company_id, supplier_id? } so we can prove who did what.
    - Background queries cancel on persona switch. Session tokens could embed persona claims to prevent cross-tenant API calls.
    - [x] UI prevents mixed actions: supplier persona can’t see buyer-only pages, and vice versa, unless they switch and reload.

- Notifications & invites
    - [x] When an owner is invited as supplier, auto-set supplier_capable = true and either create or connect them to the relevant supplier_id record; send them a notification that also exposes the persona switcher entry.
    - [x] Queue workers still deliver emails/push, but even without them the persona becomes available immediately.

- Testing
    - [x] Feature tests that simulate switching personas, ensuring the supplier sees invited RFQs while buyer functionality remains unchanged.
    - [x] Policy tests verifying tenant isolation for each persona type.