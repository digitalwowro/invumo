<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Policies\CompanyAuthorization;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CompanySettingsNavigation
{
    public function __construct(private CompanyAuthorization $authorization) {}

    /** @return array{items: list<array{key: string, href: string}>, firstUrl: string} */
    public function for(Company $company, User $actor): array
    {
        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $actor->id)
            ->first();

        if ($membership === null) {
            throw new AuthorizationException;
        }

        $items = [];

        if ($this->authorization->allows($membership->role, CompanyAbility::ManageCompanySettings)) {
            $items[] = [
                'key' => 'profile',
                'href' => route('company-settings.profile.edit', $company, false),
            ];
            $items[] = [
                'key' => 'documents',
                'href' => route('company-document-defaults.edit', $company, false),
            ];
            $items[] = [
                'key' => 'email_templates',
                'href' => route('company-email-templates.index', $company, false),
            ];
            $items[] = [
                'key' => 'reminders',
                'href' => route('company-reminder-rules.index', $company, false),
            ];
            $items[] = [
                'key' => 'numbering',
                'href' => route('company-number-series.edit', $company, false),
            ];
            $items[] = [
                'key' => 'taxes',
                'href' => route('company-tax-presets.index', $company, false),
            ];
            $items[] = [
                'key' => 'bank_accounts',
                'href' => route('company-bank-accounts.index', $company, false),
            ];
            $items[] = [
                'key' => 'appearance',
                'href' => route('company-appearance.edit', $company, false),
            ];
        }

        if ($this->authorization->allows($membership->role, CompanyAbility::ViewCompany)) {
            $items[] = [
                'key' => 'members',
                'href' => route('company-members.index', $company, false),
            ];
        }

        if ($this->authorization->allows($membership->role, CompanyAbility::ViewAudit)) {
            $items[] = [
                'key' => 'audit',
                'href' => route('company-audit.index', $company, false),
            ];
        }

        if ($this->authorization->allows($membership->role, CompanyAbility::DeleteCompany)) {
            $items[] = [
                'key' => 'data_lifecycle',
                'href' => route('company-data-lifecycle.show', $company, false),
            ];
        }

        if ($items === []) {
            throw new AuthorizationException;
        }

        return ['items' => $items, 'firstUrl' => $items[0]['href']];
    }
}
