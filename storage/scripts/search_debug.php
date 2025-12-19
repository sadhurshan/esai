<?php

use App\Models\Company;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Services\GlobalSearchService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$plan = Plan::factory()->create([
    'global_search_enabled' => true,
]);

$company = Company::factory()->create([
    'plan_id' => $plan->id,
    'plan_code' => $plan->code,
    'status' => 'active',
]);

$supplier = Supplier::factory()->create([
    'company_id' => $company->id,
    'name' => 'Omega Gear Works',
    'status' => 'approved',
]);

$rfq = RFQ::factory()->create([
    'company_id' => $company->id,
    'title' => 'Omega Gear Housing',
    'number' => 'RFQ-OMEGA-1',
    'status' => 'open',
]);

$part = Part::factory()->create([
    'company_id' => $company->id,
    'part_number' => 'PN-OMEGA-42',
    'name' => 'Omega Gear Shaft',
    'spec' => 'Omega hardened 4140 steel',
]);

$po = PurchaseOrder::factory()->create([
    'company_id' => $company->id,
    'po_number' => 'PO-OMEGA-42',
    'status' => 'sent',
]);

$invoice = Invoice::factory()->create([
    'company_id' => $company->id,
    'purchase_order_id' => $po->id,
    'supplier_id' => $supplier->id,
    'invoice_number' => 'INV-OMEGA-42',
    'status' => 'submitted',
]);

Document::create([
    'company_id' => $company->id,
    'documentable_type' => PurchaseOrder::class,
    'documentable_id' => $po->id,
    'kind' => 'po',
    'category' => 'technical',
    'visibility' => 'company',
    'version_number' => 1,
    'expires_at' => null,
    'path' => 'docs/omega.pdf',
    'filename' => 'Omega Gear Specs.pdf',
    'mime' => 'application/pdf',
    'size_bytes' => 1024,
    'hash' => null,
    'watermark' => [],
    'meta' => ['description' => 'Omega specification pack'],
]);

$rows = DB::select(
    'SELECT id, MATCH(name, capabilities_search) AGAINST (? IN BOOLEAN MODE) as score FROM suppliers WHERE company_id = ? AND MATCH(name, capabilities_search) AGAINST (? IN BOOLEAN MODE)',
    ['+omega*', $company->id, '+omega*']
);

echo "Supplier MATCH rows:\n";
var_dump($rows);

$service = app(GlobalSearchService::class);

$result = $service->search([], 'Omega', [], $company);

echo "\nService result:\n";
var_dump($result);
