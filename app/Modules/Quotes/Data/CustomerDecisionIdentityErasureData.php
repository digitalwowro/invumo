<?php

namespace App\Modules\Quotes\Data;

final readonly class CustomerDecisionIdentityErasureData
{
    public function __construct(public bool $confirmed) {}
}
