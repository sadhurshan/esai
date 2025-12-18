1. Scaffold the FastAPI application — **Status: ✅ Completed (ai_microservice/app.py)**

Create a new Python file (e.g., ai_microservice/app.py) to host the API.

Prompt to Copilot:

"Create a new module ai_microservice/app.py. Import FastAPI, logging, and any needed middleware (e.g. CORS).
• Instantiate a FastAPI app with a descriptive title and version.
• Configure CORS to allow requests from our frontend (use allow_origins=['*'] for now).
• Instantiate a global instance of AISupplyService for reuse across requests.
• Set up logging to print basic info-level messages."

2. Define request schemas using Pydantic — **Status: ✅ Completed (ai_microservice/app.py)**

Pydantic models will validate incoming JSON payloads and provide type hints.

Prompt to Copilot:

"In ai_microservice/app.py, define two Pydantic classes:
a. "ForecastRequest with fields:
– part_id: int (must be ≥ 1),
– history: list[dict] where each dict has date: str and quantity: float,
– horizon: int (must be > 0 and ≤ 90).
Include validators to check that history is not empty and that each entry contains both date and quantity."
b. "SupplierRiskRequest with one field: supplier: dict[str, Any].
Validate that this object is not empty.
Use appropriate Field definitions and @validator decorators for these models.""

3. Implement the /forecast route — **Status: ✅ Completed (ai_microservice/app.py)**

This endpoint will handle demand forecasting requests.

Prompt to Copilot:

"Add an async POST route /forecast that accepts a ForecastRequest.
• Convert the list of history dicts into a dict or Series keyed by date.
• Call service.predict_demand(part_id, history_series, horizon) and capture the returned dictionary.
• Log the request (part ID, horizon, number of history points) and the response time in milliseconds.
• Return a JSON object containing status: "ok" and the data from predict_demand().
• Wrap the logic in a try/except block; on error, log the exception and respond with a 400 status and the error message using HTTPException."

4. Implement the /supplier-risk route — **Status: ✅ Completed (ai_microservice/app.py)**

This endpoint will handle supplier risk scoring.

Prompt to Copilot:

"Add an async POST route /supplier-risk that accepts a SupplierRiskRequest.
• Extract the supplier feature dictionary from the request.
• Call service.predict_supplier_risk(supplier_dict) and capture the result.
• Log the supplier ID (if present) and the outcome (risk category) along with processing time.
• Return { "status": "ok", "data": <result> }.
• On error, log the exception and return a 400 HTTPException with the error detail."

5. Add basic monitoring and logging — **Status: ✅ Completed (ai_microservice/app.py)**

Ensure operational visibility for  API.

Prompt to Copilot:

"Within each route function, measure elapsed time using time.perf_counter().
Use the logging module to log success and failure events with context (part ID, horizon, history length; supplier ID; risk category; duration).
Configure logging at the module level (e.g. logging.basicConfig(level=logging.INFO)) so that logs include timestamps and severity levels."

6. Create simple API tests — **Status: ✅ Completed (ai_microservice/tests/test_app.py)**

Verify that endpoints behave as expected.

Prompt to Copilot:

"Write tests under ai_microservice/tests/ using pytest and httpx.AsyncClient (or FastAPI’s TestClient).
• Test /forecast with a sample ForecastRequest payload and assert a 200 status and expected keys (demand_qty, avg_daily_demand, etc.) in the response.
• Test /supplier-risk with a minimal supplier feature dict and confirm that the response includes risk_category and explanation.
• Include a test that sends malformed input and expects a 400 response."

7. Document usage — **Status: ✅ Completed (ai_microservice/app.py docstring)**

Finally, provide clear documentation for downstream teams.

Prompt to Copilot:

"Add comments or docstrings at the top of app.py describing how to start the FastAPI server (e.g. using uvicorn ai_microservice.app:app --reload) and what the two endpoints expect and return.
Document any environment variables needed by AISupplyService (e.g. database URL) for deployment."