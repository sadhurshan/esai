# Supplier-Authored Invoicing Enablement Guide

Use this runbook when gradually turning on supplier-authored invoicing for tenants. It complements the rollout checklist in [docs/launch_ready_cleanup/supplier-invoicing-rollout.md](supplier-invoicing-rollout.md) and focuses on feature flag governance plus customer communications.

## Feature Flag Overview
- Flag key: `supplier_invoicing_enabled` (mirrors `EnsureSupplierInvoicingAccess::FEATURE_KEY`).
- Default availability: automatically `true` for Growth and Enterprise plans, or when `plans.supplier_invoicing_enabled = 1`.
- Override path: create a company-scoped feature flag so Launch/Starter tenants can pilot the experience without upgrading the entire plan.
- Enforcement points: API + Inertia routes protected by `EnsureSupplierInvoicingAccess`, React supplier invoice pages (guarded via `hasFeature('supplier_invoicing_enabled')`), and audit trail via `FeatureEntitlementChecked` events.

## Admin Console / API Steps
1. Retrieve the company ID from Admin → Tenants or via `GET /api/admin/companies?filter=email_domain:example.com`.
2. List existing overrides (optional):
   ```http
   GET /api/admin/companies/{company}/feature-flags?per_page=50
   Authorization: Bearer <platform-admin-token>
   ```
3. Create/override the flag:
   ```http
   POST /api/admin/companies/{company}/feature-flags
   Content-Type: application/json
   Authorization: Bearer <platform-admin-token>

   {
     "key": "supplier_invoicing_enabled",
     "value": { "enabled": true }
   }
   ```
   - Use `{ "enabled": false }` (or delete the flag) to roll a tenant back.
   - `value` accepts any JSON object; stick to `{"enabled": <bool>}` for consistency with middleware parsing.
4. Confirm exposure by calling `GET /api/auth/session` (or reloading the app) and checking the `feature_flags.supplier_invoicing_enabled` entry is `true`.
5. Notify the customer (template below), then monitor logs for `FeatureEntitlementChecked` events tied to the tenant.

## Customer Communication Template
> Subject: Supplier-Invoicing beta now available in your Elements Supply workspace
>
> Hi <Customer>,
>
> We enabled the supplier-authored invoicing beta for your tenant (<tenant slug>). You will now see “Invoices” under the Supplier workspace navigation, including draft, submit, and buyer review flows tied to your existing purchase orders.
>
> Let us know if you need a walkthrough or want the feature disabled. Otherwise we will check in after two weeks to confirm it meets your needs.
>
> Thanks!

## Validation Checklist
- [ ] Buyer admin can reach `/app/invoices/supplier` and see supplier-submitted rows scoped to their company.
- [ ] Supplier estimator persona can create + submit invoices for seeded purchase orders (use DevTenantSeeder fixtures if needed).
- [ ] Middleware returns HTTP 402 with upgrade CTA when flag is disabled; success path emits `FeatureEntitlementChecked` event with `enabled=true`.
- [ ] QA captured at least one attachment upload to verify `invoice_attachments` table wiring post-flag.

## Rollback / Support
- Remove override via `DELETE /api/admin/companies/{company}/feature-flags/{flag}` to fall back to plan defaults.
- If the tenant should retain supplier invoicing, update their plan record (e.g., move to Growth) via billing tooling, then delete the ad-hoc flag.
- Document every toggle in Zendesk/Jira with a link to the API response for compliance.
