<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Policies\CompanyAuthorization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

final readonly class CompanyContextProps
{
    public function __construct(
        private AccessibleCompanies $companies,
        private CompanyAuthorization $authorization,
    ) {}

    /** @return array<string, mixed> */
    public function for(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->emptyBag();
        }

        $memberships = $this->companies->for($user);
        $currentMembership = $this->currentMembership($request, $memberships);

        return [
            'current' => $currentMembership === null ? null : $this->companyItem($currentMembership),
            'available' => $memberships->map($this->companyItem(...))->values(),
            'abilities' => $currentMembership === null
                ? $this->deniedAbilities()
                : $this->authorization->bagFor($currentMembership->role),
            'landingUrl' => route('home', absolute: false),
            'indexUrl' => route('companies.index', absolute: false),
            'createUrl' => route('companies.create', absolute: false),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyBag(): array
    {
        return [
            'current' => null,
            'available' => [],
            'abilities' => $this->deniedAbilities(),
            'landingUrl' => route('home', absolute: false),
            'indexUrl' => null,
            'createUrl' => null,
        ];
    }

    /** @return array<string, bool> */
    private function deniedAbilities(): array
    {
        return array_fill_keys(
            array_map(fn (CompanyAbility $ability): string => $ability->value, CompanyAbility::cases()),
            false,
        );
    }

    /** @return array{id: string, name: string, dashboardUrl: string, customersUrl: string, catalogUrl: string, settingsUrl: string, membersUrl: string} */
    private function companyItem(CompanyMembership $membership): array
    {
        $canManageSettings = $this->authorization->allows(
            $membership->role,
            CompanyAbility::ManageCompanySettings,
        );

        return [
            'id' => $membership->company_id,
            'name' => $membership->company->name,
            'dashboardUrl' => route('companies.dashboard', $membership->company_id, false),
            'customersUrl' => route('customers.index', $membership->company_id, false),
            'catalogUrl' => route('catalog.index', $membership->company_id, false),
            'settingsUrl' => $canManageSettings
                ? route('company-settings.profile.edit', $membership->company_id, false)
                : route('company-members.index', $membership->company_id, false),
            'membersUrl' => route('company-members.index', $membership->company_id, false),
        ];
    }

    /** @param Collection<int, CompanyMembership> $memberships */
    private function currentMembership(Request $request, Collection $memberships): ?CompanyMembership
    {
        $routeCompany = $request->route('company');
        $companyId = $routeCompany instanceof Company ? $routeCompany->id : $routeCompany;

        return is_string($companyId)
            ? $memberships->firstWhere('company_id', $companyId)
            : null;
    }
}
