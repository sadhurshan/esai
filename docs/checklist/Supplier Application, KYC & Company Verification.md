Supplier Application, KYC & Company Verification

**Apply as supplier (owner-only + API/UI)**

- `/api/me/apply-supplier` (routes/api.php) is protected by `auth`, `ensure.company.approved`, and the `SupplierApplicationStoreRequest::authorize()` permission check (enforcing the owner-only `suppliers.apply` capability) before `SupplierApplicationController::selfApply` runs. The controller validates via the same request, asserts the company is Active/Trial, blocks duplicate pending apps, marks `companies.supplier_status = pending`, and captures the structured payload/audit log. Feature test `tests/Feature/Api/SupplierApplicationLifecycleTest.php` covers submit + approval + guard rails (non-owners, pending companies).
- React page `resources/js/pages/settings/supplier-application-panel.tsx` renders the dedicated form with zod validation (capabilities + contact required) and disables the CTA unless the authenticated owner’s company is approved and status is `none|rejected`. It calls `useApplyForSupplier` (resources/js/hooks/api/useSupplierSelfService.ts) which POSTs to the endpoint. Non-owners are shown an alert and the CTA stays disabled, so UI parity with backend policy holds.
- Owners can review prior submissions via `/api/supplier-applications` (`SupplierApplicationController::index/show`) and can withdraw a pending request via `destroy`, which is limited to owners + status pending (`SupplierApplicationPolicy::delete`).
- Submissions emit `SupplierApplicationSubmitted` notifications to `platform_super` admins so there is always an audit trail + inbox alert when a new application arrives.

**KYC / compliance document capture**

- Company-level document uploads are available today: `CompanyDocumentController` + `StoreCompanyDocumentAction` accept type-coded uploads (`registration|tax|esg|other`) with enforced mime/size limits via `StoreCompanyDocumentRequest`, and self-registration can attach docs immediately (SelfRegistrationController loops through provided documents). These land in `company_documents` (migration 2025_11_04_150000_update_companies_for_kyc.php) but do not store expiry metadata.
- Supplier-specific compliance artifacts are modeled via `supplier_documents` (migration 2025_11_03_000200_update_suppliers_schema.php) and `SupplierDocument`/`SupplierDocumentResource` expose `type`, `issued_at`, `expires_at`, `status`, and file metadata. `SupplierDocumentController` + `StoreSupplierDocumentAction` now back DocumentStorer-powered uploads at `/api/me/supplier-documents`, ensuring every file is persisted in `documents` with `document_id` references, download URLs, and expiry-aware statuses; the controller also lists/deletes entries with audit coverage.
- The `documents` array accepted by `SupplierApplicationStoreRequest` is now enforced: referenced supplier documents must belong to the tenant, remain `valid|expiring`, and are synced onto each application via the pivot so reviewers see the exact artifacts that justified the submission.

**Supplier application entity + admin management**

- Domain model: `SupplierApplication` + `SupplierApplicationStatus` enum (`pending|approved|rejected`) with `SupplierApplicationPolicy` restricting CRUD via `PermissionRegistry` (owners or buyer_admins holding `suppliers.write` can view, only owners holding `suppliers.apply` can create/cancel).
- Platform admins handle lifecycle via `/api/admin/supplier-applications` routes backed by `SupplierApplicationReviewController`, which fans into `CompanyLifecycleService::approveSupplier/rejectSupplier`. Approval locks the company row, sets `supplier_status = approved`, stamps `verified_at/by`, creates/updates the `suppliers` record (capabilities, MOQ, lead time) and emits notifications (`SupplierApplicationSubmitted`, `SupplierApplicationApproved`, `SupplierApplicationRejected`). Rejection reverts status to `rejected`, clears verification fields, and persists reviewer notes. Tests in `SupplierApplicationLifecycleTest` assert submit/approve/reject flows, notifications, and supplier record provisioning.
- Reviewers now have a dedicated SPA queue: `resources/js/pages/admin/admin-supplier-applications-page.tsx` lists submissions with status filters, loads audit logs, previews attached compliance documents, and drives approve/reject flows via `useApproveSupplierApplication` / `useRejectSupplierApplication`. Access is limited to `platform_super|platform_support`, keeping UI + API parity.

**Directory visibility + enforcement**

- Public Supplier Directory (`SupplierController@index/show`) only returns companies whose supplier status is `approved`, `directory_visibility = public`, and `supplier_profile_completed_at` is set; filters exist for capabilities/materials/certifications. Counts of valid/expiring/expired certificates are aggregated via relationship scopes.
- `tests/Feature/Api/SupplierDirectoryTest.php` asserts the directory returns only approved/public/complete suppliers and enforces the visibility toggle rules, so backend enforcement is covered by automated tests.
- Owners can check/toggle listing from `SupplierSelfServiceController@status/updateVisibility`, which enforces authentication, ownership, and supplier approval before allowing `directory_visibility` changes (also requires a completed profile for public listing). `useSupplierSelfStatus` drives the client badge state and is invalidated whenever visibility changes.
- Backend enforcement relies on `EnsureSupplierApproved` middleware applied broadly in `routes/api.php` (supplier quote submission, supplier order + shipment routes, RFQ quote endpoints, PO acknowledgements, etc.). Frontend mirrors the rule with `RequireSupplierAccess` (blocks supplier-only React routes) and the settings panel’s disabled CTA, so an unapproved tenant cannot act as a supplier via UI or API.

**Document expiry + re-verification**

- Schema supports expiry tracking: `supplier_documents` carries `issued_at`, `expires_at`, and a `status` flag (`valid|expiring|expired`), and `SupplierResource` exposes the counts for directory consumers.
- `AuditSupplierDocumentExpiryJob` (queued via `suppliers.document_expiring_threshold_days`) now runs the lifecycle: it recalculates statuses, emits `certificate_expiry` notifications through `NotificationService`, and invokes `RequireSupplierReverificationAction` to auto-create pending applications + pause directory visibility whenever certificates lapse. Tests in `tests/Feature/Jobs/AuditSupplierDocumentExpiryJobTest.php` cover both notification + re-verification paths.

**Status / follow-ups**

- All checklist requirements for Supplier Application, KYC & Company Verification are implemented end-to-end (API, React surfaces, background jobs, notifications, and audit coverage). Continue monitoring telemetry and UX polish, but there are no open gaps blocking sign-off on this prompt.