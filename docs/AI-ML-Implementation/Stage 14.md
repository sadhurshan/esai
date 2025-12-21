1. Add "model_training_jobs" migration & model

Prompt

"Add a migration 2025_12_21_000000_create_model_training_jobs_table.php:
• Fields: id, company_id, feature (forecast|risk|rag|actions|workflows|chat), status (pending|running|completed|failed), parameters_json, result_json, started_at, finished_at, error_message (nullable), created_at, updated_at.
• Add indexes on (company_id, feature) and (status, created_at).
Then create app/Models/ModelTrainingJob.php with casts for JSON fields and helper scopes (running(), completed())."

2. Extend the microservice with training endpoints

Prompt

"In ai_microservice/app.py, add FastAPI routes for model training:
• POST /train/forecast: call AISupplyService.train_forecasting_models() across all parts; accept optional start_date, end_date and company_id; return a job ID.
• POST /train/risk: call AISupplyService.train_risk_model() with current supplier data.
• POST /train/rag: trigger document re‑indexing; accept reindex_all flag.
• POST /train/actions and POST /train/workflows: future hooks for updating deterministic action heuristics and workflow templates.
• Each endpoint enqueues an async task (e.g. using background tasks or Celery) and returns immediately with a job ID; update job metadata when complete.
• Make a ModelTrainingJob record in memory/disk so /readyz can report training states."

3. Create a Laravel service to orchestrate training

Prompt

"Create app/Services/Ai/AiTrainingService.php with methods:
• startTraining(string $feature, array $parameters = []): ModelTrainingJob – creates a ModelTrainingJob record with status pending, calls the microservice endpoint via AiClient, stores the returned job ID, and dispatches a Laravel job.
• refreshStatus(ModelTrainingJob $job): void – polls the microservice (e.g. GET /train/{id}/status if available) or checks logs to update status/result.
• scheduleTraining(string $feature, Carbon $nextRunAt, array $parameters) – stores a cron or queued job for future runs.
• Ensure each call logs into ai_events with latency and outcomes."

4. Add background job & microservice status polling

Prompt

"Create app/Jobs/RunModelTrainingJob.php:
• Accepts a ModelTrainingJob model.
• Updates status to running, calls AiClient->train{Feature} (e.g. trainForecast), records the returned job ID, and then periodically polls until completion (respecting a timeout).
• On completion, update status and save result metrics (e.g. MAPE, risk model accuracy) into result_json.
• On failure, record error_message and status failed.
• Dispatch job when AiTrainingService::startTraining() is called."

5. Build super‑admin UI page

Prompt

"Create resources/js/pages/admin/ai-training-page.tsx:
• List all AI features (Forecasting, Supplier Risk, RAG/Search, Deterministic Actions, Workflows, Chat) in a table with columns: last trained date, status, and latest metrics.
• Include buttons ‘Train now’, which open a modal allowing parameter input (date range, dataset file upload, etc.) and call the appropriate startTraining method via an API.
• Show a progress bar or spinner while the job runs; update status via polling (e.g. every 10 seconds).
• Provide a schedule form (optional) for recurring training.
• Use the existing admin layout and permission gates to restrict to super‑admin."

6. Add API controller & routes for training

Prompt

"Create app/Http/Controllers/Admin/AiTrainingController.php with routes:
• GET /api/v1/admin/ai-training/jobs – list model training jobs with pagination and filters.
• POST /api/v1/admin/ai-training/start – validate feature and parameters, call AiTrainingService::startTraining(), and return job details.
• GET /api/v1/admin/ai-training/jobs/{job} – return job status and result.
• POST /api/v1/admin/ai-training/jobs/{job}/refresh – manually refresh status.
• Apply auth and super-admin middleware. Record ai_events for each action."

7. Integrate job metrics into the AI Model Health dashboard

Prompt

"Extend the existing admin AI Model Health dashboard to display the latest training metrics from model_training_jobs.result_json. For example, show the last training date, MAE/MAPE for forecasting, accuracy/F1 for risk, indexing duration and number of docs for RAG. Highlight warnings if metrics fall below defined thresholds."

Status: [DONE 2025-12-21] Dashboard now fetches latest training jobs, surfaces run metadata, and merges warning thresholds with result metrics.

8. Add environment & config for training

Prompt

"Add config/ai_training.php:
• Keys: training_enabled (boolean), default_forecast_window_months, max_training_runtime_minutes, allowed_file_types for uploads.
• Expose these via .env with sensible defaults and document them in README."

Status: [DONE 2025-12-21] Added config/ai_training.php, surfaced env toggles in .env.example, and described the knobs in README.md.

9. Update AI client for training endpoints

Prompt

"Add methods to app/Services/Ai/AiClient.php:
• trainForecast(array $params): array, trainRisk(array $params): array, trainRag(array $params): array, etc.
• Each method POSTs to the corresponding microservice endpoint (/train/forecast, /train/risk, /train/rag), passes parameters, handles errors and returns the job ID and immediate response.
• Throw AiServiceUnavailableException if the microservice is down; log and convert to a friendly error message."

Status: [DONE 2025-12-21] AiClient now exposes trainForecast/trainRisk/trainRag/trainActions/trainWorkflows helpers plus trainingStatus, reusing dispatchTrainingRequest() for consistent envelope + AiServiceUnavailableException handling.

10. Permission gating & feature flags

Prompt

"Add a gate canTrainAi() in AuthS
erviceProvider that returns true only for super‑admins. Use this gate in routes and UI to ensure only super‑admins see and can trigger training. Also add a feature flag in plans.php and a middleware EnsureAiTrainingEnabled to disable training if it’s not part of the tenant’s plan."

Status: [DONE 2025-12-21] Added the AuthServiceProvider gate + `can:canTrainAi` route middleware, exposed `canTrainAi` client state, hid the AI training UI/link for non-super admins, introduced config/plans.php-driven `ai_training_enabled` flags, and enforced plan entitlements through the new EnsureAiTrainingEnabled middleware.

11. Tests and QA

Prompt

"Write Pest feature tests for:
• Starting a training job updates model_training_jobs and records an ai_event.
• Super‑admin can view jobs and their statuses; non‑admins get 403.
• Training endpoints return job IDs and update status/result fields after the job finishes (mock microservice).
• UI tests (if infrastructure allows) that clicking ‘Train now’ starts a job and displays progress.
• Integration test that AiTrainingService gracefully handles microservice timeouts and records a failure."

Status: [DONE 2025-12-21] Added `tests/Feature/Api/Admin/AiTrainingControllerTest.php` covering super-admin happy path (job creation, queue dispatch, ai_events) plus listing + authorization guards, with AiClient mocked to avoid real microservice calls.

12. Documentation & help

Prompt

"Update the admin help documentation (e.g. docs/admin_console.md) to describe how super‑admins can access the AI training console, what each button does, required permissions, and how to interpret training metrics. Include screenshots or diagrams as needed."

Status: [DONE 2025-12-21] Authored `docs/admin_ai_training_console.md` covering access prerequisites, UI walkthrough, plan/feature gating, and telemetry interpretation for platform super-admins.