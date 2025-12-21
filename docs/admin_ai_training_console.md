# Admin AI Training Console

## Overview
The admin AI training console (web path `/app/admin/ai-training`) lets platform operators retrain demand forecasting, supplier risk, RAG/search, deterministic actions, workflow copilots, and (future) chat assistants on behalf of any tenant. It surfaces the latest job status + metrics, exposes manual filters, and provides a controlled "Train now" flow that calls the Laravel `Admin\AiTrainingController` and ultimately the AI microservice through `AiTrainingService`.

## Access Requirements
1. **Role:** Only platform super-admins (`PlatformAdminRole::Super`, `user.role = platform_super`) can load the page. Both the route and API stack enforce `admin.guard:super` plus the `can:canTrainAi` gate registered in `AuthServiceProvider`.
2. **Plan/Feature flag:** The tenant being trained must have `ai_training_enabled`. The React auth context sets `featureFlags.ai_training_enabled` based on `config/plans.php` or a `company_feature_flags` override. The backend also gates every admin training route through the `EnsureAiTrainingEnabled` middleware which checks plan codes (`PLAN_AI_TRAINING_CODES` env) before dispatching work.
3. **Authentication:** Standard `/api/admin/ai-training/*` endpoints still require the API auth guard (`auth`, `auth.session`) and bypass company context via `BypassCompanyContext` so multi-tenant admins can operate across tenants safely.

If any requirement fails, users see the Access Denied page or receive `{ status: 'error', errors: { code: 'ai_training_disabled' } }` with HTTP 402 prompting a plan upgrade.

## Navigating the Console
- The admin home quick links filter out "AI training console" unless the current user’s `canTrainAi` flag is true.
- Within the console:
  - **Feature tiles**: One card per feature (Forecasting, Supplier Risk, RAG/Search, Deterministic Actions, Workflows, Chat) showing last run timestamps, status badge, metrics list, and a `Train now` CTA.
  - **Filters panel**: A form for feature, status, company ID, microservice job ID, and date ranges. Clicking Apply updates the React Query hook (`useAiTrainingJobs`) to fetch filtered results; Clear resets to defaults.
  - **Job table**: Auto-refreshes every 10 seconds when enabled, rendering cursor-paginated results from `GET /api/v1/admin/ai-training/jobs`. Each row exposes manual refresh (`POST /jobs/{id}/refresh`) to sync microservice status.
  - **Schedule form**: Captures recurring preferences (daily/weekly/monthly). The UI currently stores these client-side and toasts "Schedule staged" while backend scheduling endpoints are finalized.

## Starting a Manual Training Run
1. Confirm the destination tenant is plan-entitled (growth/enterprise by default) or flip the `ai_training_enabled` company override in `CompanyFeatureFlagController`.
2. Open the desired feature tile and click **Train now**. This opens the modal bound to `StartAiTrainingRequest`.
3. Provide:
   - `companyId` (required, numeric tenant ID)
   - Optional window (`startDate`, `endDate`, `horizon`), `reindexAll`, `datasetUploadId`, and JSON `additionalParams`.
4. Submit to call `POST /api/v1/admin/ai-training/start`. On success the UI toasts "Training started" and resets the dataset file field; the queue dispatches `RunModelTrainingJob`.
5. Monitor the new row in the job list. Status transitions (pending → running → completed/failed) stream via polling or manual refresh.

## Monitoring Telemetry & Troubleshooting
- **Auto-refresh toggle** in the header keeps the list synced. Disable it while deep-diving filters to avoid cursor jumps.
- **Status colors**: amber=pending, sky=running, emerald=completed, rose=failed.
- **Metrics** appear once the AI microservice posts back results (`result_json`), e.g., `mape`, `mae`, `documents_indexed`, `latency_ms`.
- **Manual Refresh** triggers `AiTrainingService::refreshStatus`, which re-polls the microservice and writes an `ai_training_<feature>_refresh` entry in `ai_events`.
- **Errors**: Failures capture `error_message`, set the badge red, and surface descriptive toasts. Common cases include plan gating (402), missing company context (403), or AI service outages (converted to `AiServiceUnavailableException`).

## Feature Flags & Overrides Cheat Sheet
- `config/plans.php` → `features.ai_training_enabled.plan_codes = ['enterprise']` by default; adjust with `PLAN_AI_TRAINING_CODES` in `.env`.
- Company-level overrides use `company_feature_flags.key = 'ai_training_enabled'` with `{ "enabled": true }` or boolean payloads.
- Frontend gating reads `featureFlags.ai_training_enabled`; backend gating relies on `EnsureAiTrainingEnabled` plus `canTrainAi`.

## Future Enhancements
- Wire the schedule form to `AiTrainingService::scheduleTraining()` once CRON semantics are finalized.
- Add Vitest coverage for the React console interactions (Train now modal, filters, auto-refresh) to complement the Pest API suite documented in `tests/Feature/Api/Admin/AiTrainingControllerTest.php`.
