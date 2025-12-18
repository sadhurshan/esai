<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeatureEntitlementChecked
{
	use Dispatchable;
	use SerializesModels;

	public function __construct(
		public readonly string $featureKey,
		public readonly bool $granted,
		public readonly ?int $companyId = null,
		public readonly ?int $userId = null,
		public readonly array $context = [],
	) {}
}
