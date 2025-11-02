# Security & RBAC
Roles: buyer_admin, buyer_requester, supplier_admin, supplier_estimator, finance, platform_super, platform_support.
- Tenant scope every query by company_id.
- Policies for RFQ/Quote/PO/Order/Invoice/Supplier; enforce in middleware.
- Fortify auth (email verify, optional 2FA). Unified re‑auth on 401/419.
- Sensitive actions audited; retention holds; export per tenant.
