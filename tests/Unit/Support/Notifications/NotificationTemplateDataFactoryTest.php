<?php

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\FormattingService;
use App\Support\Money\Money;
use App\Support\Notifications\NotificationTemplateDataFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ensureUsdCurrency(): void
{
    Currency::query()->updateOrCreate(
        ['code' => 'USD'],
        [
            'name' => 'US Dollar',
            'minor_unit' => 2,
            'symbol' => '$',
        ]
    );
}

it('formats invoice notifications with localized totals and dates', function (): void {
    ensureUsdCurrency();
    Config::set('app.url', 'https://elements.test');

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $purchaseOrder = PurchaseOrder::factory()
        ->for($company)
        ->create([
            'currency' => 'USD',
            'po_number' => 'PO-1001',
        ]);

    $invoice = Invoice::factory()
        ->for($company)
        ->for($purchaseOrder, 'purchaseOrder')
        ->create([
            'invoice_number' => 'INV-9000',
            'currency' => 'USD',
            'total' => 1250.5,
        ]);

    $dueDate = Carbon::parse('2025-11-30T00:00:00Z');

    $notification = Notification::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'event_type' => 'invoice_created',
        'title' => 'Invoice posted',
        'body' => 'Review invoice',
        'entity_type' => Invoice::class,
        'entity_id' => $invoice->id,
        'channel' => 'email',
        'meta' => [
            'due_at' => $dueDate->toIso8601String(),
            'cta_url' => '/app/invoices/'.$invoice->id,
        ],
    ]);

    $factory = app(NotificationTemplateDataFactory::class);
    $formatting = app(FormattingService::class);

    $payload = $factory->build($notification);

    expect($payload['view'])->toBe('emails.notifications.invoice-posted');
    expect($payload['data']['ctaUrl'])->toBe('https://elements.test/app/invoices/'.$invoice->id);

    $expectedMoney = $formatting->formatMoney(
        Money::fromDecimal((float) $invoice->total, $invoice->currency, 2),
        $company
    );

    expect($payload['data']['invoiceTotal'])->toBe($expectedMoney);
    expect($payload['data']['dueDate'])->toBe($formatting->formatDate($dueDate, $company));
});

it('maps purchase order notifications to the PO template with localized totals', function (): void {
    ensureUsdCurrency();

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $supplier = Supplier::factory()->for($company)->create(['name' => 'Acme Supplier']);

    $po = PurchaseOrder::factory()
        ->for($company)
        ->for($supplier)
        ->create([
            'po_number' => 'PO-5555',
            'currency' => 'USD',
            'sent_at' => Carbon::parse('2025-10-10T12:00:00Z'),
            'total_minor' => 502_500,
        ]);

    $notification = Notification::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'event_type' => 'po_issued',
        'title' => 'PO sent',
        'body' => 'The PO was issued.',
        'entity_type' => PurchaseOrder::class,
        'entity_id' => $po->id,
        'channel' => 'email',
        'meta' => [
            'cta_label' => 'View PO',
        ],
    ]);

    $factory = app(NotificationTemplateDataFactory::class);
    $formatting = app(FormattingService::class);

    $payload = $factory->build($notification);

    expect($payload['view'])->toBe('emails.notifications.po-sent');
    expect($payload['data']['supplierName'])->toBe('Acme Supplier');

    $expected = $formatting->formatMoney(Money::fromMinor(502_500, 'USD'), $company);

    expect($payload['data']['poTotal'])->toBe($expected);
    expect($payload['data']['sentAt'])->toBe(
        $formatting->formatDate(Carbon::parse('2025-10-10T12:00:00Z'), $company)
    );
});

it('detects credit note status changes and uses the credit note template', function (): void {
    ensureUsdCurrency();

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $purchaseOrder = PurchaseOrder::factory()->for($company)->create(['currency' => 'USD']);
    $invoice = Invoice::factory()
        ->for($company)
        ->for($purchaseOrder, 'purchaseOrder')
        ->create([
            'invoice_number' => 'INV-2002',
            'currency' => 'USD',
            'total' => 800,
        ]);

    $creditNote = CreditNote::factory()
        ->for($company)
        ->for($invoice)
        ->create([
            'credit_number' => 'CN-1001',
            'currency' => 'USD',
            'amount_minor' => 45_000,
            'amount' => 450,
        ]);

    $notification = Notification::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'event_type' => 'invoice_status_changed',
        'title' => 'Credit note posted',
        'body' => 'Credit note approved.',
        'entity_type' => CreditNote::class,
        'entity_id' => $creditNote->id,
        'channel' => 'email',
        'meta' => [
            'credit_event' => 'credit_note.approved',
        ],
    ]);

    $factory = app(NotificationTemplateDataFactory::class);
    $formatting = app(FormattingService::class);

    $payload = $factory->build($notification);

    expect($payload['view'])->toBe('emails.notifications.credit-note-posted');
    expect($payload['data']['invoiceNumber'])->toBe('INV-2002');

    $expectedAmount = $formatting->formatMoney(Money::fromMinor(45_000, 'USD'), $company);
    expect($payload['data']['creditAmount'])->toBe($expectedAmount);
});
