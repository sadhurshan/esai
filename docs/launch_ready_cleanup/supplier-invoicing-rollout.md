# Supplier-Authored Invoicing Rollout Plan

## Scope
- Applies the schema/seed changes delivered in [database/migrations/2025_12_14_090000_add_supplier_authored_fields_to_invoices.php](../../database/migrations/2025_12_14_090000_add_supplier_authored_fields_to_invoices.php) and the associated seeders under [database/seeders](../../database/seeders).
- Introduces supplier-authored invoice metadata (creator identity, status enum, matching state, timestamps), `invoice_attachments`, and deterministic dev fixtures.
- Requires permission template refresh so buyer/supplier roles immediately gain any new billing/order capabilities surfaced by supplier invoicing.

## Pre-deployment Checklist
1. Confirm all migrations have been generated on the release branch and that `php artisan migrate:status` is clean in staging.
2. Capture a point-in-time backup of `invoices`, `invoice_lines`, and `documents` tables (e.g., via `mysqldump --tables invoices invoice_lines documents`).
3. Ensure queues are drained and long-running workers are restarted after deployment (`php artisan queue:restart`).
4. Run automated suites locally/CI (`composer run test`, `npm run lint`, `npm run types`).

## Deployment Steps
1. Place the app in maintenance when running against production-sized datasets:

   ```bash
   php artisan down --render="errors::maintenance" --retry=60
   ```
2. Run the supplier-invoicing migration (forces enum alterations, attachment table creation, and data backfills):

   ```bash
   php artisan migrate --force --path=database/migrations/2025_12_14_090000_add_supplier_authored_fields_to_invoices.php
   ```
3. Refresh permission templates so every tenant inherits the new capabilities exposed in this release:

   ```bash
   php artisan db:seed --class=RoleTemplateSeeder
   ```
4. (Optional but recommended) reseed dev/demo tenants for QA sandboxes:

   ```bash
   php artisan db:seed --class=DevTenantSeeder
   ```
5. Bust caches/workers so permission and config changes take effect:

   ```bash
   php artisan cache:clear
   php artisan queue:restart
   ```
6. Bring the app back online:

   ```bash
   php artisan up
   ```

## Post-deployment Verification
- `php artisan migrate:status` should display the migration as "Ran".
- Smoke-test the buyer + supplier invoice dashboards plus submit/approve flows (Playwright: `npx playwright test tests/e2e/supplier-invoices.spec.ts`).
- Spot-check an upgraded invoice row to verify `supplier_company_id`, `created_by_type`, and `matched_status` populated.
- Upload an attachment to confirm the `invoice_attachments` table accepts soft-deletes and that virus-scan pipeline still emits `passed` events.

## Rollback Strategy
1. Enter maintenance mode and stop queues as in the deployment section.
2. Roll back only the supplier-invoicing migration to avoid touching unrelated schema:

   ```bash
   php artisan migrate:rollback --force --path=database/migrations/2025_12_14_090000_add_supplier_authored_fields_to_invoices.php
   ```
3. Drop `invoice_attachments` if the rollback fails mid-flight (for MySQL: `mysql -h <host> -u <user> -p<password> <database> -e "DROP TABLE IF EXISTS invoice_attachments"`).
4. Restore the `invoices` snapshot captured pre-deploy if enum conversions or money backfills corrupted legacy data.
5. Re-run `php artisan up` once rollback validation passes.

## Permission Seeding Details
- `RoleTemplateSeeder` syncs `config/rbac.php` definitions into the `role_templates` table and invalidates the cached permission registry so the supplier invoicing scopes appear immediately.
- After seeding, run the internal RBAC sync job (via scheduled worker) or the demo reset command for sandbox tenants:

   ```bash
   php artisan app:demo-reset --force
  ```
- Validate a sample tenant by hitting the `GET /api/internal/role-templates` endpoint or loading the Admin → RBAC screen to ensure supplier roles list the invoicing-related permissions.

## Communication & Audit
- Note the deployment window and commands executed in the change ticket referencing this document.
- Capture the `php artisan migrate --force` and `php artisan db:seed --class=RoleTemplateSeeder` outputs for compliance logs.
- Update customer-facing release notes once verification succeeds so CSMs can notify supplier tenants about the new workflow.
