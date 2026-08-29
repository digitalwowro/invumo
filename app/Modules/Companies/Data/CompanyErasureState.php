<?php

namespace App\Modules\Companies\Data;

final readonly class CompanyErasureState
{
    public function __construct(
        public string $companyName,
        public int $pendingSubmissionCount,
    ) {}

    public function blocked(): bool
    {
        return $this->pendingSubmissionCount > 0;
    }

    public function version(): string
    {
        return hash('sha256', $this->companyName.'|'.$this->pendingSubmissionCount);
    }
}
