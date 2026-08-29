<?php

namespace App\Modules\Delivery\Data;

final readonly class CompanyDeliveryErasureState
{
    /** @param list<DocumentArtifactFile> $files */
    public function __construct(
        public int $pendingSubmissionCount,
        public array $files,
    ) {}
}
