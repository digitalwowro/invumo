<?php

namespace App\Modules\Platform\Data;

use App\Modules\Identity\Data\PlanStatus;
use Carbon\CarbonImmutable;

final readonly class PlanLifecycleData
{
    public function __construct(
        public string $planId,
        public PlanStatus $status,
        public CarbonImmutable $startedAt,
        public ?CarbonImmutable $trialEndsAt,
        public ?CarbonImmutable $accessEndsAt,
        public bool $cancelAtPeriodEnd,
        public ?CarbonImmutable $endedAt,
    ) {}
}
