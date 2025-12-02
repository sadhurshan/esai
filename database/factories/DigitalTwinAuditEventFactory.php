<?php

namespace Database\Factories;

use App\Enums\DigitalTwinAuditEvent as DigitalTwinAuditEventEnum;
use App\Models\DigitalTwin;
use App\Models\DigitalTwinAuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DigitalTwinAuditEvent>
 */
class DigitalTwinAuditEventFactory extends Factory
{
    protected $model = DigitalTwinAuditEvent::class;

    public function definition(): array
    {
        $event = $this->faker->randomElement(DigitalTwinAuditEventEnum::cases());

        return [
            'digital_twin_id' => DigitalTwin::factory(),
            'actor_id' => User::factory(),
            'event' => $event,
            'meta' => ['description' => $this->faker->sentence()],
        ];
    }
}
