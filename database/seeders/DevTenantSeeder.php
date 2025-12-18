<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DevTenantSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $buyerEmail = 'buyer.admin@example.com';
        $supplierEmail = 'supplier.estimator@example.com';
        $planIds = DB::table('plans')
            ->whereIn('code', ['growth', 'starter'])
            ->pluck('id', 'code');

        if (! isset($planIds['growth'], $planIds['starter'])) {
            throw new \RuntimeException('Growth and Starter plans must exist before running DevTenantSeeder.');
        }

        $buyerCompanyId = $this->seedBuyerCompany($now, (int) $planIds['growth'], $buyerEmail);
        $buyerId = $this->seedBuyerUser($buyerCompanyId, $buyerEmail, $now);
        $this->ensureBuyerSubscription($buyerCompanyId, $buyerId, $buyerEmail, $now);

        [$supplierCompanyId, $supplierUserId] = $this->seedSupplierTenant(
            $now,
            (int) $planIds['starter'],
            $supplierEmail
        );

        $this->seedSupplierProfile($supplierCompanyId, $supplierEmail, $now);

        $this->seedSupplierInvoiceFixtures($buyerCompanyId, $buyerId, $supplierCompanyId, $supplierUserId);

        $this->syncCompanyUser($buyerCompanyId, $buyerId, 'buyer_admin');
        $this->syncCompanyUser($supplierCompanyId, $supplierUserId, 'supplier_estimator');
    }

    private function syncCompanyUser(int $companyId, int $userId, string $role): void
    {
        $existing = DB::table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            DB::table('company_user')
                ->where('id', $existing->id)
                ->update([
                    'role' => $role,
                    'updated_at' => Carbon::now(),
                ]);

            return;
        }

        DB::table('company_user')->insert([
            'company_id' => $companyId,
            'user_id' => $userId,
            'role' => $role,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function seedBuyerCompany(Carbon $now, int $planId, string $buyerEmail): int
    {
        $slug = 'elements-supply-dev';
        $company = DB::table('companies')->where('slug', $slug)->first();

        $data = [
            'name' => 'Elements Supply Demo Co',
            'status' => 'active',
            'region' => 'NA',
            'rfqs_monthly_used' => optional($company)->rfqs_monthly_used ?? 0,
            'storage_used_mb' => optional($company)->storage_used_mb ?? 0,
            'plan_code' => 'growth',
            'plan_id' => $planId,
            'registration_no' => optional($company)->registration_no ?: 'ES-DEV-001',
            'tax_id' => optional($company)->tax_id ?: 'TAX-123456',
            'country' => optional($company)->country ?: 'US',
            'email_domain' => optional($company)->email_domain ?: 'example.com',
            'primary_contact_name' => optional($company)->primary_contact_name ?: 'Buyer Admin',
            'primary_contact_email' => optional($company)->primary_contact_email ?: $buyerEmail,
            'primary_contact_phone' => optional($company)->primary_contact_phone ?: '+1-555-123-4567',
            'updated_at' => $now,
        ];

        if (! $company) {
            return (int) DB::table('companies')->insertGetId(array_merge($data, [
                'slug' => $slug,
                'created_at' => $now,
            ]));
        }

        DB::table('companies')->where('id', $company->id)->update($data);

        return (int) $company->id;
    }

    private function seedBuyerUser(int $companyId, string $buyerEmail, Carbon $now): int
    {
        $buyer = DB::table('users')->where('email', $buyerEmail)->first();

        if (! $buyer) {
            $buyerId = (int) DB::table('users')->insertGetId([
                'name' => 'Buyer Admin',
                'email' => $buyerEmail,
                'password' => Hash::make('password'),
                'role' => 'buyer_admin',
                'company_id' => $companyId,
                'remember_token' => Str::random(10),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $buyerId = (int) $buyer->id;

            DB::table('users')->where('id', $buyerId)->update([
                'role' => 'buyer_admin',
                'company_id' => $companyId,
                'updated_at' => $now,
            ]);
        }

        DB::table('companies')->where('id', $companyId)->update([
            'owner_user_id' => $buyerId,
            'updated_at' => $now,
        ]);

        return $buyerId;
    }

    private function ensureBuyerSubscription(int $companyId, int $buyerId, string $buyerEmail, Carbon $now): void
    {
        $customer = DB::table('customers')->where('company_id', $companyId)->first();
        $customerStripeId = 'cus_dev_'.$companyId;

        if (! $customer) {
            $customerId = (int) DB::table('customers')->insertGetId([
                'company_id' => $companyId,
                'name' => 'Elements Supply Demo Co',
                'email' => $buyerEmail,
                'stripe_id' => $customerStripeId,
                'pm_type' => null,
                'pm_last_four' => null,
                'default_payment_method' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $customerId = (int) $customer->id;

            DB::table('customers')->where('id', $customerId)->update([
                'name' => $customer->name ?: 'Elements Supply Demo Co',
                'email' => $customer->email ?: $buyerEmail,
                'stripe_id' => $customerStripeId,
                'updated_at' => $now,
            ]);
        }

        $subscriptionStripeId = 'sub_dev_'.$companyId;
        $subscription = DB::table('subscriptions')->where('stripe_id', $subscriptionStripeId)->first();

        if (! $subscription) {
            DB::table('subscriptions')->insert([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'name' => 'default',
                'stripe_id' => $subscriptionStripeId,
                'stripe_status' => 'active',
                'stripe_plan' => 'growth',
                'quantity' => 1,
                'trial_ends_at' => null,
                'ends_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('subscriptions')->where('id', $subscription->id)->update([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'name' => 'default',
                'stripe_id' => $subscriptionStripeId,
                'stripe_status' => 'active',
                'stripe_plan' => 'growth',
                'quantity' => 1,
                'trial_ends_at' => null,
                'ends_at' => null,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedSupplierTenant(Carbon $now, int $planId, string $supplierEmail): array
    {
        $slug = 'precision-fabrication-partners';
        $company = DB::table('companies')->where('slug', $slug)->first();

        $data = [
            'name' => 'Precision Fabrication Partners',
            'status' => 'active',
            'supplier_status' => 'approved',
            'directory_visibility' => 'public',
            'supplier_profile_completed_at' => optional($company)->supplier_profile_completed_at ?? $now,
            'region' => 'NA',
            'rfqs_monthly_used' => optional($company)->rfqs_monthly_used ?? 0,
            'storage_used_mb' => optional($company)->storage_used_mb ?? 0,
            'plan_code' => 'starter',
            'plan_id' => $planId,
            'registration_no' => optional($company)->registration_no ?: 'PF-DEV-001',
            'tax_id' => optional($company)->tax_id ?: 'SUP-789456',
            'country' => optional($company)->country ?: 'US',
            'email_domain' => optional($company)->email_domain ?: 'precisionfab.example',
            'primary_contact_name' => optional($company)->primary_contact_name ?: 'Supplier Estimator',
            'primary_contact_email' => optional($company)->primary_contact_email ?: $supplierEmail,
            'primary_contact_phone' => optional($company)->primary_contact_phone ?: '+1-555-987-6543',
            'address' => optional($company)->address ?: '200 Supplier Ave, Austin, TX 73301',
            'phone' => optional($company)->phone ?: '+1-555-987-6543',
            'website' => optional($company)->website ?: 'https://precisionfab.example',
            'is_verified' => true,
            'verified_at' => optional($company)->verified_at ?? $now,
            'updated_at' => $now,
        ];

        if (! $company) {
            $companyId = (int) DB::table('companies')->insertGetId(array_merge($data, [
                'slug' => $slug,
                'created_at' => $now,
            ]));
        } else {
            DB::table('companies')->where('id', $company->id)->update($data);
            $companyId = (int) $company->id;
        }

        $supplier = DB::table('users')->where('email', $supplierEmail)->first();

        if (! $supplier) {
            $supplierId = (int) DB::table('users')->insertGetId([
                'name' => 'Supplier Estimator',
                'email' => $supplierEmail,
                'password' => Hash::make('password'),
                'role' => 'supplier_estimator',
                'company_id' => $companyId,
                'remember_token' => Str::random(10),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $supplierId = (int) $supplier->id;

            DB::table('users')->where('id', $supplierId)->update([
                'role' => 'supplier_estimator',
                'company_id' => $companyId,
                'updated_at' => $now,
            ]);
        }

        DB::table('companies')->where('id', $companyId)->update([
            'owner_user_id' => $supplierId,
            'updated_at' => $now,
        ]);

        DB::table('company_user')
            ->where('user_id', $supplierId)
            ->where('company_id', '!=', $companyId)
            ->delete();

        return [$companyId, $supplierId];
    }

    private function seedSupplierProfile(int $companyId, string $supplierEmail, Carbon $now): void
    {
        $capabilities = [
            'methods' => ['CNC Milling', 'CNC Turning', 'Sheet Metal Fabrication'],
            'materials' => ['Aluminum 6061', 'Stainless Steel 304', 'Titanium Grade 5'],
            'certifications' => ['ISO 9001', 'AS9100'],
            'finishes' => ['Anodizing', 'Powder Coat', 'Passivation'],
            'industries' => ['Aerospace', 'Robotics', 'Medical Devices'],
        ];

        $profile = DB::table('suppliers')->where('company_id', $companyId)->first();

        if (! $profile) {
            DB::table('suppliers')->insert([
                'company_id' => $companyId,
                'name' => 'Precision Fabrication Partners',
                'capabilities' => json_encode($capabilities),
                'email' => $supplierEmail,
                'phone' => '+1-555-987-6543',
                'website' => 'https://precisionfab.example',
                'address' => '200 Supplier Ave, Austin, TX 73301',
                'country' => 'US',
                'city' => 'Austin',
                'status' => 'approved',
                'geo_lat' => 30.2672,
                'geo_lng' => -97.7431,
                'lead_time_days' => 7,
                'moq' => 5,
                'rating_avg' => 4.80,
                'risk_grade' => 'low',
                'verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('suppliers')->where('id', $profile->id)->update([
            'name' => $profile->name ?: 'Precision Fabrication Partners',
            'capabilities' => json_encode($capabilities),
            'email' => $supplierEmail,
            'phone' => '+1-555-987-6543',
            'website' => $profile->website ?: 'https://precisionfab.example',
            'address' => $profile->address ?: '200 Supplier Ave, Austin, TX 73301',
            'country' => $profile->country ?: 'US',
            'city' => $profile->city ?: 'Austin',
            'status' => 'approved',
            'geo_lat' => $profile->geo_lat ?? 30.2672,
            'geo_lng' => $profile->geo_lng ?? -97.7431,
            'lead_time_days' => $profile->lead_time_days ?? 7,
            'moq' => $profile->moq ?? 5,
            'rating_avg' => $profile->rating_avg ?? 4.80,
            'risk_grade' => $profile->risk_grade ?? 'low',
            'verified_at' => $profile->verified_at ?: $now,
            'updated_at' => $now,
        ]);
    }

    private function seedSupplierInvoiceFixtures(int $buyerCompanyId, int $buyerUserId, int $supplierCompanyId, int $supplierUserId): void
    {
        $supplier = CompanyContext::bypass(function () use ($supplierCompanyId) {
            return Supplier::query()->where('company_id', $supplierCompanyId)->first();
        });

        if ($supplier === null) {
            return;
        }

        $now = Carbon::now();

        CompanyContext::forCompany($buyerCompanyId, function () use ($now, $buyerCompanyId, $buyerUserId, $supplierCompanyId, $supplierUserId, $supplier): void {
            SupplierContact::query()->firstOrCreate([
                'company_id' => $buyerCompanyId,
                'supplier_id' => $supplier->id,
                'user_id' => $supplierUserId,
            ]);

            $poBlueprints = [
                [
                    'po_number' => 'PO-DEV-1001',
                    'status' => 'sent',
                    'sent_days_ago' => 18,
                    'lines' => [
                        ['line_no' => 10, 'description' => 'Precision brackets', 'quantity' => 25, 'unit_price_minor' => 12500, 'uom' => 'EA'],
                        ['line_no' => 20, 'description' => 'Machined risers', 'quantity' => 40, 'unit_price_minor' => 9800, 'uom' => 'EA'],
                    ],
                ],
                [
                    'po_number' => 'PO-DEV-2001',
                    'status' => 'confirmed',
                    'sent_days_ago' => 24,
                    'lines' => [
                        ['line_no' => 10, 'description' => 'Control housings', 'quantity' => 15, 'unit_price_minor' => 18750, 'uom' => 'EA'],
                        ['line_no' => 30, 'description' => 'Sensor brackets', 'quantity' => 60, 'unit_price_minor' => 5200, 'uom' => 'EA'],
                    ],
                ],
            ];

            $purchaseOrders = [];

            foreach ($poBlueprints as $blueprint) {
                $po = PurchaseOrder::query()->firstOrNew(['po_number' => $blueprint['po_number']]);
                $sentAt = $now->copy()->subDays($blueprint['sent_days_ago'] ?? 14);
                $po->fill([
                    'company_id' => $buyerCompanyId,
                    'supplier_id' => $supplier->id,
                    'currency' => 'USD',
                    'status' => $blueprint['status'],
                    'revision_no' => 0,
                    'sent_at' => $sentAt,
                    'expected_at' => $sentAt->copy()->addDays(21),
                ]);
                $po->save();

                $subtotalMinor = 0;

                foreach ($blueprint['lines'] as $line) {
                    $unitPriceMinor = $line['unit_price_minor'];
                    $lineTotal = $unitPriceMinor * $line['quantity'];
                    $subtotalMinor += $lineTotal;

                    PurchaseOrderLine::query()->updateOrCreate(
                        [
                            'purchase_order_id' => $po->id,
                            'line_no' => $line['line_no'],
                        ],
                        [
                            'description' => $line['description'],
                            'quantity' => $line['quantity'],
                            'uom' => $line['uom'] ?? 'EA',
                            'unit_price' => $unitPriceMinor / 100,
                            'currency' => 'USD',
                            'unit_price_minor' => $unitPriceMinor,
                        ]
                    );
                }

                $taxMinor = (int) round($subtotalMinor * 0.0825);
                $totalMinor = $subtotalMinor + $taxMinor;

                $po->forceFill([
                    'subtotal' => $subtotalMinor / 100,
                    'tax_amount' => $taxMinor / 100,
                    'total' => $totalMinor / 100,
                    'subtotal_minor' => $subtotalMinor,
                    'tax_amount_minor' => $taxMinor,
                    'total_minor' => $totalMinor,
                ])->save();

                $purchaseOrders[$po->po_number] = $po->fresh('lines');
            }

            $invoiceBlueprints = [
                [
                    'invoice_number' => 'SUP-INV-1001',
                    'po_number' => 'PO-DEV-1001',
                    'status' => InvoiceStatus::Draft->value,
                    'days_ago' => 12,
                    'lines' => [
                        ['line_no' => 10, 'quantity' => 5],
                    ],
                ],
                [
                    'invoice_number' => 'SUP-INV-1002',
                    'po_number' => 'PO-DEV-1001',
                    'status' => InvoiceStatus::Submitted->value,
                    'days_ago' => 9,
                    'submitted_days_ago' => 8,
                    'lines' => [
                        ['line_no' => 10, 'quantity' => 10],
                    ],
                ],
                [
                    'invoice_number' => 'SUP-INV-1003',
                    'po_number' => 'PO-DEV-1001',
                    'status' => InvoiceStatus::BuyerReview->value,
                    'days_ago' => 7,
                    'submitted_days_ago' => 6,
                    'review_days_ago' => 5,
                    'review_note' => 'Please attach the updated packing list so we can continue approvals.',
                    'lines' => [
                        ['line_no' => 20, 'quantity' => 12],
                    ],
                ],
                [
                    'invoice_number' => 'SUP-INV-2001',
                    'po_number' => 'PO-DEV-2001',
                    'status' => InvoiceStatus::Approved->value,
                    'days_ago' => 11,
                    'submitted_days_ago' => 9,
                    'review_days_ago' => 4,
                    'review_note' => 'Approved for net-30 payment.',
                    'matched_status' => 'matched',
                    'lines' => [
                        ['line_no' => 10, 'quantity' => 8],
                    ],
                ],
                [
                    'invoice_number' => 'SUP-INV-2002',
                    'po_number' => 'PO-DEV-2001',
                    'status' => InvoiceStatus::Rejected->value,
                    'days_ago' => 14,
                    'submitted_days_ago' => 12,
                    'review_days_ago' => 10,
                    'review_note' => 'Quantity exceeds the remaining open amount on PO-DEV-2001.',
                    'lines' => [
                        ['line_no' => 30, 'quantity' => 55],
                    ],
                ],
                [
                    'invoice_number' => 'SUP-INV-2003',
                    'po_number' => 'PO-DEV-2001',
                    'status' => InvoiceStatus::Paid->value,
                    'days_ago' => 20,
                    'submitted_days_ago' => 18,
                    'review_days_ago' => 15,
                    'matched_status' => 'matched',
                    'payment_reference' => 'PAY-DEV-0001',
                    'lines' => [
                        ['line_no' => 10, 'quantity' => 6],
                        ['line_no' => 30, 'quantity' => 18],
                    ],
                ],
            ];

            foreach ($invoiceBlueprints as $invoiceBlueprint) {
                $po = $purchaseOrders[$invoiceBlueprint['po_number']] ?? null;
                if ($po === null) {
                    continue;
                }

                $invoiceDate = $now->copy()->subDays($invoiceBlueprint['days_ago'] ?? 5);
                $dueDate = $invoiceDate->copy()->addDays(30);

                $invoice = Invoice::query()->firstOrNew([
                    'company_id' => $buyerCompanyId,
                    'invoice_number' => $invoiceBlueprint['invoice_number'],
                ]);

                $linePayloads = [];
                $subtotalMinor = 0;

                foreach ($invoiceBlueprint['lines'] as $line) {
                    $poLine = $po->lines->firstWhere('line_no', $line['line_no']);
                    if ($poLine === null) {
                        continue;
                    }

                    $unitPriceMinor = $line['unit_price_minor'] ?? ($poLine->unit_price_minor ?? (int) round($poLine->unit_price * 100));
                    $quantity = $line['quantity'];
                    $lineTotal = $unitPriceMinor * $quantity;
                    $subtotalMinor += $lineTotal;

                    $linePayloads[] = [
                        'po_line_id' => $poLine->id,
                        'description' => $line['description'] ?? $poLine->description,
                        'uom' => $line['uom'] ?? $poLine->uom,
                        'quantity' => $quantity,
                        'unit_price_minor' => $unitPriceMinor,
                    ];
                }

                if ($linePayloads === []) {
                    continue;
                }

                $taxMinor = (int) round($subtotalMinor * 0.0825);
                $totalMinor = $subtotalMinor + $taxMinor;

                $submittedAt = null;
                if ($invoiceBlueprint['status'] !== InvoiceStatus::Draft->value) {
                    $submittedAt = $now->copy()->subDays($invoiceBlueprint['submitted_days_ago'] ?? 4);
                }

                $reviewedAt = null;
                $reviewedById = null;
                if (in_array($invoiceBlueprint['status'], [
                    InvoiceStatus::BuyerReview->value,
                    InvoiceStatus::Approved->value,
                    InvoiceStatus::Rejected->value,
                    InvoiceStatus::Paid->value,
                ], true)) {
                    $reviewedAt = $now->copy()->subDays($invoiceBlueprint['review_days_ago'] ?? 2);
                    $reviewedById = $buyerUserId;
                }

                $invoice->fill([
                    'purchase_order_id' => $po->id,
                    'supplier_id' => $supplier->id,
                    'supplier_company_id' => $supplierCompanyId,
                    'currency' => 'USD',
                    'invoice_date' => $invoiceDate->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'subtotal' => $subtotalMinor / 100,
                    'tax_amount' => $taxMinor / 100,
                    'total' => $totalMinor / 100,
                    'subtotal_minor' => $subtotalMinor,
                    'tax_minor' => $taxMinor,
                    'total_minor' => $totalMinor,
                    'status' => $invoiceBlueprint['status'],
                    'created_by_type' => 'supplier',
                    'created_by_id' => $supplierUserId,
                    'matched_status' => $invoiceBlueprint['matched_status'] ?? (($invoiceBlueprint['status'] === InvoiceStatus::Approved->value || $invoiceBlueprint['status'] === InvoiceStatus::Paid->value) ? 'matched' : 'pending'),
                    'submitted_at' => $submittedAt,
                    'reviewed_at' => $reviewedAt,
                    'reviewed_by_id' => $reviewedById,
                    'review_note' => $invoiceBlueprint['review_note'] ?? null,
                    'payment_reference' => $invoiceBlueprint['payment_reference'] ?? ($invoiceBlueprint['status'] === InvoiceStatus::Paid->value ? 'PAY-'.$invoiceBlueprint['invoice_number'] : null),
                ]);

                $invoice->save();

                foreach ($linePayloads as $payload) {
                    InvoiceLine::query()->updateOrCreate(
                        [
                            'invoice_id' => $invoice->id,
                            'po_line_id' => $payload['po_line_id'],
                        ],
                        [
                            'description' => $payload['description'],
                            'quantity' => $payload['quantity'],
                            'uom' => $payload['uom'],
                            'currency' => 'USD',
                            'unit_price' => $payload['unit_price_minor'] / 100,
                            'unit_price_minor' => $payload['unit_price_minor'],
                            'line_total_minor' => $payload['unit_price_minor'] * $payload['quantity'],
                        ]
                    );
                }
            }
        });
    }
}
