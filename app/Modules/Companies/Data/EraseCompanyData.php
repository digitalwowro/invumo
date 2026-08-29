<?php

namespace App\Modules\Companies\Data;

final readonly class EraseCompanyData
{
    public function __construct(
        public bool $confirmed,
        public bool $confirmedHighRisk,
        public string $confirmationName,
        public string $stateVersion,
    ) {}
}
