<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;

if (! function_exists('createDigitalTwinUser')) {
    function createDigitalTwinUser(bool $maintenanceEnabled = true, array $overrides = []): User
    {
        $plan = Plan::factory()->create([
            'code' => 'pro-dt-'.Str::random(5),
            'name' => 'Pro Digital Twin',
            'digital_twin_enabled' => true,
            'maintenance_enabled' => $maintenanceEnabled,
            'rfqs_per_month' => 0,
            'invoices_per_month' => 0,
            'users_max' => 10,
            'storage_gb' => 50,
        ]);

        $company = $overrides['company'] ?? Company::factory()->create([
            'plan_id' => $plan->id,
            'plan_code' => $plan->code,
            'status' => 'active',
            'registration_no' => 'REG-12345',
            'tax_id' => 'TAX-67890',
            'country' => 'US',
            'email_domain' => 'example.com',
            'primary_contact_name' => 'Primary Contact',
            'primary_contact_email' => 'primary@example.com',
            'primary_contact_phone' => '+1-555-0100',
        ]);
        unset($overrides['company']);

        $customer = Customer::factory()->for($company)->create();

        Subscription::factory()->for($company)->create([
            'customer_id' => $customer->id,
            'stripe_status' => 'active',
        ]);

        $userAttributes = array_merge([
            'role' => 'buyer_admin',
        ], $overrides);

        $user = User::factory()->for($company)->create($userAttributes);

        actingAs($user);

        return $user;
    }
}
