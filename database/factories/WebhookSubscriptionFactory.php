<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookSubscription>
 */
class WebhookSubscriptionFactory extends Factory
{
    protected $model = WebhookSubscription::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'url' => $this->faker->url(),
            'secret' => Str::random(64),
            'events' => ['po.created'],
            'active' => true,
            'retry_policy_json' => [
                'max' => 5,
                'backoff' => 'exponential',
                'base_sec' => 30,
            ],
        ];
    }
}
