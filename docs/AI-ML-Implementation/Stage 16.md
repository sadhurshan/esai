1. Extend analytics service for forecast reporting

Prompt

"In app/Services/AnalyticsService.php, add a method generateForecastReport(int $companyId, array $params): array that:
• Accepts filters (start_date, end_date, part_ids, category_ids, location_ids).
• Queries ForecastSnapshot, InventoryTxn and part metadata to compute:
– Time‑series arrays of actual usage vs forecasted demand per part (daily or weekly).
– Aggregated forecast metrics: sum of forecasted demand, sum of actual consumption, MAPE, MAE, avg daily demand, recommended reorder point & safety stock.
• Returns a structured array:
– series: list of objects (part_id, part_name, data: list of {date, actual, forecast}) suitable for plotting.
– table: list of rows (part_id, part_name, total_forecast, total_actual, mape, mae, reorder_point, safety_stock).
– filters_used echoing the input filters.
• Add helper methods (private or new class) to compute forecast accuracy and reorder recommendations.
• Ensure queries respect tenant scoping and filters."

2. Extend analytics service for supplier performance reporting

Prompt

"In the same service or new SupplierAnalyticsService, implement generateSupplierPerformanceReport(int $companyId, int $supplierId, array $params): array that:
• Computes monthly or weekly metrics for the given supplier: on‑time delivery rate, defect rate, lead‑time variance, price volatility, service responsiveness, risk score.
• Returns:
– series: list of metrics over time (metric_name, data: list {date, value}).
– table: single row with aggregated metrics and current risk category.
– filters_used.
• Use existing supplier_risk_scores table and purchase order/goods receipt data.
• Ensure supplier sees only their own data (tenant + supplier scope)."

3. Create new API endpoints

Prompt

"Add controller methods in app/Http/Controllers/Api/V1/AnalyticsController.php (or create a new controller) with routes:
• GET /api/v1/analytics/forecast-report – accepts query params (start_date, end_date, part_ids[], category_ids[], location_ids[]) and returns the generateForecastReport output plus a summary string. Only buyers with analytics permission can call.
• GET /api/v1/analytics/supplier-performance-report – accepts supplier_id (implicit from auth for supplier users), date filters; returns the performance report plus a summary.
• For each, call AiClient->summarizeReport() (see next prompt) to generate the AI summary; fallback to a deterministic summary if the LLM provider is disabled.
• Wrap responses in the standard envelope {status, message, data, errors} and record an ai_event."

4. Add report summarization in the microservice

Prompt

"In ai_microservice/app.py, add a new endpoint POST /report/summarize (or extend /answer with a mode) that accepts:
• report_type (forecast|performance)
• report_data (the series/table arrays returned by the analytics service)
• filters_used and company_id, user_id_hash.
It should:
• Build a prompt with context: key numbers, trends, filter ranges, and entity names.
• Call LLMProvider.generate_answer() with a small JSON schema (REPORT_SUMMARY_SCHEMA containing summary_markdown and bullets), instructing the model to produce 3–6 bullet points and a short narrative about notable patterns.
• Return the JSON result.
• If the provider is disabled or fails, fall back to a deterministic summarizer (compute top increases/decreases, highlight high risk).
• Add logging and error handling."

5. Add summarizeReport() method to AiClient

Prompt

"In app/Services/Ai/AiClient.php, implement summarizeReport(array $params): array that posts to the microservice /report/summarize endpoint. Accepts an associative array with report_type, report_data, filters_used, and returns the summary data. Use the standard timeout and header patterns. Handle errors gracefully and fallback to a deterministic summary if necessary."

6. Front‑end: create Forecast Report page

Prompt

"Create resources/js/pages/analytics/forecast-report-page.tsx:
• Use React Query hook (useForecastReport) that calls /api/v1/analytics/forecast-report with the selected filters and caches the result.
• Implement a filter panel component with date range pickers, multi-select for parts, categories and locations. On filter change, refetch the report.
• Render:
– A summary card showing the AI-generated narrative and bullets.
– A line chart for each selected part (or aggregated), plotting actual vs forecast; include legend and tooltips.
– A sortable table summarizing total forecast vs actual, accuracy and reorder recommendations.
– Optionally, a secondary chart for forecast error over time.
• Display loading and error states; hide page if user lacks analytics permission.
• Place a link to this page in the buyers’ side navigation (e.g. under Analytics → Inventory Forecast)."

7. Front‑end: create Supplier Performance Report page

Prompt

"Create resources/js/pages/analytics/supplier-performance-page.tsx:
• Use useSupplierPerformanceReport hook to call /api/v1/analytics/supplier-performance-report.
• Render a summary card with AI narrative.
• Display multi-metric charts (e.g. one line per metric) showing weekly values for on‑time rate, defect rate, lead‑time variance, price volatility. Use toggles to hide/show series.
• Show a current metrics table (overall values, risk category and score).
• Add filters for date range; supplier sees only their own data; buyer can select a supplier to analyse (if admin/manager).
• Add this page under Analytics → Supplier Performance in the appropriate menus."

8. Build hooks for analytics APIs

Prompt

"Implement new hooks in resources/js/hooks/api/analytics/:
• useForecastReport(params) – uses React Query to call /api/v1/analytics/forecast-report, returns data (series, table, summary, filters_used), loading and error states.
• useSupplierPerformanceReport(params) – similar for supplier performance.
These hooks should handle caching keys based on filters and invalidation when filters change."

9. Create shared chart & table components

Prompt

"Under resources/js/components/analytics/, build reusable components:
• ForecastLineChart.tsx – accepts series and renders a multi-line chart (using Chart.js or existing chart library), supports tooltips, colors, and legend.
• MetricsTable.tsx – accepts an array of metric rows and column definitions, uses the existing DataTable component for sorting and pagination.
• PerformanceMultiChart.tsx – renders multiple metrics on the same axis or separate axes; include checkboxes to toggle series.
Document their props with TypeScript interfaces."

10. Gating, permissions & audits

_Status: ✅ Completed on 2025-12-22 - analytics routes enforce plan + permission gates, UI now shows upgrade/access-denied states, and ai_events capture every request/summary._

Prompt

"Apply middleware and gates:
• Buyers and supplier roles must have analytics_enabled in their plan.
• Buyers need view_forecast_report permission to access forecast reports; suppliers need view_supplier_performance.
• If unauthorized, return HTTP 403 and show a “upgrade required” or “access denied” page.
• Record each API call in ai_events with feature='forecast_report' or feature='supplier_performance_report', including filters used and summary length.
• Log summarization calls in ai_events as feature='report_summary' with latency and provider used."

11. Unit & feature tests

_Status: ✅ Completed on 2025-12-22 – Pest suites cover AnalyticsService aggregates plus API gate checks, pytest exercises the /reports/summarize FastAPI endpoint (LLM + fallback), and new Vitest suites validate the React hooks/pages render AI summaries, tables, charts, and permission states. All suites pass in CI._

Prompt

"Write tests:
• For Laravel: ensure generateForecastReport and generateSupplierPerformanceReport return correct aggregates given sample data; ensure endpoints respect filters and permissions; ensure summaries are included.
• For microservice: test /report/summarize returns JSON matching the schema, uses fallback summarization when LLM is off.
• Front-end: ensure pages render charts and tables with mocked API data; test filter interactions update the report; test summary card displays content.
• Run these tests in CI and ensure passing before moving forward."

12. Specification & acceptance criteria

• Buyers can access detailed Inventory Forecast Reports: historical consumption, future demand forecasts, safety stock / reorder points, and forecast accuracy metrics (MAE, MAPE). These should be shown as line charts and tables.
• Suppliers can access Supplier Performance Reports: on‑time delivery, quality defect rate, lead‑time variability, price volatility and risk scores over time; displayed as bar/line charts and tables.
• Both reports must support filters: date range (rolling periods or custom), part categories, individual parts, suppliers, locations.
• Each report includes an AI‑generated summary: the system uses the LLM provider to produce a narrative (“In the past 90 days, demand for Part X increased 25 %…”).
• Reports must be tenant- and role‑scoped (buyers see buyer data; suppliers see their own performance only).
• Acceptance criteria: endpoints return data and summary; UI renders charts/tables with filters; summaries appear; actions are audited; permissions enforced.”