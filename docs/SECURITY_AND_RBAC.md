# Security & RBAC
Roles: buyer_admin, buyer_requester, supplier_admin, supplier_estimator, finance, platform_super, platform_support.
- Tenant scope every query by company_id.
- Policies for RFQ/Quote/PO/Order/Invoice/Supplier; enforce in middleware.
- Fortify auth (email verify, optional 2FA). Unified re‑auth on 401/419.
- Sensitive actions audited; retention holds; export per tenant.

## AI Route Audit

- Run `php artisan ai:audit-permissions` before each release.
- The command enumerates every `/v1/ai` route, verifies the `ensure.ai.*` entitlements are attached, and confirms permission middleware (e.g. `buyer_access`, `billing_access`) covers the required scopes from `config/permissions.php`.
- The report prints a table of routes, applied middleware, expected permissions, and highlights any missing entries; CI should fail if warnings are emitted.
