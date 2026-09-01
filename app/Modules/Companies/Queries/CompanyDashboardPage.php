<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;

final readonly class CompanyDashboardPage
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private CompanyDashboardMetrics $metrics,
        private CompanyDashboardActivity $activity,
    ) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor): array
    {
        if (! $this->abilities->allowsAll(
            $actor,
            $company,
            CompanyAbility::ViewInvoices,
            CompanyAbility::ViewTransactions,
        )) {
            throw new AuthorizationException;
        }

        $settings = CompanySetting::query()->firstOrFail();
        $localDate = Date::now($settings->timezone ?? 'UTC')->toImmutable()->startOfDay();
        $groups = $this->metrics->for($localDate);
        $canManageInvoices = $this->abilities->allows(
            $actor,
            $company,
            CompanyAbility::ManageInvoices,
        );
        $activity = $this->activity->for(
            $company,
            $localDate,
            $this->abilities->allows($actor, $company, CompanyAbility::ViewQuotes),
            $this->abilities->allows($actor, $company, CompanyAbility::ViewRecurringTemplates),
            $canManageInvoices,
        );

        return [
            'asOfDate' => $localDate->toDateString(),
            'expectedThroughDate' => $localDate->addDays(30)->toDateString(),
            'monthLabel' => $localDate->translatedFormat('F Y'),
            'currencyGroups' => array_map(
                fn (array $group): array => array_merge(
                    $group,
                    $this->activityDefaults(),
                    $activity[$group['currencyCode']] ?? [],
                ),
                $groups,
            ),
            'invoicesUrl' => route('invoices.index', $company, false),
            'createInvoiceUrl' => $canManageInvoices
                ? route('invoices.create', $company, false)
                : null,
            'transactionsUrl' => route('transactions.index', $company, false),
            'quotesUrl' => route('quotes.index', $company, false),
            'recurringUrl' => route('recurring.index', $company, false),
        ];
    }

    /** @return array<string, mixed> */
    private function activityDefaults(): array
    {
        return [
            'attention' => [],
            'deliveryFailures' => [],
            'deliveryFailureCount' => 0,
            'upcoming' => [],
            'upcomingCount' => 0,
            'recentInvoices' => ['all' => [], 'unpaid' => [], 'drafts' => []],
        ];
    }
}
