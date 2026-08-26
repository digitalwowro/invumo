<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Documents\Data\LockedDocumentConfiguration;

final class LockDocumentConfiguration
{
    public function handle(): LockedDocumentConfiguration
    {
        return new LockedDocumentConfiguration(
            settings: CompanySetting::query()->orderBy('id')->lockForUpdate()->firstOrFail(),
            currencies: CompanyCurrency::query()->orderBy('id')->lockForUpdate()->get(),
            taxPresets: TaxPreset::query()->orderBy('id')->lockForUpdate()->get(),
            bankAccounts: BankAccount::query()->orderBy('id')->lockForUpdate()->get(),
        );
    }
}
