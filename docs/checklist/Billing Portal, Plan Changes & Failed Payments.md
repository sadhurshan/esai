Billing Portal, Plan Changes & Failed Payments

- [x] Stripe Billing Portal entry point
	- `POST /api/billing/portal` now lives in `routes/api.php` and is handled by `BillingPortalController`, which relies on `StripeBillingPortalService` to create Stripe customer-portal sessions (with fallbacks/audit-safe envelopes) and enforces owner/buyer-admin access through `BillingPortalSessionRequest`.
	- `resources/js/pages/settings/billing-settings-page.tsx` replaces the placeholder card with live billing-state copy and an "Open billing portal" CTA that calls the new endpoint, surfaces errors, and links to the configured fallback when Stripe is unreachable.

- [x] Self-serve plan changes for existing tenants
	- `BillingSettingsPage` now routes the "Change plan" CTA to `/app/setup/plan?mode=change`, and `PlanSelectionPage` inspects the `mode=change` query parameter to bypass the earlier redirect so existing tenants can re-open the wizard even when `requiresPlanSelection` is false.
	- When invoked in change mode, successful plan selections or checkouts return users to `Settings → Billing` while still reusing the existing `POST /api/company/plan-selection` + `POST /api/billing/checkout` flows, so paid tenants can re-run Stripe Checkout whenever they need to upgrade/downgrade.

- [x] Plan change + entitlement sync (routes + UI components)
	- Catalog + selection routes live in `routes/api.php`: `GET /api/plans` → `PlanCatalogController@index`, `POST /api/company/plan-selection` → `CompanyPlanController@store`, and `POST /api/billing/checkout` → `Billing\PlanCheckoutController@store`. Owners/buyer admins (per `PlanSelectionRequest`) can call these via the SPA.
	- `CompanyPlanController` coordinates `AssignCompanyPlanAction`, `EnsureComplimentarySubscriptionAction`, and `EnsureStubSubscriptionAction`, so `companies.plan_id`, complimentary/stub `subscriptions`, optional stub trials, and audit logs stay aligned.
	- Entitlements propagate through `App/Services/Auth/AuthResponseFactory`, which merges `plan_code` + plan feature columns into the SPA payload so `useAuth().featureFlags` reflects the new plan immediately.
	- UI components: `resources/js/pages/settings/billing-settings-page.tsx` shows allowances + billing status and links to the plan wizard, while `resources/js/pages/onboarding/plan-selection-page.tsx` renders the cards and, for paid tiers, calls `POST /api/billing/checkout` to spin up Stripe Checkout sessions via `StartPlanCheckoutAction`/`StripeCheckoutService`.

- [x] Payment failures + re-activation
	- Stripe webhooks are exposed at `POST /webhooks/stripe/invoice/payment-succeeded`, `.../invoice/payment-failed`, and `.../customer/subscription-updated` (see the bottom of `routes/api.php`).
	- `StripeWebhookController` verifies signatures and forwards events to `App/Services/Billing/StripeWebhookService`. Failed invoices set `subscriptions.stripe_status = 'past_due'` and `ends_at = now + config('services.stripe.past_due_grace_days')`; successful payments reactivate the record and clear `ends_at`, while `customer.subscription.updated` re-runs `AssignCompanyPlanAction` so Stripe-driven plan swaps keep local entitlements synced.
	- `tests/Unit/StripeWebhookServiceTest.php` covers the failed → paid flow, asserting that past-due invoices push `ends_at` five days out and that payment success restores `stripe_status = 'active'` and clears the grace window.

- [x] Past-due grace, read-only mode, and messaging
	- `App/Http/Middleware/EnsureSubscribed` now honors the configured grace window: GET/HEAD/OPTIONS continue to work while `subscriptions.ends_at` is in the future, but mutating verbs return a structured 402 with `billing_read_only`, `billing_grace_ends_at`, and `billing_lock_at` metadata. `tests/Unit/EnsureSubscribedMiddlewareTest.php` covers both read-only and locked flows so we don’t regress.
	- `App/Services/Auth/AuthResponseFactory` passes the new billing fields to the SPA, and `resources/js/components/billing-status-banner.tsx` (rendered inside `resources/js/layouts/app-layout.tsx`) surfaces a global “read-only” or “locked” banner with deadlines pulled from the server payload.
	- `resources/js/pages/settings/billing-settings-page.tsx` was rebuilt to derive tone-aware messaging, show targeted alerts for read-only vs locked states, and keep the Stripe portal CTA + fallback link inline so admins know exactly when write access is suspended and how to resolve billing issues.

- [x] Missing states, messaging, and edge cases
	- The remaining RFQ mutations (`PUT/DELETE /api/rfqs/{rfq}`, `POST /api/rfqs/{rfq}/invitations`, supplier quote submit/withdraw) and their supplier endpoints in `routes/api.php` now include `ensure.subscribed`, so past-due tenants hit the same read-only/lock guardrail enforced elsewhere.
	- `GET /api/billing/invoices` (guarded by onboarding + billing access middleware) delegates to `BillingInvoiceController`/`StripeInvoiceService`, which hydrates recent Stripe invoices using the configurable `services.stripe.invoice_history_limit` to cap provider calls.
	- `resources/js/pages/settings/billing-settings-page.tsx` now fetches that endpoint, renders loading/error/empty states, and displays hosted invoice/PDF links alongside the existing portal CTA to satisfy FR-15’s “Subscriptions tab → Stripe invoice list + ‘Open Billing Portal’.”
	- `Company` billing flags (`billing_read_only`, `billing_grace_ends_at`, `billing_lock_at`) continue to flow through `EnsureSubscribed` + `AuthResponseFactory`, so the SPA flips between read-only vs locked messaging automatically rather than relying on manual route toggles.