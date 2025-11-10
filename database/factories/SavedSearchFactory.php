<?php

namespace Database\Factories;

use App\Enums\SearchEntityType;
use App\Models\Company;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'name' => $this->faker->unique()->words(3, true),
            'query' => $this->faker->words(2, true),
            'entity_types' => [SearchEntityType::Supplier->value],
            'filters' => [],
            'tags' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (SavedSearch $search): void {
            if ($search->user instanceof User && $search->company_id !== null && $search->user->company_id === null) {
                $search->user->company_id = $search->company_id;
            }
        })->afterCreating(function (SavedSearch $search): void {
            if ($search->user instanceof User && $search->company_id !== null && (int) $search->user->company_id !== (int) $search->company_id) {
                $search->user->company_id = $search->company_id;
                $search->user->save();
            }
        });
    }
}
