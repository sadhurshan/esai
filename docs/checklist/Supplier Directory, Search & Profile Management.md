Supplier Directory, Search & Profile Management

Status: Completed – 2025-11-25

- [x] Verify the Supplier Directory and related flows — reviewed `/resources/js/pages/suppliers/Directory.tsx` and companion controller logic to ensure listings respect tenant scoping per `/deep-specs/suppliers.md`.
- [x] Supplier profile structure (capabilities, materials, processes, certifications, industries served, location) — confirmed against `app/Models/SupplierProfile.php` attributes and `/docs/DOMAIN_MODEL.md` definitions.
- [x] Search + filtering UI/backend (capability, process, material, tolerance, finish, region, rating, etc.) — validated filters wired through `SupplierDirectoryFilterRequest` and cursor pagination as required.
- [x] Sorting logic (match score, rating, lead time, distance) — checked sort enum in `app/Enums/SupplierDirectorySort.php` and ensured default ordering honors match score.
- [x] Profile editing by supplier admins and approval/audit for sensitive fields — verified `SupplierProfileController@update` dispatches audit events per `/docs/DEFINITION_OF_DONE.md` and is gated by policies.
- [x] Verified/approved suppliers visibility in search/RFQ targeting — ensured scopes restrict to `is_verified` suppliers before surfacing in RFQ targeting services.
- [x] Missing filters/profile fields/audit logs — no gaps found; existing TODO remains to clarify tolerance granularity in `/deep-specs/suppliers.md` if future expansion needed.