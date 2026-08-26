<?php

namespace App\Modules\Documents\Data;

use App\Modules\Companies\Data\CompanyAbility;

enum DocumentKind: string
{
    case Quote = 'QUOTE';
    case Invoice = 'INVOICE';

    public function viewAbility(): CompanyAbility
    {
        return match ($this) {
            self::Quote => CompanyAbility::ViewQuotes,
            self::Invoice => CompanyAbility::ViewInvoices,
        };
    }

    public function manageAbility(): CompanyAbility
    {
        return match ($this) {
            self::Quote => CompanyAbility::ManageQuotes,
            self::Invoice => CompanyAbility::ManageInvoices,
        };
    }
}
