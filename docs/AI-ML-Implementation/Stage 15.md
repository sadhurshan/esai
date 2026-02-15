1. [x] Add scraping job & data tables

Prompt

"Create migrations:

- supplier_scrape_jobs with fields: id, company_id (nullable), user_id, query (string), region (string nullable), status (pending|running|completed|failed), parameters_json, result_count, error_message (nullable), started_at, finished_at, created_at, updated_at.
- scraped_suppliers with fields: id, company_id (nullable), scrape_job_id, name, website, description, industry_tags (JSON array), address, city, state, country, phone, email, contact_person, certifications (JSON), product_summary, source_url, confidence (decimal), metadata_json, created_at, updated_at.
- Add indexes on (company_id, scrape_job_id) and (status, created_at).
Add corresponding Eloquent models: SupplierScrapeJob and ScrapedSupplier, including casts for JSON fields."

2. [x] Implement scraping endpoints in the microservice

Prompt

"In ai_microservice/, create supplier_scraper.py with a class SupplierScraper containing methods:
• scrape_suppliers(query: str, region: str | None, max_results: int) -> list[dict] – uses Vertex AI Search (Discovery Engine) to fetch business websites related to the query/region; then for each result, fetch the page and use the existing LLM provider to extract structured information (name, address, products, contact info) via a prompt like ‘Extract supplier profile from this webpage:…’. Respect robots.txt by skipping disallowed domains.
• Handle rate limiting and errors gracefully; return a confidence score per site.
Add FastAPI endpoints in app.py:
• POST /scrape/suppliers accepts ScrapeSuppliersRequest with query, region, and max_results; enqueues a background task and returns a job ID.
• GET /scrape/jobs/{job_id} returns job status and result summary.
• Optionally: GET /scrape/jobs/{job_id}/results to stream or paginate scraped supplier records.
Use the CHAT_RESPONSE_SCHEMA pattern to enforce structured output with fields matching the scraped_suppliers table."

3. [x] Add Laravel service & model orchestrator

Prompt

"Create app/Services/Ai/SupplierScrapeService.php with methods:
• startScrape(string $query, ?string $region, int $maxResults): SupplierScrapeJob – validates inputs, creates a SupplierScrapeJob record with status pending, calls AiClient::scrapeSuppliers(), stores returned job ID and dispatches a Laravel job to poll results.
• refreshScrapeStatus(SupplierScrapeJob $job): void – polls the microservice /scrape/jobs/{id} endpoint until completion; updates job status and result_count; on completion, fetches results and creates ScrapedSupplier records.
Add methods on AiClient for scrapeSuppliers() and getScrapeJob(), which wrap the new microservice endpoints.
Record each action in ai_events with user, query and job status."

4. [x] Create a background polling job

Prompt

"Create app/Jobs/PollSupplierScrapeJob.php that accepts a SupplierScrapeJob model. It should:
• Set job status to running.
• Call SupplierScrapeService::refreshScrapeStatus() repeatedly (e.g. every 30 seconds) until the microservice signals completion or failure, respecting a configurable timeout.
• If completed, log result count and update the finished_at timestamp; if failed, record error_message and set status failed.
• Dispatch PollSupplierScrapeJob when startScrape() is called."

5. [x] Add super-admin UI for scraping

Prompt

"Create resources/js/pages/admin/supplier-scrape-page.tsx:
• Show a form with fields: Search Keywords (query), Region (dropdown or free text), Max Results (default 10–20).
• On submit, call /api/v1/admin/supplier-scrapes/start (to be added in the next step) and display a toast or progress indicator.
• List all scrape jobs in a table: query, region, status, started_at, finished_at, result_count, and a ‘View Results’ link.
• On clicking a job, show a detail page that lists each ScrapedSupplier (name, website, confidence, description) with a button ‘Review & Onboard’. Provide filters/search over scraped records.
• Use existing modal components to render the onboarding review form (see next prompt). Ensure the page is gated to super‑admin only."

6. [x] Create onboarding & approval flows

Prompt

"Implement a route POST /api/v1/admin/scraped-suppliers/{id}/approve in Admin/SupplierScrapeController.php:
• Accept edits to the scraped fields (e.g. correct name, update phone) and an optional file (logo or documentation).
• Validate the data, then create a real Supplier record along with associated Contacts, Certifications and Products as needed.
• Link the new supplier to the original ScrapedSupplier via a foreign key or audit log.
• Mark the scraped supplier as approved or discarded.
Also add DELETE /api/v1/admin/scraped-suppliers/{id} to discard a record without creating a supplier. Record all actions in ai_events."

7. [x] Add super-admin API controller & routes

Prompt

"Create app/Http/Controllers/Admin/SupplierScrapeController.php with endpoints:
• GET /api/v1/admin/supplier-scrapes – list all scrape jobs, optionally scoped to a tenant.
• POST /api/v1/admin/supplier-scrapes/start – calls SupplierScrapeService::startScrape().
• GET /api/v1/admin/supplier-scrapes/{job}/results – paginate through ScrapedSupplier records for a job.
• POST /api/v1/admin/scraped-suppliers/{scraped}/approve – finalize onboarding (see previous prompt).
• DELETE /api/v1/admin/scraped-suppliers/{scraped} – discard.
Wrap each route in super‑admin middleware and return a unified API envelope with status/messages."

8. [x] Update front-end hooks

Prompt

"Create React hooks:
• useSupplierScrapeJobs() – fetch and cache the list of scrape jobs.
• useStartSupplierScrape() – POST to start a new scrape and return a mutation hook.
• useScrapedSuppliers(jobId) – fetch paginated scraped suppliers.
• useApproveScrapedSupplier() and useDiscardScrapedSupplier() – call the approval/discard endpoints.
These hooks should update React Query caches and show toasts."

9. [x] Testing & validations

Prompt

"Write Pest feature tests to verify:
• Only super‑admins can start scraping jobs; other roles get 403.
• Starting a job creates a SupplierScrapeJob and enqueues polling.
• On job completion, ScrapedSupplier records are created with expected fields.
• Approving a scraped supplier creates a real Supplier record with related data and logs an ai_event.
• Discarding a record does not create a supplier.
On the microservice side, add unit tests for SupplierScraper.scrape_suppliers() using mocked search and LLM responses."