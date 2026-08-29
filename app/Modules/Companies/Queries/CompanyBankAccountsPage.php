<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Companies\Data\BankRoutingField;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Policies\CompanyAuthorization;
use App\Modules\Documents\Models\DocumentBankSnapshot;
use App\Modules\Recurring\Models\RecurringTemplateDefault;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CompanyBankAccountsPage
{
    public function __construct(private CompanyAuthorization $authorization) {}

    /** @return array{bankAccounts: list<array<string, mixed>>, currencyOptions: list<array{value: string, label: string}>, routingFields: list<string>} */
    public function for(Company $company, User $actor): array
    {
        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $actor->id)
            ->first();

        if (
            $membership === null
            || ! $this->authorization->allows(
                $membership->role,
                CompanyAbility::ManageCompanySettings,
            )
        ) {
            throw new AuthorizationException;
        }

        $accounts = array_values(BankAccount::query()
            ->select('bank_accounts.*')
            ->addSelect([
                'document_reference_count' => DocumentBankSnapshot::query()->selectRaw('count(*)')
                    ->whereColumn('document_bank_snapshots.bank_account_id', 'bank_accounts.id'),
                'template_reference_count' => RecurringTemplateDefault::query()->selectRaw('count(*)')
                    ->whereColumn('recurring_template_defaults.bank_account_id', 'bank_accounts.id'),
            ])
            ->with('currency')
            ->orderByRaw('archived_at ASC NULLS FIRST')
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get()
            ->map(fn (BankAccount $account): array => $this->row($company, $account))
            ->values()
            ->all());
        $currencyOptions = array_values(CompanyCurrency::query()
            ->where('active', true)
            ->orderBy('currency_code')
            ->get()
            ->map(fn (CompanyCurrency $currency): array => [
                'value' => $currency->id,
                'label' => $currency->currency_code,
            ])
            ->values()
            ->all());

        return [
            'bankAccounts' => $accounts,
            'currencyOptions' => $currencyOptions,
            'routingFields' => BankRoutingField::values(),
        ];
    }

    /** @return array<string, mixed> */
    private function row(Company $company, BankAccount $account): array
    {
        $documentCount = (int) $account->getAttribute('document_reference_count');
        $templateCount = (int) $account->getAttribute('template_reference_count');
        $blocked = $documentCount + $templateCount > 0;

        return [
            'id' => $account->id,
            'label' => $account->label,
            'bankName' => $account->bank_name,
            'accountHolder' => $account->account_holder,
            'accountNumber' => $account->account_number,
            'swiftBic' => $account->swift_bic,
            'currencyId' => $account->currency_id,
            'currencyCode' => $account->currency?->currency_code,
            'localRoutingDetails' => $account->local_routing_details ?? [],
            'isDefault' => $account->is_default,
            'archived' => $account->archived_at !== null,
            'updateUrl' => $account->archived_at === null
                ? route('company-bank-accounts.update', [$company, $account], false)
                : null,
            'archiveUrl' => $account->archived_at === null
                ? route('company-bank-accounts.archive', [$company, $account], false)
                : null,
            'restoreUrl' => $account->archived_at !== null
                ? route('company-bank-accounts.restore', [$company, $account], false)
                : null,
            'deleteUrl' => route('company-bank-accounts.destroy', [$company, $account], false),
            'deleteGuard' => [
                'blocked' => $blocked,
                'description' => $blocked
                    ? __('companies_ui.settings.bank_accounts.delete_dependency_description', [
                        'documents' => $documentCount,
                        'templates' => $templateCount,
                    ])
                    : null,
            ],
        ];
    }
}
