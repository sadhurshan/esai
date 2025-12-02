Multi-Org Membership, Invites & Organization Switching

- Check the multi-organization and invite system:
- Companies can invite users by email and assign roles (buyer_admin, requester, supplier_admin, estimator, finance, etc.).
- Invitation tokens, expiration, and acceptance flows exist and are secure.
- Users can belong to multiple companies via a join table and can switch active organization (e.g. dropdown/switcher).
- Edge cases:
    - What happens when a user is removed from their last company?
    - What happens when a company is suspended?
    - What happens if an invitation is revoked before acceptance?
- Identify all related models, policies/middleware, and UI.
- Flag any cases where a user could get stuck, see wrong tenant’s data, or bypass tenant scoping.

Checklist Findings (Updated)

- ✅ **Context switching + membership storage exist.** `company_user` (see `database/migrations/2025_11_03_000030_create_company_user_table.php` and `2025_11_21_150000_add_default_flags_to_company_user_table.php`) carries `role`, `is_default`, `last_used_at`. `UserCompanyController` (`app/Http/Controllers/Api/UserCompanyController.php`) exposes `/api/me/companies` + `/api/me/companies/switch`, and the React top bar (`resources/js/components/top-bar.tsx`) plus the profile page default manager (`resources/js/pages/settings/profile-settings-page.tsx`) consume those endpoints and log `company_switch` audit entries.
- ✅ **Company member invitations now exist end-to-end.** Migration `2025_11_30_200000_create_company_invitations_table.php`, model `App\Models\CompanyInvitation`, actions `InviteCompanyUsersAction`/`AcceptCompanyInvitationAction`, and `CompanyInvitationController` (`routes/api.php` company-invitations group) back the React UI at `resources/js/pages/settings/company-invitations-page.tsx`. Buyer admins/owners can draft batches (up to 25), specify roles, optional expiry/messages, and revoke pending invites via `/api/company-invitations`.
- ✅ **Invitation security covers tokens, expiry, acceptance, and revocation.** Each invite stores a 64-char token + `expires_at`, exposes `/api/company-invitations/{token}/accept`, and enforces revocation + expiry inside `AcceptCompanyInvitationAction`. Revocation writes `revoked_at/by` and acceptance rotates the token via `Hash::make()` before storing, preventing replays.
- ✅ **Per-company roles cascade correctly.** `UserCompanyController::switch` and `AcceptCompanyInvitationAction` both synchronize the pivot role back to `users.role`, ensuring each active company switch reflects the membership record. Permission-aware middleware now resolve roles through `PermissionRegistry`, so every request reuses the correct tenant membership without leaking access across companies.
- ✅ **Edge cases now have explicit UX.**
    - Removing the final membership triggers `EnsureCompanyOnboarded`, which first attempts to reattach any default pivot and, if none exist, blocks access with actionable guidance (“Request a new invitation…”). This prevents silent failures when a user no longer belongs to any tenant.
    - Company suspension returns a descriptive payload from `EnsureCompanyApproved` (HTTP 403, `errors.company => Account suspended…`), giving administrators a clear remediation path.
    - Invitation revocation/expiry now surfaces immediately thanks to the dedicated acceptance screen at `resources/js/pages/auth/accept-company-invitation-page.tsx` (`/invitations/accept/:token`). The page auto-attempts acceptance post-login, refreshes membership state, and shows tailored messaging (with retry CTA) for revoked or expired tokens.
- ✅ **Tenant-scoping protections hardened.** `ApiController::resolveUserCompanyId()` no longer falls back to supplier email (it now limits to explicit memberships/ownership), and `RFQController::show/update/destroy` enforce `company_id` filters before returning data. Regression coverage lives in `tests/Feature/Api/RFQAuthorizationTest.php`, preventing cross-tenant reads/writes.
- ✅ **Relevant models/policies/UI audited.** Core touchpoints: `App\Models\User`, `App\Models\Company`, the `company_user` pivot, middleware trio `EnsureCompanyRegistered`/`EnsureCompanyOnboarded`/`EnsureCompanyApproved`, and the Inertia consumers named above. No additional invite-related resources were found, confirming the feature gap.