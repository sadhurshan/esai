# Prompt for Copilot: Admin Console & Tenant Settings (Plans, Roles, API Keys, Webhooks, Rate Limits, Audit)

## Goal
Create a production-ready **Admin Console** for tenant admins. Manage **plans & features**, **roles/permissions**, **API keys**, **webhooks** (create/rotate/test & delivery log), **rate limits**, and an **audit log viewer**. Use **TS SDK + React Query**, **react-hook-form + zod**, **Tailwind + shadcn/ui**, existing **Auth/ApiClient** providers, and enforce **plan/role gating** (admin only).

---

## 1) Routes & Files
```tsx
<Route element={<RequireAuth><AppLayout/></RequireAuth>}>
  <Route path="/app/admin" element={<AdminHomePage />} />
  <Route path="/app/admin/plans" element={<AdminPlansPage />} />
  <Route path="/app/admin/roles" element={<AdminRolesPage />} />
  <Route path="/app/admin/api-keys" element={<AdminApiKeysPage />} />
  <Route path="/app/admin/webhooks" element={<AdminWebhooksPage />} />
  <Route path="/app/admin/rate-limits" element={<AdminRateLimitsPage />} />
  <Route path="/app/admin/audit" element={<AdminAuditLogPage />} />
</Route>
```

**Create/extend files**
```
resources/js/pages/admin/admin-home-page.tsx
resources/js/pages/admin/admin-plans-page.tsx
resources/js/pages/admin/admin-roles-page.tsx
resources/js/pages/admin/admin-api-keys-page.tsx
resources/js/pages/admin/admin-webhooks-page.tsx
resources/js/pages/admin/admin-rate-limits-page.tsx
resources/js/pages/admin/admin-audit-log-page.tsx

resources/js/components/admin/feature-matrix-editor.tsx
resources/js/components/admin/role-editor.tsx
resources/js/components/admin/api-key-card.tsx
resources/js/components/admin/webhook-endpoint-editor.tsx
resources/js/components/admin/webhook-delivery-table.tsx
resources/js/components/admin/rate-limit-rule-editor.tsx
resources/js/components/admin/audit-log-table.tsx

resources/js/hooks/api/admin/use-plans.ts
resources/js/hooks/api/admin/use-plan.ts
resources/js/hooks/api/admin/use-update-plan.ts
resources/js/hooks/api/admin/use-roles.ts
resources/js/hooks/api/admin/use-update-role.ts
resources/js/hooks/api/admin/use-api-keys.ts
resources/js/hooks/api/admin/use-create-api-key.ts
resources/js/hooks/api/admin/use-revoke-api-key.ts
resources/js/hooks/api/admin/use-webhooks.ts
resources/js/hooks/api/admin/use-create-webhook.ts
resources/js/hooks/api/admin/use-update-webhook.ts
resources/js/hooks/api/admin/use-delete-webhook.ts
resources/js/hooks/api/admin/use-webhook-deliveries.ts
resources/js/hooks/api/admin/use-retry-delivery.ts
resources/js/hooks/api/admin/use-rate-limits.ts
resources/js/hooks/api/admin/use-update-rate-limits.ts
resources/js/hooks/api/admin/use-audit-log.ts
```
Keep paths consistent with your codebase.

---

## 2) Backend Integration (SDK Hooks)
Map to OpenAPI SDK endpoints (names may differ):

- **Plans & Features**
  - `usePlans()` → GET `/admin/plans` with features per plan.
  - `useUpdatePlan()` → PATCH `/admin/plans/:id` with `feature_flags`/limits (e.g., `quotes_enabled`, `inventory_enabled`, `max_rfq_lines`, etc.).

- **Roles & Permissions**
  - `useRoles()` → GET `/admin/roles` (role → permissions[]).
  - `useUpdateRole()` → PATCH `/admin/roles/:id` with permissions changes.

- **API Keys**
  - `useApiKeys()` → GET `/admin/api-keys` (masked tokens, last used at, scopes).
  - `useCreateApiKey()` → POST `/admin/api-keys` with `{ name, scopes[] }` → returns **plaintext token once**; surface copy UI.
  - `useRevokeApiKey()` → DELETE `/admin/api-keys/:id`.

- **Webhooks**
  - `useWebhooks()` → GET `/admin/webhooks` (endpoints, secret, events, status).
  - `useCreateWebhook()` / `useUpdateWebhook()` / `useDeleteWebhook()` for CRUD.
  - `useWebhookDeliveries(endpointId, params)` → GET `/admin/webhooks/:id/deliveries` (status, latency, response code, retries).
  - `useRetryDelivery(deliveryId)` → POST `/admin/webhooks/deliveries/:id/retry`.

- **Rate Limits**
  - `useRateLimits()` → GET `/admin/rate-limits` (per-endpoint or per-scope).
  - `useUpdateRateLimits()` → PATCH `/admin/rate-limits` with rules `{ scope, window_sec, max_requests }`.

- **Audit Log**
  - `useAuditLog(params)` → GET `/admin/audit` (filters: actor, event, date range, resource).

All mutations invalidate related queries, use toasts for success/failure, and handle 401/402/403/422 via global handlers + `PlanUpgradeBanner`.

---

## 3) UI Requirements

### AdminHomePage
- Admin-only guard (`useAuth().isAdmin`). Show quick cards linking to each admin area and current plan summary.

### Plans & Feature Matrix
- `AdminPlansPage` with **FeatureMatrixEditor**:
  - Rows = features/limits; Columns = plans.
  - Cells editable: boolean features and numeric limits.
  - Save with `useUpdatePlan`. Zod validation for numeric ranges.
  - Confirm dialog before reducing limits (warn about blocking existing tenants).

### Roles & Permissions
- `AdminRolesPage` with **RoleEditor**:
  - List roles (Admin, Agent, Viewer…).
  - Permission tree (read/write per domain). Bulk check/uncheck.
  - Save via `useUpdateRole`. Prevent removal of last admin permission.

### API Keys
- `AdminApiKeysPage`:
  - `ApiKeyCard` list (name, scopes, lastUsedAt). Create dialog: name + scopes multiselect.
  - On create → show **plaintext token** once with copy-to-clipboard + “treat like password” warning.
  - Revoke key → confirm dialog.

### Webhooks
- `AdminWebhooksPage` with **WebhookEndpointEditor** and **WebhookDeliveryTable**:
  - Create/Update endpoint: URL, events[], secret (auto-generate/rotate), retry policy (backoff).
  - Delivery table: id, event, attempt, status, latency, response code, createdAt. Retry failed.
  - “Send test event” button to validate endpoint (use backend test route).

### Rate Limits
- `AdminRateLimitsPage` with **RateLimitRuleEditor**:
  - Rules per scope (e.g., `public_api`, `supplier_api`, `web_app`).
  - Inputs: window seconds, max requests. Show examples (“100 req / 60s”).
  - Save and show preview summary.

### Audit Log Viewer
- `AdminAuditLogPage` with **AuditLogTable**:
  - Filters: actor (user/email), event name, resource (type/id), date range.
  - Columns: timestamp, actor, event, resource, metadata (expandable JSON). Export CSV.

---

## 4) Validation & Business Rules
- Admin guard for all pages; non-admins get 403.
- API key tokens shown once; force copy action; never store plaintext client-side.
- Webhook URL must be https; debounce validate with HEAD/GET test (if endpoint available).
- Rotating secrets invalidates old signature immediately; warn user.
- Rate-limit updates apply immediately; show UTC clock reference.
- Audit log is read-only; pagination & server-side filtering required.
- Plan gating: only tenants with `admin_console_enabled` can access Admin area (if you gate it).

---

## 5) Components & UX
- Use shadcn `DataTable`, `Dialog`, `Drawer`, `Tabs`, `Badge`, `Card`, `Alert`, `Tooltip`.
- Skeletons and empty states for all lists.
- a11y: field labels, aria descriptions, keyboard navigation, focus traps in dialogs.
- Copy-to-clipboard success toast and “copied!” feedback.

---

## 6) Testing
- Hooks: unit tests for API key create/revoke, webhook create/retry, plan update, rate-limit update.
- Pages: render tests with admin guard + form validation.
- Contract tests: ensure OpenAPI/SDK endpoints match used shapes (CI job).
- (Optional) E2E: Admin user can modify plan, create webhook, send test, see delivery, create API key.

---

## 7) Acceptance Criteria
- Admin-only area accessible; non-admins receive 403.
- Plans page edits features/limits and persists with server.
- Roles page updates permissions and prevents “no-admin-left” state.
- API keys: create shows token once; revoke works; last used at visible.
- Webhooks: CRUD endpoints; can send test & view delivery log; retry failed deliveries.
- Rate limits: rules editable & saved; summaries visible.
- Audit log: filterable/paginatable with CSV export.
- All calls via TS SDK; errors handled globally; UI responsive with skeleton/empty/error states.
