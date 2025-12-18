1. Add AI config + env vars — **Status: ✅ Completed (config/ai.php, .env.example)**

Prompt:

"Create config/ai.php and add env-driven settings for the AI microservice: AI_BASE_URL, AI_TIMEOUT_SECONDS, AI_ENABLED, AI_SHARED_SECRET. Add these keys to .env.example. Use sensible defaults and keep secrets only in .env."

2. Create a typed Laravel AI client — **Status: ✅ Completed (app/Services/Ai/AiClient.php, app/Exceptions/AiServiceUnavailableException.php)**

Prompt:

"Create app/Services/Ai/AiClient.php using Laravel Http:: client. Implement methods:
- "forecast(array $payload): array"
- "supplierRisk(array $payload): array"
The client must:
- "Use timeout from config"
- "Send X-AI-Secret header"
- "Return a uniform array { status, message, data, errors }"
- "Throw a custom exception AiServiceUnavailableException on connection/timeouts.""

3. Create an ai_events table for AI auditability — **Status: ✅ Completed (database/migrations/2025_12_18_090000_create_ai_events_table.php)**

Prompt:

"Create a migration for ai_events table to log every AI request/response:
- company_id, user_id
- feature (e.g. forecast, supplier_risk)
- entity_type, entity_id (nullable)
- request_json, response_json (JSON)
- latency_ms, status (success|error), error_message (nullable)
- timestamps
- Add indexes on (company_id, feature, created_at) and (entity_type, entity_id)."

4. Add AiEvent model + helper recorder — **Status: ✅ Completed (app/Models/AiEvent.php, app/Services/Ai/AiEventRecorder.php)**

Prompt:

"Create app/Models/AiEvent.php with proper casts for JSON columns. Then create app/Services/Ai/AiEventRecorder.php with a method record(...) that writes an ai_events row for both success and error cases. Ensure tenant scoping via company_id."

5. Expose Laravel API endpoints that proxy to the microservice — **Status: ✅ Completed (app/Http/Controllers/Api/V1/AiController.php, routes/api.php, app/Http/Requests/Api/Ai/**)**

Prompt:

"Create app/Http/Controllers/Api/V1/AiController.php with:
- POST /api/v1/ai/forecast
- POST /api/v1/ai/supplier-risk
- Validate inputs using FormRequest classes.
- Use AiClient to call the microservice.
- Always return the standard API envelope { status, message, data, errors } with correct HTTP codes.
- Record each call using AiEventRecorder (include user, company, payload, latency, result)."

6. Add RBAC + plan gating middleware for AI endpoints — **Status: ✅ Completed (routes/api.php, app/Http/Controllers/Api/V1/AiController.php)**

Prompt:

"Apply middleware to AI endpoints:
- auth (Sanctum/session as per existing project)
- tenant scoping
- EnsureSubscribed where relevant
Add simple permission checks:
- Forecast endpoint: buyer roles + finance (read) allowed
- Supplier risk: buyer roles allowed; supplier roles only see their own supplier data if applicable
- Return 403 with standard envelope when denied."

7. Add frontend API wrapper for AI calls — **Status: ✅ Completed (resources/js/services/ai.ts)**

Prompt:

"Create resources/js/services/ai.ts using the existing api.ts wrapper. Implement:
- getForecast(payload)
- getSupplierRisk(payload)
- Each function must return {status,message,data,errors} and throw a normalized error object for toasts. No ad-hoc fetch calls."

8. Add “Forecast Insight” UI widget (read-only, human-in-the-loop)

Prompt:

"Create a reusable React component ForecastInsightCard.tsx under resources/js/components/ai/.
It should:
- Accept partId and history props
- Call getForecast() on button click ‘Generate forecast’
- Show results: safety stock, reorder point, suggested order by date, model used, and explanation (if available)
- Never auto-apply changes; show an ‘Apply to reorder settings’ button that only emits an event/callback to parent.
- Add loading skeleton + error toast + empty state."

9. Add "Supplier Risk Badge" UI component + drilldown

Prompt:

"Create SupplierRiskBadge.tsx under resources/js/components/ai/.
- Shows Low/Medium/High with neutral enterprise styling
- On click opens a drawer/modal showing: risk_score, risk_category, explanation bullets, last updated timestamp
- Fetches via getSupplierRisk() (do not compute in UI)
- If AI is unavailable, show ‘Hint unavailable’ and keep the rest of the page functional."

10. Wire the badge into Supplier Directory + Quote Comparison

Prompt:

"Integrate SupplierRiskBadge into:
- Supplier Directory list rows/cards
- Quote comparison table rows
Ensure it does not block page load: render badge in a lazy way (load on hover/click or after initial render). Respect role/plan gating by hiding or locking the badge."

11. Add a simple AI Activity Log screen for admins

Prompt:

"Create a backend endpoint GET /api/v1/admin/ai-events (platform admin / company admin) with filters: feature, status, date range, entity.
Build a React admin page using the shared DataTable component to show AI events and allow CSV export.
Columns: time, user, feature, entity, status, latency_ms."

12. dd minimal feature tests (don't overdo) — **Status: ✅ Completed (tests/Feature/Api/Ai/AiControllerTest.php)**

Prompt:

"Write Pest/Feature tests for:
- 401 if unauthenticated
- 403 if role not allowed
- 200 success envelope shape for forecast and supplier-risk (mock AiClient)
- ai_events row created on success and on failure."