<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $currencies = [
            ['code' => 'USD', 'name' => 'United States Dollar', 'minor_unit' => 2, 'symbol' => 'USD'],
            ['code' => 'EUR', 'name' => 'Euro', 'minor_unit' => 2, 'symbol' => 'EUR'],
            ['code' => 'GBP', 'name' => 'British Pound Sterling', 'minor_unit' => 2, 'symbol' => 'GBP'],
            ['code' => 'LKR', 'name' => 'Sri Lankan Rupee', 'minor_unit' => 2, 'symbol' => 'LKR'],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'minor_unit' => 2, 'symbol' => 'INR'],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'minor_unit' => 0, 'symbol' => 'JPY'],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'minor_unit' => 2, 'symbol' => 'AUD'],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'minor_unit' => 2, 'symbol' => 'CAD'],
            ['code' => 'CHF', 'name' => 'Swiss Franc', 'minor_unit' => 2, 'symbol' => 'CHF'],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'minor_unit' => 2, 'symbol' => 'SGD'],
        ];

        $payload = array_map(static fn (array $currency) => array_merge($currency, [
            'created_at' => $now,
            'updated_at' => $now,
        ]), $currencies);

        DB::table('currencies')->upsert(
            $payload,
            ['code'],
            ['name', 'minor_unit', 'symbol', 'updated_at']
        );
    }
}
