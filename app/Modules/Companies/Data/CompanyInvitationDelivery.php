<?php

namespace App\Modules\Companies\Data;

use Carbon\CarbonImmutable;

final readonly class CompanyInvitationDelivery
{
    public function __construct(
        public string $email,
        public string $companyName,
        public string $inviterName,
        public CarbonImmutable $expiresAt,
    ) {}
}
