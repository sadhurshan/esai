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
