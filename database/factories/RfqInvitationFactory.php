<?php

namespace Database\Factories;

use App\Models\RfqInvitation;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfqInvitation>
 */
class RfqInvitationFactory extends Factory
{
    protected $model = RfqInvitation::class;

    public function configure(): static
    {
        return $this
            ->afterMaking(function (RfqInvitation $invitation): void {
                $invitation->company_id ??= $this->resolveCompanyId($invitation);
            })
            ->afterCreating(function (RfqInvitation $invitation): void {
                if ($invitation->company_id === null) {
                    $invitation->forceFill(['company_id' => $this->resolveCompanyId($invitation)])->save();
                }
            });
    }

    public function definition(): array
    {
        return [
            'company_id' => null,
            'rfq_id' => RFQ::factory(),
            'supplier_id' => Supplier::factory(),
            'invited_by' => null,
            'status' => RfqInvitation::STATUS_PENDING,
        ];
    }

    private function resolveCompanyId(RfqInvitation $invitation): ?int
    {
        if ($invitation->relationLoaded('rfq') && $invitation->rfq instanceof RFQ) {
            return $invitation->rfq->company_id;
        }

        if ($invitation->rfq_id === null) {
            return null;
        }

        return CompanyContext::bypass(static function () use ($invitation): ?int {
            return RFQ::query()->withoutGlobalScopes()->whereKey($invitation->rfq_id)->value('company_id');
        });
    }
}
