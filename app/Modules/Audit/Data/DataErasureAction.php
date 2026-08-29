<?php

namespace App\Modules\Audit\Data;

enum DataErasureAction: string
{
    case CompanyErased = 'COMPANY_ERASED';
    case UserAccountErased = 'USER_ACCOUNT_ERASED';

    public function subjectType(): string
    {
        return match ($this) {
            self::CompanyErased => 'COMPANY',
            self::UserAccountErased => 'USER_ACCOUNT',
        };
    }
}
