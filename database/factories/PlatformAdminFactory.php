<?php

namespace Database\Factories;

use App\Enums\PlatformAdminRole;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformAdmin>
 */
class PlatformAdminFactory extends Factory
{
    protected $model = PlatformAdmin::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => $this->faker->randomElement([
                PlatformAdminRole::Super,
                PlatformAdminRole::Support,
            ]),
            'enabled' => true,
        ];
    }

    public function super(): self
    {
        return $this->state(fn () => ['role' => PlatformAdminRole::Super]);
    }

    public function support(): self
    {
        return $this->state(fn () => ['role' => PlatformAdminRole::Support]);
    }

    public function disabled(): self
    {
        return $this->state(fn () => ['enabled' => false]);
    }
}
