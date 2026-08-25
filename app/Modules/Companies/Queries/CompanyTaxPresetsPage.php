<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Companies\Policies\CompanyAuthorization;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CompanyTaxPresetsPage
{
    public function __construct(private CompanyAuthorization $authorization) {}

    /** @return array{taxPresets: list<array<string, mixed>>} */
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

        $presets = TaxPreset::query()
            ->orderByRaw('archived_at ASC NULLS FIRST')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (TaxPreset $preset): array => [
                'id' => $preset->id,
                'name' => $preset->name,
                'percentage' => $this->displayPercentage($preset->percentage),
                'isDefault' => $preset->is_default,
                'archived' => $preset->archived_at !== null,
                'updateUrl' => $preset->archived_at === null
                    ? route('company-tax-presets.update', [$company, $preset], false)
                    : null,
                'archiveUrl' => $preset->archived_at === null
                    ? route('company-tax-presets.archive', [$company, $preset], false)
                    : null,
            ])
            ->all();

        return ['taxPresets' => array_values($presets)];
    }

    private function displayPercentage(string $percentage): string
    {
        $trimmed = rtrim(rtrim($percentage, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
