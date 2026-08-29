<?php

namespace App\Modules\Recurring\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Recurring\Data\RecurringRunOutcome;
use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Models\RecurringTemplate;

final readonly class RecurringAutomationStatus
{
    public function __construct(private CompanyAbilityCheck $abilities) {}

    /** @return array{failedRecurringCount: int, failedRecurringUrl: string}|null */
    public function for(User $actor, Company $company): ?array
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewOperations)) {
            return null;
        }

        $count = RecurringTemplate::query()
            ->where('state', RecurringTemplateState::Active)
            ->where('last_run_outcome', RecurringRunOutcome::Failed)
            ->count();

        return [
            'failedRecurringCount' => $count,
            'failedRecurringUrl' => route('recurring.index', [
                'company' => $company->id,
                'outcome' => 'failed',
            ], false),
        ];
    }
}
