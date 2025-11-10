<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Delegation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Delegation>
 */
class DelegationFactory extends Factory
{
    protected $model = Delegation::class;

    public function definition(): array
    {
        $startsAt = Carbon::now()->addDay();
        $endsAt = (clone $startsAt)->addDays(7);

        return [
            'company_id' => Company::factory(),
            'approver_user_id' => User::factory(),
            'delegate_user_id' => User::factory(),
            'starts_at' => $startsAt->toDateString(),
            'ends_at' => $endsAt->toDateString(),
            'created_by' => User::factory(),
        ];
    }
}
