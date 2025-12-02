Company Registration & Default Buyer Setup

✅ **On first signup a tenant + owner is created**

- `/api/auth/register` is handled by `App\Http\Controllers\Api\Auth\SelfRegistrationController::register`, which validates the payload via `SelfRegistrationRequest`, creates a `User` with role `owner`, runs `RegisterCompanyAction`, and logs the user in with a session token via `AuthResponseFactory`.
- Feature tests (`tests/Feature/Api/Auth/SelfRegistrationTest.php`) cover end‑to‑end owner creation, token issuance, and event dispatching.

✅ **Companies default to buyer configuration**

- `RegisterCompanyAction` (used both by self-registration and the authenticated `/api/companies` flow) persists a `Company` row with `status = pending_verification`, `supplier_status = none`, `directory_visibility = private`, and sets `owner_user_id` to the registering user.
- It also seeds the `company_user` pivot ensuring the owner is linked as their default tenant, preventing orphaned companies or users.

✅ **Statuses and enforcement**

- `CompanyStatus`/`CompanySupplierStatus` enums are cast on the model and surfaced through `CompanyResource` + `AuthResponseFactory`. Events `CompanyPendingVerification` + `Registered` fire during signup so review workflows trigger automatically.
- `EnsureCompanyRegistered` middleware blocks access to the main app until `Company::hasCompletedBuyerOnboarding()` returns true, redirecting to the onboarding route or returning a JSON 403 with missing fields.

✅ **Onboarding + profile completion**

- `CompanyRegistrationController` exposes `/api/companies` to revisit or update buyer details post-signup (`RegisterCompanyRequest`, `UpdateCompanyRequest`, `UpdateCompanyProfileAction`). The SPA uses the corresponding hooks (`useRegisterCompany`, `useCompanySettings`) and the workspace gate surfaces setup status in the plan/onboarding flows.

✅ **Routes, services, and data model inventory**

- Routes: `/api/auth/register`, `/api/companies` (POST/GET/PUT); Middleware: `EnsureCompanyRegistered`.
- Controllers/Actions: `SelfRegistrationController`, `CompanyRegistrationController`, `RegisterCompanyAction`, `UpdateCompanyProfileAction`, `StoreCompanyDocumentAction`.
- Requests: `SelfRegistrationRequest`, `RegisterCompanyRequest`, `UpdateCompanyRequest`.
- Tables: `users`, `companies`, `company_user`, `company_documents` (+ enums in migrations).
- Validations cover uniqueness (email, company name/domain), password policy, document mime/size, and required company metadata; no missing validations or owner/tenant inconsistencies remain.