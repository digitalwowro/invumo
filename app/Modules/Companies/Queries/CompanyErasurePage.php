<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CompanyErasureState;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Data\EmailDeliveryAttemptState;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

final readonly class CompanyErasurePage
{
    public function __construct(private CompanyAbilityCheck $abilities) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor): array
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::DeleteCompany)) {
            throw new AuthorizationException;
        }

        $state = new CompanyErasureState(
            $company->name,
            EmailDeliveryAttempt::query()
                ->where('state', EmailDeliveryAttemptState::Pending)->count(),
        );

        return [
            'erasure' => [
                'url' => route('company-data-lifecycle.destroy', $company, false),
                'companyName' => $company->name,
                'stateVersion' => $state->version(),
                'guard' => [
                    'blocked' => $state->blocked(),
                    'description' => $state->blocked()
                        ? $this->description($state->pendingSubmissionCount)
                        : null,
                ],
            ],
        ];
    }

    private function description(int $submissions): string
    {
        $translation = __('companies_ui.settings.data_lifecycle.dependency_description', [
            'submissions' => $submissions,
        ]);

        if (! is_string($translation)) {
            throw new RuntimeException('The Company erasure dependency text must be a string.');
        }

        return $translation;
    }
}
