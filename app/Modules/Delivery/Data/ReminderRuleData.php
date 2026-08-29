<?php

namespace App\Modules\Delivery\Data;

final readonly class ReminderRuleData
{
    public function __construct(
        public ?string $id,
        public ReminderRelation $relation,
        public int $dayOffset,
        public bool $enabled,
    ) {}
}
