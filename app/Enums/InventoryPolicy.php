<?php

namespace App\Enums;

enum InventoryPolicy: string
{
    case Minmax = 'minmax';
    case Fixed = 'fixed';
    case ForecastDriven = 'forecast_driven';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
