<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UomSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $uoms = [
            ['code' => 'kg', 'name' => 'Kilogram', 'dimension' => 'mass', 'symbol' => 'kg', 'si_base' => true],
            ['code' => 'g', 'name' => 'Gram', 'dimension' => 'mass', 'symbol' => 'g', 'si_base' => false],
            ['code' => 'lb', 'name' => 'Pound', 'dimension' => 'mass', 'symbol' => 'lb', 'si_base' => false],
            ['code' => 'm', 'name' => 'Meter', 'dimension' => 'length', 'symbol' => 'm', 'si_base' => true],
            ['code' => 'cm', 'name' => 'Centimeter', 'dimension' => 'length', 'symbol' => 'cm', 'si_base' => false],
            ['code' => 'mm', 'name' => 'Millimeter', 'dimension' => 'length', 'symbol' => 'mm', 'si_base' => false],
            ['code' => 'in', 'name' => 'Inch', 'dimension' => 'length', 'symbol' => 'in', 'si_base' => false],
            ['code' => 'ft', 'name' => 'Foot', 'dimension' => 'length', 'symbol' => 'ft', 'si_base' => false],
            ['code' => 'L', 'name' => 'Liter', 'dimension' => 'volume', 'symbol' => 'L', 'si_base' => true],
            ['code' => 'mL', 'name' => 'Milliliter', 'dimension' => 'volume', 'symbol' => 'mL', 'si_base' => false],
            ['code' => 's', 'name' => 'Second', 'dimension' => 'time', 'symbol' => 's', 'si_base' => true],
            ['code' => 'min', 'name' => 'Minute', 'dimension' => 'time', 'symbol' => 'min', 'si_base' => false],
            ['code' => 'hr', 'name' => 'Hour', 'dimension' => 'time', 'symbol' => 'h', 'si_base' => false],
            ['code' => 'ea', 'name' => 'Each', 'dimension' => 'count', 'symbol' => null, 'si_base' => false],
            ['code' => 'K', 'name' => 'Kelvin', 'dimension' => 'temperature', 'symbol' => 'K', 'si_base' => true],
            ['code' => 'C', 'name' => 'Celsius', 'dimension' => 'temperature', 'symbol' => 'degC', 'si_base' => false],
            ['code' => 'F', 'name' => 'Fahrenheit', 'dimension' => 'temperature', 'symbol' => 'degF', 'si_base' => false],
        ];

        $payload = array_map(static function (array $uom) use ($now) {
            return array_merge($uom, [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $uoms);

        DB::table('uoms')->upsert(
            $payload,
            ['code'],
            ['name', 'dimension', 'symbol', 'si_base', 'updated_at']
        );

        $codes = DB::table('uoms')
            ->whereIn('code', array_column($uoms, 'code'))
            ->pluck('id', 'code');

        $conversions = [
            ['from' => 'kg', 'to' => 'g', 'factor' => '1000', 'offset' => '0'],
            ['from' => 'g', 'to' => 'kg', 'factor' => '0.001', 'offset' => '0'],
            ['from' => 'lb', 'to' => 'kg', 'factor' => '0.45359237', 'offset' => '0'],
            ['from' => 'kg', 'to' => 'lb', 'factor' => '2.20462262', 'offset' => '0'],
            ['from' => 'm', 'to' => 'cm', 'factor' => '100', 'offset' => '0'],
            ['from' => 'cm', 'to' => 'm', 'factor' => '0.01', 'offset' => '0'],
            ['from' => 'cm', 'to' => 'mm', 'factor' => '10', 'offset' => '0'],
            ['from' => 'mm', 'to' => 'cm', 'factor' => '0.1', 'offset' => '0'],
            ['from' => 'm', 'to' => 'mm', 'factor' => '1000', 'offset' => '0'],
            ['from' => 'mm', 'to' => 'm', 'factor' => '0.001', 'offset' => '0'],
            ['from' => 'in', 'to' => 'mm', 'factor' => '25.4', 'offset' => '0'],
            ['from' => 'mm', 'to' => 'in', 'factor' => '0.03937007874', 'offset' => '0'],
            ['from' => 'ft', 'to' => 'in', 'factor' => '12', 'offset' => '0'],
            ['from' => 'in', 'to' => 'ft', 'factor' => '0.08333333333', 'offset' => '0'],
            ['from' => 'L', 'to' => 'mL', 'factor' => '1000', 'offset' => '0'],
            ['from' => 'mL', 'to' => 'L', 'factor' => '0.001', 'offset' => '0'],
            ['from' => 'hr', 'to' => 'min', 'factor' => '60', 'offset' => '0'],
            ['from' => 'min', 'to' => 'hr', 'factor' => '0.01666666667', 'offset' => '0'],
            ['from' => 'min', 'to' => 's', 'factor' => '60', 'offset' => '0'],
            ['from' => 's', 'to' => 'min', 'factor' => '0.01666666667', 'offset' => '0'],
            ['from' => 'hr', 'to' => 's', 'factor' => '3600', 'offset' => '0'],
            ['from' => 's', 'to' => 'hr', 'factor' => '0.0002777777778', 'offset' => '0'],
            ['from' => 'C', 'to' => 'K', 'factor' => '1', 'offset' => '273.15'],
            ['from' => 'K', 'to' => 'C', 'factor' => '1', 'offset' => '-273.15'],
            ['from' => 'C', 'to' => 'F', 'factor' => '1.8', 'offset' => '32'],
            ['from' => 'F', 'to' => 'C', 'factor' => '0.5555555556', 'offset' => '-17.7777777778'],
        ];

        $conversionPayload = [];

        foreach ($conversions as $row) {
            $fromId = $codes->get($row['from']);
            $toId = $codes->get($row['to']);

            if ($fromId === null || $toId === null) {
                continue;
            }

            $conversionPayload[] = [
                'from_uom_id' => $fromId,
                'to_uom_id' => $toId,
                'factor' => $row['factor'],
                'offset' => $row['offset'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($conversionPayload === []) {
            return;
        }

        DB::table('uom_conversions')->upsert(
            $conversionPayload,
            ['from_uom_id', 'to_uom_id'],
            ['factor', 'offset', 'updated_at']
        );
    }
}
