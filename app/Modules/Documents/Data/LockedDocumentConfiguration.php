<?php

namespace App\Modules\Documents\Data;

use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
use Illuminate\Database\Eloquent\Collection;

final readonly class LockedDocumentConfiguration
{
    /**
     * @param  Collection<int, CompanyCurrency>  $currencies
     * @param  Collection<int, TaxPreset>  $taxPresets
     * @param  Collection<int, BankAccount>  $bankAccounts
     */
    public function __construct(
        public CompanySetting $settings,
        public Collection $currencies,
        public Collection $taxPresets,
        public Collection $bankAccounts,
    ) {}
}
