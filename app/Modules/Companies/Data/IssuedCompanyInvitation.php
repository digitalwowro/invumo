<?php

namespace App\Modules\Companies\Data;

use App\Modules\Companies\Models\CompanyInvitation;

final readonly class IssuedCompanyInvitation
{
    public function __construct(
        public CompanyInvitation $invitation,
        public string $plainTextToken,
    ) {}
}
