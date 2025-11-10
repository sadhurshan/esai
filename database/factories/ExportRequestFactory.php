<?php

namespace Database\Factories;

use App\Enums\ExportRequestStatus;
use App\Enums\ExportRequestType;
use App\Models\Company;
use App\Models\ExportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExportRequest>
 */
class ExportRequestFactory extends Factory
{
    protected $model = ExportRequest::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'requested_by' => null,
            'type' => ExportRequestType::FullData->value,
            'status' => ExportRequestStatus::Pending->value,
            'filters' => null,
            'file_path' => null,
            'expires_at' => null,
            'completed_at' => null,
            'error_message' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ExportRequest $request): void {
            if ($request->company_id === null) {
                $company = Company::factory()->create();
                $request->company_id = $company->id;
            }

            if ($request->requested_by === null) {
                $user = User::factory()->create([
                    'company_id' => $request->company_id,
                ]);

                $request->requested_by = $user->id;
            }
        })->afterCreating(function (ExportRequest $request): void {
            $request->loadMissing('requester');
        });
    }

    public function completed(?string $path = null): self
    {
        return $this->state(function () use ($path): array {
            return [
                'status' => ExportRequestStatus::Completed->value,
                'file_path' => $path ?? '1/export-test.zip',
                'expires_at' => now()->addDays(7),
                'completed_at' => now(),
            ];
        });
    }
}
