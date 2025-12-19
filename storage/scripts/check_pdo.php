<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
var_dump([
    'stringify_fetches' => $pdo->getAttribute(PDO::ATTR_STRINGIFY_FETCHES),
    'emulate_prepares' => $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES),
]);
