<?php

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$policy = $app->make(App\Policies\InvoicePolicy::class);

$user = App\Models\User::factory()->create([
    'company_id' => 1,
    'role' => 'finance',
]);

var_dump($policy->create($user));
