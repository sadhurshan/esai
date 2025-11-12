<?php

use App\Enums\ExportRequestStatus;
use App\Enums\ExportRequestType;
use App\Enums\MoneyRoundRule;
use App\Enums\TaxRegime;
use App\Models\Company;
use App\Models\CompanyLocaleSetting;
use App\Models\CompanyMoneySetting;
use App\Models\Currency;
use App\Models\ExportRequest;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Models\Subscription;
use App\Models\User;
use App\Support\OpenApi\SpecBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\AssertionFailedError;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Resolvers\SchemaResolver;
use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Uri;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Currency::query()->updateOrCreate([
        'code' => 'USD',
    ], [
        'name' => 'US Dollar',
        'minor_unit' => 2,
        'symbol' => '$',
    ]);

    $plan = Plan::factory()->create([
        'rfqs_per_month' => 100,
        'invoices_per_month' => 100,
        'users_max' => 50,
        'storage_gb' => 50,
        'inventory_enabled' => true,
        'exports_enabled' => true,
        'data_export_enabled' => true,
        'localization_enabled' => true,
        'multi_currency_enabled' => true,
        'tax_engine_enabled' => true,
    ]);

    $company = Company::factory()->for($plan)->create([
        'plan_code' => $plan->code,
        'trial_ends_at' => now()->addDays(14),
    ]);

    Subscription::factory()->for($company)->create([
        'stripe_status' => 'active',
        'trial_ends_at' => now()->addDays(14),
        'ends_at' => null,
    ]);

    $user = User::factory()->for($company)->create([
        'role' => 'buyer_admin',
    ]);

    test()->plan = $plan;
    test()->company = $company;
    test()->user = $user;
});

it('validates rfq collection responses against the OpenAPI contract', function (): void {
    [$rfq] = createRfqWithQuote();

    actingAs(test()->user);

    $response = getJson('/api/rfqs');

    $response->assertOk();

    assertResponseMatchesSchema($response, [
        'paths',
        '/api/rfqs',
        'get',
        'responses',
        '200',
        'content',
        'application/json',
        'schema',
    ]);
})->group('contract');

it('validates quote listings against the OpenAPI contract', function (): void {
    [$rfq] = createRfqWithQuote();

    actingAs(test()->user);

    $response = getJson("/api/rfqs/{$rfq->id}/quotes");

    $response->assertOk();

    assertResponseMatchesSchema($response, [
        'paths',
        '/api/rfqs/{rfqId}/quotes',
        'get',
        'responses',
        '200',
        'content',
        'application/json',
        'schema',
    ]);
})->group('contract');

it('validates purchase order detail payloads against the OpenAPI contract', function (): void {
    [$rfq, $supplier, $quote] = createRfqWithQuote();
    [$purchaseOrder] = createPurchaseOrderWithLine($rfq, $supplier, $quote);

    actingAs(test()->user);

    $response = getJson("/api/purchase-orders/{$purchaseOrder->id}");

    $response->assertOk();

    assertResponseMatchesSchema($response, [
        'paths',
        '/api/purchase-orders/{purchaseOrderId}',
        'get',
        'responses',
        '200',
        'content',
        'application/json',
        'schema',
    ]);
})->group('contract');

it('validates invoice detail payloads against the OpenAPI contract', function (): void {
    [$rfq, $supplier, $quote] = createRfqWithQuote();
    [$purchaseOrder, $poLine] = createPurchaseOrderWithLine($rfq, $supplier, $quote);
    [$invoice] = createInvoiceWithLine($purchaseOrder, $supplier, $poLine);

    actingAs(test()->user);

    $response = getJson("/api/invoices/{$invoice->id}");

    $response->assertOk();

    assertResponseMatchesSchema($response, [
        'paths',
        '/api/invoices/{invoiceId}',
        'get',
        'responses',
        '200',
        'content',
        'application/json',
        'schema',
    ]);
})->group('contract');

it('validates inventory goods receipt listings against the OpenAPI contract', function (): void {
    [$rfq, $supplier, $quote] = createRfqWithQuote();
    [$purchaseOrder] = createPurchaseOrderWithLine($rfq, $supplier, $quote);

    GoodsReceiptNote::query()->create([
        'company_id' => test()->company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'number' => 'GRN-'.Str::upper(Str::random(6)),
        'inspected_by_id' => test()->user->id,
    'inspected_at' => now(),
    'status' => 'pending',
    ]);

    actingAs(test()->user);

    $response = getJson("/api/purchase-orders/{$purchaseOrder->id}/grns");

    $response->assertOk();

    assertResponseMatchesSchema($response, [
        'paths',
        '/api/purchase-orders/{purchaseOrderId}/grns',
        'get',
        'responses',
        '200',
        'content',
        'application/json',
        'schema',
    ]);
})->group('contract');

it('validates export listings against the OpenAPI contract', function (): void {
    ExportRequest::factory()
        ->for(test()->company, 'company')
        ->for(test()->user, 'requester')
        ->create([
            'type' => ExportRequestType::FullData,
            'status' => ExportRequestStatus::Completed,
            'filters' => [
                'from' => now()->subDays(2)->toIso8601String(),
                'to' => now()->toIso8601String(),
            ],
            'file_path' => sprintf('%d/exports-demo.zip', test()->company->id),
            'completed_at' => now()->subHour(),
            'expires_at' => now()->addDays(5),
        ]);

    actingAs(test()->user);

    $response = getJson('/api/exports');

    $response->assertOk();

    assertResponseMatchesSchema($response, [
        'paths',
        '/api/exports',
        'get',
        'responses',
        '200',
        'content',
        'application/json',
        'schema',
    ]);
})->group('contract');

it('validates localization settings against the OpenAPI contract', function (): void {
    CompanyLocaleSetting::query()->create([
        'company_id' => test()->company->id,
        'locale' => 'en',
        'timezone' => 'America/New_York',
        'number_format' => 'system',
        'date_format' => 'YMD',
        'first_day_of_week' => 1,
        'weekend_days' => [6, 0],
    ]);

    actingAs(test()->user);

    $response = getJson('/api/localization/settings');

    $response->assertOk();

    assertResponseMatchesSchema($response, [
        'paths',
        '/api/localization/settings',
        'get',
        'responses',
        '200',
        'content',
        'application/json',
        'schema',
    ]);
})->group('contract');

it('validates money settings against the OpenAPI contract', function (): void {
    CompanyMoneySetting::query()->create([
        'company_id' => test()->company->id,
        'base_currency' => 'USD',
        'pricing_currency' => 'USD',
        'fx_source' => 'manual',
        'price_round_rule' => MoneyRoundRule::HalfUp->value,
        'tax_regime' => TaxRegime::Exclusive->value,
        'defaults_meta' => ['payment_terms' => 'Net 30'],
    ]);

    actingAs(test()->user);

    $response = getJson('/api/money/settings');

    $response->assertOk();

    assertResponseMatchesSchema($response, [
        'paths',
        '/api/money/settings',
        'get',
        'responses',
        '200',
        'content',
        'application/json',
        'schema',
    ]);
})->group('contract');

/**
 * @return array{0: RFQ, 1: Supplier, 2: Quote}
 */
function createRfqWithQuote(): array
{
    $company = test()->company;
    $user = test()->user;

    $rfq = RFQ::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'status' => 'open',
        'is_open_bidding' => true,
        'sent_at' => now()->subDay(),
        'deadline_at' => now()->addDays(7),
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'status' => 'approved',
        'verified_at' => now(),
    ]);

    $quote = Quote::query()->create([
        'company_id' => $company->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'submitted_by' => $user->id,
        'currency' => 'USD',
        'unit_price' => 480.50,
        'subtotal' => 480.50,
        'tax_amount' => 48.05,
        'total' => 528.55,
        'subtotal_minor' => 48050,
        'tax_amount_minor' => 4805,
        'total_minor' => 52855,
        'min_order_qty' => 10,
        'lead_time_days' => 14,
        'status' => 'submitted',
        'revision_no' => 1,
        'note' => 'Quote seeded for schema validation.',
    ]);

    return [$rfq->refresh(), $supplier->refresh(), $quote->refresh()];
}

/**
 * @return array{0: PurchaseOrder, 1: PurchaseOrderLine}
 */
function createPurchaseOrderWithLine(RFQ $rfq, Supplier $supplier, Quote $quote): array
{
    $company = test()->company;

    $purchaseOrder = PurchaseOrder::factory()->for($company)->create([
        'rfq_id' => $rfq->id,
        'quote_id' => $quote->id,
        'supplier_id' => $supplier->id,
        'status' => 'sent',
        'currency' => 'USD',
        'subtotal' => 200.00,
        'tax_amount' => 20.00,
        'total' => 220.00,
        'subtotal_minor' => 20000,
        'tax_amount_minor' => 2000,
        'total_minor' => 22000,
        'incoterm' => 'FOB',
        'revision_no' => 1,
    ]);

    $line = PurchaseOrderLine::factory()->for($purchaseOrder)->create([
        'line_no' => 1,
        'description' => 'Machined bracket',
        'quantity' => 4,
        'currency' => 'USD',
        'unit_price' => 50.00,
        'unit_price_minor' => 5000,
        'delivery_date' => now()->addDays(14),
    ]);

    return [$purchaseOrder->refresh(), $line->refresh()];
}

/**
 * @return array{0: Invoice, 1: InvoiceLine}
 */
function createInvoiceWithLine(PurchaseOrder $purchaseOrder, Supplier $supplier, PurchaseOrderLine $poLine): array
{
    $company = test()->company;

    $invoice = Invoice::factory()
        ->for($company)
        ->for($purchaseOrder)
        ->for($supplier)
        ->create([
            'currency' => 'USD',
            'subtotal' => 200.00,
            'tax_amount' => 20.00,
            'total' => 220.00,
            'status' => 'pending',
        ]);

    $line = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'po_line_id' => $poLine->id,
        'description' => 'Machined bracket invoiced',
        'quantity' => 4,
        'uom' => 'EA',
        'currency' => 'USD',
        'unit_price' => 50.00,
        'unit_price_minor' => 5000,
    ]);

    return [$invoice->refresh(), $line->refresh()];
}

function assertResponseMatchesSchema(TestResponse $response, array $pointerSegments): void
{
    ['validator' => $validator, 'base' => $baseId] = contractSchemaState();

    $payload = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);

    expect($payload)->not()->toBeNull();

    $uri = $baseId.schemaPointer($pointerSegments);
    $result = $validator->validate($payload, $uri);

    if ($result->isValid()) {
        expect($result->isValid())->toBeTrue();

        return;
    }

    $messages = implode(PHP_EOL, flattenValidationErrors($result->error())) ?: 'Schema validation failed.';

    throw new PHPUnit\Framework\AssertionFailedError($messages);
}

function schemaPointer(array $segments): string
{
    $encoded = array_map(static function (string $segment): string {
        $escaped = str_replace(['~', '/'], ['~0', '~1'], $segment);

        return strtr($escaped, [
            '{' => '%7B',
            '}' => '%7D',
        ]);
    }, $segments);

    return '#/'.implode('/', $encoded);
}

/**
 * @return list<string>
 */
function flattenValidationErrors(?ValidationError $error): array
{
    if ($error === null) {
        return [];
    }

    $pathSegments = array_map(static fn ($segment) => (string) $segment, $error->data()->fullPath());
    $pointer = $pathSegments === [] ? '/' : '/'.implode('/', $pathSegments);
    $args = $error->args();
    $details = $args !== [] ? json_encode($args, JSON_UNESCAPED_SLASHES) : '';
    $keyword = $error->keyword();
    $messages = [trim(sprintf('%s: %s [keyword: %s%s%s]', $pointer, $error->message(), $keyword, $details !== '' ? ', args: ' : '', $details))];

    foreach ($error->subErrors() as $subError) {
        $messages = array_merge($messages, flattenValidationErrors($subError));
    }

    return $messages;
}

function normalizeOpenApiForJsonSchema(mixed &$value): void
{
    if (! is_array($value)) {
        return;
    }

    if (($value['nullable'] ?? false) === true) {
        $type = $value['type'] ?? null;

        if (is_string($type) && $type !== '') {
            $types = [$type];
        } elseif (is_array($type)) {
            $types = array_values(array_filter(array_map(static function ($entry) {
                return is_string($entry) && $entry !== '' ? $entry : null;
            }, $type)));
        } else {
            $types = [];
        }

        if (! in_array('null', $types, true)) {
            $types[] = 'null';
        }

        $types = array_values(array_unique($types));

        if (count($types) === 1) {
            $value['type'] = $types[0];
        } elseif ($types !== []) {
            $value['type'] = $types;
        } else {
            $value['type'] = ['null'];
        }

        if (isset($value['enum']) && is_array($value['enum']) && ! in_array(null, $value['enum'], true)) {
            $value['enum'][] = null;
        }

        unset($value['nullable']);
    }

    if (($value['format'] ?? null) === 'uuid') {
        unset($value['format']);
    }

    foreach ($value as &$child) {
        normalizeOpenApiForJsonSchema($child);
    }
}

/**
 * @return array{validator: Validator, base: string}
 */
function contractSchemaState(): array
{
    static $state = null;

    if ($state !== null) {
        return $state;
    }

    $specBuilder = app(SpecBuilder::class);
    $compiled = $specBuilder->compile();
    $baseId = 'https://elements-supply.ai/openapi.json';
    $compiled['$id'] = $baseId;

    normalizeOpenApiForJsonSchema($compiled);

    $encoded = json_encode($compiled, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $document = json_decode($encoded, false, 512, JSON_THROW_ON_ERROR);

    $resolver = new SchemaResolver();
    $resolver->registerRaw($document, $baseId);

    $loader = new SchemaLoader(null, $resolver, true);
    $loader->setBaseUri(Uri::parse($baseId, true));

    $validator = new Validator($loader);
    $validator->setMaxErrors(10);
    $validator->setStopAtFirstError(false);

    $state = [
        'validator' => $validator,
        'base' => $baseId,
    ];

    return $state;
}
