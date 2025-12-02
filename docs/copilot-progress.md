## 2025-11-21 09:05
- Shipped the Analytics MVP prompt by enriching snapshot metadata (quote counts, supplier spend) and exposing it through the `/api/analytics/overview` hook so KPI cards and charts share a consistent payload.
- Built reusable KPI and mini-chart components plus the fully plan-gated `/app/analytics` page that renders RFQ/Quote trends, supplier spend bars, and on-time vs late receipt stacks with skeleton states.
- Added Pest coverage for the analytics overview endpoint (`vendor/bin/pest tests/Feature/Api/Analytics/AnalyticsOverviewEndpointTest.php`) and ran `npm run lint` afterward to keep the frontend passing the agreed acceptance checklist.

## 2025-11-19 18:45
- Finalized the Orders module prompt by tightening `ShipmentCreateDialog` validation, surfacing inline remaining-quantity errors, and extending the supplier detail page test to cover the create-shipment happy path.
- Added Vitest coverage for the Orders React Query hooks and buyer/supplier detail pages to exercise acknowledgement, shipment creation, and tracking scenarios per the spec.
- Ran the consolidated Orders Vitest bundle (`npx vitest run resources/js/hooks/api/orders/__tests__/orders-hooks.test.tsx resources/js/pages/orders/__tests__/supplier-order-detail-page.test.tsx resources/js/pages/orders/__tests__/buyer-order-detail-page.test.tsx resources/js/components/orders/__tests__/shipment-create-dialog.test.tsx`) to verify the module end-to-end.

## 2025-11-16 11:20
- Added the Community (free) plan to the billing seeder and exposed a public `/api/plans` catalog plus authenticated `/api/company/plan-selection` endpoint backed by a reusable `AssignCompanyPlanAction`.
- Updated auth responses, middleware, and company billing helpers so free plans count as active while past-due customers are redirected to the new React plan selection flow.
- Implemented the `/app/setup/plan` onboarding page, router guard, and storage-aware auth context flags so new or lapsed tenants must pick a plan before accessing the main application, along with Pest coverage for the middleware and selection API.

## 2025-11-15 09:10
- Extended `docs/openapi/fragments/quotes.yaml` to cover quote detail, supplier listing, draft submission, and line-level CRUD endpoints so the frontend SDK can call the new Laravel APIs.
- Added `QuoteLineRequest`/`QuoteLineUpdateRequest` schemas plus richer `Quote`/`QuoteItem` payloads that include totals, lead times, attachments, and taxes to mirror the backend resources.
- Follow-up: regenerate the TS SDK/REST client (`npm run sdk:generate`) before wiring the new React Query hooks so they rely on the refreshed contract.

## 2025-11-14 00:46
- Added Vitest unit coverage for RFQ data hooks and the creation wizard, including query mocks and step validation assertions.
- Configured Vitest in `vite.config.ts` with path aliases, jsdom environment, vmThreads pool, and test setup bootstrap.
- Polyfilled `ResizeObserver` for jsdom and introduced frontend test harness utilities in `resources/js/tests/setup.ts`.
- Verified lint, TypeScript, and Vitest suites succeed (`npm run lint`, `npm run types`, `npx vitest run`).

## 2025-11-04 09:30
- Added `EnsureCompanyRegistered` middleware to the auth stack, gating dashboard and navigation routes until a user completes the company wizard.
- Updated the login and registration Inertia pages to surface a "Launch the wizard" link that opens the company registration flow for unassigned accounts.
- Documented the auth link requirement in `docs/ACCEPTANCE.md` and added feature coverage to ensure redirects when a user without a company visits `/dashboard`.

## 2025-11-03 21:15
- Built the multi-step company registration wizard with document upload handling, status messaging, and tenant-aware routing.
- Added company profile management in settings, supplier-facing profile read view, and admin pending company queue with approve/reject flows.
- Created React Query hooks for company detail/documents/admin approvals and extended query key constants.
- Authored `tests/Feature/Api/CompanyLifecycleTest.php` to cover registration, profile updates, document CRUD, and admin approvals.
- Updated acceptance checklist to reflect the registration, profile, and admin review deliverables; tests still pending local execution.

## 2025-11-03 17:20
- Added buyer-facing change order review tab on the purchase order detail page with approve/reject actions and toast feedback.
- Exposed purchase order company context to the frontend and wired change order hooks for buyer workflows.
- Documented acceptance updates and introduced API feature tests covering change order approvals and rejections.

## 2025-11-03 14:30
- Added the RFQ quote comparison page wiring existing award APIs, including supplier totals, inline acceptance, and back navigation.
- Implemented purchase order list and detail Inertia pages with status filtering, totals, and line item presentation.
- Updated web routes, toast flows, status badge mappings, and added feature tests to cover RFQ compare plus purchase order access.

## 2025-11-02 18:00
- Updated `resources/js/components/app/file-dropzone.tsx` to import `ChangeEventHandler` directly and reuse the type alias in the file input change handler for clearer React typings.
- Ran `npm run types` to confirm the frontend type checks successfully after the adjustments.

## 2025-11-02 18:45
- Added database migrations, Eloquent models, factories, and seeder wiring for suppliers, RFQs, RFQ quotes, and orders with realistic demo data aligned to project requirements.
- Created placeholder CAD/attachment files via the seeder for demo previews and uploaded assets.
- Introduced `app:demo-reset` Artisan command and registered it in `bootstrap/app.php` to refresh the demo environment.
- Added Pest smoke tests for the new factories to ensure generated models persist as expected.
- Ran `php artisan test` followed by `php artisan app:demo-reset` to verify the seeding and command workflow end-to-end.

## 2025-11-02 19:10
- Adjusted `resources/js/components/app/filter-bar.tsx` to map empty option values to a sentinel before passing them into `SelectItem`, preventing runtime errors from empty string values while keeping reset behavior intact.
- Ran `npm run types` to ensure the UI build remains type-safe after the select handling update.
