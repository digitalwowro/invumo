<?php

namespace App\Modules\Documents\Data;

final readonly class LockedDocumentDeletionResources
{
    public function __construct(
        public int $publicLinkCount,
        public int $deliveryCount,
        public int $submissionInFlightCount,
    ) {}
}
