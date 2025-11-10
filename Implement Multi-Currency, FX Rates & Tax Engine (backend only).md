Implement Multi-Currency, FX Rates & Tax Engine (backend only)

Goal

Add robust money handling with currency codes, FX conversions (daily rates with caching), tax codes (VAT/GST/Sales-Tax), line-level taxes, and correct subtotal/total calculations across RFQs, Quotes, POs, Invoices, and Credit Notes. Include rounding rules, plan gating, policies, requests/resources, routes, and feature tests.

Migrations

currencies: id, code char(3) unique, name string, minor_unit tinyint (e.g., 2), symbol string nullable, timestamps. Seed ISO 4217 basics (USD, EUR, GBP, LKR, INR, etc.).

fx_rates: id, base_code char(3), quote_code char(3), rate decimal(18,8), as_of date, unique (base_code, quote_code, as_of), index as_of.

tax_codes: id, company_id FK, code string unique per company, name string, type enum(vat,gst,sales,withholding,custom), rate_percent decimal(6,3) nullable, is_compound bool default false, active bool default true, meta JSON, timestamps, soft deletes.

company_money_settings: id, company_id FK unique, base_currency char(3), pricing_currency char(3) nullable, fx_source enum(manual,provider) default manual, price_round_rule enum(bankers,half_up) default half_up, tax_regime enum(exclusive,inclusive) default exclusive, defaults_meta JSON (e.g., default tax code), timestamps.

line_taxes (polymorphic): id, company_id FK, tax_code_id FK, taxable_type string, taxable_id bigint, rate_percent decimal(6,3), amount_minor bigint (store in minor units), sequence tinyint default 1 (for compound taxes), timestamps.

Add money/tax columns where missing:

rfq_items: currency char(3) nullable, target_price_minor bigint nullable.
quote_items: currency char(3), unit_price_minor bigint.
purchase_order_lines: currency char(3), unit_price_minor bigint.
invoice_lines: currency char(3), unit_price_minor bigint.
credit_notes: ensure currency char(3), add amount_minor bigint.
plans: add boolean multi_currency_enabled (default false) and tax_engine_enabled (default false).

Notes: Store money as minor units (integers). Keep legacy decimal columns for backward compatibility only if needed; otherwise migrate and adapt resources.

Enums & Value Objects

MoneyRoundRule (bankers,half_up)

TaxRegime (exclusive,inclusive)

Create a lightweight Money value object (VO) with: amountMinor, currency, toDecimal(minor_unit), add/sub/mul/div, round(rule, minor_unit), format() (server-side only).

Services

FxService
a. getRate(base:string, quote:string, date?:Carbon) → decimal(18,8). If base==quote, return 1.0. Prefer most recent as_of <= date fallback to latest.
b. convert(Money $money, string $toCurrency, Carbon $asOf) → Money using stored rate.
c. upsertDailyRates(array $rows) to load admin/manual rates (provider integration later).

TaxService
a. Resolve applicable TaxCodes for a given company and line (use explicit selection from payload for now).
b. Compute line taxes: given Money $unit, qty, regime and one or more tax codes (respect sequence for compound). Return: taxes[], lineSubtotal, lineTaxTotal, lineGrand.

TotalsCalculator (shared)
a. For a Quote, PO, Invoice, CreditNote: iterate lines (ensure same currency per document), sum subtotals/taxes, return document totals in minor units. Enforce rounding/precision from settings.

Policies & Middleware

EnsureMoneyAccess middleware: block endpoints if multi_currency_enabled/tax_engine_enabled are false (per plan) → return 402 with {status:'error', message:'Upgrade required'}.

Update relevant policies to ensure company scoping when changing money/tax settings or rates (admin/buyer_admin only).

Controllers & Endpoints

MoneySettingsController
a. show() – returns company money & tax settings.
b. update(UpdateMoneySettingsRequest) – set base/pricing currency, rounding rule, tax regime, defaults.

FxRateController
a. index() – list rates filtered by base/quote/date.
b. upsert(UpsertFxRatesRequest) – bulk upsert daily rates [ {base, quote, rate, as_of} ].

TaxCodeController CRUD (company-scoped).

Adjust existing create/update endpoints for QuoteItem/PO line/Invoice line to accept:
a. currency (defaults to document/company pricing currency),
b. unit_price_minor (or accept decimal and convert to minor server-side),
c. tax_code_ids (array) for line_taxes.

Add recalculate endpoints for quotes/{id}/recalculate, purchase-orders/{id}/recalculate, invoices/{id}/recalculate, credit-notes/{id}/recalculate → call TotalsCalculator and persist totals.

Routes (sketch)

Route::middleware(['ensure.company.onboarded','ensure.money.access'])->group(function () {
Route::get('money/settings', [MoneySettingsController::class,'show']);
Route::put('money/settings', [MoneySettingsController::class,'update']);
Route::get('money/fx', [FxRateController::class,'index']);
Route::post('money/fx', [FxRateController::class,'upsert']);
Route::apiResource('money/tax-codes', TaxCodeController::class)->only(['index','store','show','update','destroy']);
Route::post('quotes/{quote}/recalculate', [QuoteTotalsController::class,'recalculate']);
Route::post('purchase-orders/{po}/recalculate', [PoTotalsController::class,'recalculate']);
Route::post('invoices/{invoice}/recalculate', [InvoiceTotalsController::class,'recalculate']);
Route::post('credit-notes/{credit}/recalculate', [CreditTotalsController::class,'recalculate']);
});

Form Requests

MoneySettingsResource, FxRateResource, TaxCodeResource.

Update existing line/document resources to expose money as:
a. { amount_minor, currency, amount } where amount = amount_minor / 10^minor_unit for convenience.
b. Include taxes[] on lines and tax_total on documents.

Integration Points to Update

Quotes/POs/Invoices/Credit Notes:
a. Persist line taxes in line_taxes.
b. Ensure totals are recomputed on create/update/delete of lines or tax changes.
c. Prevent mixed currencies within a single document; 422 if attempted.

Three-way match: comparisons should use consistent currency; if GRN/PO in base and invoice in foreign, convert invoice lines to PO currency using FxService at PO date (pass a date param).

Tests (Feature/Pest)

Settings & FX: update money settings; upsert daily FX; convert() returns expected amounts; rounding follows rule.

Line taxes: create a PO with two tax codes (e.g., VAT 8% + Withholding 1%) and verify line and document totals for exclusive vs inclusive regimes.

Recalculate endpoints: modifying a line price or tax triggers totals to change correctly; documents reject mixed currencies.

Invoices & credit notes: issuing a credit note reduces invoice tax and grand total appropriately.

Three-way match w/ FX: invoice in EUR vs PO in USD converts using as-of rate and flags mismatches properly.

Plan gating & auth: disabling features yields 402; non-admins cannot alter money settings or FX rates.

Notes

Keep all logic company-scoped and auditable (log settings changes, FX upserts, tax code edits).

Use queues for any heavy recompute if needed (but keep endpoints synchronous for now).

No UI—only API and tests.

In resources, never expose floating-point math results without rounding using the company’s rules.