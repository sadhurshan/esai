1. Add microservice health + readiness endpoints

Prompt:

"In ai_microservice/app.py, add:
- GET /healthz returning {status:'ok', service:'ai_microservice', time:<iso>}
- GET /readyz returning {status:'ok', models_loaded:true|false, last_trained_at:<iso|null>}
Ensure no DB calls here. Add basic logging."

2. Add request correlation IDs (trace end-to-end)

Prompt:

"In ai_microservice/app.py, implement middleware that:
- Reads X-Request-Id header (or generates UUID)
- Adds it to response headers
- Logs it with every request/exception
Then update Laravel AiClient to send X-Request-Id (use Laravel request id if exists, otherwise generate)."

3. Add microservice response caching (safe + short-lived)

Prompt:

"Add an in-memory TTL cache in the microservice for /forecast and /supplier-risk responses:
- Cache key should include company_id (if provided), part_id/supplier_id, horizon, and a hash of history length + last date
- Default TTL: 5 minutes
- Bypass cache if request header X-AI-Cache: bypass is sent
Keep it simple (no Redis yet)."

4. Persist trained models to disk (so restarts don’t lose training)

Prompt:

"In ai_service.py, implement model persistence:
- Add save_models(path) and load_models(path) using joblib (or pickle) for forecasting registry + supplier risk model + metadata (thresholds, feature columns, imputation values)
- On service startup, call load_models() if files exist
- Store last_trained_at timestamps in metadata
Add clear docstrings and error handling."

5. Add AI model metrics table in Laravel (for drift + quality)

Prompt:

"Create a migration for ai_model_metrics table with:
- company_id
- feature (forecast|supplier_risk)
- entity_type, entity_id (nullable)
- metric_name (mape|mae|f1|accuracy|r2 etc.)
- metric_value decimal
- window_start, window_end
- notes json nullable
- timestamps
Add indexes on (company_id, feature, metric_name, window_end)."

6. Compute forecast accuracy (MAPE/MAE) periodically

Prompt:

"Create a Laravel job ComputeForecastAccuracyMetricsJob:
- For each part with ForecastSnapshots older than the horizon, compare predicted demand vs actual usage from InventoryTxns over the same window
- Compute MAE and MAPE
- Write results into ai_model_metrics
- Record an ai_events row for the metrics run (feature = 'forecast_metrics')
Make it safe for large datasets (chunking)."

7. Compute supplier-risk calibration/consistency metrics (basic)

Prompt:

"Create a Laravel job ComputeSupplierRiskMetricsJob:
- Compare risk scores vs real outcomes proxy (late delivery rate + defect rate trend during next 30 days)
- Compute simple correlation / bucket stats (e.g., High-risk suppliers should have higher late rates)
- Store into ai_model_metrics as risk_bucket_late_rate_high|medium|low
- Record an ai_events row for this run."

Status: ✅ Implemented in app/Jobs/ComputeSupplierRiskMetricsJob.php with nightly scheduling and feature tests in tests/Feature/Jobs/ComputeSupplierRiskMetricsJobTest.php.

8. Add an Admin "AI Model Health" dashboard

Prompt:

"Create an admin API endpoint GET /api/v1/admin/ai-model-metrics with filters: feature, metric_name, date range.
Build a React admin page that:
- Shows latest metrics cards (MAPE, MAE, F1, error-rate)
- Shows a simple trend chart for selected metric
- Shows warnings if thresholds exceeded (e.g., MAPE > 35%).
Keep UI consistent with existing admin styling."

Status: ✅ API served by app/Http/Controllers/Admin/AiModelMetricController.php with policy + tests, surfaced via AdminAiModelHealthPage in resources/js/pages/admin/admin-ai-model-health-page.tsx.

9. Add circuit breaker + fallback in Laravel AiClient

Prompt:

"Enhance AiClient:
- If microservice fails N times within a rolling window (e.g., 5 failures in 2 minutes), open circuit for 5 minutes
- During open circuit, return {status:'error', message:'AI temporarily unavailable', data:null} without calling microservice
- Record an ai_events row for circuit-open and circuit-skip
Keep config in config/ai.php."

Status: ✅ Circuit breaker implemented in app/Services/Ai/AiClient.php with cache-backed failure tracking and coverage in tests/Unit/Services/AiClientTest.php.

10. Add rate limiting + security hardening for AI endpoints

Prompt:

"Apply Laravel rate limiting to /api/v1/ai/* routes:
- Example: 30 requests/minute per user, separate bucket per company
- Ensure consistent JSON envelope on 429
Also ensure the X-AI-Secret header is required server-side when calling microservice, and reject calls if AI_ENABLED=false."

Status: ✅ Guarded via EnsureAiServiceAvailable + AiRateLimiter middleware with config-driven limits, enforced headers, and feature tests in tests/Feature/Api/Ai/AiControllerTest.php.

11. Add integration tests (Laravel ↔ microservice contract)

Prompt:

"Write contract tests that validate the response shape for:
- /forecast returns keys: demand_qty, avg_daily_demand, reorder_point, safety_stock, order_by_date, model_used
- /supplier-risk returns: risk_category, risk_score, explanation
Use mocked microservice responses in Laravel tests and also add microservice unit tests for Pydantic validation errors."

Status: ✅ Contract coverage lives in tests/Feature/Api/Ai/AiControllerTest.php and microservice validation tests reside in ai_microservice/tests/test_app.py.

12. Add deployment packaging for microservice

Prompt:

"Add ai_microservice/Dockerfile and docker-compose.ai.yml:
- Install dependencies
- Run uvicorn with sensible workers
- Expose port and healthchecks using /healthz
Add a short README for local + production run commands and required env vars."

Status: ✅ Shipping via ai_microservice/Dockerfile, docker-compose.ai.yml, and ai_microservice/README.md with health-aware uvicorn startup guidance.