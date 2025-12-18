1. Set up the AI/ML microservice

Status: DONE (2025-12-18) — Created ai_service.py with the AISupplyService skeleton and fully documented method stubs.

Goal: Isolate the ML logic from the Laravel app by creating a Python microservice. This makes experimentation and deployment easier.

- Prompt to Copilot:
"Create a new Python module called ai_service.py. Define an empty class AISupplyService with methods load_inventory_data(), train_forecasting_models(), predict_demand(), load_supplier_data(), train_risk_model(), and predict_supplier_risk(). Each method should include a detailed docstring explaining its purpose and input/output formats."

- Rationale: This prompt gives Copilot enough context to generate a well‑documented skeleton. You can then fill in each method incrementally.

2. Data extraction & preparation

Status: DONE (2025-12-18) — Implemented load_inventory_data() for scoped inventory history and load_supplier_data() to aggregate supplier KPIs (on-time, defects, lead-time variance, price volatility, responsiveness) via SQLAlchemy queries.

Goal: Gather and clean the data required for forecasting and risk models.

- Prompt to Copilot:
"Inside load_inventory_data(), write Python code (using SQLAlchemy or raw SQL) to connect to our PostgreSQL/MySQL database and fetch at least 12 months of historical inventory transactions. Filter for issues, returns, transfers and adjustments; group by part and date; and return a DataFrame with columns part_id, date, and quantity. Handle missing values by filling zeros for days with no transactions."

- Prompt to Copilot:
"In load_supplier_data(), load supplier delivery records (PO delivery dates vs promised dates), defect/return rates, lead‑time data and price history. Structure the return value as a DataFrame with supplier identifiers and feature columns such as on_time_rate, defect_rate, lead_time_variance, price_volatility and service_responsiveness."

- Rationale: The requirement document specifies that forecasting must consider demand history, lead times, supplier performance and maintenance signals. These prompts will collect that data in a structured way.

3. Time‑series forecasting

Status: DONE (2025-12-18) — Added Exponential Smoothing + Random Forest trainers in AISupplyService, stored per-part metrics/registry, and wired predict_demand() to select the best model and compute demand, reorder point, safety stock, and order-by dates.

Goal: Implement and evaluate several forecasting models.

- Prompt to Copilot:
"Implement train_forecasting_models(self, data: pd.DataFrame, horizon: int) to train at least two time‑series models: (1) a statistical model (ARIMA or exponential smoothing) and (2) a machine‑learning model (such as Prophet or an LSTM). Split the data into training and validation sets, fit each model per part_id, and compute metrics like MAPE and MAE."

- Prompt to Copilot:
"Implement predict_demand(self, part_id: int, history: pd.Series, horizon: int) that uses the best performing model from train_forecasting_models to forecast demand for the next horizon days. Return a dictionary with demand_qty, avg_daily_demand, reorder_point, safety_stock and order_by_date."

- Rationale: The requirements call for seasonal/time‑series models for steady items and frequency/size estimation for infrequent spare parts. Starting with ARIMA and Prophet/LSTM allows you to test different approaches without complexity.

4. Supplier risk and ESG scoring

Status: DONE (2025-12-18) — Implemented Gradient Boosting-based train_risk_model() with metrics + feature tracking and predict_supplier_risk() that returns risk category, score, and explanation derived from feature importances.

Goal: Build a model that scores suppliers based on performance and risk factors.

- Prompt to Copilot:
"Implement train_risk_model(self, supplier_df: pd.DataFrame) that trains a classification or regression model (e.g. gradient boosting or random forest) to predict a supplier risk score. Use features on_time_rate, defect_rate, lead_time_variance, price_volatility, and service_responsiveness. Split the data into training and validation sets and return the fitted model along with performance metrics."

- Prompt to Copilot:
"Implement predict_supplier_risk(self, supplier_row: pd.Series) that takes a single supplier’s features and returns a risk category (High, Medium, Low) and an explanation string summarising which factors influenced the score (e.g. ‘High risk due to 3 late POs + missing ISO9001 + rising defect trend’’)."

- Rationale: The requirement document specifies blending on‑time delivery, defect/return rates, and price/lead‑time volatility into a risk score with explainable badges. Copilot can scaffold a model and add simple explanation logic, which you can refine later.

5. API layer

Status: DONE (2025-12-18) — Added FastAPI app (ai_microservice/app.py) exposing /forecast and /supplier-risk endpoints with validation, CORS, and AISupplyService wiring.

Goal: Expose the models via a REST API so the Laravel app can consume predictions.

- Prompt to Copilot:
"Create a Flask (or FastAPI) application in app.py with two routes: /forecast and /supplier-risk. The /forecast route should accept JSON containing part_id, history (list of date/quantity pairs), and horizon, call predict_demand() and return the results. The /supplier-risk route should accept a JSON object with supplier features, call predict_supplier_risk() and return the risk category and explanation."

- Rationale: Encapsulating the ML logic in an API enables easy integration with your existing Laravel jobs and future services.

6. Integration with Laravel

Status: DONE (2025-12-18) — Forecast snapshots now call the AI microservice for demand metrics and the new ComputeSupplierRiskScoresJob pushes supplier KPIs to /supplier-risk, persisting AI grades/scores via SupplierRiskScore records.

Goal: Modify the PHP jobs to call the Python microservice and store predictions.

- Prompt to Copilot (PHP):
"In ComputeInventoryForecastSnapshotsJob.php, replace the call to calculateAverageDailyDemand() with an HTTP request to the /forecast endpoint of our AI microservice. Send the part’s transaction history and horizon, then parse the response and update ForecastSnapshot fields (e.g. demand_qty, avg_daily_demand, projected_runout_days, reorder_point, safety_stock). Handle errors gracefully."

- Prompt to Copilot (PHP):
"Create a new Laravel job ComputeSupplierRiskScoresJob that iterates through suppliers, gathers their performance metrics, sends them to the /supplier-risk endpoint, and stores the returned risk score and explanation in a supplier_risk_scores table."

- Rationale: This step links your AI/ML outputs to the existing application workflow, ensuring there is no gap between the requirement and the implementation.

7. Testing & monitoring

Status: DONE (2025-12-18) — Added pytest coverage for AISupplyService forecasting/risk training, introduced monitoring helpers (weekly MAPE + risk drift PSI), and instrumented FastAPI routes with structured request/response logging and timing; verified via `pytest ai_microservice/tests` passing after tightening the risk split fallback for tiny supervised datasets.

Goal: Ensure that the AI models behave as expected and that any issues are detected early.

- Prompt to Copilot:
"Write pytest unit tests for train_forecasting_models() and train_risk_model() using synthetic datasets. Validate that the functions return a model object and metrics dictionary, and that predictions fall within reasonable ranges."

- Prompt to Copilot:
"Implement logging inside each API route to record request inputs, prediction outputs, and processing time. Add simple monitoring functions that compute weekly MAPE for forecasts and track drift in supplier risk distributions."

- Rationale: Continuous testing and monitoring will help you catch data quality issues and model degradation early.