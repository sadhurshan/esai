<?php

namespace Tests\Support;

class RfqPayloadFactory
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function make(array $overrides = []): array
    {
        $base = [
            'title' => 'Precision Bracket',
            'method' => 'cnc',
            'material' => 'aluminium 6061',
            'delivery_location' => 'Elements Supply AI',
            'notes' => 'Urgent run for pilot build.',
            'due_at' => now()->addDays(10)->toIso8601String(),
            'items' => [
                [
                    'part_number' => 'Bracket A',
                    'description' => 'Bracket A',
                    'qty' => 100,
                    'uom' => 'pcs',
                    'method' => 'cnc',
                    'material' => 'aluminium 6061',
                    'tolerance' => '+/-0.01 mm',
                    'finish' => 'Anodized',
                ],
            ],
        ];

        return array_replace_recursive($base, $overrides);
    }
}
