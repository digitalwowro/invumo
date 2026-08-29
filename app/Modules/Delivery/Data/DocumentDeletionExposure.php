<?php

namespace App\Modules\Delivery\Data;

final readonly class DocumentDeletionExposure
{
    public function __construct(
        public int $publicLinkCount,
        public int $deliveryCount,
        public int $submissionInFlightCount,
    ) {}
}
