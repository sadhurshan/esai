Plans, Stripe Subscriptions & Feature Entitlements

- [x] Check the plan and billing implementation with Stripe:
	- Stripe credentials, price overrides, and grace periods are loaded via `config/services.php`, `.env.example`, and consumed across the billing actions. Company/plan/subscription schema lives in `database/migrations/2025_11_03_000100_create_billing_tables.php`, while `app/Models/Plan.php` exposes every limit flag for downstream logic.
	- Company plan assignment plus complimentary/stub subscription scaffolding happens through `App\Http\Controllers\Api\CompanyPlanController`, `AssignCompanyPlanAction`, and the complimentary/stub actions so every tenant lands on a plan with a synthetic subscription immediately.

- [x] Plan entities (Starter/Growth/Enterprise + Community) and their limits:
	- `database/seeders/PlansSeeder.php` seeds all four tiers with the RFQ/invoice/user/storage caps and feature booleans, matching the spec list in `/docs/REQUIREMENTS_FULL.md`. The public catalog is exposed through `PlanCatalogController` + `PlanCatalogResource`, and the onboarding UI at `resources/js/pages/onboarding/plan-selection-page.tsx` renders those limits for buyers.

- [x] Subscription signup flow via Stripe Checkout and storing subscription IDs/statuses:
	- Authenticated buyers can now hit `POST /api/billing/checkout`, which invokes `StartPlanCheckoutAction` → `StripeCheckoutService::createPlanCheckout`, persists the checkout session metadata on the company subscription (`checkout_session_id`, `checkout_status`, etc.), and returns the hosted checkout URL.
	- `resources/js/pages/onboarding/plan-selection-page.tsx` detects paid tiers and routes them through the new checkout endpoint, redirecting the browser to Stripe once a session URL is returned. Free/community plans continue to flow through `/api/company/plan-selection` without requiring checkout.

- [x] Stripe webhook handling (invoice.paid, invoice.payment_failed, subscription updated/cancelled):
	- `routes/api.php` registers `/webhooks/stripe/*` endpoints that land in `App\Http\Controllers\Api\Billing\StripeWebhookController`, which authenticates events via `StripeWebhookService::verify()` and records a webhook audit trail. Handlers exist for all required event types and are covered by `tests/Unit/StripeWebhookServiceTest.php`.

- [x] Logic converting Stripe events into internal subscription state and entitlements:
	- `App\Services\Billing\StripeWebhookService` resolves the tenant (`company_id` metadata or stored `stripe_id`), upserts `customers`/`subscriptions`, and re-runs `AssignCompanyPlanAction` so active invoices keep the plan in sync. Past-due handling applies grace windows based on `services.stripe.past_due_grace_days`, and all mutations emit audit logs via `AuditLogger`.

- [x] Confirm that limits and entitlements are enforced in code and reflected in UI:
	- Backend enforcement is in place: `EnsureSubscribed` blocks RFQ/invoice/user/storage quota overruns, while feature middlewares such as `EnsureAnalyticsAccess`, `EnsureRiskAccess`, `EnsureDigitalTwinAccess`, `EnsureExportAccess`, etc., check the relevant plan booleans before letting routes execute. Feature tests (`tests/Feature/Billing/SubscriptionGatingTest.php`, `tests/Unit/EnsureAnalyticsAccessTest.php`, etc.) verify these guardrails.
	- Billing UX now exists at `/app/settings/billing`, and legacy links are redirected automatically. The page fetches the plan catalog, surfaces allowances, and provides actions for plan changes and billing contacts so upgrade CTAs land in a real destination.
	- `App\Services\Auth\AuthResponseFactory` now merges plan booleans into the SPA feature-flag payload, so `useAuth().hasFeature()` immediately unlocks plan-specific UI for risk, analytics, inventory, digital twins, exports, etc.

- [x] Report any plan features from the requirements that are not gated in code or are not exposed in the UI:
	- All documented plan features are now enforced server-side and surfaced to the client through the merged feature-flag payload and Billing UI. Paid upgrades flow through Stripe Checkout, so no outstanding gating gaps remain.