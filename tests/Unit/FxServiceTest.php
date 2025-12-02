<?php

use App\Exceptions\FxRateNotFoundException;
use App\Models\FxRate;
use App\Services\FxService;
use App\Support\Money\Money;
use Carbon\Carbon;
use Database\Seeders\CurrenciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

beforeEach(function (): void {
    app(CurrenciesSeeder::class)->run();
    Cache::clear();
});

it('returns unity rate for identical currencies', function (): void {
    $service = app(FxService::class);

    expect($service->getRate('usd', 'usd'))->toBe('1.00000000');
});

it('resolves direct and inverse fx rates', function (): void {
    $service = app(FxService::class);

    FxRate::create([
        'base_code' => 'USD',
        'quote_code' => 'EUR',
        'rate' => '0.91000000',
        'as_of' => '2024-01-10',
    ]);

    FxRate::create([
        'base_code' => 'USD',
        'quote_code' => 'EUR',
        'rate' => '0.93000000',
        'as_of' => '2024-01-15',
    ]);

    expect($service->getRate('USD', 'EUR'))->toBe('0.93000000');
    expect($service->getRate('USD', 'EUR', Carbon::parse('2024-01-12')))->toBe('0.91000000');

    Cache::clear();
    FxRate::query()->delete();

    FxRate::create([
        'base_code' => 'EUR',
        'quote_code' => 'USD',
        'rate' => '1.20000000',
        'as_of' => '2024-01-05',
    ]);

    expect($service->getRate('USD', 'EUR'))->toBe('0.83333333');
});

it('converts money using configured fx rate', function (): void {
    $service = app(FxService::class);

    $service->upsertDailyRates([
        [
            'base_code' => 'USD',
            'quote_code' => 'EUR',
            'rate' => '0.75000000',
            'as_of' => '2024-02-01',
        ],
    ]);

    $money = Money::fromMinor(10000, 'USD');

    $converted = $service->convert($money, 'EUR', Carbon::parse('2024-02-15'));

    expect($converted->currency())->toBe('EUR');
    expect($converted->amountMinor())->toBe(7500);
    expect($converted->toDecimal(2))->toBe('75.00');
});

it('throws when no fx rate is available', function (): void {
    $service = app(FxService::class);

    expect(fn () => $service->getRate('USD', 'NZD'))->toThrow(FxRateNotFoundException::class);
});
