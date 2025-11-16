# Prompt: Implement Self‑Registration & Supplier Application

## Background
- The backend already includes `RegisterCompanyAction` and `CompanyRegistrationController` for company+user creation.
- We need **self‑service signup** for new users/companies. The first user becomes **owner** (not buyer_admin).
- New companies are **buyers by default**. Owners may later **apply to become suppliers**.
- Supplier activities are **blocked** until the company is **approved** as a supplier by super admins.
- Approved suppliers appear in the **Supplier Directory**; non‑approved ones do not.

---

## 1) API: Self‑Registration Endpoint
Create `SelfRegistrationController` in `app/Http/Controllers/Api/Auth` and expose:
```php
// routes/api.php
Route::middleware('guest')->group(function () {
    Route::post('/auth/register', [SelfRegistrationController::class, 'register']);
});
```
**SelfRegistrationController@register** must:
1) Validate request via `SelfRegistrationRequest` (fields: `name`, `email`, `password`, `company_name`, `company_domain`, optional address/phone).
2) Call `RegisterCompanyAction` to create the **Company** with `supplier_status = 'none'` and the **User** with role `owner`.
3) Ensure initial buyer posture (no supplier rights).
4) Issue auth token (reuse existing login auth) and return `{ user, company }`.

**SelfRegistrationRequest**:
- Strong password policy, unique `email`, unique `company_name` (and/or `company_domain`).

---

## 2) DB & Model Adjustments
Ensure `companies` table & `Company` model have:
- `supplier_status` enum: `none | pending | approved | rejected | suspended` (default `none`).
- `supplier_profile_completed_at` (nullable datetime).
- `is_verified` boolean (default `false`).

Ensure `users` role handling supports `owner`. If roles are RBAC tables, seed `owner` if missing.

---

## 3) Owner‑Initiated Supplier Application
Add routes (owner‑only):
```php
Route::middleware(['auth:sanctum','role:owner'])->group(function () {
    Route::post('/me/apply-supplier', [SupplierApplicationController::class, 'selfApply']);
});
```
**SupplierApplicationController@selfApply** must:
1) Assert company `supplier_status === 'none'` (or not already pending/approved).
2) Validate `SupplierApplicationRequest` (e.g., `description`, `capabilities[]`, `certifications[]`, `website`, optional files).
3) Create `SupplierApplication` with `status='pending_review'`, `submitted_by=auth()->id()`.
4) Update `company.supplier_status='pending'`; optionally set `supplier_profile_completed_at` when minimum profile is done.
5) Notify super‑admins (email/notification/webhook).

Approval flow (super admin area) sets:
- Approve → `company.supplier_status='approved'`, `is_verified=true`.
- Reject → `company.supplier_status='rejected'` (store reason).

---

## 4) Roles, Policies & Route Guards
- **Owner** can invite users with various roles (buyer roles always; supplier roles only after approval).
- Add policy/middleware on **supplier** routes: block if `company.supplier_status!=='approved'` → return 403 with message.
- FE should hide supplier features until approval; show status badge: `Not applied`, `Pending`, `Approved`, `Rejected`.

---

## 5) Supplier Directory Visibility
- Supplier directory queries must filter `supplier_status='approved'` only.
- Optionally add a company setting `directory_visibility` (private/public). Default private; let owners toggle later.

---

## 6) Frontend (React + TS) Touchpoints
- Add **/register** page that posts to `/auth/register` and on success redirects to `/app`.
- In account/company settings, add a **“Apply as Supplier”** button → opens form (mapped to `SupplierApplicationRequest`). Show live status chip.
- Disable supplier‑only menus/routes until approval. Reuse plan/role gating components to check `company.supplier_status`.
- Add toasts & empty/error states.

---

## 7) Tests
Feature tests:
- Self‑registration creates Company(owner) with `supplier_status='none'`.
- Owner can submit supplier application → `pending_review` & company to `pending`.
- Non‑owner users cannot apply.
- Supplier routes 403 until `approved`.
- Only `approved` companies appear in directory.

---

## 8) Acceptance Criteria
- Public **/auth/register** works; first user is **owner**, company is buyer‑only.
- Owners can **apply as suppliers**; super admins can approve/reject.
- Supplier features & invitations are **blocked until approved**.
- Only **approved** companies show in the Supplier Directory.
- Tests for the above pass.
