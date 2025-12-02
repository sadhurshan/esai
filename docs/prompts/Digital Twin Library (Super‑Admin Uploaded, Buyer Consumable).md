# Digital Twin Library (Super‑Admin Uploaded, Buyer Consumable)

## Goal
Build a **Digital Twin Library** where **Super Admins** curate and publish Digital Twins (DTs) into categories; **Buyers** can **browse/search**, preview, and **use a DT to start an RFQ** (and other flows later). **Suppliers cannot create/edit DTs.** Enforce multi‑tenant boundaries and plan gating (`digital_twin_enabled`). Use **Laravel 12 + React Starter Kit**, **TS SDK + React Query**, **react-hook-form + zod**, **Tailwind + shadcn/ui**.

---

## 1) Roles & Access
- **Super Admin (platform)**: Full create/edit/publish/archive DTs & categories; version management; delete; audit.
- **Tenant Admin/Buyer**: Read‑only library access; may **“Use for RFQ”** (creates RFQ draft prefilled from DT).
- **Supplier**: No access to create/edit DTs; read‑only only when referenced inside an RFQ/PO they’re involved in (not listing library).

Route guards/policies must enforce the above.

---

## 2) Data Model (Migrations + Eloquent)
Create migrations and models (names can vary, keep consistent with your conventions):

**digital_twin_categories**
- `id`, `slug` (unique), `name`, `description?`, `parent_id?`, `is_active` (bool), timestamps

**digital_twins**
- `id`, `category_id` (FK), `sku?`/`code?`, `title`, `summary`, `status` (`draft|published|archived`), `version` (string), `revision_notes?`, `tags` (json), `thumbnail_path?`, `visibility` (`public` only for platform), timestamps

**digital_twin_specs** (key/val attributes)
- `id`, `digital_twin_id` (FK), `name`, `value`, `uom?`, `sort_order`

**digital_twin_assets**
- `id`, `digital_twin_id` (FK), `type` (`CAD|STEP|STL|PDF|IMAGE|DATA|OTHER`), `path`, `filename`, `size_bytes`, `checksum?`, `mime`, `is_primary` (bool), timestamps

**digital_twin_audit_events**
- `id`, `digital_twin_id`, `actor_id`, `event` (`created|updated|published|archived|asset_added|asset_removed|spec_changed`), `meta` (json), timestamps

Indexes on slugs, status, tags JSON, and full‑text on title/summary if supported.

---

## 3) Backend (Controllers, Requests, Resources)
Create API under `/admin/digital-twins/*` for super admins; `/library/digital-twins/*` for consumer read‑only.

**Admin (Super Admin only)**
- `POST /admin/digital-twins` → create DT (draft)
- `PATCH /admin/digital-twins/:id` → update metadata/specs
- `POST /admin/digital-twins/:id/assets` → upload assets (file upload; validate type/size; virus scan hook stub)
- `DELETE /admin/digital-twins/:id/assets/:assetId`
- `POST /admin/digital-twins/:id/publish` → status: published
- `POST /admin/digital-twins/:id/archive` → status: archived
- `POST /admin/digital-twin-categories` / `PATCH /admin/digital-twin-categories/:id` / `DELETE ...`

**Library (Buyer read‑only)**
- `GET /library/digital-twins` (filters below)
- `GET /library/digital-twins/:id` (detail: specs, assets, versions if applicable)
- `POST /library/digital-twins/:id/use-for-rfq` → returns RFQ draft payload (server returns composed draft; FE redirects to RFQ wizard prefilled)

**Requests & validation**
- `StoreDigitalTwinRequest`, `UpdateDigitalTwinRequest`: title required; category exists; tags array; version semantic-ish; assets: whitelist MIME, size ≤ configured.
- Category requests validate unique slug and parent cycle.
- All mutation endpoints emit `digital_twin_audit_events`.

**Resources/Transformers**
- `DigitalTwinResource`, `DigitalTwinListResource`, `DigitalTwinCategoryResource`, `DigitalTwinAssetResource`.

**Policies/Middleware**
- Admin routes require `isSuperAdmin()` (platform scope), not tenant admin.
- Library routes require `auth` + tenant plan `digital_twin_enabled`; suppliers may access **only** via reference endpoints (not list).

---

## 4) TS SDK Hooks (Front‑end)
Under `resources/js/hooks/api/digital-twins/`:

```
use-dt-categories.ts         // GET /library/digital-twins?include=categories (or dedicated endpoint)
use-dts.ts                   // list with filters
use-dt.ts                    // detail by id
use-use-for-rfq.ts           // POST :id/use-for-rfq → returns rfq draft payload

// Admin
use-admin-dts.ts             // list admin view
use-admin-dt.ts              // detail (with drafts, assets)
use-create-dt.ts             // POST create
use-update-dt.ts             // PATCH update
use-upload-dt-asset.ts       // POST asset
use-delete-dt-asset.ts       // DELETE asset
use-publish-dt.ts            // POST publish
use-archive-dt.ts            // POST archive
use-admin-dt-categories.ts   // CRUD categories
```
All mutations invalidate relevant queries (`dts`, `dt`, `dtAdmin*`). Handle 401/403/422 via global handlers + `PlanUpgradeBanner`.

---

## 5) Library UI (Buyer)
Routes (auth + plan gate):
```tsx
<Route element={<RequireAuth><AppLayout/></RequireAuth>}>
  <Route path="/app/library/digital-twins" element={<DigitalTwinLibraryPage/>} />
  <Route path="/app/library/digital-twins/:id" element={<DigitalTwinDetailPage/>} />
</Route>
```

**DigitalTwinLibraryPage**
- Sidebar: category tree, tag chips, plan gate banner if disabled.
- Top bar: search (title/summary/specs), filters (status=published only, file types, has CAD, UoM, updated date).
- Grid or table cards: thumbnail, title, version, category, tags, primary asset badge, quick actions.
- Actions: **View**, **Use for RFQ** (opens RFQ wizard prefilled), **Copy link**.
- Empty + skeleton states; keyboard & screen‑reader friendly.

**DigitalTwinDetailPage**
- Header: title, version, category chip, tags, “Published” badge.
- Tabs:
  1) **Overview**: summary, key specs table.
  2) **Assets**: list with file type badges, sizes, **Preview** (embed image/PDF/STL viewer placeholder).
  3) **Compatibility/Notes** (optional).
- CTA: **Use for RFQ** → calls `useUseForRfq` then routes to RFQ wizard with draft `state`.

**RFQ integration**
- RFQ wizard receives `digitalTwinId`, `title`, `specs`, and **attachments** (asset references). Lines default from specs where applicable (e.g., UoM, target qty blank).

---

## 6) Admin UI (Super Admin Only)
Routes (super admin guard, not tenant admin):
```tsx
<Route element={<RequireAuth><AdminLayout/></RequireAuth>}>
  <Route path="/app/admin/digital-twins" element={<AdminDTListPage/>} />
  <Route path="/app/admin/digital-twins/new" element={<AdminDTCreatePage/>} />
  <Route path="/app/admin/digital-twins/:id" element={<AdminDTDetailPage/>} />
  <Route path="/app/admin/digital-twin-categories" element={<AdminDTCategoriesPage/>} />
</Route>
```

Pages & components:
- **AdminDTListPage**: filters (status, category), search; bulk publish/archive; pagination.
- **AdminDTCreatePage / AdminDTDetailPage**: `react-hook-form + zod` for metadata/specs; **Asset uploader** with progress, type whitelist, primary asset selection; version + revision notes; **Publish**/**Archive** buttons; **Audit timeline**.
- **AdminDTCategoriesPage**: CRUD with parent tree; slug auto‑generate; active toggle.

Shared components:
- `DigitalTwinCard`, `DigitalTwinSpecTable`, `DigitalTwinAssetList`, `CategoryTree`, `PlanUpgradeBanner`.
- Accessibility: labels, aria-describedby, focus management in dialogs.

---

## 7) Search & Filters (Server + FE)
- Server accepts: `q` (full‑text on title/summary/specs), `category`, `tag`, `hasAsset=CAD|STEP|STL|PDF|IMAGE`, `updated_from`, `updated_to`.
- FE debounced search; preserve filters in URL query params.
- Sort: relevance, updated_at desc, title a‑z.

---

## 8) Files & Storage
- Store large assets in object storage; save `path`, `size_bytes`, `checksum`.
- Generate signed download URLs; thumbnails for images/PDF first page; STL viewer placeholder (client‑side only).
- **Virus scan hook** (stub ok); max file size & allowed MIME via config.
- CDN headers, short cache on private URLs.

---

## 9) Plan Gating & Policies
- Feature flag `digital_twin_enabled` at tenant plan level for **library access**.
- Admin routes require `isSuperAdmin()`; never exposed to tenant admins.
- Suppliers cannot list/search the library; they can only fetch a DT **referenced** inside a document they’re part of.

---

## 10) Tests
- Feature tests: admin create/publish; library list returns **published** only; “use for RFQ” returns draft payload.
- Policy tests: tenant buyers can access library; suppliers can’t; super admin routes blocked for others.
- Upload tests: MIME/size validation; asset attach/detach; primary asset logic.
- Search tests: q/category/tag/hasAsset filters and sorting.

---

## 11) Acceptance Criteria
- Super Admins can create, edit, publish, archive DTs and categories; assets upload with validation.
- Buyers can browse/search **published** DTs; **Use for RFQ** creates a draft and redirects to RFQ wizard with prefilled context.
- Suppliers cannot create or list DTs; can only view DTs referenced in docs they participate in.
- Plan/role gates enforced; queries/mutations via TS SDK; responsive UI with skeleton/empty/error states; a11y respected.
