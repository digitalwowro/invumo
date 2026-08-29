<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Models\CompanyReminderRule;
use Illuminate\Database\Eloquent\Collection;

final readonly class LockCompanyReminderRules
{
    /** @return Collection<int, CompanyReminderRule> */
    public function handle(): Collection
    {
        return CompanyReminderRule::query()->orderBy('id')->lockForUpdate()->get();
    }
}
