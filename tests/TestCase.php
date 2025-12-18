<?php

namespace Tests;

use Database\Seeders\CurrenciesSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);

        $this->seed(CurrenciesSeeder::class);
    }

    public function createApplication()
    {
        $this->purgeCachedConfiguration();

        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function purgeCachedConfiguration(): void
    {
        $configCache = __DIR__.'/../bootstrap/cache/config.php';

        if (file_exists($configCache)) {
            unlink($configCache);
        }
    }

    protected function tearDown(): void
    {
        \App\Support\CompanyContext::clear();

        parent::tearDown();
    }
}
